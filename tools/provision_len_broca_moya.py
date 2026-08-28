#!/usr/bin/env python3
"""
Provision broca-len on moya — poll dev CRM len-bridge first.

Usage (from Otto workspace):
  python3 tools/provision_len_broca_moya.py --crm-base https://dev.crm.soletigre.com

Requires SSH moya + Letta agent Len_Vernal already on 223.
"""
from __future__ import annotations

import argparse
import textwrap

DEFAULT_AGENT_ID = "agent-1265f1ed-ddf5-4da8-b768-d8209c01ac51"
DEFAULT_CRM = "https://dev.crm.soletigre.com"


def main() -> None:
    p = argparse.ArgumentParser(description="Print broca-len env checklist for moya")
    p.add_argument("--crm-base", default=DEFAULT_CRM, help="CRM origin for len-bridge poll")
    p.add_argument("--agent-id", default=DEFAULT_AGENT_ID, help="Letta Len agent id")
    args = p.parse_args()
    crm = args.crm_base.rstrip("/")
    bridge = f"{crm}/len-bridge/api/v1/"
    print(
        textwrap.dedent(
            f"""
            === broca-len provision checklist (moya) ===

            1. Clone/sync sanctum-crm dev on multihost target ({crm})
            2. Settings → Ask Len: enable, sanctum URL, agent id {args.agent_id}
            3. Place poll key on CRM host:
               db/len_bridge_poll_api_key.txt  (or CRM_LEN_BRIDGE_POLL_API_KEY env)
            4. On moya, broca-len env:
               CRM_LEN_BRIDGE_API_URL={bridge}
               CRM_LEN_BRIDGE_POLL_API_KEY=<same poll key>
               CRM_LEN_CHATTER_FILE=/opt/broca-len/run/current_crm_user_id.txt
               CRM_API_BASE_URL={crm}
            5. Install SMCP:
               bash tools/moya-install-len-smcp.sh
            6. Attach len_crm tools to Letta agent {args.agent_id}

            See docs/LEN-JOB-RULES.md and docs/LEN-INTEGRATION-EPIC.md
            """
        ).strip()
    )


if __name__ == "__main__":
    main()
