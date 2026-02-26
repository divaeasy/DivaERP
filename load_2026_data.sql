-- Additional test data for 2026 to populate the dashboard charts
-- This includes more invoices and line items for 2026

-- Insert more Invoices for 2026 (March to December)
INSERT INTO entetepiece (type, typet, client_id, pieceno, pieceref, devise_id, remise, delai, reglement_id, statut, edition, rapport, montant, dossier_id, datep, created_at, updated_at) VALUES
('FACT', 'VAT', 8, 2006, 'INV-2026-2006', NULL, NULL, '2026-03-22', 1, 'Validée', NULL, NULL, 2750.75, 1, '2026-02-20', NOW(), NOW()),
('FACT', 'VAT', 9, 2007, 'INV-2026-2007', NULL, NULL, '2026-03-28', 1, 'Validée', NULL, NULL, 2150.30, 2, '2026-02-26', NOW(), NOW()),
('FACT', 'VAT', 10, 2008, 'INV-2026-2008', NULL, NULL, '2026-04-08', 1, 'Validée', NULL, NULL, 1950.50, 3, '2026-03-09', NOW(), NOW()),
('FACT', 'VAT', 1, 2009, 'INV-2026-2009', NULL, NULL, '2026-04-15', 1, 'Validée', NULL, NULL, 2800.00, 1, '2026-03-16', NOW(), NOW()),
('FACT', 'VAT', 2, 2010, 'INV-2026-2010', NULL, NULL, '2026-04-22', 1, 'Validée', NULL, NULL, 2350.75, 2, '2026-03-23', NOW(), NOW()),
('FACT', 'VAT', 3, 2011, 'INV-2026-2011', NULL, NULL, '2026-05-08', 1, 'Validée', NULL, NULL, 2100.30, 3, '2026-04-09', NOW(), NOW()),
('FACT', 'VAT', 4, 2012, 'INV-2026-2012', NULL, NULL, '2026-05-15', 1, 'Validée', NULL, NULL, 2650.50, 1, '2026-04-16', NOW(), NOW()),
('FACT', 'VAT', 5, 2013, 'INV-2026-2013', NULL, NULL, '2026-05-22', 1, 'Validée', NULL, NULL, 2200.00, 2, '2026-04-23', NOW(), NOW()),
('FACT', 'VAT', 6, 2014, 'INV-2026-2014', NULL, NULL, '2026-06-08', 1, 'Validée', NULL, NULL, 1900.75, 3, '2026-05-09', NOW(), NOW()),
('FACT', 'VAT', 7, 2015, 'INV-2026-2015', NULL, NULL, '2026-06-15', 1, 'Validée', NULL, NULL, 2550.30, 1, '2026-05-16', NOW(), NOW()),
('FACT', 'VAT', 8, 2016, 'INV-2026-2016', NULL, NULL, '2026-06-22', 1, 'Validée', NULL, NULL, 2100.50, 2, '2026-05-23', NOW(), NOW()),
('FACT', 'VAT', 9, 2017, 'INV-2026-2017', NULL, NULL, '2026-07-08', 1, 'Validée', NULL, NULL, 1800.00, 3, '2026-06-09', NOW(), NOW()),
('FACT', 'VAT', 10, 2018, 'INV-2026-2018', NULL, NULL, '2026-07-15', 1, 'Validée', NULL, NULL, 2750.75, 1, '2026-06-16', NOW(), NOW()),
('FACT', 'VAT', 1, 2019, 'INV-2026-2019', NULL, NULL, '2026-07-22', 1, 'Validée', NULL, NULL, 2150.30, 2, '2026-06-23', NOW(), NOW()),
('FACT', 'VAT', 2, 2020, 'INV-2026-2020', NULL, NULL, '2026-08-08', 1, 'Validée', NULL, NULL, 1950.50, 3, '2026-07-09', NOW(), NOW()),
('FACT', 'VAT', 3, 2021, 'INV-2026-2021', NULL, NULL, '2026-08-15', 1, 'Validée', NULL, NULL, 2800.00, 1, '2026-07-16', NOW(), NOW()),
('FACT', 'VAT', 4, 2022, 'INV-2026-2022', NULL, NULL, '2026-08-22', 1, 'Validée', NULL, NULL, 2350.75, 2, '2026-07-23', NOW(), NOW()),
('FACT', 'VAT', 5, 2023, 'INV-2026-2023', NULL, NULL, '2026-09-08', 1, 'Validée', NULL, NULL, 2100.30, 3, '2026-08-09', NOW(), NOW()),
('FACT', 'VAT', 6, 2024, 'INV-2026-2024', NULL, NULL, '2026-09-15', 1, 'Validée', NULL, NULL, 2650.50, 1, '2026-08-16', NOW(), NOW()),
('FACT', 'VAT', 7, 2025, 'INV-2026-2025', NULL, NULL, '2026-09-22', 1, 'Validée', NULL, NULL, 2200.00, 2, '2026-08-23', NOW(), NOW()),
('FACT', 'VAT', 8, 2026, 'INV-2026-2026', NULL, NULL, '2026-10-08', 1, 'Validée', NULL, NULL, 1900.75, 3, '2026-09-09', NOW(), NOW()),
('FACT', 'VAT', 9, 2027, 'INV-2026-2027', NULL, NULL, '2026-10-15', 1, 'Validée', NULL, NULL, 2550.30, 1, '2026-09-16', NOW(), NOW()),
('FACT', 'VAT', 10, 2028, 'INV-2026-2028', NULL, NULL, '2026-10-22', 1, 'Validée', NULL, NULL, 2100.50, 2, '2026-09-23', NOW(), NOW()),
('FACT', 'VAT', 1, 2029, 'INV-2026-2029', NULL, NULL, '2026-11-08', 1, 'Validée', NULL, NULL, 1800.00, 3, '2026-10-09', NOW(), NOW()),
('FACT', 'VAT', 2, 2030, 'INV-2026-2030', NULL, NULL, '2026-11-15', 1, 'Validée', NULL, NULL, 2750.75, 1, '2026-10-16', NOW(), NOW());

-- Insert Line Items for new 2026 invoices
-- Invoices 38-62 (IDs 38-62)
INSERT INTO lignepiece (piece_id, article_id, qte, pub, montant, remise, dossier_id, created_at, updated_at) VALUES
(38, 1, 3, 916.92, 2750.75, NULL, 1, NOW(), NOW()),
(39, 2, 2, 1075.15, 2150.30, NULL, 2, NOW(), NOW()),
(40, 3, 2, 975.25, 1950.50, NULL, 3, NOW(), NOW()),
(41, 4, 4, 700.00, 2800.00, NULL, 1, NOW(), NOW()),
(42, 5, 5, 470.15, 2350.75, NULL, 2, NOW(), NOW()),
(43, 6, 2, 1050.15, 2100.30, NULL, 3, NOW(), NOW()),
(44, 7, 1, 2650.50, 2650.50, NULL, 1, NOW(), NOW()),
(45, 8, 2, 1100.00, 2200.00, NULL, 2, NOW(), NOW()),
(46, 9, 2, 950.38, 1900.75, NULL, 3, NOW(), NOW()),
(47, 10, 2, 1275.15, 2550.30, NULL, 1, NOW(), NOW()),
(48, 1, 2, 1050.25, 2100.50, NULL, 2, NOW(), NOW()),
(49, 2, 1, 1800.00, 1800.00, NULL, 3, NOW(), NOW()),
(50, 3, 3, 916.92, 2750.75, NULL, 1, NOW(), NOW()),
(51, 4, 2, 1075.15, 2150.30, NULL, 2, NOW(), NOW()),
(52, 5, 2, 975.25, 1950.50, NULL, 3, NOW(), NOW()),
(53, 6, 1, 2800.00, 2800.00, NULL, 1, NOW(), NOW()),
(54, 7, 1, 2350.75, 2350.75, NULL, 2, NOW(), NOW()),
(55, 8, 1, 2100.30, 2100.30, NULL, 3, NOW(), NOW()),
(56, 9, 1, 2650.50, 2650.50, NULL, 1, NOW(), NOW()),
(57, 10, 1, 2200.00, 2200.00, NULL, 2, NOW(), NOW()),
(58, 1, 1, 1900.75, 1900.75, NULL, 3, NOW(), NOW()),
(59, 2, 2, 1275.15, 2550.30, NULL, 1, NOW(), NOW()),
(60, 3, 1, 2100.50, 2100.50, NULL, 2, NOW(), NOW()),
(61, 4, 1, 1800.00, 1800.00, NULL, 3, NOW(), NOW()),
(62, 5, 2, 1375.38, 2750.75, NULL, 1, NOW(), NOW());
