# CRM schema migrations (PHP)

Each `*.php` file must `return` an array:

```php
return [
    'version' => 'YYYYMMDD_NNN_slug',
    'description' => 'Short human summary',
    'up' => function (Database $db): void { /* idempotent */ },
];
```

Apply with `php tools/migrate.php` from the repo root. See `docs/MIGRATIONS.md`.
