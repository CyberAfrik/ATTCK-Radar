#!/usr/bin/env bash
# Construit le paquet de référentiel dans pipeline/dist
set -euo pipefail
cd "$(dirname "$0")"
mkdir -p upstream
[ -d upstream/sigma ] && git -C upstream/sigma pull -q --depth 1 || git clone -q --depth 1 https://github.com/SigmaHQ/sigma.git upstream/sigma
curl -fsSL -o upstream/enterprise-attack.json https://raw.githubusercontent.com/mitre-attack/attack-stix-data/master/enterprise-attack/enterprise-attack.json
python3 src/build.py
