#!/usr/bin/env python3
"""Resolve per-chatter CRM API key via Len bridge (poll auth only)."""

from __future__ import annotations

import json
import os
import urllib.error
import urllib.request
from typing import Optional


def chatter_user_id_from_context() -> Optional[int]:
    """Trusted chatter id: env override, else file written by Broca len_crm webchat plugin."""
    raw = os.getenv("CRM_LEN_CHATTER_USER_ID", "").strip()
    if not raw:
        path = os.getenv(
            "CRM_LEN_CHATTER_FILE",
            "/opt/broca-len/run/current_crm_user_id.txt",
        )
        if os.path.isfile(path):
            raw = open(path, encoding="utf-8").read().strip()
    if not raw:
        return None
    try:
        uid = int(raw)
        return uid if uid > 0 else None
    except ValueError:
        return None


def resolve_crm_api_key(crm_user_id: int) -> str:
    base = os.getenv("CRM_LEN_BRIDGE_API_URL", "").rstrip("/")
    poll = os.getenv("CRM_LEN_BRIDGE_POLL_API_KEY", "").strip()
    if not base or not poll:
        raise RuntimeError(
            "CRM_LEN_BRIDGE_API_URL and CRM_LEN_BRIDGE_POLL_API_KEY must be set for len_crm SMCP"
        )
    url = base + "/api/v1/index.php?action=resolve_user_key"
    body = json.dumps({"crm_user_id": crm_user_id}).encode()
    req = urllib.request.Request(
        url,
        data=body,
        method="POST",
        headers={
            "Authorization": f"Bearer {poll}",
            "Content-Type": "application/json",
        },
    )
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            payload = json.loads(resp.read().decode())
    except urllib.error.HTTPError as e:
        detail = e.read().decode()[:500]
        raise RuntimeError(f"resolve_user_key HTTP {e.code}: {detail}") from e
    if not payload.get("success"):
        raise RuntimeError(payload.get("message") or "resolve_user_key failed")
    data = payload.get("data") or {}
    key = data.get("api_key")
    if not key:
        raise RuntimeError("resolve_user_key returned no api_key")
    return str(key)
