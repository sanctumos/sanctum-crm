#!/usr/bin/env python3
"""Send a tool-quiz to Len on dev CRM and poll for his reply."""

from __future__ import annotations

import json
import os
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request

BASE = os.environ.get("CRM_QUIZ_BASE", "https://dev.crm.soletigre.com").rstrip("/")
POLL_WAIT_S = int(os.environ.get("CRM_QUIZ_WAIT", "600"))
POLL_INTERVAL = 3

QUIZ_BODY = """[Otto · orchestrator → broca-len — managed handoff]

Mark asked me to quiz you on **dev.crm.soletigre.com**. This is the **dev** server — prod data was cloned here for testing. You do **not** need to worry about data integrity on dev.

Please run your **len_crm** SMCP tools against this instance and reply with a pass/fail table:

1. `health`
2. `me`
3. `list-contacts` (limit 3)
4. `get-contact` on the first id returned
5. `list-deals` (limit 5)
6. `list-tags`
7. `create-contact` — JSON body for a test person: first_name "Len", last_name "QuizProbe", email "len.quiz.probe@dev.soletigre.test", contact_type "lead"
8. `attach-contact-tag` on that new contact with tag "len-quiz-dev"

If you prefer Ada-style destructive confirmation tokens for writes, issue the token in your reply and Otto will relay it from Mark. On dev you may skip tokens unless you want the exercise.

Include one-line excerpts (counts, ids, names) per tool — not raw dumps."""


def ssh_sqlite_api_key() -> str:
    cmd = [
        "sshpass", "-f", os.path.expanduser("~/.ssh/multihost.pass"),
        "ssh", "-o", "StrictHostKeyChecking=no", "root@64.95.10.156",
        "sqlite3 /var/www/dev.crm.soletigre.com/db/crm.db "
        "\"SELECT api_key FROM users WHERE username='rizzn' AND is_active=1 LIMIT 1;\"",
    ]
    return subprocess.check_output(cmd, text=True).strip()


def api(method: str, url: str, headers: dict, body: dict | None = None) -> dict:
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(url, data=data, method=method, headers=headers)
    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            return json.loads(resp.read().decode())
    except urllib.error.HTTPError as e:
        raw = e.read().decode()
        try:
            return json.loads(raw)
        except json.JSONDecodeError:
            return {"success": False, "error": raw, "_http": e.code}


def main() -> int:
    api_key = os.environ.get("CRM_E2E_API_KEY") or ssh_sqlite_api_key()
    auth = {"Authorization": f"Bearer {api_key}", "Content-Type": "application/json"}

    sess = api("GET", f"{BASE}/len-bridge/api/v1/index.php?action=user_session", auth)
    if not sess.get("success"):
        print("user_session failed:", json.dumps(sess, indent=2), file=sys.stderr)
        return 1
    session_id = sess["data"]["session_id"]
    print(f"session_id={session_id}")

    msg = api(
        "POST",
        f"{BASE}/len-bridge/api/v1/index.php?action=messages",
        auth,
        {
            "session_id": session_id,
            "message": QUIZ_BODY,
            "page_context": {"surface": "settings", "admin_origin": BASE, "label": "Otto tool quiz"},
        },
    )
    if not msg.get("success"):
        print("messages POST failed:", json.dumps(msg, indent=2), file=sys.stderr)
        return 1
    print(f"queued message_id={msg['data'].get('message_id')}")

    inbox = api(
        "GET",
        f"{BASE}/len-bridge/api/v1/index.php?action=inbox",
        {"Authorization": f"Bearer {subprocess.check_output(['sshpass','-f',os.path.expanduser('~/.ssh/multihost.pass'),'ssh','root@64.95.10.156','cat /var/www/dev.crm.soletigre.com/db/len_bridge_poll_api_key.txt'], text=True).strip()}"},
    )
    print(f"inbox pending={inbox.get('data', {}).get('pagination', {}).get('total')}")

    deadline = time.time() + POLL_WAIT_S
    seen_ids: set[str] = set()
    while time.time() < deadline:
        resp = api(
            "GET",
            f"{BASE}/len-bridge/api/v1/index.php?action=responses&session_id={urllib.parse.quote(session_id)}",
            auth,
        )
        if resp.get("success"):
            for row in resp.get("data", {}).get("responses", []):
                rid = str(row.get("id", ""))
                if rid and rid not in seen_ids:
                    seen_ids.add(rid)
                    print("\n--- Len reply ---\n")
                    print(row.get("response", ""))
                    print("\n--- end ---\n")
                    return 0
        time.sleep(POLL_INTERVAL)

    print("Timed out waiting for Len reply", file=sys.stderr)
    return 2


if __name__ == "__main__":
    sys.exit(main())
