# Len Vernal webchat bridge (PHP)

Ask Q pattern ported into Docket. See `docs/wren/ASK-WREN-OPS.md`.

- **API:** `/len-bridge/api/v1/index.php?action=…` (`messages`, `inbox`, `outbox`, `responses`, `sessions`, `config`, `resolve_user_key`, …)
- **Widget:** `/len-bridge/widget/`
- **DB:** `len_bridge_webchat.db` beside `docket.db` (not in web root)
- **Poll auth:** `CRM_LEN_BRIDGE_POLL_API_KEY` or `db/len_bridge_poll_api_key.txt`
- **User messages:** require logged-in Docket session; `crm_user_id` in session metadata for SMCP injection
- **Page context:** page / contact id / list filters → Broca prepends `[Chat context — Sanctum CRM UI]`
- **Gate:** `CRM_LEN_BRIDGE_ENABLED` (default off)
