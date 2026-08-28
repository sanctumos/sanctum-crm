#!/usr/bin/env bash
# Install len_crm SMCP plugin beside broca-len on moya (run on 223 as rizzn).
set -euo pipefail

PLUGIN_SRC="${1:-$HOME/sanctum/repos/sanctum-crm/smcp_plugin/len_crm}"
BROCA_LEN="${BROCA_LEN_HOME:-$HOME/sanctum/agents/len/broca}"
DEST="${BROCA_LEN}/smcp_plugins/len_crm"

if [[ ! -d "$PLUGIN_SRC" ]]; then
  echo "Missing plugin source: $PLUGIN_SRC" >&2
  exit 1
fi

mkdir -p "$(dirname "$DEST")"
rm -rf "$DEST"
cp -a "$PLUGIN_SRC" "$DEST"
chmod +x "$DEST/cli.py" 2>/dev/null || true
echo "Installed len_crm SMCP to $DEST"
