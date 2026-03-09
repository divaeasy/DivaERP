<?php
/**
 * Diagnostic script - Check OVH hosting setup
 * Access via: http://myerp.divaeasy.com/diagnose.php
 */

echo "<h2>DivaERP Hosting Diagnostic</h2>";
echo "<pre>";

// 1. Check PHP version
echo "PHP Version: " . phpversion() . "\n";

// 2. Check required extensions
$required = ['pdo', 'pdo_mysql', 'mbstring', 'xml', 'json', 'ctype'];
echo "\nRequired Extensions:\n";
foreach ($required as $ext) {
    $status = extension_loaded($ext) ? '✓' : '✗';
    echo "  $status $ext\n";
}

// 3. Check directories
echo "\nDirectory Permissions:\n";
$dirs = ['var', 'var/cache', 'var/log', 'public'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_dir($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $writable = is_writable($path) ? '✓ writable' : '✗ NOT writable';
        echo "  ✓ $dir ($perms) - $writable\n";
    } else {
        echo "  ✗ $dir - MISSING!\n";
    }
}

// 4. Check .env files
echo "\n.env Files:\n";
$env_files = ['.env', '.env.local', '.env.prod.local'];
foreach ($env_files as $file) {
    $path = __DIR__ . '/' . $file;
    echo "  " . (file_exists($path) ? '✓' : '✗') . " $file\n";
}

// 5. Check Symfony files
echo "\nSymfony Files:\n";
echo "  " . (file_exists(__DIR__ . '/public/index.php') ? '✓' : '✗') . " public/index.php\n";
echo "  " . (file_exists(__DIR__ . '/src') ? '✓' : '✗') . " src/ directory\n";
echo "  " . (file_exists(__DIR__ . '/vendor/autoload.php') ? '✓' : '✗') . " vendor/autoload.php\n";

// 6. Try to load environment
echo "\nEnvironment Status:\n";
$env_file = __DIR__ . '/.env.prod.local';
if (file_exists($env_file)) {
    $content = file_get_contents($env_file);
    if (strpos($content, 'DATABASE_URL="mysql://USER:PASSWORD') !== false) {
        echo "  ✗ DATABASE_URL not configured (still has placeholders)\n";
        echo "    UPDATE .env.prod.local with your OVH database credentials!\n";
    } else {
        echo "  ✓ DATABASE_URL appears configured\n";
    }
}

// 7. Check .htaccess
echo "\n.htaccess Files:\n";
echo "  " . (file_exists(__DIR__ . '/.htaccess') ? '✓' : '✗') . " /.htaccess\n";
echo "  " . (file_exists(__DIR__ . '/public/.htaccess') ? '✓' : '✗') . " /public/.htaccess\n";

// 8. Check if mod_rewrite is available
echo "\nServer Capabilities:\n";
if (function_exists('apache_get_modules')) {
    $mods = apache_get_modules();
    echo "  " . (in_array('mod_rewrite', $mods) ? '✓' : '✗') . " mod_rewrite\n";
} else {
    echo "  ? Cannot determine mod_rewrite (not Apache or not enabled)\n";
}

echo "\n";
echo "=====================================\n";
echo "NEXT STEPS:\n";
echo "=====================================\n";
echo "1. Check all ✓ in 'Required Extensions'\n";
echo "2. Ensure var/ directories are WRITABLE\n";
echo "3. UPDATE .env.prod.local with OVH database:\n";
echo "   - Ask OVH support for: hostname, user, password, database name\n";
echo "4. Create cache: rm -rf var/cache/*\n";
echo "5. Reload this page\n";
echo "</pre>";
