#!/usr/bin/env python3
"""Register len-crm-smcp on moya Letta and attach len_crm__ tools to Len_Vernal."""

from __future__ import annotations

import argparse
import json
import os
import sys
import urllib.error
import urllib.request
from pathlib import Path

LETTA_BASE = os.getenv("LETTA_BASE", "http://127.0.0.1:8284").rstrip("/")
AGENT_ID = os.getenv("LEN_AGENT_ID", "")
SERVER_NAME = os.getenv("LEN_SMCP_SERVER_NAME", "len-crm-smcp")
RUN_SCRIPT = os.getenv(
    "LEN_SMCP_RUN_SCRIPT",
    "/home/rizzn/sanctum/agents/len/smcp/run-smcp-stdio-for-letta.sh",
)
TOOL_PREFIX = "len_crm__"

CHATTER_TOOL_SUFFIXES = (
    "health",
    "me",
    "list-contacts",
    "get-contact",
    "create-contact",
    "update-contact",
    "list-deals",
    "get-deal",
    "create-deal",
    "update-deal",
    "list-tags",
    "list-contact-tags",
    "attach-contact-tag",
    "tool-help",
)
CHATTER_TOOL_NAMES = frozenset(f"{TOOL_PREFIX}{s}" for s in CHATTER_TOOL_SUFFIXES)


def req(method: str, path: str, body: dict | None = None) -> dict:
    key = os.environ["LETTA_API_KEY"]
    if body is not None:
        data = json.dumps(body).encode()
    elif method.upper() in ("POST", "PATCH", "PUT", "DELETE"):
        data = b""
    else:
        data = None
    r = urllib.request.Request(
        LETTA_BASE + path,
        data=data,
        method=method.upper(),
        headers={
            "Authorization": f"Bearer {key}",
            "Content-Type": "application/json",
        },
    )
    try:
        with urllib.request.urlopen(r, timeout=120) as resp:
            raw = resp.read().decode()
            return json.loads(raw) if raw else {}
    except urllib.error.HTTPError as e:
        print(e.read().decode()[:2000], file=sys.stderr)
        raise


def tool_list(payload: dict | list) -> list:
    if isinstance(payload, list):
        return payload
    return payload.get("tools") or payload.get("data") or []


def profile_allows(name: str, profile: str) -> bool:
    if not name.startswith(TOOL_PREFIX):
        return False
    if profile == "full":
        return True
    if profile == "chatter":
        return name in CHATTER_TOOL_NAMES
    raise ValueError(f"unknown profile: {profile}")


def main() -> None:
    parser = argparse.ArgumentParser(description="Attach Len CRM SMCP tools on moya Letta")
    parser.add_argument("--profile", choices=("chatter", "full"), default="chatter")
    args = parser.parse_args()
    profile = args.profile

    if not os.getenv("LETTA_API_KEY"):
        for path in (
            Path("/home/rizzn/sanctum/agents/len/.env"),
            Path("/home/rizzn/sanctum/agents/athena/broca/.env"),
            Path("/home/rizzn/sanctum/env.letta"),
        ):
            if not path.is_file():
                continue
            for line in path.read_text(encoding="utf-8").splitlines():
                if line.startswith("AGENT_API_KEY="):
                    os.environ["LETTA_API_KEY"] = line.split("=", 1)[1].strip().strip("\"'")
                    break
                if line.startswith("LETTA_API_KEY=") or line.startswith("LETTA_SERVER_PASSWORD="):
                    os.environ["LETTA_API_KEY"] = line.split("=", 1)[1].strip().strip("\"'")
                    break
            if os.getenv("LETTA_API_KEY"):
                break
    if not os.getenv("LETTA_API_KEY"):
        print("LETTA_API_KEY required", file=sys.stderr)
        sys.exit(2)

    global AGENT_ID
    if not AGENT_ID:
        p = Path("/home/rizzn/sanctum/agents/len/agent_id.txt")
        AGENT_ID = p.read_text(encoding="utf-8").strip() if p.is_file() else ""
    if not AGENT_ID:
        print("LEN_AGENT_ID required", file=sys.stderr)
        sys.exit(2)

    existing = req("GET", "/v1/mcp-servers/")
    servers = existing if isinstance(existing, list) else existing.get("mcp_servers", [])
    server_id = None
    for s in servers:
        if s.get("server_name") == SERVER_NAME:
            server_id = s.get("id")
            break

    if not server_id:
        created = req(
            "POST",
            "/v1/mcp-servers/",
            {
                "server_name": SERVER_NAME,
                "config": {
                    "mcp_server_type": "stdio",
                    "command": RUN_SCRIPT,
                    "args": [],
                },
            },
        )
        server_id = created.get("id") or created.get("mcp_server", {}).get("id")
        print("created mcp server", server_id)
    else:
        print("reuse mcp server", server_id)

    if not server_id:
        print("no server_id", file=sys.stderr)
        sys.exit(1)

    for method, path, label in (
        ("POST", f"/v1/mcp-servers/connect/{server_id}", "connect"),
        ("POST", f"/v1/mcp-servers/{server_id}/refresh", "refresh"),
    ):
        try:
            req(method, path)
            print(label, "ok")
        except urllib.error.HTTPError as e:
            print(label, "skipped", e.code)

    tools = req("GET", f"/v1/mcp-servers/{server_id}/tools")
    tool_list_data = tool_list(tools)
    print("mcp tools", len(tool_list_data), "profile", profile)

    attached = 0
    for t in tool_list_data:
        name = t.get("name") or ""
        if not profile_allows(name, profile):
            continue
        tid = t.get("id")
        if not tid:
            continue
        try:
            req("PATCH", f"/v1/agents/{AGENT_ID}/tools/attach/{tid}")
            attached += 1
            print("attached", name)
        except urllib.error.HTTPError as e:
            if e.code in (409, 200):
                attached += 1
            else:
                print("attach fail", name, e.code, file=sys.stderr)

    final = tool_list(req("GET", f"/v1/agents/{AGENT_ID}/tools"))
    final_len = sorted(
        n for n in ((t.get("name") or "") for t in final) if n.startswith(TOOL_PREFIX)
    )
    print("attached_profile", attached)
    print("agent_len_tool_count", len(final_len))
    print("agent_len_tools", final_len)
    if profile == "chatter" and len(final_len) < len(CHATTER_TOOL_NAMES):
        missing = sorted(CHATTER_TOOL_NAMES - set(final_len))
        print("missing", missing, file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()
