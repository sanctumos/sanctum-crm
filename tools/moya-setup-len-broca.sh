#!/usr/bin/env bash
# Setup broca-len on moya from Wren broca tree. Run on moya as rizzn.
set -euo pipefail

LEN=/home/rizzn/sanctum/agents/len
WREN=/home/rizzn/sanctum/agents/wren/broca
POLL_FILE="${1:-/tmp/len_bridge_poll_api_key.txt}"
CRM_BASE="${2:-https://dev.crm.soletigre.com}"
AGENT_ID="$(cat "$LEN/agent_id.txt")"

if [[ ! -f "$POLL_FILE" ]]; then
  echo "missing poll key file: $POLL_FILE" >&2
  exit 1
fi
POLL_KEY="$(tr -d '\n\r' < "$POLL_FILE")"
if [[ ${#POLL_KEY} -lt 16 ]]; then
  echo "poll key too short" >&2
  exit 1
fi

mkdir -p "$LEN"
cp -a "$POLL_FILE" "$LEN/len_bridge_poll_api_key.txt"
chmod 600 "$LEN/len_bridge_poll_api_key.txt"

if [[ -d "$LEN/broca" ]]; then
  cp -a "$LEN/broca" "$LEN/broca.bak.$(date +%Y%m%d%H%M%S)"
fi

rsync -a --delete \
  --exclude 'run/' \
  --exclude 'sanctum.db*' \
  --exclude '__pycache__/' \
  --exclude '*.bak*' \
  "$WREN/" "$LEN/broca/"

mkdir -p "$LEN/broca/run"
rm -rf "$LEN/broca/plugins/telegram_bot" 2>/dev/null || true
rm -rf "$LEN/broca/plugins/sanctum_otto_bridge" 2>/dev/null || true

# Rename webchat plugin wren → len
if [[ -d "$LEN/broca/plugins/wren_vernal_webchat" ]]; then
  rm -rf "$LEN/broca/plugins/len_vernal_webchat"
  mv "$LEN/broca/plugins/wren_vernal_webchat" "$LEN/broca/plugins/len_vernal_webchat"
fi

# Point settings at CRM_LEN_* env vars; keep Wren names as fallbacks
python3 - <<'PY'
from pathlib import Path
p = Path("/home/rizzn/sanctum/agents/len/broca/plugins/len_vernal_webchat/settings.py")
text = p.read_text()
text = text.replace("wren_vernal_webchat", "len_vernal_webchat")
text = text.replace(
    "api_url=os.getenv('DOCKET_WREN_BRIDGE_API_URL', os.getenv('WEB_CHAT_API_URL', 'http://localhost:8000'))",
    "api_url=os.getenv('CRM_LEN_BRIDGE_API_URL', os.getenv('DOCKET_WREN_BRIDGE_API_URL', os.getenv('WEB_CHAT_API_URL', 'http://localhost:8000')))",
)
text = text.replace(
    "api_key=os.getenv('DOCKET_WREN_BRIDGE_POLL_API_KEY', os.getenv('WEB_CHAT_API_KEY', ''))",
    "api_key=os.getenv('CRM_LEN_BRIDGE_POLL_API_KEY', os.getenv('DOCKET_WREN_BRIDGE_POLL_API_KEY', os.getenv('WEB_CHAT_API_KEY', '')))",
)
p.write_text(text)

# message_handler: accept crm_user_id + publish current_crm_user_id.txt
mh = Path("/home/rizzn/sanctum/agents/len/broca/plugins/len_vernal_webchat/message_handler.py")
t = mh.read_text()
t = t.replace("current_docket_user_id.txt", "current_crm_user_id.txt")
t = t.replace('f"docket:{tasks_user_id}"', 'f"crm:{tasks_user_id}"')
t = t.replace("(Docket user id", "(CRM user id")
t = t.replace("Greet them by username (Docket).", "Greet them by username (CRM).")
t = t.replace("Docket username:", "CRM username:")
t = t.replace("Docket user id:", "CRM user id:")
t = t.replace('"identity_scope": "docket_user"', '"identity_scope": "crm_user"')
# Prefer crm_user_id from inbox rows
old = "message_data.get(\"docket_user_id\") or message_data.get(\"tasks_user_id\")"
new = "message_data.get(\"crm_user_id\") or message_data.get(\"docket_user_id\") or message_data.get(\"tasks_user_id\")"
if old not in t:
    raise SystemExit("message_handler parse site not found")
t = t.replace(old, new)
old2 = "message_data.get(\"tasks_username\") or \"\""
new2 = "message_data.get(\"crm_username\") or message_data.get(\"tasks_username\") or \"\""
t = t.replace(old2, new2, 1)
mh.write_text(t)

# tasks_tool_bridge → resolve crm_user_id for Len bridge
tb = Path("/home/rizzn/sanctum/agents/len/broca/plugins/len_vernal_webchat/tasks_tool_bridge.py")
tb.write_text(
'''"""Resolve per-user CRM API keys via Len bridge (poll-auth only)."""

from __future__ import annotations

import logging
from typing import Optional

import aiohttp

logger = logging.getLogger(__name__)


async def resolve_crm_api_key(
    crm_user_id: int,
    *,
    bridge_api_base: str,
    poll_api_key: str,
    timeout: int = 30,
) -> Optional[str]:
    """POST resolve_user_key — server returns hidden key for SMCP injection."""
    if crm_user_id <= 0:
        return None
    base = bridge_api_base.rstrip("/") + "/"
    url = base + "?action=resolve_user_key"
    headers = {
        "Authorization": f"Bearer {poll_api_key}",
        "Content-Type": "application/json",
    }
    try:
        async with aiohttp.ClientSession() as session:
            async with session.post(
                url,
                json={"crm_user_id": crm_user_id},
                headers=headers,
                timeout=aiohttp.ClientTimeout(total=timeout),
            ) as resp:
                if resp.status != 200:
                    logger.warning("resolve_user_key HTTP %s", resp.status)
                    return None
                body = await resp.json()
                if not body.get("success"):
                    return None
                data = body.get("data") or {}
                key = data.get("api_key")
                return str(key) if key else None
    except Exception as exc:
        logger.error("resolve_crm_api_key failed: %s", exc)
        return None


# Back-compat alias for any caller still using Tasks naming
async def resolve_tasks_api_key(tasks_user_id: int, **kwargs):
    return await resolve_crm_api_key(tasks_user_id, **kwargs)
'''
)
print("plugin patches ok")
PY

# Broca .env
cat > "$LEN/broca/.env" <<ENV
DEBUG_MODE=false
MESSAGE_MODE=live
QUEUE_REFRESH=5
MAX_RETRIES=3
LOG_LEVEL=INFO
AGENT_ID=$AGENT_ID
CRM_LEN_BRIDGE_API_URL=${CRM_BASE}/len-bridge/api/v1/
CRM_LEN_BRIDGE_POLL_API_KEY=${POLL_KEY}
WEB_CHAT_POLL_INTERVAL=5
WEB_CHAT_PLATFORM_NAME=len_vernal_webchat
WEB_CHAT_PLUGIN_NAME=len_vernal_webchat
BROCA_RUN_DIR=$LEN/broca/run
CRM_LEN_CHATTER_FILE=$LEN/broca/run/current_crm_user_id.txt
CRM_API_BASE_URL=${CRM_BASE}
CRM_LEN_CRM_API_BASE=${CRM_BASE}
ENV
chmod 600 "$LEN/broca/.env"

# Parent .env for start script (AGENT_ID + Letta)
if [[ ! -f "$LEN/.env" ]]; then
  cat > "$LEN/.env" <<ENV
AGENT_ID=$AGENT_ID
AGENT_ENDPOINT=http://127.0.0.1:8284
ENV
  # Pull AGENT_API_KEY from Wren/Q if present
  for src in /home/rizzn/sanctum/agents/wren/.env /home/rizzn/sanctum/agents/q/broca/.env /home/rizzn/sanctum/agents/athena/broca/.env; do
    if [[ -f "$src" ]] && grep -q '^AGENT_API_KEY=' "$src"; then
      grep '^AGENT_API_KEY=' "$src" >> "$LEN/.env"
      break
    fi
  done
  chmod 600 "$LEN/.env"
fi

cat > "$LEN/start-len-broca.sh" <<'START'
#!/bin/bash
# Idempotent Broca starter for Len Vernal (Ask Len on Sanctum CRM).
SESSION_NAME="broca-len"
PROJECT_DIR="/home/rizzn/sanctum/agents/len/broca"
PARENT_ENV="/home/rizzn/sanctum/agents/len/.env"
VENV_DIR="/home/rizzn/sanctum/venv"

screen_line=$(screen -list 2>/dev/null | grep -F "$SESSION_NAME" || true)
if echo "$screen_line" | grep -q "Dead"; then
  screen -S "$SESSION_NAME" -X quit 2>/dev/null || true
  screen_line=""
fi
if [ -n "$screen_line" ] && ! pgrep -f "$PROJECT_DIR.*main.py" >/dev/null 2>&1; then
  screen -S "$SESSION_NAME" -X quit 2>/dev/null || true
  screen_line=""
fi
if [ -n "$screen_line" ]; then
  exit 0
fi
PID_FILE="$PROJECT_DIR/run/broca.pid"
if [ -f "$PID_FILE" ] && ! pgrep -f "$PROJECT_DIR.*main.py" >/dev/null 2>&1; then
  rm -f "$PID_FILE"
fi
mkdir -p "$PROJECT_DIR/run"
CMD="cd $PROJECT_DIR && export \$(grep -v '^#' $PARENT_ENV | grep -v '^$' | xargs) && export \$(grep -v '^#' .env | grep -v '^$' | xargs) && exec $VENV_DIR/bin/python main.py >> run/broca-len.log 2>&1"
screen -dmS "$SESSION_NAME" bash -c "$CMD"
echo "started screen $SESSION_NAME"
START
chmod +x "$LEN/start-len-broca.sh"

# SMCP plugin install
mkdir -p "$LEN/smcp/plugins"
rm -rf "$LEN/smcp/plugins/len_crm"
if [[ -d /tmp/len_crm ]]; then
  cp -a /tmp/len_crm "$LEN/smcp/plugins/len_crm"
fi
chmod +x "$LEN/smcp/plugins/len_crm/cli.py" 2>/dev/null || true

echo "OK broca-len provisioned; agent=$AGENT_ID poll_len=${#POLL_KEY} crm=$CRM_BASE"
