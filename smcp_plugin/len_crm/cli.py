#!/usr/bin/env python3
"""
Len Vernal CRM SMCP — wraps Sanctum CRM /api/v1/ with per-chatter API keys.

Never accepts --api-key from the model. Resolves the hidden key server-side via
the Len bridge (poll Bearer + crm_user_id from Broca plugin context file).
"""

from __future__ import annotations

import argparse
import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request
from typing import Any, Callable, Dict, List, Optional

_PLUGIN_ROOT = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, _PLUGIN_ROOT)

from resolve_key import chatter_user_id_from_context, resolve_crm_api_key  # noqa: E402

PLUGIN = {
    "name": "len_crm",
    "version": "0.1.1",
    "description": (
        "Sanctum CRM API tools for Len Vernal — relationship memory agent. "
        "Uses the logged-in chatter's hidden API key (resolved server-side)."
    ),
}

COMMAND_SPECS: List[Dict[str, Any]] = [
    {"name": "health", "description": "Check CRM API reachability for this chatter.", "parameters": []},
    {"name": "me", "description": "Who you are acting as — CRM username/id.", "parameters": []},
    {
        "name": "list-contacts",
        "description": "List/filter contacts. Use q= for name search.",
        "parameters": [
            {"name": "limit", "type": "integer", "required": False, "default": 25, "description": "Page size"},
            {"name": "offset", "type": "integer", "required": False, "default": 0, "description": "Offset"},
            {"name": "q", "type": "string", "required": False, "default": None, "description": "Name/email search"},
            {"name": "tag", "type": "string", "required": False, "default": None, "description": "Tag filter"},
            {"name": "contact-type", "type": "string", "required": False, "default": None, "description": "lead|customer"},
            {"name": "contact-status", "type": "string", "required": False, "default": None, "description": "Status filter"},
        ],
    },
    {
        "name": "get-contact",
        "description": "Fetch one contact by id.",
        "parameters": [{"name": "id", "type": "integer", "required": True, "default": None, "description": "Contact id"}],
    },
    {
        "name": "create-contact",
        "description": "Create one contact from JSON body.",
        "parameters": [{"name": "json", "type": "string", "required": True, "default": None, "description": "JSON body"}],
    },
    {
        "name": "update-contact",
        "description": "Patch one contact by id.",
        "parameters": [
            {"name": "id", "type": "integer", "required": True, "default": None, "description": "Contact id"},
            {"name": "json", "type": "string", "required": True, "default": None, "description": "JSON body"},
        ],
    },
    {
        "name": "list-deals",
        "description": "List deals, optional stage filter.",
        "parameters": [
            {"name": "limit", "type": "integer", "required": False, "default": 25, "description": "Page size"},
            {"name": "offset", "type": "integer", "required": False, "default": 0, "description": "Offset"},
            {"name": "stage", "type": "string", "required": False, "default": None, "description": "Deal stage"},
        ],
    },
    {
        "name": "get-deal",
        "description": "Fetch one deal by id.",
        "parameters": [{"name": "id", "type": "integer", "required": True, "default": None, "description": "Deal id"}],
    },
    {
        "name": "create-deal",
        "description": "Create one deal from JSON body.",
        "parameters": [{"name": "json", "type": "string", "required": True, "default": None, "description": "JSON body"}],
    },
    {
        "name": "update-deal",
        "description": "Patch one deal by id.",
        "parameters": [
            {"name": "id", "type": "integer", "required": True, "default": None, "description": "Deal id"},
            {"name": "json", "type": "string", "required": True, "default": None, "description": "JSON body"},
        ],
    },
    {"name": "list-tags", "description": "List tag catalog.", "parameters": []},
    {
        "name": "list-contact-tags",
        "description": "List tags on one contact.",
        "parameters": [{"name": "id", "type": "integer", "required": True, "default": None, "description": "Contact id"}],
    },
    {
        "name": "attach-contact-tag",
        "description": "Attach a tag name to one contact.",
        "parameters": [
            {"name": "id", "type": "integer", "required": True, "default": None, "description": "Contact id"},
            {"name": "tag", "type": "string", "required": True, "default": None, "description": "Tag name"},
        ],
    },
    {
        "name": "tool-help",
        "description": "Intent → tool routing cheat sheet.",
        "parameters": [],
    },
]

COMMANDS = {c["name"] for c in COMMAND_SPECS}


def _base_url() -> str:
    return os.getenv("CRM_API_BASE_URL", os.getenv("CRM_LEN_CRM_API_BASE", "")).rstrip("/")


def _request(
    method: str,
    path: str,
    api_key: str,
    body: Optional[dict] = None,
    query: Optional[dict] = None,
) -> dict:
    base = _base_url()
    if not base:
        raise RuntimeError("CRM_API_BASE_URL (or CRM_LEN_CRM_API_BASE) must be set")
    url = base + path
    if query:
        qs = urllib.parse.urlencode({k: v for k, v in query.items() if v is not None})
        url += ("&" if "?" in url else "?") + qs
    data = None
    headers = {"Authorization": f"Bearer {api_key}", "Accept": "application/json"}
    if body is not None:
        data = json.dumps(body).encode()
        headers["Content-Type"] = "application/json"
    req = urllib.request.Request(url, data=data, method=method, headers=headers)
    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            raw = resp.read().decode()
            if not raw:
                return {"status": resp.status}
            return json.loads(raw)
    except urllib.error.HTTPError as e:
        detail = e.read().decode()[:2000]
        try:
            parsed = json.loads(detail)
        except json.JSONDecodeError:
            parsed = {"error": detail}
        parsed["_http_status"] = e.code
        return parsed


def _handlers(api_key: str) -> Dict[str, Callable[[argparse.Namespace], dict]]:
    def health(_: argparse.Namespace) -> dict:
        return _request("GET", "/api/v1/contacts?limit=1", api_key)

    def me(_: argparse.Namespace) -> dict:
        return _request("GET", "/api/v1/users/me", api_key)

    def list_contacts(args: argparse.Namespace) -> dict:
        q = {"limit": args.limit, "offset": args.offset}
        if args.q:
            q["q"] = args.q
        if args.tag:
            q["tag"] = args.tag
        if args.contact_type:
            q["contact_type"] = args.contact_type
        if args.contact_status:
            q["contact_status"] = args.contact_status
        return _request("GET", "/api/v1/contacts", api_key, query=q)

    def get_contact(args: argparse.Namespace) -> dict:
        return _request("GET", f"/api/v1/contacts/{args.id}", api_key)

    def create_contact(args: argparse.Namespace) -> dict:
        body = json.loads(args.json) if args.json else {}
        return _request("POST", "/api/v1/contacts", api_key, body=body)

    def update_contact(args: argparse.Namespace) -> dict:
        body = json.loads(args.json) if args.json else {}
        return _request("PUT", f"/api/v1/contacts/{args.id}", api_key, body=body)

    def list_deals(args: argparse.Namespace) -> dict:
        q = {"limit": args.limit, "offset": args.offset}
        if args.stage:
            q["stage"] = args.stage
        return _request("GET", "/api/v1/deals", api_key, query=q)

    def get_deal(args: argparse.Namespace) -> dict:
        return _request("GET", f"/api/v1/deals/{args.id}", api_key)

    def create_deal(args: argparse.Namespace) -> dict:
        body = json.loads(args.json) if args.json else {}
        return _request("POST", "/api/v1/deals", api_key, body=body)

    def update_deal(args: argparse.Namespace) -> dict:
        body = json.loads(args.json) if args.json else {}
        return _request("PUT", f"/api/v1/deals/{args.id}", api_key, body=body)

    def list_tags(_: argparse.Namespace) -> dict:
        return _request("GET", "/api/v1/contacts/tags", api_key)

    def list_contact_tags(args: argparse.Namespace) -> dict:
        return _request("GET", f"/api/v1/contacts/{args.id}/tags", api_key)

    def attach_contact_tag(args: argparse.Namespace) -> dict:
        return _request(
            "POST",
            f"/api/v1/contacts/{args.id}/tags",
            api_key,
            body={"tag": args.tag},
        )

    def tool_help(_: argparse.Namespace) -> dict:
        return {
            "status": "ok",
            "commands": sorted(COMMANDS),
            "notes": (
                "Use list-contacts with q= for name search. "
                "get-contact for full dossier. Never pass api-key."
            ),
        }

    return {
        "health": health,
        "me": me,
        "list-contacts": list_contacts,
        "get-contact": get_contact,
        "create-contact": create_contact,
        "update-contact": update_contact,
        "list-deals": list_deals,
        "get-deal": get_deal,
        "create-deal": create_deal,
        "update-deal": update_deal,
        "list-tags": list_tags,
        "list-contact-tags": list_contact_tags,
        "attach-contact-tag": attach_contact_tag,
        "tool-help": tool_help,
    }


def build_parser() -> argparse.ArgumentParser:
    p = argparse.ArgumentParser(description=PLUGIN["description"])
    p.add_argument("--describe", action="store_true", help="JSON tool manifest")
    p.add_argument("--debug", action="store_true")
    sub = p.add_subparsers(dest="command")

    for name in sorted(COMMANDS):
        sp = sub.add_parser(name, help=name)
        if name == "list-contacts":
            sp.add_argument("--limit", type=int, default=25)
            sp.add_argument("--offset", type=int, default=0)
            sp.add_argument("--q")
            sp.add_argument("--tag")
            sp.add_argument("--contact-type")
            sp.add_argument("--contact-status")
        elif name in {"get-contact", "get-deal", "list-contact-tags"}:
            sp.add_argument("--id", type=int, required=True)
        elif name in {"update-contact", "update-deal"}:
            sp.add_argument("--id", type=int, required=True)
            sp.add_argument("--json", required=True, help="JSON body")
        elif name in {"create-contact", "create-deal"}:
            sp.add_argument("--json", required=True, help="JSON body")
        elif name == "list-deals":
            sp.add_argument("--limit", type=int, default=25)
            sp.add_argument("--offset", type=int, default=0)
            sp.add_argument("--stage")
        elif name == "attach-contact-tag":
            sp.add_argument("--id", type=int, required=True)
            sp.add_argument("--tag", required=True)
    return p


def main() -> None:
    parser = build_parser()
    args = parser.parse_args()
    if args.describe:
        print(
            json.dumps(
                {
                    "contract_version": "1.0",
                    "plugin": PLUGIN,
                    "commands": COMMAND_SPECS,
                },
                indent=2,
            )
        )
        sys.exit(0)
    if not args.command:
        parser.print_help()
        sys.exit(1)

    uid = chatter_user_id_from_context()
    if not uid:
        print(json.dumps({"status": "error", "error": "No active CRM chatter context", "error_type": "auth"}))
        sys.exit(1)
    try:
        api_key = resolve_crm_api_key(uid)
    except Exception as e:
        print(json.dumps({"status": "error", "error": str(e), "error_type": "auth"}))
        sys.exit(1)

    handler = _handlers(api_key).get(args.command)
    if not handler:
        print(json.dumps({"status": "error", "error": "Unknown command"}))
        sys.exit(1)
    result = handler(args)
    print(json.dumps(result, indent=2))


if __name__ == "__main__":
    main()
