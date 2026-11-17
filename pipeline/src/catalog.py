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
    ("vendor", "Produits tiers (connecteurs Microsoft Sentinel)"),
    ("other", "Autres sources"),
]

# id, famille, libellé, description courte, exemples de produits, [secondaire : repliée par défaut]
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
    ("vpn", "network", "VPN / accès distant", "Journaux de la passerelle VPN : connexions, échecs, événements système",
     ["FortiGate SSL-VPN", "Palo Alto GlobalProtect", "Pulse Secure", "Cisco AnyConnect"]),
    ("network_devices", "network", "Équipements réseau (routeurs, commutateurs)",
     "Commandes d'administration (AAA / TACACS), journaux système, événements BGP et LDP", ["Cisco IOS", "Juniper", "Huawei"]),
    ("ids", "network", "IDS / IPS réseau", "Alertes et métadonnées d'une sonde de détection d'intrusion", ["Suricata", "Snort", "Cisco Firepower"]),
    ("esxi", "cloud", "Hyperviseur VMware ESXi", "Syslog des hôtes ESXi : shell, comptes, arrêt de machines virtuelles", ["VMware ESXi", "vCenter"]),
    ("azure_diag", "cloud", "Azure Diagnostics", "Journaux de ressources Azure : Key Vault, Azure Firewall, WAF, App Services",
     ["Azure Key Vault", "Azure Firewall", "Azure WAF"]),
    ("win_firewall", "endpoint_win", "Pare-feu Windows", "Canal Windows Firewall With Advanced Security : règles ajoutées, modifiées, supprimées", []),
    ("win_other_channels", "endpoint_win", "Autres journaux Windows",
     "Canaux applicatifs et services : AppLocker, Code Integrity, BITS, Task Scheduler, NTLM, WMI, PrintService, SMB, AppX, CAPI2, OpenSSH, Terminal Services",
     [], True),
    ("exchange", "app", "Exchange Server (sur site)", "Canal MSExchange Management : commandes d'administration de la messagerie", ["Microsoft Exchange"]),
    ("app_logs", "app", "Journaux applicatifs", "Erreurs et exceptions des applications web et de leurs frameworks",
     ["Java / JVM", "Spring", "Django", "Node.js", "Ruby on Rails", "Python"]),
    ("database", "app", "Bases de données", "Journaux d'audit et d'erreurs du moteur de base de données", ["Oracle", "SQL Server", "PostgreSQL", "MySQL"]),
    ("osquery", "endpoint_nix", "Osquery", "Résultats des requêtes osquery planifiées sur les postes et serveurs", ["osquery"], True),
    ("cisco_duo", "identity_saas", "Cisco Duo (MFA)", "Journaux d'authentification et d'administration Duo", ["Cisco Duo"], True),
    ("onelogin", "identity_saas", "OneLogin", "Événements du fournisseur d'identité OneLogin", ["OneLogin"], True),
    ("bitbucket", "identity_saas", "Bitbucket", "Journal d'audit Bitbucket : dépôts, permissions, secrets", ["Bitbucket"], True),
    ("honeypot", "security_tools", "Honeypot", "Événements d'un leurre réseau : toute interaction est suspecte", ["OpenCanary"], True),
    ("rpc_firewall", "security_tools", "RPC Firewall", "Appels RPC filtrés et journalisés par RPC Firewall", ["RPC Firewall"], True),
    ("cisco_nvm", "network", "Cisco Network Visibility Module", "Flux réseau par processus remontés par l'agent Cisco Secure Client", ["Cisco NVM"], True),
    ("isovalent", "cloud", "Cisco Isovalent (Tetragon)", "Exécutions de processus et connexions observées dans les conteneurs via eBPF", ["Isovalent", "Tetragon"], True),
    ("ai_llm", "app", "Plateformes IA / LLM", "Journaux d'invocation et d'administration de modèles de langage", ["AWS Bedrock", "Ollama", "Serveurs MCP"], True),
]

WIN_DNS = {"dns-server", "dns-server-analytic"}
APP_PRODUCTS = {"jvm", "spring", "django", "nodejs", "python", "ruby_on_rails", "sql", "velocity"}

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
        if s == "firewall-as":
            return ["win_firewall"], None
        if s == "msexchange-management":
            return ["exchange"], None
        if s in WIN_DNS:
            return ["dns"], None
        return ["win_other_channels"], None
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
    if p == "opencanary":
        return ["honeypot"], None
    if p == "rpc_firewall":
        return ["rpc_firewall"], None
    if p == "bitbucket":
        return ["bitbucket"], None
    if p == "onelogin":
        return ["onelogin"], None
    if p == "cisco" and s == "duo":
        return ["cisco_duo"], None
    if p in ("cisco", "juniper", "huawei"):
        return ["network_devices"], None
    if p in ("fortigate", "fortios", "paloalto"):
        return ["vpn"], None
    if p in APP_PRODUCTS:
        return ["app_logs"], None
    if c == "database":
        return ["database"], None
    if p is None and s in ("apache", "nginx"):
        return ["webserver"], None
    # Source automatique
    parts = [x for x in (p, s if s != p else None, c if (p is None or s is None) else None) if x]
    sid = "auto_" + "_".join(parts).replace("-", "_").replace(".", "_").replace(" ", "_").lower()
    fam = PRODUCT_FAMILY.get(p) or CATEGORY_FAMILY.get(c) or "other"
    base = PRODUCT_LABEL.get(p, (p or "").replace("_", " ").title())
    detail = s if (s and s != p) else c
    label = (base + (" · " + detail if detail else "")).strip(" ·") or "Source générique"
    return [sid], (sid, fam, label)


# ---------------------------------------------------------------- Règles natives Splunk

import re as _re

SPLUNK_PREFIXES = [
    ("Sysmon for Linux", ["edr_nix"]),
    ("Sysmon", ["sysmon", "edr"]),
    ("CrowdStrike ProcessRollup2", ["edr"]),
    ("Windows Event Log Security 4688", ["win_4688"]),
    ("Windows Event Log Security", ["win_security"]),
    ("Windows Active Directory Admon", ["win_security"]),
    ("Powershell Script Block Logging", ["win_powershell"]),
    ("Windows Event Log System", ["win_system"]),
    ("Windows Event Log Application", ["win_application"]),
    ("Windows Event Log Defender", ["win_defender"]),
    ("Windows Event Log", ["win_other_channels"]),
    ("NTLM Operational", ["win_other_channels"]),
    ("ASL AWS CloudTrail", ["aws_cloudtrail"]),
    ("AWS CloudTrail", ["aws_cloudtrail"]),
    ("Office 365", ["m365"]), ("O365", ["m365"]), ("M365", ["m365"]),
    ("Azure Active Directory", ["entra_id"]),
    ("Azure Audit", ["azure_activity"]), ("Azure Monitor Activity", ["azure_activity"]),
    ("Okta", ["okta"]), ("Kubernetes Audit", ["k8s_audit"]), ("GitHub", ["github"]),
    ("Google Workspace", ["google_workspace"]), ("G Suite", ["google_workspace"]),
    ("Linux Auditd", ["auditd"]), ("Linux Secure", ["linux_syslog"]), ("Linux Messages Syslog", ["linux_syslog"]),
    ("Nginx", ["webserver"]), ("Apache", ["webserver"]), ("Windows IIS", ["webserver"]),
    ("Suricata", ["ids"]), ("Cisco Secure Firewall Threat Defense Intrusion", ["ids"]),
    ("Zeek", ["zeek"]), ("Splunk Stream", ["zeek"]),
    ("Palo Alto Network", ["firewall"]), ("Cisco ASA", ["firewall"]), ("Cisco Secure Firewall", ["firewall"]),
    ("Cisco Secure Access Firewall", ["firewall"]), ("Cisco Secure Access Proxy", ["proxy"]), ("Cisco Secure Access DNS", ["dns"]),
    ("VMWare ESXi", ["esxi"]), ("Cisco IOS", ["network_devices"]), ("Cisco SD-WAN", ["network_devices"]),
    ("Cisco Duo", ["cisco_duo"]), ("Osquery", ["osquery"]),
    ("Cisco Network Visibility Module", ["cisco_nvm"]), ("Cisco Isovalent", ["isovalent"]),
    ("AWS Bedrock", ["ai_llm"]), ("Ollama", ["ai_llm"]), ("MCP Server", ["ai_llm"]),
]


def splunk_sources(data_sources):
    out = []
    for ds in data_sources or []:
        for part in str(ds).split(" AND "):
            part = part.strip()
            for prefix, ids in SPLUNK_PREFIXES:
                if part.startswith(prefix):
                    out += [i for i in ids if i not in out]
                    break
    return out


# ---------------------------------------------------------------- Règles natives Microsoft Sentinel

SENTINEL_TABLES = {
    "SecurityEvent": "win_security", "SecurityEvents": "win_security", "WindowsEvent": "win_security",
    "AuditLogs": "entra_id", "SigninLogs": "entra_id", "AADNonInteractiveUserSignInLogs": "entra_id",
    "AADServicePrincipalSignInLogs": "entra_id", "AADManagedIdentitySignInLogs": "entra_id",
    "AWSCloudTrail": "aws_cloudtrail", "OfficeActivity": "m365", "AzureActivity": "azure_activity",
    "AzureDiagnostics": "azure_diag", "KeyVaultData": "azure_diag", "AZFWApplicationRule": "azure_diag",
    "AZFWIdpsSignature": "azure_diag", "AZFWNetworkRule": "azure_diag",
    "DeviceProcessEvents": "edr", "DeviceNetworkEvents": "edr", "DeviceFileEvents": "edr", "DeviceEvents": "edr",
    "DeviceImageLoadEvents": "edr", "DeviceRegistryEvents": "edr", "DeviceLogonEvents": "edr",
    "DnsEvents": "dns", "Cisco_Umbrella_dns_CL": "dns", "W3CIISLog": "webserver",
    "Cisco_Umbrella_proxy_CL": "proxy", "SquidProxy_CL": "proxy",
    "GCPAuditLogs": "gcp_audit", "GWorkspaceActivityReports": "google_workspace",
    "Okta_CL": "okta", "OktaSSO": "okta", "OktaV2_CL": "okta", "GitHubAuditLogPolling_CL": "github", "GitHubAuditLogsV2_CL": "github",
}
# Tables qui ne correspondent pas à une source de logs brute (alertes, renseignement, analyses)
SENTINEL_SKIP = {"ThreatIntelligenceIndicator", "ThreatIntelIndicators", "SecurityAlert", "Anomalies", "BehaviorAnalytics",
                 "SecurityIncident", "SecurityNestedRecommendation", "Heartbeat", "LAQueryLogs"}
SENTINEL_SOLUTIONS = {"GitHub": "github", "ApacheHTTPServer": "webserver", "NGINX HTTP Server": "webserver", "OracleDatabaseAudit": "database",
                      "CiscoDuoSecurity": "cisco_duo", "DNS Essentials": "dns", "Corelight": "zeek", "Okta Single Sign-On": "okta",
                      "VMWareESXi": "esxi", "Windows Firewall": "win_firewall", "Microsoft Exchange Security - Exchange On-Premises": "exchange"}
SENTINEL_VENDOR_MIN = 5


def slug(text):
    return _re.sub(r"[^a-z0-9]+", "_", text.lower()).strip("_")[:60]
