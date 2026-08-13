# API v1 handler modules

Resource handlers live under `public/api/v1/handlers/` and are required by `index.php`.

| File | Function |
|------|----------|
| `handlers/contacts.php` | `handleContacts()` |
| `handlers/deals.php` | `handleDeals()` |
| `handlers/users.php` | `handleUsers()` |
| `handlers/webhooks.php` | `handleWebhooks()` |
| `handlers/reports.php` | `handleReports()` |

Router (`index.php`) keeps path parsing, auth, rate limit, contacts export shortcut, and remaining resources (commands/enrichment/import/…). Further splits continue this pattern.
