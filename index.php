<?php
/**
 * Front Controller
 * Works around OVH's mod_rewrite limitations
 */

// Ensure we're in the correct directory
chdir(__DIR__);

// Boot Symfony from the public folder
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/public/index.php';
$_SERVER['SCRIPT_NAME'] = '/public/index.php';
$_SERVER['PHP_SELF'] = '/public/index.php';

// Load and execute the main index.php
require __DIR__ . '/public/index.php';




