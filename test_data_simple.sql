-- Insert Dossiers (Categories)
INSERT IGNORE INTO dossier (id, nom, adresse, logo, rc, devise_id, factureno) VALUES
(1, 'Électronique', '123 Rue de l''Électronique', NULL, 'RC1001', NULL, 100),
(2, 'Mobilier', '456 Avenue du Mobilier', NULL, 'RC1002', NULL, 101),
(3, 'Services', '789 Boulevard des Services', NULL, 'RC1003', NULL, 102),
(4, 'Logiciels', '321 Chemin Logiciels', NULL, 'RC1004', NULL, 103),
(5, 'Consulting', '654 Place du Consulting', NULL, 'RC1005', NULL, 104);

-- Insert Clients
INSERT IGNORE INTO clients (id, nom, adresse, telephone, email, created_at, updated_at) VALUES
(1, 'Client A', '100 Avenue A', '0612345678', 'client.a@example.com', NOW(), NOW()),
(2, 'Client B', '200 Avenue B', '0623456789', 'client.b@example.com', NOW(), NOW()),
(3, 'Client C', '300 Avenue C', '0634567890', 'client.c@example.com', NOW(), NOW()),
(4, 'Client D', '400 Avenue D', '0645678901', 'client.d@example.com', NOW(), NOW()),
(5, 'Client E', '500 Avenue E', '0656789012', 'client.e@example.com', NOW(), NOW()),
(6, 'Client F', '600 Avenue F', '0667890123', 'client.f@example.com', NOW(), NOW()),
(7, 'Client G', '700 Avenue G', '0678901234', 'client.g@example.com', NOW(), NOW()),
(8, 'Client H', '800 Avenue H', '0689012345', 'client.h@example.com', NOW(), NOW()),
(9, 'Client I', '900 Avenue I', '0690123456', 'client.i@example.com', NOW(), NOW()),
(10, 'Client J', '1000 Avenue J', '0601234567', 'client.j@example.com', NOW(), NOW());

-- Insert Articles (Products)
INSERT IGNORE INTO article (id, libelle, unite_id, tarif_id, dossier_id, created_at, updated_at) VALUES
(1, 'Laptop Pro', NULL, NULL, 1, NOW(), NOW()),
(2, 'Desktop Computer', NULL, NULL, 1, NOW(), NOW()),
(3, 'Monitor 27"', NULL, NULL, 1, NOW(), NOW()),
(4, 'Keyboard Mechanical', NULL, NULL, 1, NOW(), NOW()),
(5, 'Mouse Wireless', NULL, NULL, 1, NOW(), NOW()),
(6, 'Office Chair', NULL, NULL, 2, NOW(), NOW()),
(7, 'Desk Wooden', NULL, NULL, 2, NOW(), NOW()),
(8, 'Lamp LED', NULL, NULL, 2, NOW(), NOW()),
(9, 'Consultation Hours', NULL, NULL, 3, NOW(), NOW()),
(10, 'Support Package', NULL, NULL, 4, NOW(), NOW());

-- Insert Reglement
INSERT IGNORE INTO reglement (id, libelle, echeance, created_at, updated_at) VALUES
(1, 'Virement', 30, NOW(), NOW());

-- Insert Invoices for 2025
INSERT IGNORE INTO entetepiece (id, type, typet, client_id, pieceno, pieceref, devise_id, remise, delai, reglement_id, statut, edition, rapport, montant, dossier_id, datep, created_at, updated_at) VALUES
(1, 'FACT', 'VAT', 1, 1001, 'INV-2025-1001', NULL, NULL, '2025-02-05', 1, 'Validée', NULL, NULL, 1200.50, 1, '2025-01-06', NOW(), NOW()),
(2, 'FACT', 'VAT', 2, 1002, 'INV-2025-1002', NULL, NULL, '2025-02-10', 1, 'Validée', NULL, NULL, 1850.75, 2, '2025-01-11', NOW(), NOW()),
(3, 'FACT', 'VAT', 3, 1003, 'INV-2025-1003', NULL, NULL, '2025-02-15', 1, 'Validée', NULL, NULL, 950.30, 3, '2025-01-16', NOW(), NOW()),
(4, 'FACT', 'VAT', 4, 1004, 'INV-2025-1004', NULL, NULL, '2025-03-05', 1, 'Validée', NULL, NULL, 2100.00, 1, '2025-02-03', NOW(), NOW()),
(5, 'FACT', 'VAT', 5, 1005, 'INV-2025-1005', NULL, NULL, '2025-03-12', 1, 'Validée', NULL, NULL, 1500.50, 2, '2025-02-10', NOW(), NOW()),
(6, 'FACT', 'VAT', 6, 1006, 'INV-2025-1006', NULL, NULL, '2025-03-20', 1, 'Validée', NULL, NULL, 1200.00, 3, '2025-02-18', NOW(), NOW()),
(7, 'FACT', 'VAT', 7, 1007, 'INV-2025-1007', NULL, NULL, '2025-04-05', 1, 'Validée', NULL, NULL, 3200.75, 1, '2025-03-06', NOW(), NOW()),
(8, 'FACT', 'VAT', 8, 1008, 'INV-2025-1008', NULL, NULL, '2025-04-12', 1, 'Validée', NULL, NULL, 2100.30, 2, '2025-03-13', NOW(), NOW()),
(9, 'FACT', 'VAT', 9, 1009, 'INV-2025-1009', NULL, NULL, '2025-04-20', 1, 'Validée', NULL, NULL, 1800.00, 3, '2025-03-21', NOW(), NOW()),
(10, 'FACT', 'VAT', 10, 1010, 'INV-2025-1010', NULL, NULL, '2025-05-10', 1, 'Validée', NULL, NULL, 2500.50, 4, '2025-04-10', NOW(), NOW()),
(11, 'FACT', 'VAT', 1, 1011, 'INV-2025-1011', NULL, NULL, '2025-05-18', 1, 'Validée', NULL, NULL, 1900.75, 1, '2025-04-18', NOW(), NOW()),
(12, 'FACT', 'VAT', 2, 1012, 'INV-2025-1012', NULL, NULL, '2025-05-25', 1, 'Validée', NULL, NULL, 2200.00, 2, '2025-04-25', NOW(), NOW()),
(13, 'FACT', 'VAT', 3, 1013, 'INV-2025-1013', NULL, NULL, '2025-06-08', 1, 'Validée', NULL, NULL, 1650.30, 3, '2025-05-09', NOW(), NOW()),
(14, 'FACT', 'VAT', 4, 1014, 'INV-2025-1014', NULL, NULL, '2025-06-15', 1, 'Validée', NULL, NULL, 2800.50, 1, '2025-05-16', NOW(), NOW()),
(15, 'FACT', 'VAT', 5, 1015, 'INV-2025-1015', NULL, NULL, '2025-06-22', 1, 'Validée', NULL, NULL, 2100.00, 2, '2025-05-23', NOW(), NOW()),
(16, 'FACT', 'VAT', 6, 1016, 'INV-2025-1016', NULL, NULL, '2025-07-05', 1, 'Validée', NULL, NULL, 1750.75, 3, '2025-06-05', NOW(), NOW()),
(17, 'FACT', 'VAT', 7, 1017, 'INV-2025-1017', NULL, NULL, '2025-07-12', 1, 'Validée', NULL, NULL, 2950.30, 4, '2025-06-12', NOW(), NOW()),
(18, 'FACT', 'VAT', 8, 1018, 'INV-2025-1018', NULL, NULL, '2025-07-20', 1, 'Validée', NULL, NULL, 1900.50, 1, '2025-06-20', NOW(), NOW()),
(19, 'FACT', 'VAT', 9, 1019, 'INV-2025-1019', NULL, NULL, '2025-08-08', 1, 'Validée', NULL, NULL, 2400.00, 2, '2025-07-09', NOW(), NOW()),
(20, 'FACT', 'VAT', 10, 1020, 'INV-2025-1020', NULL, NULL, '2025-08-15', 1, 'Validée', NULL, NULL, 2100.75, 3, '2025-07-16', NOW(), NOW()),
(21, 'FACT', 'VAT', 1, 1021, 'INV-2025-1021', NULL, NULL, '2025-08-22', 1, 'Validée', NULL, NULL, 1850.30, 1, '2025-07-23', NOW(), NOW()),
(22, 'FACT', 'VAT', 2, 1022, 'INV-2025-1022', NULL, NULL, '2025-09-05', 1, 'Validée', NULL, NULL, 2650.50, 2, '2025-08-06', NOW(), NOW()),
(23, 'FACT', 'VAT', 3, 1023, 'INV-2025-1023', NULL, NULL, '2025-09-12', 1, 'Validée', NULL, NULL, 2200.00, 3, '2025-08-13', NOW(), NOW()),
(24, 'FACT', 'VAT', 4, 1024, 'INV-2025-1024', NULL, NULL, '2025-09-20', 1, 'Validée', NULL, NULL, 1900.75, 4, '2025-08-21', NOW(), NOW()),
(25, 'FACT', 'VAT', 5, 1025, 'INV-2025-1025', NULL, NULL, '2025-10-08', 1, 'Validée', NULL, NULL, 2550.30, 1, '2025-09-08', NOW(), NOW()),
(26, 'FACT', 'VAT', 6, 1026, 'INV-2025-1026', NULL, NULL, '2025-10-15', 1, 'Validée', NULL, NULL, 2100.50, 2, '2025-09-15', NOW(), NOW()),
(27, 'FACT', 'VAT', 7, 1027, 'INV-2025-1027', NULL, NULL, '2025-10-22', 1, 'Validée', NULL, NULL, 1800.00, 3, '2025-09-22', NOW(), NOW()),
(28, 'FACT', 'VAT', 8, 1028, 'INV-2025-1028', NULL, NULL, '2025-11-05', 1, 'Validée', NULL, NULL, 2750.75, 1, '2025-10-06', NOW(), NOW()),
(29, 'FACT', 'VAT', 9, 1029, 'INV-2025-1029', NULL, NULL, '2025-11-12', 1, 'Validée', NULL, NULL, 2150.30, 2, '2025-10-13', NOW(), NOW()),
(30, 'FACT', 'VAT', 10, 1030, 'INV-2025-1030', NULL, NULL, '2025-11-20', 1, 'Validée', NULL, NULL, 1950.50, 3, '2025-10-21', NOW(), NOW()),
(31, 'FACT', 'VAT', 1, 1031, 'INV-2025-1031', NULL, NULL, '2025-12-08', 1, 'Validée', NULL, NULL, 2800.00, 4, '2025-11-08', NOW(), NOW()),
(32, 'FACT', 'VAT', 2, 1032, 'INV-2025-1032', NULL, NULL, '2025-12-15', 1, 'Validée', NULL, NULL, 2350.75, 1, '2025-11-15', NOW(), NOW());

-- Insert Invoices for 2026 (January and February)
INSERT IGNORE INTO entetepiece (id, type, typet, client_id, pieceno, pieceref, devise_id, remise, delai, reglement_id, statut, edition, rapport, montant, dossier_id, datep, created_at, updated_at) VALUES
(33, 'FACT', 'VAT', 3, 2001, 'INV-2026-2001', NULL, NULL, '2026-02-05', 1, 'Validée', NULL, NULL, 2100.30, 2, '2026-01-06', NOW(), NOW()),
(34, 'FACT', 'VAT', 4, 2002, 'INV-2026-2002', NULL, NULL, '2026-02-12', 1, 'Validée', NULL, NULL, 2650.50, 3, '2026-01-13', NOW(), NOW()),
(35, 'FACT', 'VAT', 5, 2003, 'INV-2026-2003', NULL, NULL, '2026-02-20', 1, 'Validée', NULL, NULL, 2200.00, 1, '2026-01-21', NOW(), NOW()),
(36, 'FACT', 'VAT', 6, 2004, 'INV-2026-2004', NULL, NULL, '2026-03-08', 1, 'Validée', NULL, NULL, 1900.75, 2, '2026-02-06', NOW(), NOW()),
(37, 'FACT', 'VAT', 7, 2005, 'INV-2026-2005', NULL, NULL, '2026-03-15', 1, 'Validée', NULL, NULL, 2550.30, 3, '2026-02-13', NOW(), NOW());

-- Insert Line Items for invoices
INSERT IGNORE INTO lignepiece (id, piece_id, article_id, qte, pub, montant, remise, dossier_id, created_at, updated_at) VALUES
(1, 1, 1, 1, 1200.50, 1200.50, NULL, 1, NOW(), NOW()),
(2, 2, 6, 2, 925.375, 1850.75, NULL, 2, NOW(), NOW()),
(3, 3, 9, 1, 950.30, 950.30, NULL, 3, NOW(), NOW()),
(4, 4, 2, 2, 1050.00, 2100.00, NULL, 1, NOW(), NOW()),
(5, 5, 7, 1, 1500.50, 1500.50, NULL, 2, NOW(), NOW()),
(6, 6, 10, 1, 1200.00, 1200.00, NULL, 3, NOW(), NOW()),
(7, 7, 3, 3, 1066.92, 3200.75, NULL, 1, NOW(), NOW()),
(8, 8, 8, 2, 1050.15, 2100.30, NULL, 2, NOW(), NOW()),
(9, 9, 9, 2, 900.00, 1800.00, NULL, 3, NOW(), NOW()),
(10, 10, 4, 2, 1250.25, 2500.50, NULL, 4, NOW(), NOW()),
(11, 11, 1, 1, 1900.75, 1900.75, NULL, 1, NOW(), NOW()),
(12, 12, 5, 4, 550.00, 2200.00, NULL, 2, NOW(), NOW()),
(13, 13, 9, 1, 1650.30, 1650.30, NULL, 3, NOW(), NOW()),
(14, 14, 2, 2, 1400.25, 2800.50, NULL, 1, NOW(), NOW()),
(15, 15, 7, 1, 2100.00, 2100.00, NULL, 2, NOW(), NOW()),
(16, 16, 10, 1, 1750.75, 1750.75, NULL, 3, NOW(), NOW()),
(17, 17, 3, 2, 1475.15, 2950.30, NULL, 4, NOW(), NOW()),
(18, 18, 8, 2, 950.25, 1900.50, NULL, 1, NOW(), NOW()),
(19, 19, 4, 2, 1200.00, 2400.00, NULL, 2, NOW(), NOW()),
(20, 20, 1, 1, 2100.75, 2100.75, NULL, 3, NOW(), NOW()),
(21, 21, 6, 2, 925.15, 1850.30, NULL, 1, NOW(), NOW()),
(22, 22, 5, 4, 662.625, 2650.50, NULL, 2, NOW(), NOW()),
(23, 23, 9, 2, 1100.00, 2200.00, NULL, 3, NOW(), NOW()),
(24, 24, 2, 1, 1900.75, 1900.75, NULL, 4, NOW(), NOW()),
(25, 25, 3, 2, 1275.15, 2550.30, NULL, 1, NOW(), NOW()),
(26, 26, 7, 1, 2100.50, 2100.50, NULL, 2, NOW(), NOW()),
(27, 27, 10, 1, 1800.00, 1800.00, NULL, 3, NOW(), NOW()),
(28, 28, 4, 2, 1375.375, 2750.75, NULL, 1, NOW(), NOW()),
(29, 29, 8, 2, 1075.15, 2150.30, NULL, 2, NOW(), NOW()),
(30, 30, 1, 1, 1950.50, 1950.50, NULL, 3, NOW(), NOW()),
(31, 31, 6, 3, 933.33, 2800.00, NULL, 4, NOW(), NOW()),
(32, 32, 5, 3, 783.58, 2350.75, NULL, 1, NOW(), NOW()),
(33, 33, 7, 1, 2100.30, 2100.30, NULL, 2, NOW(), NOW()),
(34, 34, 9, 2, 1325.25, 2650.50, NULL, 3, NOW(), NOW()),
(35, 35, 2, 2, 1100.00, 2200.00, NULL, 1, NOW(), NOW()),
(36, 36, 10, 1, 1900.75, 1900.75, NULL, 2, NOW(), NOW()),
(37, 37, 3, 2, 1275.15, 2550.30, NULL, 3, NOW(), NOW());
