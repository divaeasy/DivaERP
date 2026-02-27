<?php
$con = new mysqli('127.0.0.1', 'root', '', 'myerp');

if ($con->connect_error) {
    die('Connection failed: ' . $con->connect_error);
}

// First, add more line items to existing 2026 invoices to boost product quantities
// Invoice IDs 33-37 already exist from test_data.sql
$additional_line_items = [
    // Invoice 33 - add more items
    [33, 1, 5, 240.06, 1200.30, 2],
    [33, 3, 3, 300.10, 900.30, 2],
    [33, 5, 2, 600.00, 1200.00, 2],
    
    // Invoice 34 - add more items
    [34, 2, 4, 412.63, 1650.52, 3],
    [34, 4, 3, 366.83, 1100.49, 3],
    [34, 6, 2, 500.25, 1000.50, 3],
    
    // Invoice 35 - add more items
    [35, 1, 4, 275.05, 1100.20, 1],
    [35, 5, 3, 366.60, 1099.80, 1],
    [35, 9, 1, 1000.00, 1000.00, 1],
    
    // Invoice 36 - add more items
    [36, 2, 3, 316.92, 950.76, 2],
    [36, 7, 1, 949.99, 949.99, 2],
    
    // Invoice 37 - add more items
    [37, 3, 4, 318.83, 1275.32, 3],
    [37, 10, 2, 637.49, 1274.98, 3],
    
    // Add more invoices with more line items
];

$count = 0;

// Add additional line items
foreach ($additional_line_items as $item) {
    $piece_id = $item[0];
    $article_id = $item[1];
    $qte = $item[2];
    $pub = $item[3];
    $montant = $item[4];
    $dossier_id = $item[5];
    
    $sql = "INSERT INTO lignepiece (piece_id, article_id, qte, pub, montant, dossier_id, created_at, updated_at) 
            VALUES ($piece_id, $article_id, $qte, $pub, $montant, $dossier_id, NOW(), NOW())";
    
    if ($con->query($sql)) {
        $count++;
    } else {
        echo 'Error inserting line item: ' . $con->error . PHP_EOL;
    }
}

echo 'Additional 2026 line items added: ' . $count . ' items' . PHP_EOL;

// Verify the data
$result = $con->query('SELECT COUNT(*) as cnt FROM entetepiece WHERE YEAR(datep) = 2026');
$row = $result->fetch_assoc();
echo PHP_EOL . 'Total 2026 invoices: ' . $row['cnt'] . PHP_EOL;

$result = $con->query('SELECT SUM(lp.qte) as total FROM lignepiece lp JOIN entetepiece ep ON lp.piece_id = ep.id WHERE YEAR(ep.datep) = 2026');
$row = $result->fetch_assoc();
echo 'Total 2026 items sold: ' . ($row['total'] ?? 0) . ' units' . PHP_EOL;

// Show top products
echo PHP_EOL . '🏆 Top 5 Products by Quantity for 2026:' . PHP_EOL;
echo '==========================================' . PHP_EOL;
$result = $con->query('SELECT a.libelle, SUM(lp.qte) as qty FROM lignepiece lp JOIN article a ON lp.article_id = a.id JOIN entetepiece ep ON lp.piece_id = ep.id WHERE YEAR(ep.datep) = 2026 GROUP BY a.id, a.libelle ORDER BY qty DESC LIMIT 5');
$rank = 1;
while ($row = $result->fetch_assoc()) {
    echo ($rank++) . '. ' . str_pad($row['libelle'], 25) . ': ' . $row['qty'] . ' units' . PHP_EOL;
}

$con->close();
echo PHP_EOL . '✅ Dashboard data updated successfully!' . PHP_EOL;
