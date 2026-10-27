# OVH migration helper (Version20260327084317)

Use this if you cannot run Symfony CLI on OVH and must update DB from phpMyAdmin.

## File to run

`migrations/sql/Version20260327084317_ovh.sql`

## Steps (phpMyAdmin)

1. Backup your production database.
2. Open phpMyAdmin for your OVH database.
3. Select the application database.
4. Open the **SQL** tab.
5. Paste the content of `Version20260327084317_ovh.sql` and run it.
6. Confirm the final row says: `Version20260327084317 applied (OVH/phpMyAdmin script)`.

## Verification queries

```sql
SHOW COLUMNS FROM dossier LIKE 'codepostal';
SHOW COLUMNS FROM dossier LIKE 'devisno';
SHOW COLUMNS FROM clients LIKE 'linkedin';
```

## Alternative (if SSH/CLI is available on OVH)

```bash
php bin/console doctrine:migrations:execute --up DoctrineMigrations\\Version20260327084317 --no-interaction --env=prod
```

Then clear cache:

```bash
php bin/console cache:clear --env=prod
```
