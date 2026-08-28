# Len integration epic — Sanctum CRM

Track build slices for **Ask Len** (in-app agent bubble + Broca + SMCP). Pattern cribbed from Ask Q (Tasks) and Ask Wren (Docket).

**Letta agent:** `Len_Vernal` — `agent-1265f1ed-ddf5-4da8-b768-d8209c01ac51` (moya)  
**Dev target:** `https://dev.crm.soletigre.com`  
**Branch:** `dev` on `sanctumos/sanctum-crm`  
**Coverage gate:** ≥90% includes methods + common route checklist per slice (`php tools/check_coverage.php`)

---

## Slice 0 — Epic + harness

- [x] This doc (`docs/LEN-INTEGRATION-EPIC.md`)
- [x] `docs/LEN-JOB-RULES.md` — tool contract for Len persona
- [x] Port `tools/check_coverage.php` (90% gate)

## Slice 1 — len-bridge foundation

- [x] Port Docket `wren-bridge` → `public/len-bridge/` (rename wren→len, docket→crm)
- [x] `crm_session.php` — session auth + auto-mint `users.api_key`
- [x] Poll key: `CRM_LEN_BRIDGE_POLL_API_KEY` or `db/len_bridge_poll_api_key.txt`
- [x] Separate SQLite: `len_bridge_webchat.db`
- [x] Unit tests: session key mint, page context, connection config defaults

## Slice 2 — Ask Len UI + settings

- [x] `public/includes/_ask_len.php` — bubble embed
- [x] Include in `layout.php` footer (logged-in only)
- [x] `connection_config.php` — `system_config` category `len_bridge`
- [x] Settings → Ask Len admin section
- [x] Page context for CRM surfaces (contacts, deals, merges, import, webhooks, reports)

## Slice 3 — SMCP `len_crm`

- [x] `smcp_plugin/len_crm/` — CLI wrapping `/api/v1/`
- [x] `resolve_key.py` — poll `resolve_user_key` (no `--api-key` from model)
- [x] Commands: health, me, list/get/create/update contacts & deals, list-tags
- [x] Unit/smoke tests for resolve_key + CLI describe

## Slice 4 — Bridge API tests + E2E

- [x] `tests/api/LenBridgeApiTest.php` — inbox/outbox auth, resolve_user_key
- [x] `tests/e2e/LenBridgeE2ETest.php` — widget routes with session
- [x] Route checklist includes len-bridge paths in `check_coverage.php`

## Slice 5 — Moya Broca-len ops

- [x] `tools/provision_len_broca_moya.py` — broca-len on moya
- [x] `tools/moya-install-len-smcp.sh` — install len_crm SMCP beside Broca
- [x] Point Broca poll at dev CRM first; prod promotion separate

## Slice 6 — Promote

- [ ] Green coverage + full test suite on `dev`
- [ ] Mark review → merge `dev` → `main`
- [ ] Ada sync Sole Tigre dev + DSC overlay when ordered

---

## References

| Artifact | Path |
|----------|------|
| Modality pattern | `sanctum-tasks/docs/MODALITY-EMBEDDED-AGENT-CHAT-IN-SANCTUM-APP.md` |
| Wren bridge (fork source) | `docket.socialqualifier.com/public/wren-bridge/` |
| Q bridge | `sanctum-tasks/public/q-bridge/` |
| Len birth notes | `sanctum/otto-athena-summaries/2026-07-30-crm-agent-birth-len.md` |
