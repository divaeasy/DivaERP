# Code Operation Migration Guide

## Objective
This guide explains how code operation migration works in DivaERP, what is updated, and how to test it safely.

## Scope in the project
- UI page: `templates/admin/code_operation_migration.html.twig`
- Controller: `src/Controller/CodeOperationMigrationController.php`
- Main service: `src/Service/CodeOperationMigrationService.php`
- CLI command: `src/Command/BackfillCodeOperationCommand.php`
- Domain helper: `src/Service/CodeOperationService.php`

## What the migration does
The migration process performs 3 things inside one DB transaction.

1. Ensure the 4 standard `code_operation` records exist.
2. Assign missing `entetepiece.code_operation_id` based on `entetepiece.typet`.
3. Fill missing `lignepiece.sens` from the related piece's code operation sens.

If one SQL step fails, the full transaction is rolled back.

## Standard operation codes
`CodeOperationMigrationService::DEFAULT_CODE_OPERATIONS` defines these records:

1. `Vente Standard` (`Sortie`)
2. `Achat Standard` (`Entree`)
3. `Transfert Interne Sortie` (`Sortie`)
4. `Transfert Interne Entree` (`Entree`)

The service inserts each one only if it does not already exist (`WHERE NOT EXISTS`).

## Assignment rules used by migration
Migration assigns only when `entetepiece.code_operation_id IS NULL`.

1. Client-like pieces -> `Vente Standard`
- `LOWER(typet)` in: `client`, `prospect`, `vat`

2. Supplier pieces -> `Achat Standard`
- `LOWER(typet)` equals: `fournisseur`

3. Internal pieces -> `Transfert Interne Sortie`
- `LOWER(typet)` in: `interne`, `tiersinterne`, `tiers interne`

Then line sens backfill runs:
- `lignepiece.sens` is set from `code_operation.sens` through joins on `entetepiece` and `code_operation`.

## Auto vs manual mode
- Auto mode: runs without SQL `LIMIT`, processes all eligible rows.
- Manual mode: same logic but limited batch size (`limit` clamped between 1 and 2000).

UI route:
- GET `/admin/code-operation-migration`
- POST `/admin/code-operation-migration/run`

CLI route:
- `php bin/console app:backfill-code-operation --mode=auto`
- `php bin/console app:backfill-code-operation --mode=manual --limit=200`

## Status metrics shown in the page
`getStatus()` computes:

1. Total pieces and pieces missing code operation.
2. Total lines and lines missing sens.
3. Existing standard operations and missing labels.
4. Completion percentages.

`isMigrationComplete` is `true` only when:
- no piece missing code operation,
- no line missing sens,
- no standard label missing.

## Recommended test workflow
Use this sequence on staging before production.

### 1) Pre-check database state
Run SQL checks:

```sql
SELECT COUNT(*) AS pieces_total FROM entetepiece;
SELECT COUNT(*) AS pieces_without_code_operation FROM entetepiece WHERE code_operation_id IS NULL;
SELECT COUNT(*) AS lines_total FROM lignepiece;
SELECT COUNT(*) AS lines_without_sens FROM lignepiece WHERE sens IS NULL;
SELECT id, libelle, sens, is_active FROM code_operation
WHERE libelle IN ('Vente Standard', 'Achat Standard', 'Transfert Interne Sortie', 'Transfert Interne Entree')
ORDER BY id;
```

### 2) Run manual batch first
Start with a safe batch:

```bash
php bin/console app:backfill-code-operation --mode=manual --limit=200
```

Why manual first:
- safer on large datasets,
- easier to observe lock/performance impact,
- easier to validate counters decrease correctly.

### 3) Validate counters after each run
Re-run the SQL pre-check queries and confirm:

1. `pieces_without_code_operation` decreases.
2. `lines_without_sens` decreases.
3. Standard operation rows exist and are active.

### 4) Complete migration
When behavior is correct, finish with either:

1. repeated manual batches until 0/0 remaining, or
2. one final auto run to finish all remaining rows.

### 5) Functional regression checks
Validate business flows after migration:

1. Create/edit a client piece and confirm code operation is consistent.
2. Create/edit a supplier piece and confirm code operation is consistent.
3. Open piece lines and confirm `sens` is no longer null on migrated records.
4. Confirm the migration page shows expected KPI values and complete/partial badge.

### 6) Performance and safety checks
On large DBs, monitor:

1. long-running transactions,
2. lock waits,
3. DB CPU usage during auto runs.

If needed, keep using manual batches only.

## Common troubleshooting
1. "Migration partielle" stays visible:
- check for missing standard labels,
- check rows with uncommon `typet` values not covered by current rules.

2. No pieces updated for a category:
- verify `typet` values are exactly matched after lowercase normalization.

3. Lines still missing sens:
- verify pieces now have non-null `code_operation_id`,
- verify linked `code_operation.sens` values are set.

## Notes for maintainers
- To support new `typet` values, update the SQL where clauses in `CodeOperationMigrationService::runMigrationBatch()`.
- Keep UI descriptions aligned with service logic whenever rules change.
- Run at least one manual test batch after changing migration SQL.
