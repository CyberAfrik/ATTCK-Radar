"""Catalogue de sources de logs : familles, sources cochables par l'utilisateur,
et correspondance logsource Sigma -> sources capables de fournir cette télémétrie.

Une règle est applicable si AU MOINS UNE des sources qui fournissent son logsource est en place.
Tout logsource non couvert par les règles ci-dessous génère une source automatique (auto=True),
à classer plus tard dans le back-office.
"""

FAMILIES = [
    ("endpoint_win", "Postes et serveurs Windows"),
    ("endpoint_nix", "Linux et macOS"),
    ("network", "Réseau"),
    ("cloud", "Cloud et conteneurs"),
    ("identity_saas", "Identité et SaaS"),
    ("security_tools", "Outils de sécurité"),
    ("app", "Applications et middleware"),
    ("other", "Autres sources"),
]

# id, famille, libellé, description courte, exemples de produits
SOURCES = [
    ("edr", "endpoint_win", "EDR / XDR", "Télémétrie poste : processus, fichiers, registre, réseau",
     ["Microsoft Defender for Endpoint", "CrowdStrike Falcon", "Cortex XDR", "SentinelOne", "Elastic Defend"]),
    ("sysmon", "endpoint_win", "Sysmon", "Sysmon déployé avec une configuration complète", ["Sysmon"]),
    ("win_security", "endpoint_win", "Journal Security Windows",
     "Postes, serveurs et contrôleurs de domaine (authentification, comptes, Kerberos, objets AD)",
     ["Active Directory", "Windows Event Forwarding"]),
    ("win_4688", "endpoint_win", "Audit de création de processus (4688)",
     "Event ID 4688 avec la ligne de commande activée", ["Windows Security"]),
    ("win_powershell", "endpoint_win", "Journalisation PowerShell",
     "Script Block Logging (4104) et Module Logging (4103)", ["PowerShell"]),
    ("win_system", "endpoint_win", "Journal System Windows", "Services, pilotes, arrêts et démarrages", []),
    ("win_application", "endpoint_win", "Journal Application Windows", "Erreurs applicatives, MSI, SQL Server", []),
    ("win_defender", "endpoint_win", "Microsoft Defender Antivirus", "Canal Windows Defender Operational", []),
    ("auditd", "endpoint_nix", "Linux auditd", "Appels système, exécutions, accès fichiers", ["auditd", "auditbeat"]),
    ("linux_syslog", "endpoint_nix", "Syslog Linux", "auth, sshd, sudo, cron, journaux système", ["rsyslog", "journald"]),
    ("edr_nix", "endpoint_nix", "EDR Linux / macOS", "Télémétrie processus, fichiers et réseau hors Windows",
     ["Elastic Defend", "CrowdStrike Falcon", "SentinelOne", "Sysmon for Linux"]),
    ("proxy", "network", "Proxy web", "Journaux de navigation sortante", ["Zscaler", "Squid", "Palo Alto Prisma", "Netskope"]),
    ("dns", "network", "DNS", "Requêtes DNS résolues par vos serveurs", ["Bind", "Windows DNS", "Infoblox", "Efficient IP"]),
    ("firewall", "network", "Pare-feu", "Flux autorisés et refusés", ["Fortigate", "Palo Alto", "Check Point", "Cisco ASA"]),
    ("webserver", "network", "Serveur web / WAF", "Journaux d'accès HTTP", ["Apache", "Nginx", "IIS", "F5", "ModSecurity"]),
    ("zeek", "network", "Sonde réseau (Zeek / NDR)", "Métadonnées réseau : SMB, DCE-RPC, HTTP, DNS, Kerberos", ["Zeek", "Corelight"]),
    ("aws_cloudtrail", "cloud", "AWS CloudTrail", "Appels d'API du compte AWS", ["AWS"]),
    ("azure_activity", "cloud", "Azure Activity Logs", "Opérations sur les ressources Azure", ["Azure"]),
    ("gcp_audit", "cloud", "GCP Audit Logs", "Journaux d'audit Google Cloud", ["Google Cloud"]),
    ("k8s_audit", "cloud", "Kubernetes Audit", "Journal d'audit de l'API server", ["Kubernetes", "EKS", "AKS", "GKE"]),
    ("entra_id", "identity_saas", "Microsoft Entra ID", "Connexions, audit, détections de risque, PIM", ["Entra ID"]),
    ("m365", "identity_saas", "Microsoft 365", "Audit unifié, Exchange Online, alertes de sécurité", ["Microsoft 365"]),
    ("okta", "identity_saas", "Okta", "System Log Okta", ["Okta"]),
    ("google_workspace", "identity_saas", "Google Workspace", "Journaux d'administration et de connexion", ["Google Workspace"]),
    ("github", "identity_saas", "GitHub", "Audit log de l'organisation", ["GitHub"]),
    ("antivirus", "security_tools", "Antivirus", "Détections de l'antivirus", ["Trend Micro", "Trellix", "ESET", "Kaspersky"]),
]

SYSMON_CATS = {
    "process_creation", "file_event", "file_change", "file_rename", "file_delete", "file_access",
    "file_executable_detected", "file_block_executable", "file_block_shredding", "image_load",
    "network_connection", "dns_query", "registry_add", "registry_delete", "registry_event", "registry_set",
    "registry_rename", "process_access", "pipe_created", "create_remote_thread", "create_stream_hash",
    "driver_load", "raw_access_thread", "wmi_event", "process_tampering", "clipboard_capture",
    "sysmon_error", "sysmon_status",
}
EDR_WIN_CATS = SYSMON_CATS - {"sysmon_error", "sysmon_status", "clipboard_capture", "create_stream_hash"}
PS_CATS = {"ps_script", "ps_module", "ps_classic_start", "ps_classic_provider_start", "ps_classic_script"}
LINUX_SYSLOG_SERVICES = {"sshd", "sudo", "auth", "syslog", "cron", "vsftpd", "clamav", "guacamole", "modsecurity", "sysmon"}

PRODUCT_FAMILY = {
    "aws": "cloud", "azure": "cloud", "gcp": "cloud", "kubernetes": "cloud", "docker": "cloud",
    "okta": "identity_saas", "onelogin": "identity_saas", "m365": "identity_saas", "github": "identity_saas",
    "bitbucket": "identity_saas", "google_workspace": "identity_saas", "cisco_duo": "identity_saas",
    "zeek": "network", "cisco": "network", "fortigate": "network", "juniper": "network", "huawei": "network",
    "paloalto": "network", "fortios": "network", "nginx": "network", "apache": "network",
    "opencanary": "security_tools", "rpc_firewall": "security_tools", "qualys": "security_tools",
    "windows": "endpoint_win", "linux": "endpoint_nix", "macos": "endpoint_nix",
    "jvm": "app", "django": "app", "python": "app", "spring": "app", "sql": "app", "ruby_on_rails": "app",
    "nodejs": "app", "velocity": "app", "database": "app", "modsecurity": "network",
}
PRODUCT_LABEL = {
    "aws": "AWS", "azure": "Azure", "gcp": "GCP", "kubernetes": "Kubernetes", "okta": "Okta", "m365": "Microsoft 365",
    "github": "GitHub", "bitbucket": "Bitbucket", "zeek": "Zeek", "cisco": "Cisco", "fortigate": "Fortigate",
    "juniper": "Juniper", "huawei": "Huawei", "paloalto": "Palo Alto", "opencanary": "OpenCanary",
    "rpc_firewall": "RPC Firewall", "windows": "Windows", "linux": "Linux", "macos": "macOS", "jvm": "JVM",
    "django": "Django", "python": "Python", "spring": "Spring", "sql": "SQL", "ruby_on_rails": "Ruby on Rails",
    "nodejs": "Node.js", "velocity": "Velocity", "onelogin": "OneLogin", "cisco_duo": "Cisco Duo",
}
CATEGORY_FAMILY = {"proxy": "network", "dns": "network", "firewall": "network", "webserver": "network",
                   "antivirus": "security_tools", "application": "app", "database": "app"}


def grants_for(product, service, category):
    """Retourne (liste d'ids de sources, source_auto ou None)."""
    p, s, c = product, service, category
    if p == "windows":
        if c in PS_CATS or s in ("powershell", "powershell-classic"):
            out = ["win_powershell"]
            if c == "ps_script":
                out.append("edr")
            return out, None
        if c == "process_creation":
            return ["edr", "sysmon", "win_4688"], None
        if c in SYSMON_CATS or s == "sysmon":
            return (["edr", "sysmon"] if c in EDR_WIN_CATS else ["sysmon"]), None
        if s == "security":
            return ["win_security"], None
        if s == "system":
            return ["win_system"], None
        if s == "application":
            return ["win_application"], None
        if s == "windefend":
            return ["win_defender"], None
    if p == "linux":
        if s == "auditd":
            return ["auditd"], None
        if c in ("process_creation", "file_event", "network_connection"):
            return ["edr_nix", "auditd"], None
        if s in LINUX_SYSLOG_SERVICES or (s is None and c is None):
            return ["linux_syslog"], None
    if p == "macos" and c in ("process_creation", "file_event", "network_connection"):
        return ["edr_nix"], None
    if p == "aws" and s == "cloudtrail":
        return ["aws_cloudtrail"], None
    if p == "azure":
        if s in ("auditlogs", "signinlogs", "riskdetection", "pim", "identityprotection"):
            return ["entra_id"], None
        if s == "activitylogs":
            return ["azure_activity"], None
    if p == "gcp":
        if s and s.startswith("google_workspace"):
            return ["google_workspace"], None
        if s == "gcp.audit":
            return ["gcp_audit"], None
    if p == "kubernetes":
        return ["k8s_audit"], None
    if p == "m365":
        return ["m365"], None
    if p == "okta":
        return ["okta"], None
    if p == "github":
        return ["github"], None
    if p == "zeek":
        return ["zeek"], None
    if p is None or p in ("nginx", "apache"):
        if c == "proxy":
            return ["proxy"], None
        if c == "dns":
            return ["dns"], None
        if c == "firewall":
            return ["firewall"], None
        if c == "webserver":
            return ["webserver"], None
        if c == "antivirus":
            return ["antivirus"], None
    # Source automatique
    parts = [x for x in (p, s if s != p else None, c if (p is None or s is None) else None) if x]
    sid = "auto_" + "_".join(parts).replace("-", "_").replace(".", "_").replace(" ", "_").lower()
    fam = PRODUCT_FAMILY.get(p) or CATEGORY_FAMILY.get(c) or "other"
    base = PRODUCT_LABEL.get(p, (p or "").replace("_", " ").title())
    detail = s if (s and s != p) else c
    label = (base + (" · " + detail if detail else "")).strip(" ·") or "Source générique"
    return [sid], (sid, fam, label)
