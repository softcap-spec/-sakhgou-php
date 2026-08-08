<?php
/**
 * Вспомогательные функции
 */
require_once __DIR__ . '/db.php';

// === CSRF Protection ===
function csrf_token(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function csrf_check(): void {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['csrf_token']) || !isset($_POST['_csrf'])
        || !hash_equals($_SESSION['csrf_token'], $_POST['_csrf'])) {
      http_response_code(403);
      die('Ошибка безопасности: недействительный CSRF-токен. Обновите страницу и попробуйте снова.');
    }
  }
}

function csrf_field(): string {
  return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function format_price(float $price): string {
  if ($price == 0) return 'Бесплатно';
  return number_format($price, 0, ',', ' ') . ' ₽';
}

function time_ago(string $date): string {
  $diff = time() - strtotime($date);
  if ($diff < 60) return 'только что';
  if ($diff < 3600) return floor($diff / 60) . ' мин. назад';
  if ($diff < 86400) return floor($diff / 3600) . ' ч. назад';
  if ($diff < 604800) return floor($diff / 86400) . ' дн. назад';
  return date('d.m.Y', strtotime($date));
}

function transliterate(string $text): string {
  $map = ['Р°'=>'a','Р±'=>'b','РІ'=>'v','Рі'=>'g','Рґ'=>'d','Рµ'=>'e','С‘'=>'yo','Р¶'=>'zh','Р·'=>'z','Рё'=>'i','Р№'=>'y','Рє'=>'k','Р»'=>'l','Рј'=>'m','РЅ'=>'n','Рѕ'=>'o','Рї'=>'p','СЂ'=>'r','СЃ'=>'s','С‚'=>'t','Сѓ'=>'u','С„'=>'f','С…'=>'h','С†'=>'ts','С‡'=>'ch','С€'=>'sh','С‰'=>'sch','СЉ'=>'','С‹'=>'y','СЊ'=>'','СЌ'=>'e','СЋ'=>'yu','СЏ'=>'ya',' '=>'-'];
  $text = mb_strtolower($text, 'UTF-8');
  $text = strtr($text, $map);
  $text = preg_replace('/[^a-z0-9-]/', '', $text);
  $text = preg_replace('/-+/', '-', $text);
  return trim($text, '-');
}

function slugify(string $text): string {
  $text = mb_strtolower($text, 'UTF-8');
  $text = preg_replace('/[^a-zа-яё0-9\s-]/u', '', $text);
  $text = preg_replace('/[\s-]+/', '-', trim($text));
  return $text;
}

function get_categories(): array {
  return json_decode(CATEGORIES, true);
}

function get_category(string $slug): ?array {
  foreach (get_categories() as $cat) {
    if ($cat['slug'] === $slug) return $cat;
  }
  return null;
}

function get_listings(string $category = '', string $search = '', int $page = 1, string $status = 'active'): array {
  $pdo = db();
  $where = ['l.status = ?'];
  $params = [$status];
  
  if ($category) {
    $where[] = 'c.slug = ?';
    $params[] = $category;
  }
  if ($search) {
    $where[] = '(l.title LIKE ? OR l.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
  }
  
  $whereSQL = implode(' AND ', $where);
  $offset = ($page - 1) * ITEMS_PER_PAGE;
  
  // Count
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM listings l JOIN categories c ON l.category_id = c.id WHERE $whereSQL");
  $stmt->execute($params);
  $total = (int) $stmt->fetchColumn();
  
  // Fetch with first image
  $stmt = $pdo->prepare("
    SELECT l.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name,
      (SELECT filename FROM listing_images WHERE listing_id = l.id ORDER BY sort_order LIMIT 1) AS image,
      promo.promo_type AS promo_type
    FROM listings l
    JOIN categories c ON l.category_id = c.id
    JOIN users u ON l.user_id = u.id
    LEFT JOIN promotions promo ON l.id = promo.listing_id AND promo.status = 'active' AND promo.expires_at > NOW()
    WHERE $whereSQL
    ORDER BY CASE WHEN promo.id IS NOT NULL THEN 0 ELSE 1 END, l.created_at DESC
    LIMIT " . ITEMS_PER_PAGE . " OFFSET $offset
  ");
  $stmt->execute($params);
  $items = $stmt->fetchAll();
  
  return ['items' => $items, 'total' => $total, 'pages' => ceil($total / ITEMS_PER_PAGE), 'page' => $page];
}

function get_listing(int $id): ?array {
  $pdo = db();
  $stmt = $pdo->prepare("
    SELECT l.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name, u.email AS author_email, u.phone AS author_phone
    FROM listings l
    JOIN categories c ON l.category_id = c.id
    JOIN users u ON l.user_id = u.id
    WHERE l.id = ?
  ");
  $stmt->execute([$id]);
  $listing = $stmt->fetch();
  if (!$listing) return null;
  
  // Images
  $stmt = $pdo->prepare('SELECT filename FROM listing_images WHERE listing_id = ? ORDER BY sort_order');
  $stmt->execute([$id]);
  $listing['images'] = $stmt->fetchAll(\PDO::FETCH_COLUMN);
  
  return $listing;
}

function create_listing(int $userId, string $title, string $description, float $price, string $categorySlug, string $location, array $images = []): int {
  $pdo = db();
  // Get category id
  $stmt = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
  $stmt->execute([$categorySlug]);
  $cat = $stmt->fetch();
  if (!$cat) throw new \RuntimeException('Категория не найдена');
  
  $pdo->beginTransaction();
  try {
    $stmt = $pdo->prepare('INSERT INTO listings (user_id, category_id, title, description, price, location) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $cat['id'], $title, $description, $price, $location]);
    $listingId = (int) $pdo->lastInsertId();
    
    // Save images
    foreach ($images as $i => $tmpPath) {
      $ext = pathinfo($tmpPath, PATHINFO_EXTENSION);
      $filename = $listingId . '_' . ($i + 1) . '_' . time() . '.' . $ext;
      $dest = UPLOAD_DIR . '/' . $filename;
      move_uploaded_file($tmpPath, $dest);
      
      $stmt = $pdo->prepare('INSERT INTO listing_images (listing_id, filename, sort_order) VALUES (?, ?, ?)');
      $stmt->execute([$listingId, $filename, $i]);
    }
    
    $pdo->commit();
    return $listingId;
  } catch (\Throwable $e) {
    $pdo->rollBack();
    throw $e;
  }
}

function delete_listing(int $id, int $userId): bool {
  $pdo = db();
  $stmt = $pdo->prepare('DELETE FROM listings WHERE id = ? AND user_id = ?');
  $stmt->execute([$id, $userId]);
  return $stmt->rowCount() > 0;
}

function add_notification(int $user_id, string $type, string $text, string $link = ''): void {
  $pdo = db();
  $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, text, link, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
  $stmt->execute([$user_id, $type, $text, $link]);
}

function toggle_favorite(int $user_id, int $listing_id): bool {
  $pdo = db();
  $stmt = $pdo->prepare('SELECT id FROM favorites WHERE user_id = ? AND listing_id = ?');
  $stmt->execute([$user_id, $listing_id]);
  if ($stmt->fetch()) {
    $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND listing_id = ?')->execute([$user_id, $listing_id]);
    return false;
  }
  $pdo->prepare('INSERT INTO favorites (user_id, listing_id) VALUES (?, ?)')->execute([$user_id, $listing_id]);
  return true;
}

function is_favorite(int $user_id, int $listing_id): bool {
  $pdo = db();
  $stmt = $pdo->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND listing_id = ?');
  $stmt->execute([$user_id, $listing_id]);
  return (bool)$stmt->fetch();
}

function get_user_favorites(int $user_id): array {
  $pdo = db();
  $stmt = $pdo->prepare('
    SELECT l.*, c.name AS category_name, c.slug AS category_slug,
      (SELECT filename FROM listing_images WHERE listing_id = l.id ORDER BY sort_order LIMIT 1) AS image
    FROM favorites f
    JOIN listings l ON f.listing_id = l.id
    JOIN categories c ON l.category_id = c.id
    WHERE f.user_id = ? AND l.status = ?
    ORDER BY f.created_at DESC LIMIT 20
  ');
  $stmt->execute([$user_id, 'active']);
  return $stmt->fetchAll();
}

function get_user_bookings(int $user_id): array {
  $pdo = db();
  $stmt = $pdo->prepare("SELECT b.*, l.title AS listing_title, l.listing_type, l.location, u.name AS host_name FROM bookings b JOIN listings l ON b.listing_id = l.id JOIN users u ON b.host_id = u.id WHERE b.guest_id = ? ORDER BY b.created_at DESC LIMIT 20");
  $stmt->execute([$user_id]);
  return $stmt->fetchAll();
}

function get_host_bookings(int $user_id): array {
  $pdo = db();
  $stmt = $pdo->prepare("SELECT b.*, l.title AS listing_title, u.name AS guest_name FROM bookings b JOIN listings l ON b.listing_id = l.id JOIN users u ON b.guest_id = u.id WHERE b.host_id = ? ORDER BY b.created_at DESC LIMIT 20");
  $stmt->execute([$user_id]);
  return $stmt->fetchAll();
}

function send_message(int $sender_id, int $receiver_id, int $listing_id, string $text): int {
  $pdo = db();
  $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, listing_id, text, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
  $stmt->execute([$sender_id, $receiver_id, $listing_id, $text]);
  return (int)$pdo->lastInsertId();
}

function get_recent_listings(int $limit = 6): array {
  $pdo = db();
  $stmt = $pdo->prepare("
    SELECT l.*, c.name AS category_name, c.slug AS category_slug,
      (SELECT filename FROM listing_images WHERE listing_id = l.id ORDER BY sort_order LIMIT 1) AS image,
      promo.promo_type AS promo_type
    FROM listings l
    JOIN categories c ON l.category_id = c.id
    LEFT JOIN promotions promo ON l.id = promo.listing_id AND promo.status = 'active' AND promo.expires_at > NOW()
    WHERE l.status = 'active'
    ORDER BY CASE WHEN promo.id IS NOT NULL THEN 0 ELSE 1 END, l.created_at DESC
    LIMIT ?
  ");
  $stmt->bindValue(1, $limit, PDO::PARAM_INT);
  $stmt->execute();
  return $stmt->fetchAll();
}
