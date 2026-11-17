#!/usr/bin/env python3
"""Construit le paquet de référentiel ATT&CK Radar.

Entrées : upstream/enterprise-attack.json (MITRE ATT&CK STIX), upstream/sigma (dépôt SigmaHQ)
Sorties (dans dist/) : catalog.json, rules.ndjson.gz, manifest.json (avec SHA-256)
"""
import datetime
import glob
import gzip
import hashlib
import json
import os
import re
import subprocess
import sys
import warnings
from concurrent.futures import ProcessPoolExecutor

import yaml

sys.path.insert(0, os.path.dirname(__file__))
import catalog  # noqa: E402

warnings.filterwarnings("ignore")
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
UP = os.path.join(ROOT, "upstream")
DIST = os.path.join(ROOT, "dist")
RULE_DIRS = ["rules", "rules-emerging-threats", "rules-threat-hunting"]
CUSTOM_DIR = os.path.join(ROOT, "custom-rules")
LIMIT = int(os.environ.get("LIMIT", "0"))

_BACKENDS = None
# Falcon : uniquement la télémétrie de l'agent (logsource par catégorie sur un OS)
FALCON_OK = re.compile(r"logsource:\s*(?:\n\s+\w+:.*)*?\n\s+category:\s*(process_creation|network_connection|dns_query|image_load|driver_load|file_event|ps_script|registry_set|registry_event|registry_add)")


def backends():
    """Initialise les backends pySigma une seule fois par processus."""
    global _BACKENDS
    if _BACKENDS is not None:
        return _BACKENDS
    b = {}
    from sigma.backends.splunk import SplunkBackend
    from sigma.pipelines.splunk import splunk_windows_pipeline
    b["splunk"] = SplunkBackend(splunk_windows_pipeline())
    from sigma.backends.elasticsearch import LuceneBackend, ESQLBackend
    from sigma.pipelines.elasticsearch.windows import ecs_windows
    b["elastic_lucene"] = LuceneBackend(ecs_windows())
    b["elastic_esql"] = ESQLBackend(ecs_windows())
    from sigma.backends.kusto import KustoBackend
    from sigma.pipelines.sentinelasim import sentinel_asim_pipeline
    b["sentinel_kql"] = KustoBackend(sentinel_asim_pipeline())
    from sigma.backends.crowdstrike import LogScaleBackend
    from sigma.pipelines.crowdstrike import crowdstrike_falcon_pipeline
    b["falcon_logscale"] = LogScaleBackend(crowdstrike_falcon_pipeline())
    from sigma.backends.cortexxdr import CortexXDRBackend
    b["cortex_xql"] = CortexXDRBackend()
    _BACKENDS = b
    return b


def convert_rule(path):
    from sigma.collection import SigmaCollection
    text = open(path, encoding="utf8").read()
    out = {}
    for name, be in backends().items():
        try:
            if name == "falcon_logscale" and not FALCON_OK.search(text):
                out[name] = None
                continue
            res = be.convert(SigmaCollection.from_yaml(text))
            if isinstance(res, str):
                res = [res]
            q = "\n".join(str(x) for x in res) if res else None
            out[name] = q if q and len(q) < 20000 else None
        except Exception:
            out[name] = None
    return path, text, out


def main():
    os.makedirs(DIST, exist_ok=True)
    stix = json.load(open(os.path.join(UP, "enterprise-attack.json")))
    objs = stix["objects"]
    by_id = {o["id"]: o for o in objs}
    attack_version = next(o["x_mitre_version"] for o in objs if o["type"] == "x-mitre-collection")

    def ext_id(o):
        return next((r["external_id"] for r in o.get("external_references", []) if r.get("source_name") == "mitre-attack"), None)

    def dead(o):
        return o.get("revoked") or o.get("x_mitre_deprecated")

    matrix = next(o for o in objs if o["type"] == "x-mitre-matrix")
    tactics, short_to_tid = [], {}
    for pos, ref in enumerate(matrix["tactic_refs"]):
        t = by_id[ref]
        tactics.append({"id": ext_id(t), "shortname": t["x_mitre_shortname"], "name": t["name"], "position": pos})
        short_to_tid[t["x_mitre_shortname"]] = ext_id(t)

    techniques, alive = [], set()
    for o in objs:
        if o["type"] != "attack-pattern" or dead(o):
            continue
        tid = ext_id(o)
        alive.add(tid)
        techniques.append({
            "id": tid, "name": o["name"], "is_sub": bool(o.get("x_mitre_is_subtechnique")),
            "parent": tid.split(".")[0] if "." in tid else None,
            "tactics": [short_to_tid[k["phase_name"]] for k in o.get("kill_chain_phases", []) if k["phase_name"] in short_to_tid],
            "platforms": o.get("x_mitre_platforms", []),
            "url": "https://attack.mitre.org/techniques/" + tid.replace(".", "/"),
        })
    techniques.sort(key=lambda t: t["id"])

    # Anciennes références -> technique vivante (relations revoked-by)
    redirect = {}
    for o in objs:
        if o["type"] == "relationship" and o.get("relationship_type") == "revoked-by":
            s, t = by_id.get(o["source_ref"]), by_id.get(o["target_ref"])
            if s and t and s["type"] == "attack-pattern" and ext_id(s) and ext_id(t):
                redirect[ext_id(s)] = ext_id(t)

    def resolve(tid):
        seen = set()
        while tid not in alive and tid in redirect and tid not in seen:
            seen.add(tid)
            tid = redirect[tid]
        return tid if tid in alive else None

    tech_tactics = {t["id"]: t["tactics"] for t in techniques}

    files = sorted(f for d in RULE_DIRS for f in glob.glob(os.path.join(UP, "sigma", d, "**", "*.yml"), recursive=True))
    custom = sorted(glob.glob(os.path.join(CUSTOM_DIR, "**", "*.yml"), recursive=True))
    files += custom
    if LIMIT:
        files = files[:: max(1, len(files) // LIMIT)][:LIMIT]
    sigma_commit = subprocess.check_output(["git", "-C", os.path.join(UP, "sigma"), "rev-parse", "HEAD"]).decode().strip()

    sources = {s[0]: {"id": s[0], "family": s[1], "label": s[2], "description": s[3], "products": s[4], "auto": False,
                      "minor": len(s) > 5 and bool(s[5])} for s in catalog.SOURCES}
    stats = {k: 0 for k in ["rules", "no_technique", "unresolved_tag"]}
    conv_ok = {}
    seen_uids = set()
    native_counts = {"splunk": 0, "sentinel": 0}
    native_commits = {}
    rules_path = os.path.join(DIST, "rules.ndjson.gz")
    n = 0
    with gzip.open(rules_path, "wt", encoding="utf8", compresslevel=9) as gz, ProcessPoolExecutor(max_workers=4) as ex:
        for path, text, queries in ex.map(convert_rule, files, chunksize=20):
            r = yaml.safe_load(text)
            if not isinstance(r, dict) or "detection" not in r or not r.get("id") or str(r["id"]) in seen_uids:
                continue
            seen_uids.add(str(r["id"]))
            ls = r.get("logsource") or {}
            prod, serv, cat = ls.get("product"), ls.get("service"), ls.get("category")
            src_ids, auto = catalog.grants_for(prod, serv, cat)
            if auto and auto[0] not in sources:
                sources[auto[0]] = {"id": auto[0], "family": auto[1], "label": auto[2], "description": "", "products": [], "auto": True, "minor": True}
            techs, cves = [], []
            for tag in r.get("tags") or []:
                tag = str(tag).lower()
                m = re.fullmatch(r"attack\.(t\d{4}(?:\.\d{3})?)", tag)
                if m:
                    tid = resolve(m.group(1).upper())
                    if tid and tid not in techs:
                        techs.append(tid)
                    elif not tid:
                        stats["unresolved_tag"] += 1
                elif tag.startswith("cve."):
                    cves.append("CVE-" + tag[4:].replace(".", "-").upper())
            tacs = []
            for tag in r.get("tags") or []:
                short = str(tag).lower().removeprefix("attack.")
                if short in short_to_tid and short_to_tid[short] not in tacs:
                    tacs.append(short_to_tid[short])
            for t in techs:
                for tc in tech_tactics.get(t, []):
                    if not tacs and tc not in tacs:
                        tacs.append(tc)
            if not techs:
                stats["no_technique"] += 1
            is_custom = path.startswith(CUSTOM_DIR)
            rel = os.path.relpath(path, ROOT if is_custom else os.path.join(UP, "sigma"))
            rule = {
                "uid": str(r["id"]), "origin": "CyberAfrik" if is_custom else "sigmahq", "title": str(r.get("title", ""))[:250],
                "description": str(r.get("description", "")).strip()[:2000],
                "level": r.get("level") or "medium", "status": r.get("status") or "test",
                "author": str(r.get("author", ""))[:250], "date": str(r.get("date", ""))[:10],
                "modified": str(r.get("modified", r.get("date", "")))[:10],
                "logsource": {"product": prod, "service": serv, "category": cat},
                "sources": src_ids, "techniques": techs, "tactics": tacs, "cves": cves,
                "references": [str(x)[:500] for x in (r.get("references") or [])][:10],
                "falsepositives": [str(x)[:300] for x in (r.get("falsepositives") or [])][:10],
                "kind": "custom" if is_custom else rel.split("/")[0],
                "upstream_url": "" if is_custom else "https://github.com/SigmaHQ/sigma/blob/" + sigma_commit + "/" + rel,
                "license": str(r.get("license", "MIT")) if is_custom else "DRL-1.1",
                "sigma": text, "queries": queries, "native": None,
            }
            gz.write(json.dumps(rule, ensure_ascii=False) + "\n")
            n += 1
            for k, v in queries.items():
                conv_ok[k] = conv_ok.get(k, 0) + (1 if v else 0)

        # ------------------------------------------------ Règles natives (non converties)
        level_map = {"high": "high", "medium": "medium", "low": "low", "informational": "informational", "critical": "critical"}

        def emit(rule):
            nonlocal n
            if not rule["uid"] or rule["uid"] in seen_uids or not rule["sources"] or not rule["title"]:
                return False
            seen_uids.add(rule["uid"])
            techs = []
            for t in rule.pop("raw_techniques"):
                m = re.fullmatch(r"(T\d{4}(?:\.\d{3})?)", str(t).strip().upper())
                tid = resolve(m.group(1)) if m else None
                if tid and tid not in techs:
                    techs.append(tid)
            rule["techniques"] = techs
            rule["tactics"] = list(dict.fromkeys(tc for t in techs for tc in tech_tactics.get(t, [])))
            rule.update({"cves": rule.get("cves", []), "sigma": "", "logsource": {"product": None, "service": None, "category": None}})
            gz.write(json.dumps(rule, ensure_ascii=False) + "\n")
            n += 1
            return True

        def commit_of(repo):
            return subprocess.check_output(["git", "-C", os.path.join(UP, repo), "rev-parse", "HEAD"]).decode().strip()

        # Splunk Security Content (Apache-2.0)
        if os.path.isdir(os.path.join(UP, "splunk", "detections")):
            commit = native_commits["splunk"] = commit_of("splunk")
            type_level = {"TTP": "high", "Correlation": "high", "Anomaly": "medium", "Hunting": "low"}
            for path in sorted(glob.glob(os.path.join(UP, "splunk", "detections", "**", "*.yml"), recursive=True)):
                try:
                    r = yaml.safe_load(open(path, encoding="utf8"))
                except Exception:
                    continue
                if not isinstance(r, dict) or r.get("status") != "production" or not r.get("search"):
                    continue
                rel = os.path.relpath(path, os.path.join(UP, "splunk"))
                ok = emit({
                    "uid": str(r.get("id", "")), "origin": "splunk", "native": "splunk", "kind": "splunk",
                    "title": str(r.get("name", ""))[:250], "description": str(r.get("description", "")).strip()[:2000],
                    "level": type_level.get(r.get("type"), "medium"), "status": "stable", "author": str(r.get("author", ""))[:250],
                    "date": str(r.get("creation_date", r.get("date", "")))[:10], "modified": str(r.get("modification_date", r.get("date", "")))[:10],
                    "sources": catalog.splunk_sources(r.get("data_source")), "raw_techniques": r.get("mitre_attack_id") or (r.get("tags") or {}).get("mitre_attack_id") or [],
                    "cves": [str(c)[:30] for c in (r.get("cve") or [])][:10],
                    "references": [str(x)[:500] for x in (r.get("references") or [])][:10],
                    "falsepositives": [str(r.get("known_false_positives", "")).strip()[:300]] if r.get("known_false_positives") else [],
                    "upstream_url": "https://github.com/splunk/security_content/blob/" + commit + "/" + rel, "license": "Apache-2.0",
                    "queries": {"splunk": str(r["search"]).strip()[:20000]},
                })
                native_counts["splunk"] += 1 if ok else 0

        # Microsoft Sentinel (MIT)
        if os.path.isdir(os.path.join(UP, "sentinel")):
            commit = native_commits["sentinel"] = commit_of("sentinel")
            base = os.path.join(UP, "sentinel")
            paths = sorted(glob.glob(os.path.join(base, "Detections", "**", "*.yaml"), recursive=True)
                           + glob.glob(os.path.join(base, "Solutions", "*", "Analytic Rules", "**", "*.yaml"), recursive=True))
            parsed = []
            for path in paths:
                try:
                    r = yaml.safe_load(open(path, encoding="utf-8-sig"))
                except Exception:
                    continue
                if not isinstance(r, dict) or not r.get("query") or not r.get("id") or r.get("kind") not in ("Scheduled", "NRT"):
                    continue
                rel = os.path.relpath(path, base)
                tables = [str(t).split(" ")[0] for c in (r.get("requiredDataConnectors") or []) for t in (c.get("dataTypes") or [])]
                if any(t in catalog.SENTINEL_SKIP for t in tables):
                    continue
                known = list(dict.fromkeys(catalog.SENTINEL_TABLES[t] for t in tables if t in catalog.SENTINEL_TABLES))
                solution = rel.split(os.sep)[1] if rel.startswith("Solutions") else None
                if not known and solution in catalog.SENTINEL_SOLUTIONS:
                    known = [catalog.SENTINEL_SOLUTIONS[solution]]
                if not known and not solution and rel.split(os.sep)[1] == "Syslog":
                    known = ["linux_syslog"]
                parsed.append((r, rel, known, solution))
            per_solution = {}
            for r, rel, known, solution in parsed:
                if not known and solution:
                    per_solution[solution] = per_solution.get(solution, 0) + 1
            for r, rel, known, solution in parsed:
                if not known:
                    if not solution or per_solution.get(solution, 0) < catalog.SENTINEL_VENDOR_MIN:
                        continue
                    sid = "sx_" + catalog.slug(solution)
                    if sid not in sources:
                        sources[sid] = {"id": sid, "family": "vendor", "label": solution[:150],
                                        "description": "Règles du connecteur Microsoft Sentinel pour ce produit", "products": [], "auto": False, "minor": True}
                    known = [sid]
                ok = emit({
                    "uid": str(r["id"]), "origin": "sentinel", "native": "sentinel_kql", "kind": "sentinel",
                    "title": str(r.get("name", ""))[:250], "description": str(r.get("description", "")).strip().strip("'").strip()[:2000],
                    "level": level_map.get(str(r.get("severity", "")).lower(), "medium"), "status": "stable",
                    "author": str(((r.get("metadata") or {}).get("author") or {}).get("name", "Microsoft"))[:250],
                    "date": "", "modified": str(r.get("version", ""))[:10],
                    "sources": known, "raw_techniques": r.get("relevantTechniques") or [], "references": [], "falsepositives": [],
                    "upstream_url": "https://github.com/Azure/Azure-Sentinel/blob/" + commit + "/" + rel.replace(os.sep, "/").replace(" ", "%20"),
                    "license": "MIT", "queries": {"sentinel_kql": str(r["query"]).strip()[:20000]},
                })
                native_counts["sentinel"] += 1 if ok else 0
    stats["rules"] = n

    playbooks = json.load(open(os.path.join(ROOT, "src", "playbooks.json"), encoding="utf8"))
    for pb in playbooks:
        pb["techniques"] = [t for t in dict.fromkeys(resolve(x) for x in pb["techniques"]) if t]

    languages = [
        {"id": "sigma", "label": "Sigma", "note": "Format pivot. Les autres langages sont générés à partir de cette règle."},
        {"id": "splunk", "label": "Splunk", "note": "SPL. Règles converties : noms de champs des add-ons Windows. Règles natives Splunk : elles utilisent les macros et les data models CIM du contenu Splunk ES (ESCU)."},
        {"id": "elastic_lucene", "label": "Elastic (Lucene)", "note": "Requête Lucene sur le schéma ECS."},
        {"id": "elastic_esql", "label": "Elastic (ES|QL)", "note": "Requête ES|QL sur le schéma ECS."},
        {"id": "sentinel_kql", "label": "Sentinel (KQL)", "note": "KQL sur les tables normalisées ASIM de Microsoft Sentinel."},
        {"id": "falcon_logscale", "label": "Falcon (LogScale)", "note": "Requête LogScale sur la télémétrie de l'agent Falcon."},
        {"id": "cortex_xql", "label": "Cortex (XQL)", "note": "XQL sur le jeu de données xdr_data de Cortex XDR / XSIAM."},
    ]
    cat = {
        "attack_version": attack_version, "tactics": tactics, "techniques": techniques,
        "families": [{"id": f[0], "label": f[1], "position": i} for i, f in enumerate(catalog.FAMILIES)],
        "sources": list(sources.values()), "playbooks": playbooks, "languages": languages,
    }
    json.dump(cat, open(os.path.join(DIST, "catalog.json"), "w", encoding="utf8"), ensure_ascii=False)

    def sha(p):
        h = hashlib.sha256()
        with open(p, "rb") as f:
            for chunk in iter(lambda: f.read(1 << 20), b""):
                h.update(chunk)
        return h.hexdigest()

    manifest = {
        "version": datetime.datetime.now(datetime.timezone.utc).strftime("%Y.%m.%d.%H%M"),
        "generated_at": datetime.datetime.now(datetime.timezone.utc).isoformat(timespec="seconds"),
        "upstream": {"attack": attack_version, "sigmahq_commit": sigma_commit, **native_commits},
        "native": native_counts,
        "counts": {"tactics": len(tactics), "techniques": len(techniques), "sources": len(sources), "rules": n},
        "conversion": conv_ok, "stats": stats,
        "files": {f: {"sha256": sha(os.path.join(DIST, f)), "bytes": os.path.getsize(os.path.join(DIST, f))}
                  for f in ["catalog.json", "rules.ndjson.gz"]},
    }
    json.dump(manifest, open(os.path.join(DIST, "manifest.json"), "w"), indent=1)
    print(json.dumps(manifest, indent=1))


if __name__ == "__main__":
    main()
