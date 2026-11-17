#!/usr/bin/env bash
# Construit le paquet de référentiel dans pipeline/dist
set -euo pipefail
cd "$(dirname "$0")"
mkdir -p upstream
[ -d upstream/sigma ] && git -C upstream/sigma pull -q --depth 1 || git clone -q --depth 1 https://github.com/SigmaHQ/sigma.git upstream/sigma
[ -d upstream/splunk ] && git -C upstream/splunk pull -q --depth 1 || git clone -q --depth 1 https://github.com/splunk/security_content.git upstream/splunk
if [ ! -d upstream/sentinel ]; then
  git clone -q --depth 1 --filter=blob:none --sparse https://github.com/Azure/Azure-Sentinel.git upstream/sentinel
  git -C upstream/sentinel sparse-checkout set --no-cone '/LICENSE' '/Detections/**' '/Solutions/*/Analytic Rules/**'
else
  git -C upstream/sentinel pull -q --depth 1
fi
curl -fsSL -o upstream/enterprise-attack.json https://raw.githubusercontent.com/mitre-attack/attack-stix-data/master/enterprise-attack/enterprise-attack.json
python3 src/build.py
