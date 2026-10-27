-- SQL Script to generate test data for Code Operation Migration testing
-- Run this AFTER running the migration to allow code_operation_id to be NULL
-- Generated: 2026-06-01

-- 1. Create test pieces WITHOUT code_operation for migration testing
-- This tests the migration logic's ability to assign operations

INSERT INTO entetepiece (
    type, typet, tier_id, code_operation_id, dossier_id,
    pieceno, statut, montant, datep,
    created_by, created_at, updated_by, updated_at
) VALUES
-- Client pieces (should get "Vente Standard")
('Facture', 'Client', 1, NULL, 1, 9001, 'Brouillon', 1000.00, NOW(), 1, NOW(), 1, NOW()),
('Facture', 'Client', 2, NULL, 1, 9002, 'Brouillon', 2000.00, NOW(), 1, NOW(), 1, NOW()),
('Commande', 'Client', 3, NULL, 1, 9003, 'Brouillon', 1500.00, NOW(), 1, NOW(), 1, NOW()),

-- Prospect pieces (should get "Vente Standard")
('Devis', 'Prospect', 4, NULL, 1, 9004, 'Brouillon', 3000.00, NOW(), 1, NOW(), 1, NOW()),
('Facture', 'Prospect', 5, NULL, 1, 9005, 'Brouillon', 500.00, NOW(), 1, NOW(), 1, NOW()),

-- Fournisseur pieces (should get "Achat Standard")
('Facture', 'Fournisseur', 6, NULL, 1, 9006, 'Brouillon', 5000.00, NOW(), 1, NOW(), 1, NOW()),
('Commande', 'Fournisseur', 7, NULL, 1, 9007, 'Brouillon', 2500.00, NOW(), 1, NOW(), 1, NOW()),
('BL', 'Fournisseur', 8, NULL, 1, 9008, 'Brouillon', 1800.00, NOW(), 1, NOW(), 1, NOW()),

-- Internal pieces (should get "Transfert Interne Sortie")
('BL', 'Interne', NULL, NULL, 1, 9009, 'Brouillon', 2000.00, NOW(), 1, NOW(), 1, NOW()),
('Facture', 'Interne', NULL, NULL, 1, 9010, 'Brouillon', 1500.00, NOW(), 1, NOW(), 1, NOW());

-- 2. Get the IDs of newly created test pieces
SET @test_piece_client_1 = LAST_INSERT_ID() - 9;
SET @test_piece_client_2 = LAST_INSERT_ID() - 8;
SET @test_piece_client_3 = LAST_INSERT_ID() - 7;
SET @test_piece_prospect_1 = LAST_INSERT_ID() - 6;
SET @test_piece_prospect_2 = LAST_INSERT_ID() - 5;
SET @test_piece_fournisseur_1 = LAST_INSERT_ID() - 4;
SET @test_piece_fournisseur_2 = LAST_INSERT_ID() - 3;
SET @test_piece_fournisseur_3 = LAST_INSERT_ID() - 2;
SET @test_piece_interne_1 = LAST_INSERT_ID() - 1;
SET @test_piece_interne_2 = LAST_INSERT_ID();

-- 3. Create test lines WITHOUT sens (for sens backfill testing)
-- These should be filled by the migration with the correct sens from their piece's code_operation

INSERT INTO lignepiece (
    piece_id, article_id, qte, pub, sens, montant, dossier_id,
    created_by, created_at, updated_by, updated_at
) VALUES
-- Lines for client piece (should get sens='Sortie' after migration assigns code_operation)
(@test_piece_client_1, 1, 1, 100.00, NULL, 100.00, 1, 1, NOW(), 1, NOW()),
(@test_piece_client_1, 2, 5, 180.00, NULL, 900.00, 1, 1, NOW(), 1, NOW()),

-- Lines for prospect piece (should get sens='Sortie')
(@test_piece_prospect_1, 1, 2, 1500.00, NULL, 3000.00, 1, 1, NOW(), 1, NOW()),

-- Lines for fournisseur piece (should get sens='Entree')
(@test_piece_fournisseur_1, 3, 10, 500.00, NULL, 5000.00, 1, 1, NOW(), 1, NOW()),

-- Lines for internal piece (should get sens='Sortie')
(@test_piece_interne_1, 4, 5, 400.00, NULL, 2000.00, 1, 1, NOW(), 1, NOW());

-- 4. Verify test data creation
SELECT 
    COUNT(*) as total_test_pieces,
    SUM(CASE WHEN code_operation_id IS NULL THEN 1 ELSE 0 END) as pieces_without_code_op,
    SUM(CASE WHEN pieceno >= 9001 AND pieceno <= 9010 THEN 1 ELSE 0 END) as expected_test_pieces
FROM entetepiece
WHERE pieceno >= 9001 AND pieceno <= 9010;

SELECT 
    COUNT(*) as total_test_lines,
    SUM(CASE WHEN sens IS NULL THEN 1 ELSE 0 END) as lines_without_sens
FROM lignepiece lp
WHERE lp.piece_id IN (
    SELECT id FROM entetepiece WHERE pieceno >= 9001 AND pieceno <= 9010
);

-- 5. Usage instructions:
-- After running this script:
-- 1. Visit: /admin/code-operation-migration
-- 2. You should see pending items (pieces and lines)
-- 3. Click "Mode automatique" or use manual batches to test the migration
-- 4. After migration, verify:
--    a) All 10 test pieces now have code_operation_id assigned
--    b) All test lines now have sens set from their piece's code_operation
--    c) Client/Prospect pieces have "Vente Standard"
--    d) Fournisseur pieces have "Achat Standard"
--    e) Interne pieces have "Transfert Interne Sortie"

-- Cleanup (run this to remove test data if needed):
-- DELETE FROM lignepiece WHERE piece_id IN (
--     SELECT id FROM entetepiece WHERE pieceno >= 9001 AND pieceno <= 9010
-- );
-- DELETE FROM entetepiece WHERE pieceno >= 9001 AND pieceno <= 9010;
