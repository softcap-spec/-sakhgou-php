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
  $map = [
    'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo',
    'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
    'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
    'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '',
    'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', ' ' => '-',
  ];
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

/**
 * Return avatar HTML: img if avatar_url exists, or colored circle with first letter
 */
function avatar_html(?array $user, string $size_class = 'w-8 h-8', string $text_class = 'text-xs'): string {
  $name = $user['name'] ?? '?';
  $initial = mb_strtoupper(mb_substr($name, 0, 1));
  $url = $user['avatar_url'] ?? null;
  if ($url) {
    $img_url = (str_starts_with($url, 'http')) ? $url : $url;
    return '<img src="' . h($img_url) . '" alt="' . h($name) . '" class="' . $size_class . ' rounded-full object-cover" />';
  }
  $colors = ['bg-accent text-white', 'bg-emerald-600 text-white', 'bg-violet-600 text-white',
             'bg-amber-600 text-white', 'bg-rose-600 text-white', 'bg-cyan-600 text-white',
             'bg-indigo-600 text-white', 'bg-teal-600 text-white'];
  $color = $colors[abs(crc32($name)) % count($colors)];
  return '<span class="' . $size_class . ' rounded-full ' . $color . ' inline-flex items-center justify-center font-semibold ' . $text_class . ' shrink-0">' . $initial . '</span>';
}

/**
 * Get promo prices from settings: prices[type][days] = rub
 */
function get_promo_prices(): array {
  $pdo = db();
  $defaults = [
    'top' => ['7'=>4900,'14'=>8400,'30'=>15000],
    'highlight' => ['7'=>2800,'14'=>4800,'30'=>8500],
    'urgent' => ['7'=>1400,'14'=>2400,'30'=>4200],
  ];
  $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'promo_%'");
  while ($row = $stmt->fetch()) {
    // key format: promo_top_14
    $parts = explode('_', $row['setting_key']); // ['promo','top','14']
    if (count($parts) === 3 && isset($defaults[$parts[1]])) {
      $defaults[$parts[1]][$parts[2]] = (int)$row['setting_value'];
    }
  }
  return $defaults;
}

/**
 * Get price suffix for listing type
 */
function price_label(?string $type): string {
  if ($type === 'rental_gear' || $type === 'car_rental') return '₽ / сутки';
  if ($type === 'property') return '₽ / сутки';
  return '₽ / чел.';
}

/**
 * Render banners for a specific placement
 */
function render_banners(string $placement): void {
  try {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM banners WHERE placement = ? AND is_active = 1 ORDER BY sort_order, id");
    $stmt->execute([$placement]);
    $banners = $stmt->fetchAll();
    
    foreach ($banners as $b) {
      // Avito-style: inline ad card inside listing grid
      if ($b['placement'] === 'home_listings_inline') {
        if ($b['type'] === 'image') {
          $wrap = !empty($b['link']) ? '<a href="' . h($b['link']) . '" class="listing-card hover:-translate-y-0.5 hover:shadow-[0_8px_24px_-8px_rgba(0,0,0,0.12)] relative">' : '<div class="listing-card relative">';
          $close = !empty($b['link']) ? '</a>' : '</div>';
          echo $wrap;
          echo '<div class="listing-img"><img src="' . h($b['content']) . '" alt="' . h($b['title']) . '" loading="lazy"></div>';
          echo '<span class="absolute top-2 left-2 bg-amber-500 text-white text-[10px] px-1.5 py-0.5 rounded font-medium z-10">Реклама</span>';
          echo '<div class="listing-body"><div class="listing-title">' . h($b['title']) . '</div>';
          if (!empty($b['advertiser'])) echo '<div class="text-[10px] text-[#7A8A9A] mt-1">' . h($b['advertiser']) . '</div>';
          echo '</div>' . $close;
        } else {
          echo '<div class="listing-card">' . $b['content'] . '</div>';
        }
        continue;
      }

      $html = '';
      if ($b['type'] === 'image') {
        $img = '<img src="' . h($b['content']) . '" alt="' . h($b['title']) . '" class="h-[90px] object-cover rounded-lg mx-auto block" loading="lazy" style="width:100%;max-width:600px">';
        if (!empty($b['link'])) {
          $html = '<a href="' . h($b['link']) . '" class="block max-w-3xl mx-auto">' . $img . '</a>';
        } else {
          $html = '<div class="max-w-3xl mx-auto">' . $img . '</div>';
        }
      } else {
        $html = $b['content']; // raw HTML
      }
      
      echo '<div class="my-4 max-w-7xl mx-auto relative">' . $html;
      if (!empty($b['is_ad'])) {
        $adText = 'Реклама';
        if (!empty($b['advertiser'])) $adText .= '. ' . h($b['advertiser']);
        if (!empty($b['erid'])) $adText .= '. erid: ' . h($b['erid']);
        echo '<div class="text-[10px] text-[#7A8A9A] mt-1 text-center">' . $adText . '</div>';
      }
      echo '</div>';
    }
  } catch (Exception $e) {
    // Silently fail — banners shouldn't break the page
  }
}

/**
 * Breadcrumbs with JSON-LD
 */
function breadcrumbs($items) {
  $json = ['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[]];
  $pos = 1;
  echo '<nav aria-label="Хлебные крошки" class="text-xs text-[#7A8A9A] mb-4"><ol class="flex flex-wrap items-center gap-1">';
  $last = count($items); $i = 0;
  foreach ($items as $name => $url) {
    $i++;
    $json['itemListElement'][] = ['@type'=>'ListItem','position'=>$pos++,'name'=>$name,'item'=>$url];
    if ($i === $last) {
      echo '<li class="text-[#3A4A5C] font-medium">' . h($name) . '</li>';
    } else {
      echo '<li><a href="' . h($url) . '" class="hover:text-accent transition-colors">' . h($name) . '</a></li><li class="text-[#D5DCE5]">/</li>';
    }
  }
  echo '</ol></nav>';
  echo '<script type="application/ld+json">' . json_encode($json, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . '</script>';
}
