#!/usr/bin/env python3
"""
E2E smoke for Sole Tigre CRM dev — common pages + len-bridge routes.

Usage:
  python3 tools/e2e_dev_smoke.py
  python3 tools/e2e_dev_smoke.py --base https://dev.crm.soletigre.com

Requires admin API key (Bearer) — reads from dev DB on multihost via SSH,
or pass CRM_E2E_API_KEY in env.
"""
from __future__ import annotations

import argparse
import json
import os
import re
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass, field


DEFAULT_BASE = "https://dev.crm.soletigre.com"
MULTIHOST = "64.95.10.156"
DEV_DB = "/var/www/dev.crm.soletigre.com/db/crm.db"


@dataclass
class Result:
    name: str
    method: str
    url: str
    status: int | None
    ok: bool
    detail: str = ""


@dataclass
class Report:
    base: str
    results: list[Result] = field(default_factory=list)

    def add(self, r: Result) -> None:
        self.results.append(r)

    @property
    def failed(self) -> list[Result]:
        return [r for r in self.results if not r.ok]

    def print_summary(self) -> int:
        for r in self.results:
            mark = "OK" if r.ok else "FAIL"
            st = r.status if r.status is not None else "—"
            line = f"[{mark}] {r.method} {r.status} {r.name}"
            if r.detail:
                line += f" — {r.detail}"
            print(line)
        fails = self.failed
        print(f"\n{len(self.results) - len(fails)}/{len(self.results)} passed")
        return 1 if fails else 0


def fetch_api_key_via_ssh() -> str:
    cmd = [
        "sshpass",
        "-f",
        os.path.expanduser("~/.ssh/multihost.pass"),
        "ssh",
        "-o",
        "StrictHostKeyChecking=no",
        f"root@{MULTIHOST}",
        f"sqlite3 {DEV_DB} \"SELECT api_key FROM users WHERE username='rizzn' AND is_active=1 LIMIT 1;\"",
    ]
    out = subprocess.check_output(cmd, text=True, stderr=subprocess.DEVNULL).strip()
    if not out:
        raise RuntimeError("Could not load rizzn api_key from dev DB")
    return out


def fetch_poll_key_via_ssh() -> str:
    cmd = [
        "sshpass",
        "-f",
        os.path.expanduser("~/.ssh/multihost.pass"),
        "ssh",
        "-o",
        "StrictHostKeyChecking=no",
        f"root@{MULTIHOST}",
        "cat /var/www/dev.crm.soletigre.com/db/len_bridge_poll_api_key.txt",
    ]
    return subprocess.check_output(cmd, text=True, stderr=subprocess.DEVNULL).strip()


def http(
    report: Report,
    name: str,
    method: str,
    url: str,
    *,
    headers: dict | None = None,
    data: bytes | None = None,
    expect_status: int = 200,
    body_check: str | None = None,
    json_ok: bool = False,
) -> urllib.request.Request:
    req = urllib.request.Request(url, data=data, method=method, headers=headers or {})
    status: int | None = None
    body = ""
    try:
        with urllib.request.urlopen(req, timeout=45) as resp:
            status = resp.getcode()
            body = resp.read().decode("utf-8", errors="replace")
    except urllib.error.HTTPError as e:
        status = e.code
        body = e.read().decode("utf-8", errors="replace")
    except Exception as e:
        report.add(Result(name, method, url, None, False, str(e)))
        return

    ok = status == expect_status
    detail = ""
    if body_check and body_check not in body:
        ok = False
        detail = f"missing {body_check!r}"
    if json_ok:
        try:
            json.loads(body)
        except json.JSONDecodeError:
            ok = False
            detail = (detail + "; " if detail else "") + "invalid JSON"
    if not ok and not detail:
        detail = body[:120].replace("\n", " ")
    report.add(Result(name, method, url, status, ok, detail))


def post_form(report: Report, name: str, url: str, fields: dict, headers: dict) -> None:
    body = urllib.parse.urlencode(fields).encode()
    h = dict(headers)
    h["Content-Type"] = "application/x-www-form-urlencoded"
    http(report, name, "POST", url, headers=h, data=body, expect_status=200, body_check="Settings")


def main() -> int:
    p = argparse.ArgumentParser(description="CRM dev E2E smoke")
    p.add_argument("--base", default=os.environ.get("CRM_E2E_BASE", DEFAULT_BASE))
    args = p.parse_args()
    base = args.base.rstrip("/")
    report = Report(base=base)

    api_key = os.environ.get("CRM_E2E_API_KEY") or fetch_api_key_via_ssh()
    poll_key = os.environ.get("CRM_LEN_POLL_KEY") or fetch_poll_key_via_ssh()
    auth = {"Authorization": f"Bearer {api_key}"}

    # Public / unauthenticated
    http(report, "login page", "GET", f"{base}/login.php", expect_status=200, body_check="login")
    http(
        report,
        "len health",
        "GET",
        f"{base}/len-bridge/widget/health.php",
        expect_status=200,
        json_ok=True,
    )

    # Admin pages (API key auth)
    pages = [
        "dashboard",
        "contacts",
        "deals",
        "merges",
        "reports",
        "webhooks",
        "users",
        "settings",
        "help",
        "profile",
    ]
    for page in pages:
        http(
            report,
            f"page:{page}",
            "GET",
            f"{base}/index.php?page={page}",
            headers=auth,
            expect_status=200,
        )

    # Settings GET should include Ask Len section
    http(
        report,
        "settings has Ask Len",
        "GET",
        f"{base}/index.php?page=settings",
        headers=auth,
        expect_status=200,
        body_check="Ask Len",
    )

    # Len settings save (the 500 repro)
    post_form(
        report,
        "settings save Ask Len",
        f"{base}/index.php?page=settings",
        {
            "action": "save_ask_len_connection",
            "ask_len_enabled": "1",
            "sanctum_url": "https://sanctum.zero1.network:8443",
            "agent_id": "agent-1265f1ed-ddf5-4da8-b768-d8209c01ac51",
            "agent_label": "Len Vernal",
        },
        auth,
    )

    # Toggle off and on again
    post_form(
        report,
        "settings disable Ask Len",
        f"{base}/index.php?page=settings",
        {
            "action": "save_ask_len_connection",
            "sanctum_url": "https://sanctum.zero1.network:8443",
            "agent_id": "agent-1265f1ed-ddf5-4da8-b768-d8209c01ac51",
            "agent_label": "Len Vernal",
        },
        auth,
    )
    post_form(
        report,
        "settings re-enable Ask Len",
        f"{base}/index.php?page=settings",
        {
            "action": "save_ask_len_connection",
            "ask_len_enabled": "1",
            "sanctum_url": "https://sanctum.zero1.network:8443",
            "agent_id": "agent-1265f1ed-ddf5-4da8-b768-d8209c01ac51",
            "agent_label": "Len Vernal",
        },
        auth,
    )

    # Branding save
    post_form(
        report,
        "settings save branding",
        f"{base}/index.php?page=settings",
        {"action": "save_branding", "app_name": "Sole Tigre sCRM"},
        auth,
    )

    # Dashboard should embed Ask Len when enabled
    http(
        report,
        "dashboard Ask Len embed",
        "GET",
        f"{base}/index.php?page=dashboard",
        headers=auth,
        expect_status=200,
        body_check="SanctumChat",
    )

    # Len-bridge widget assets
    for path in [
        "/len-bridge/widget/assets/css/widget.css",
        "/len-bridge/widget/assets/js/chat-widget.js",
        "/len-bridge/widget/config.php",
    ]:
        http(report, f"asset:{path.split('/')[-1]}", "GET", f"{base}{path}", expect_status=200)

    # Broca poll routes
    poll_auth = {"Authorization": f"Bearer {poll_key}"}
    http(
        report,
        "len inbox poll",
        "GET",
        f"{base}/len-bridge/api/v1/index.php?action=inbox",
        headers=poll_auth,
        expect_status=200,
        json_ok=True,
    )
    # Broca outbox is POST-only (agent → widget delivery)
    http(
        report,
        "len outbox POST empty",
        "POST",
        f"{base}/len-bridge/api/v1/index.php?action=outbox",
        headers={**poll_auth, "Content-Type": "application/json"},
        data=json.dumps({"session_id": "e2e-smoke", "response": "ping"}).encode(),
        expect_status=400,
        json_ok=True,
    )
    http(
        report,
        "len resolve_user_key",
        "POST",
        f"{base}/len-bridge/api/v1/index.php?action=resolve_user_key",
        headers={**poll_auth, "Content-Type": "application/json"},
        data=json.dumps({"crm_user_id": 1}).encode(),
        expect_status=200,
        json_ok=True,
    )

    # CRM REST API v1
    http(
        report,
        "crm api contacts",
        "GET",
        f"{base}/api/v1/index.php?path=/contacts&limit=1",
        headers=auth,
        expect_status=200,
        json_ok=True,
    )

    # Len session routes work with CRM API key (same as logged-in user)
    for action in ["user_session", "history"]:
        req_url = f"{base}/len-bridge/api/v1/index.php?action={action}"
        http(report, f"len {action}", "GET", req_url, headers=auth, expect_status=200, json_ok=True)

    return report.print_summary()


if __name__ == "__main__":
    sys.exit(main())
