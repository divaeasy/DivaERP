<?php

// Load test data directly
$dsn = 'mysql:host=127.0.0.1;dbname=myerp;charset=utf8';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Disable FK checks for clean insert
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    
    $sql = file_get_contents(__DIR__ . '/test_data.sql');
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $count = 0;
    foreach ($statements as $statement) {
        // Remove SQL comments from the statement
        $cleanStatement = preg_replace('/--.*$/m', '', $statement);
        $cleanStatement = trim($cleanStatement);
        if (!empty($cleanStatement)) {
            $pdo->exec($cleanStatement);
            $count++;
        }
    }
    
    // Re-enable FK checks
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    
    echo "Test data loaded successfully! ($count statements executed)\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Statement: " . substr($statement ?? '', 0, 100) . "\n";
}
