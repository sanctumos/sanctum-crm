#!/usr/bin/env python3
"""
E2E smoke for Sanctum CRM + overlays — common pages + len-bridge routes.

Usage:
  python3 tools/e2e_dev_smoke.py
  python3 tools/e2e_dev_smoke.py --profile soletigre
  python3 tools/e2e_dev_smoke.py --profile dsc --base https://dev.crm.decisionsciencecorp.com
  python3 tools/e2e_dev_smoke.py --matrix   # Sole Tigre + DSC when bases resolve

Auth: CRM_E2E_API_KEY, or SSH multihost SQLite for known hosts, or ~/.ssh/dsc-crm-api.pass.
"""
from __future__ import annotations

import argparse
import json
import os
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass, field


MULTIHOST = "64.95.10.156"

PROFILES = {
    "soletigre": {
        "base": "https://dev.crm.soletigre.com",
        "db": "/var/www/dev.crm.soletigre.com/db/crm.db",
        "poll_file": "/var/www/dev.crm.soletigre.com/db/len_bridge_poll_api_key.txt",
        "brand_name": "Sole Tigre sCRM",
    },
    "dsc": {
        "base": "https://dev.crm.decisionsciencecorp.com",
        "db": "/var/www/dev.crm.decisionsciencecorp.com/db/crm.db",
        "poll_file": "/var/www/dev.crm.decisionsciencecorp.com/db/len_bridge_poll_api_key.txt",
        "brand_name": "Decision Science Corp CRM",
        "api_pass": os.path.expanduser("~/.ssh/dsc-crm-api.pass"),
    },
}


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


def _ssh(remote_cmd: str) -> str:
    cmd = [
        "sshpass",
        "-f",
        os.path.expanduser("~/.ssh/multihost.pass"),
        "ssh",
        "-o",
        "StrictHostKeyChecking=no",
        f"root@{MULTIHOST}",
        remote_cmd,
    ]
    return subprocess.check_output(cmd, text=True, stderr=subprocess.DEVNULL).strip()


def fetch_api_key(profile: dict) -> str:
    if os.environ.get("CRM_E2E_API_KEY"):
        return os.environ["CRM_E2E_API_KEY"]
    pass_file = profile.get("api_pass")
    if pass_file and os.path.isfile(pass_file):
        env: dict[str, str] = {}
        for line in open(pass_file, encoding="utf-8"):
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            k, v = line.split("=", 1)
            env[k.strip()] = v.strip().strip("\"'")
        key = env.get("DSC_CRM_API_KEY") or env.get("CRM_API_KEY") or ""
        if key:
            return key
    db = profile.get("db")
    if db:
        out = _ssh(
            f"sqlite3 {db} \"SELECT api_key FROM users WHERE username='rizzn' AND is_active=1 LIMIT 1;\""
        )
        if out:
            return out
    raise RuntimeError("Could not resolve CRM API key (env / pass file / multihost DB)")


def fetch_poll_key(profile: dict) -> str:
    if os.environ.get("CRM_LEN_POLL_KEY"):
        return os.environ["CRM_LEN_POLL_KEY"]
    poll_file = profile.get("poll_file")
    if not poll_file:
        raise RuntimeError("No poll key path for profile")
    return _ssh(f"cat {poll_file}")


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


def run_smoke(base: str, profile: dict) -> int:
    report = Report(base=base)
    api_key = fetch_api_key(profile)
    poll_key = fetch_poll_key(profile)
    auth = {"Authorization": f"Bearer {api_key}"}
    brand = profile.get("brand_name") or "Sanctum CRM"

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

    # Branding save — keep overlay brand name for that host
    post_form(
        report,
        "settings save branding",
        f"{base}/index.php?page=settings",
        {"action": "save_branding", "app_name": brand},
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
    http(
        report,
        "crm api contact tags catalog",
        "GET",
        f"{base}/api/v1/index.php?path=/contacts/tags",
        headers=auth,
        expect_status=200,
        json_ok=True,
    )

    # Len session routes work with CRM API key (same as logged-in user)
    for action in ["user_session", "history"]:
        req_url = f"{base}/len-bridge/api/v1/index.php?action={action}"
        http(report, f"len {action}", "GET", req_url, headers=auth, expect_status=200, json_ok=True)

    print(f"\n=== smoke {base} ===")
    return report.print_summary()


def host_resolves(url: str) -> bool:
    try:
        req = urllib.request.Request(url, method="GET")
        with urllib.request.urlopen(req, timeout=15) as resp:
            return resp.getcode() < 500
    except Exception:
        return False


def main() -> int:
    p = argparse.ArgumentParser(description="CRM overlay E2E smoke (Sole Tigre + DSC)")
    p.add_argument("--profile", choices=sorted(PROFILES), default=os.environ.get("CRM_E2E_PROFILE", "soletigre"))
    p.add_argument("--base", default=os.environ.get("CRM_E2E_BASE"))
    p.add_argument(
        "--matrix",
        action="store_true",
        help="Run soletigre + dsc profiles (skips host that does not resolve)",
    )
    args = p.parse_args()

    if args.matrix:
        rc = 0
        for name, profile in PROFILES.items():
            base = profile["base"]
            if not host_resolves(f"{base}/login.php"):
                print(f"SKIP {name}: {base} not reachable")
                continue
            print(f"\n######## PROFILE {name} ########")
            try:
                rc |= run_smoke(base, profile)
            except Exception as e:
                print(f"FAIL {name}: {e}")
                rc = 1
        return rc

    profile = dict(PROFILES[args.profile])
    base = (args.base or profile["base"]).rstrip("/")
    return run_smoke(base, profile)


if __name__ == "__main__":
    sys.exit(main())
