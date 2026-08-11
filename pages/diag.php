<?php
header('Content-Type: text/plain; charset=utf-8');
echo "DIAG START\n\n";

// 1. Check includes
echo "1. Loading config...\n";
include __DIR__ . '/../includes/config.php';
echo "   OK: DB_NAME=" . DB_NAME . "\n";

echo "2. Loading functions...\n";
echo "   auth_user exists: " . (function_exists('auth_user') ? 'YES' : 'NO') . "\n";
echo "   get_listings exists: " . (function_exists('get_listings') ? 'YES' : 'NO') . "\n";
echo "   h() exists: " . (function_exists('h') ? 'YES' : 'NO') . "\n";

echo "3. Testing db()...\n";
$db = db();
echo "   db() class: " . get_class($db) . "\n";

echo "4. Testing categories query...\n";
try {
    $s = $db->prepare('SELECT li.filename FROM listing_images li JOIN listings l ON li.listing_id=l.id JOIN categories c ON l.category_id=c.id WHERE c.slug=? AND l.status="active" ORDER BY RAND() LIMIT 1');
    $s->execute(['property']);
    $img = $s->fetchColumn();
    echo "   property image: " . ($img ?: 'EMPTY') . "\n";
} catch (Exception $e) {
    echo "   ERROR: " . $e->getMessage() . "\n";
}

echo "5. Testing get_listings...\n";
$r = get_listings('property', '', 1);
echo "   property total: " . $r['total'] . "\n";

echo "6. Checking uploads dir...\n";
$test = glob('uploads/*.jpg');
echo "   jpg files: " . count($test) . "\n";
if ($test) echo "   first: " . basename($test[0]) . "\n";

echo "\nDIAG END\n";
