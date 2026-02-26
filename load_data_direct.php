<?php
// Direct test data insertion script
ini_set('display_errors', 1);

$host = '127.0.0.1';
$db = 'myerp';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Connected to database\n\n";
    
    // Clear existing data
    echo "Clearing existing data...\n";
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->exec('DELETE FROM lignepiece');
    $pdo->exec('DELETE FROM entetepiece');
    $pdo->exec('DELETE FROM article');
    $pdo->exec('DELETE FROM clients');
    $pdo->exec('DELETE FROM dossier');
    $pdo->exec('DELETE FROM reglement');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    echo "✓ Database cleared\n\n";
    
    // Load test data directly
    echo "Loading test data...\n";
    
    $executed = 0;
    
    // Insert Dossiers
    $dossier_stmt = $pdo->prepare("INSERT IGNORE INTO dossier (id, nom, adresse, rc) VALUES (?, ?, ?, ?)");
    $dossiers = [1 => 'Électronique', 2 => 'Mobilier', 3 => 'Services', 4 => 'Logiciels', 5 => 'Consulting'];
    foreach ($dossiers as $id => $name) {
        $dossier_stmt->execute([$id, $name, "123 Rue de $name", "RC100$id"]);
        $executed++;
    }
    echo "✓ Added dossiers\n";
    
    // Insert Clients (requires dossier_id)
    $client_stmt = $pdo->prepare("INSERT IGNORE INTO clients (id, dossier_id, nom, adr1, adr2, rue, tel, email, web, linkedin, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, '', '', NOW(), NOW())");
    $client_names = ['Client A', 'Client B', 'Client C', 'Client D', 'Client E', 'Client F', 'Client G', 'Client H', 'Client I', 'Client J'];
    foreach ($client_names as $idx => $name) {
        $id = $idx + 1;
        $dossier_id = (($idx % 5) + 1);
        $tel = '06' . str_pad(rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        $email = strtolower(str_replace(' ', '.', $name)) . '@example.com';
        $client_stmt->execute([$id, $dossier_id, $name, $name, '', "Rue de $name", $tel, $email]);
        $executed++;
    }
    echo "✓ Added clients\n";
    
    // Insert Articles
    $article_stmt = $pdo->prepare("INSERT IGNORE INTO article (id, libelle, dossier_id, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
    $products = [
        1 => ['Laptop Pro', 1],
        2 => ['Desktop Computer', 1],
        3 => ['Monitor 27"', 1],
        4 => ['Keyboard Mechanical', 1],
        5 => ['Mouse Wireless', 1],
        6 => ['Office Chair', 2],
        7 => ['Desk Wooden', 2],
        8 => ['Lamp LED', 2],
        9 => ['Consultation Hours', 3],
        10 => ['Support Package', 4],
    ];
    foreach ($products as $id => [$name, $dossier_id]) {
        $article_stmt->execute([$id, $name, $dossier_id]);
        $executed++;
    }
    echo "✓ Added articles\n";
    
    // Insert Reglement
    $pdo->exec("INSERT IGNORE INTO reglement (id, libelle, echeance, created_at, updated_at) VALUES (1, 'Virement', 30, NOW(), NOW())");
    $executed++;
    echo "✓ Added reglement\n";
    
    // Insert Invoices for 2025
    $invoice_stmt = $pdo->prepare("INSERT IGNORE INTO entetepiece (id, type, typet, client_id, pieceno, pieceref, reglement_id, statut, montant, dossier_id, datep, delai, created_at, updated_at) VALUES (?, 'FACT', 'VAT', ?, ?, ?, 1, 'Validée', ?, ?, ?, ?, NOW(), NOW())");
    
    $invoice_data = [
        [1, 1, 1001, 'INV-2025-1001', 1200.50, 1, '2025-01-06', '2025-02-05'],
        [2, 2, 1002, 'INV-2025-1002', 1850.75, 2, '2025-01-11', '2025-02-10'],
        [3, 3, 1003, 'INV-2025-1003', 950.30, 3, '2025-01-16', '2025-02-15'],
        [4, 4, 1004, 'INV-2025-1004', 2100.00, 1, '2025-02-03', '2025-03-05'],
        [5, 5, 1005, 'INV-2025-1005', 1500.50, 2, '2025-02-10', '2025-03-12'],
        [6, 6, 1006, 'INV-2025-1006', 1200.00, 3, '2025-02-18', '2025-03-20'],
        [7, 7, 1007, 'INV-2025-1007', 3200.75, 1, '2025-03-06', '2025-04-05'],
        [8, 8, 1008, 'INV-2025-1008', 2100.30, 2, '2025-03-13', '2025-04-12'],
        [9, 9, 1009, 'INV-2025-1009', 1800.00, 3, '2025-03-21', '2025-04-20'],
        [10, 10, 1010, 'INV-2025-1010', 2500.50, 4, '2025-04-10', '2025-05-10'],
        [11, 1, 1011, 'INV-2025-1011', 1900.75, 1, '2025-04-18', '2025-05-18'],
        [12, 2, 1012, 'INV-2025-1012', 2200.00, 2, '2025-04-25', '2025-05-25'],
        [13, 3, 1013, 'INV-2025-1013', 1650.30, 3, '2025-05-09', '2025-06-08'],
        [14, 4, 1014, 'INV-2025-1014', 2800.50, 1, '2025-05-16', '2025-06-15'],
        [15, 5, 1015, 'INV-2025-1015', 2100.00, 2, '2025-05-23', '2025-06-22'],
        [16, 6, 1016, 'INV-2025-1016', 1750.75, 3, '2025-06-05', '2025-07-05'],
        [17, 7, 1017, 'INV-2025-1017', 2950.30, 4, '2025-06-12', '2025-07-12'],
        [18, 8, 1018, 'INV-2025-1018', 1900.50, 1, '2025-06-20', '2025-07-20'],
        [19, 9, 1019, 'INV-2025-1019', 2400.00, 2, '2025-07-09', '2025-08-08'],
        [20, 10, 1020, 'INV-2025-1020', 2100.75, 3, '2025-07-16', '2025-08-15'],
        [21, 1, 1021, 'INV-2025-1021', 1850.30, 1, '2025-07-23', '2025-08-22'],
        [22, 2, 1022, 'INV-2025-1022', 2650.50, 2, '2025-08-06', '2025-09-05'],
        [23, 3, 1023, 'INV-2025-1023', 2200.00, 3, '2025-08-13', '2025-09-12'],
        [24, 4, 1024, 'INV-2025-1024', 1900.75, 4, '2025-08-21', '2025-09-20'],
        [25, 5, 1025, 'INV-2025-1025', 2550.30, 1, '2025-09-08', '2025-10-08'],
        [26, 6, 1026, 'INV-2025-1026', 2100.50, 2, '2025-09-15', '2025-10-15'],
        [27, 7, 1027, 'INV-2025-1027', 1800.00, 3, '2025-09-22', '2025-10-22'],
        [28, 8, 1028, 'INV-2025-1028', 2750.75, 1, '2025-10-06', '2025-11-05'],
        [29, 9, 1029, 'INV-2025-1029', 2150.30, 2, '2025-10-13', '2025-11-12'],
        [30, 10, 1030, 'INV-2025-1030', 1950.50, 3, '2025-10-21', '2025-11-20'],
        [31, 1, 1031, 'INV-2025-1031', 2800.00, 4, '2025-11-08', '2025-12-08'],
        [32, 2, 1032, 'INV-2025-1032', 2350.75, 1, '2025-11-15', '2025-12-15'],
        [33, 3, 2001, 'INV-2026-2001', 2100.30, 2, '2026-01-06', '2026-02-05'],
        [34, 4, 2002, 'INV-2026-2002', 2650.50, 3, '2026-01-13', '2026-02-12'],
        [35, 5, 2003, 'INV-2026-2003', 2200.00, 1, '2026-01-21', '2026-02-20'],
        [36, 6, 2004, 'INV-2026-2004', 1900.75, 2, '2026-02-06', '2026-03-08'],
        [37, 7, 2005, 'INV-2026-2005', 2550.30, 3, '2026-02-13', '2026-03-15'],
    ];
    
    foreach ($invoice_data as [$inv_id, $client_id, $pieceno, $pieceref, $montant, $dossier_id, $datep, $delai]) {
        $invoice_stmt->execute([$inv_id, $client_id, $pieceno, $pieceref, $montant, $dossier_id, $datep, $delai]);
        $executed++;
    }
    echo "✓ Added invoices\n";
    
    // Insert Line Items
    $line_stmt = $pdo->prepare("INSERT IGNORE INTO lignepiece (id, piece_id, article_id, qte, pub, montant, dossier_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $line_items = [
        [1, 1, 1, 1, 1200.50, 1200.50, 1],
        [2, 2, 6, 2, 925.38, 1850.75, 2],
        [3, 3, 9, 1, 950.30, 950.30, 3],
        [4, 4, 2, 2, 1050.00, 2100.00, 1],
        [5, 5, 7, 1, 1500.50, 1500.50, 2],
        [6, 6, 10, 1, 1200.00, 1200.00, 3],
        [7, 7, 3, 3, 1066.92, 3200.75, 1],
        [8, 8, 8, 2, 1050.15, 2100.30, 2],
        [9, 9, 9, 2, 900.00, 1800.00, 3],
        [10, 10, 4, 2, 1250.25, 2500.50, 4],
    ];
    
    foreach ($line_items as [$line_id, $piece_id, $article_id, $qte, $pub, $montant, $dossier_id]) {
        $line_stmt->execute([$line_id, $piece_id, $article_id, $qte, $pub, $montant, $dossier_id]);
        $executed++;
    }
    echo "✓ Added line items\n";
    
    echo "✓ Executed $executed queries\n\n";
    
    // Verify data
    echo "Verifying loaded data:\n";
    
    $counts = [
        'dossier' => 'Dossiers',
        'clients' => 'Clients',
        'article' => 'Products',
        'reglement' => 'Payment Terms',
        'entetepiece' => 'Invoices',
        'lignepiece' => 'Line Items'
    ];
    
    foreach ($counts as $table => $label) {
        $result = $pdo->query("SELECT COUNT(*) as cnt FROM $table");
        $count = $result->fetch(PDO::FETCH_ASSOC)['cnt'];
        echo "  $label: $count\n";
    }
    
    echo "\n✅ Test data loaded successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
