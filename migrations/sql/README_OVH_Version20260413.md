# OVH/phpMyAdmin - Fournisseur + Tier migration

Exécuter les scripts SQL **dans cet ordre**:

1. `migrations/sql/Version20260413120000.sql`
2. `migrations/sql/Version20260413121500.sql`

## Vérifications rapides

```sql
SHOW COLUMNS FROM fournisseur;
SHOW INDEX FROM fournisseur;

SHOW COLUMNS FROM entetepiece LIKE 'tier_id';
SHOW COLUMNS FROM entetepiece LIKE 'client_id';
```

Attendu:
- table `fournisseur` créée
- colonne `tier_id` présente dans `entetepiece`
- colonne `client_id` absente dans `entetepiece`

## (Optionnel) marquer les migrations comme exécutées

Si vous appliquez les scripts manuellement, vous pouvez aligner la table Doctrine:

```sql
INSERT INTO doctrine_migration_versions (version, executed_at, execution_time)
VALUES ('DoctrineMigrations\\Version20260413120000', NOW(), 1)
ON DUPLICATE KEY UPDATE executed_at = VALUES(executed_at);

INSERT INTO doctrine_migration_versions (version, executed_at, execution_time)
VALUES ('DoctrineMigrations\\Version20260413121500', NOW(), 1)
ON DUPLICATE KEY UPDATE executed_at = VALUES(executed_at);
```
