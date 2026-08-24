# Lead enrichment (RocketReach & Apollo)

Sanctum CRM can enrich contacts from **one active external provider** at a time:

| Provider | Settings value | API key column | Person ID column |
|----------|----------------|----------------|------------------|
| RocketReach (default) | `rocketreach` | `rocketreach_api_key` | `rocketreach_profile_id` |
| Apollo | `apollo` | `apollo_api_key` | `apollo_person_id` |

Both keys may be stored. Cron, UI Enrich actions, and the REST API all use `settings.enrichment_provider`.

## Setup

1. Admin → **Settings** → Lead Enrichment.
2. Select **RocketReach** or **Apollo**.
3. Paste the corresponding API key; optionally keep the other key for later.
4. Apollo: use **Test Apollo** after save. People enrich needs `people/match` (or master) scope — org-only trial keys fail people lookups.
5. Optionally enable enrichment cron and set max-per-run / max-per-day.

## Match strategies

Passed as `strategy` on enrich API / used by auto resolution:

| Strategy | RocketReach | Apollo |
|----------|-------------|--------|
| `auto` | Best available among email → LinkedIn → name+company → Twitter | Best among email → LinkedIn → name+company |
| `email` | Yes | Yes |
| `linkedin` | Yes | Yes |
| `name_company` | Yes | Yes |
| `twitter` | Yes | No (treated as unsupported) |

## Code map

| Piece | Path |
|-------|------|
| Orchestrator | `public/includes/LeadEnrichmentService.php` |
| Provider constants | `public/includes/enrichment/EnrichmentProviders.php` |
| Apollo HTTP client | `public/includes/enrichment/ApolloEnrichmentClient.php` |
| RocketReach SDK | `public/helpers/rocketreach/` |
| Cron | `public/cron/enrichment.php` + `EnrichmentCronService.php` |
| Settings UI | `public/pages/settings.php` |
| In-app help | `public/pages/help/enrichment.php` |

## API (logical paths)

Use `/api/v1/index.php?path=…` on stock nginx (same as other CRM routes).

| Method | Path | Notes |
|--------|------|--------|
| `POST` | `/contacts/{id}/enrich` | Body optional: `{"strategy":"auto"}` |
| `GET` | `/contacts/{id}/enrichment-status` | Per-contact status |
| `POST` | `/contacts/bulk-enrich` | `{"contact_ids":[…],"strategy":"auto"}` |
| `GET` | `/enrichment/stats` | Aggregates |
| `GET` / `PUT` | `/enrichment/cron` | Config; writes require admin |

OpenAPI: `public/api/openapi.json` (enrichment paths + contact enrichment fields).

## Outcomes

Typical `outcome` / `enrichment_status` values: `enriched`, `not_found`, `failed`, `skipped`, `processing`. Successful rows store provider source and raw JSON for debugging on the contact detail page.

## Secrets

Never commit API keys. Inject via Settings UI or DB (`settings` row). Staging copies may live in Otto `~/.ssh/*.pass` files only — not in git or Tasks bodies.
