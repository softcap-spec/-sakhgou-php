<?php
// dashboard.php — v3 clean design
$user = auth_required();
$pdo = db();

$sub = $_GET['sub'] ?? 'listings';

// My listings
$st = $pdo->prepare("SELECT l.*, c.name AS category_name,
  (SELECT filename FROM listing_images WHERE listing_id = l.id ORDER BY sort_order LIMIT 1) AS image,
  promo.promo_type AS promo_type
  FROM listings l JOIN categories c ON l.category_id = c.id
  LEFT JOIN promotions promo ON l.id = promo.listing_id AND promo.status = 'active' AND promo.expires_at > NOW()
  WHERE l.user_id = ? ORDER BY l.created_at DESC");
$st->execute([$user['id']]);
$myListings = $st->fetchAll();

// Tabs
$tabs = [
  'listings'   => 'Мои объявления',
  'messages'   => 'Сообщения',
  'favorites'  => 'Избранное',
  'bookings'   => 'Мои брони',
  'host_bookings' => 'Ко мне',
  'calendar'   => 'Календарь',
  'profile'    => 'Профиль',
];
// Брони: мои (гость) и входящие (хозяин) — считаем один раз, используем и в табах, и во вьюхах
$myBookings = get_user_bookings($user['id']);
$hb = get_host_bookings($user['id']);
$pendingHostBookings = count(array_filter($hb, fn($x) => $x['status'] === 'pending'));

// POST: update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
  csrf_check();
  $name = trim($_POST['name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $email = trim($_POST['email'] ?? '');
  if (!empty($name)) {
    // Обычный юзер с уже привязанным телефоном: только имя и email; без телефона или админ — можно всё
    if ($user['role'] !== 'admin' && !empty($user['phone'])) {
      $pdo->prepare('UPDATE users SET name=?, email=? WHERE id=?')->execute([$name, $email, $user['id']]);
      $user['name'] = $name;
      $user['email'] = $email;
    } else {
      $pdo->prepare('UPDATE users SET name=?, phone=?, email=? WHERE id=?')->execute([$name, $phone, $email, $user['id']]);
      $user['name'] = $name;
      $user['phone'] = $phone;
      $user['email'] = $email;
    }
  }

  // Avatar upload
  if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $_FILES['avatar']['tmp_name']);
    finfo_close($finfo);
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (in_array($mime, $allowed)) {
      $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime];
      $fn = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
      if (move_uploaded_file($_FILES['avatar']['tmp_name'], UPLOAD_DIR . '/' . $fn)) {
        if ($user['avatar_url'] && !str_starts_with($user['avatar_url'], 'http')) {
          @unlink(UPLOAD_DIR . '/' . basename($user['avatar_url']));
        }
        $avatar_url = '/uploads/' . $fn;
        $pdo->prepare('UPDATE users SET avatar_url=? WHERE id=?')->execute([$avatar_url, $user['id']]);
        $user['avatar_url'] = $avatar_url;
      }
    }
  }

  header('Location: /dashboard?sub=profile&ok=1'); exit;
}

// POST: change password
$pw_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
  csrf_check();
  $result = auth_change_password($user['id'], $_POST['current_password'] ?? '', $_POST['new_password'] ?? '');
  if ($result['ok']) {
    header('Location: /dashboard?sub=profile&pwok=1'); exit;
  }
  $pw_error = $result['error'];
}

// POST: delete listing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
  csrf_check();
  $pdo->prepare('DELETE FROM listings WHERE id=? AND user_id=?')->execute([(int)$_POST['delete'], $user['id']]);
  header('Location: /dashboard'); exit;
}

// POST: отвязать уведомления «Макс»
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unbind_max'])) {
  csrf_check();
  $pdo->prepare('UPDATE users SET max_user_id = NULL WHERE id = ?')->execute([$user['id']]);
  $user['max_user_id'] = null;
  header('Location: /dashboard?sub=profile&maxok=1'); exit;
}

// Календарь: назад на тот же месяц после действий
$calBack = '/dashboard?sub=calendar&m=' . urlencode($_POST['m'] ?? date('Y-m'));

// POST: ручная запись в календарь (занять даты, клиент вне сайта)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_manual'])) {
  csrf_check();
  $lid = (int)($_POST['listing_id'] ?? 0);
  $df = $_POST['date_from'] ?? '';
  $dt = $_POST['date_to'] ?? '';
  $gname = trim($_POST['guest_name'] ?? '');
  $gphone = trim($_POST['guest_phone'] ?? '');
  $gcount = max(1, (int)($_POST['guests_count'] ?? 1));
  $price = (float)str_replace([',', ' '], ['.', ''], $_POST['total_price'] ?? '0');
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $df) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt)) { header('Location: ' . $calBack . '&cerr=1'); exit; }
  $st = $pdo->prepare('SELECT id, title FROM listings WHERE id = ? AND user_id = ?');
  $st->execute([$lid, $user['id']]);
  $lst = $st->fetch();
  if (!$lst || strtotime($dt) < strtotime($df)) { header('Location: ' . $calBack . '&cerr=1'); exit; }
  bookings_expire_pendings($lid);
  $ov = $pdo->prepare("SELECT id, status FROM bookings WHERE listing_id=? AND status IN ('pending','confirmed','blocked') AND check_in_date < ? AND check_out_date > ?");
  $ov->execute([$lid, $dt, $df]);
  $conflicts = $ov->fetchAll();
  foreach ($conflicts as $cv) {
    if (in_array($cv['status'], ['confirmed', 'blocked'], true)) { header('Location: ' . $calBack . '&cerr=2'); exit; }
  }
  foreach ($conflicts as $cv) {
    if ($cv['status'] === 'pending') $pdo->prepare("UPDATE bookings SET status='declined' WHERE id=?")->execute([$cv['id']]);
  }
  $pdo->prepare("INSERT INTO bookings (listing_id, guest_id, host_id, check_in_date, check_out_date, guests_count, status, total_price, guest_name, guest_phone, source, created_at) VALUES (?, NULL, ?, ?, ?, ?, 'blocked', ?, ?, ?, 'manual', NOW())")
    ->execute([$lid, $user['id'], $df, $dt, $gcount, $price, $gname !== '' ? $gname : 'Занято', $gphone !== '' ? $gphone : null]);
  header('Location: ' . $calBack . '&bok=1'); exit;
}

// POST: правка ручной записи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_manual'])) {
  csrf_check();
  $bid = (int)($_POST['bid'] ?? 0);
  $lid = (int)($_POST['listing_id'] ?? 0);
  $df = $_POST['date_from'] ?? '';
  $dt = $_POST['date_to'] ?? '';
  $gname = trim($_POST['guest_name'] ?? '');
  $gphone = trim($_POST['guest_phone'] ?? '');
  $gcount = max(1, (int)($_POST['guests_count'] ?? 1));
  $price = (float)str_replace([',', ' '], ['.', ''], $_POST['total_price'] ?? '0');
  $st = $pdo->prepare("SELECT id, listing_id FROM bookings WHERE id=? AND host_id=? AND source='manual' AND status='blocked'");
  $st->execute([$bid, $user['id']]);
  $bk = $st->fetch();
  if (!$bk || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $df) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt) || strtotime($dt) < strtotime($df)) { header('Location: ' . $calBack . '&cerr=1'); exit; }
  bookings_expire_pendings($bk['listing_id']);
  $ov = $pdo->prepare("SELECT id, status FROM bookings WHERE listing_id=? AND id!=? AND status IN ('pending','confirmed','blocked') AND check_in_date < ? AND check_out_date > ?");
  $ov->execute([$bk['listing_id'], $bid, $dt, $df]);
  $conflicts = $ov->fetchAll();
  foreach ($conflicts as $cv) {
    if (in_array($cv['status'], ['confirmed', 'blocked'], true)) { header('Location: ' . $calBack . '&cerr=2'); exit; }
  }
  foreach ($conflicts as $cv) {
    if ($cv['status'] === 'pending') $pdo->prepare("UPDATE bookings SET status='declined' WHERE id=?")->execute([$cv['id']]);
  }
  $pdo->prepare('UPDATE bookings SET check_in_date=?, check_out_date=?, guests_count=?, guest_name=?, guest_phone=?, total_price=? WHERE id=?')
    ->execute([$df, $dt, $gcount, $gname !== '' ? $gname : 'Занято', $gphone !== '' ? $gphone : null, $price, $bid]);
  header('Location: ' . $calBack . '&bok=1'); exit;
}

// POST: удалить ручную запись
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_manual'])) {
  csrf_check();
  $bid = (int)($_POST['bid'] ?? 0);
  $pdo->prepare("DELETE FROM bookings WHERE id=? AND host_id=? AND source='manual' AND status='blocked'")->execute([$bid, $user['id']]);
  header('Location: ' . $calBack . '&bok=1'); exit;
}

// POST: подтвердить / отклонить бронь (только хозяин объявления, только для pending)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_action'])) {
  csrf_check();
  $bid = (int)($_POST['bid'] ?? 0);
  $action = $_POST['booking_action'] === 'confirm' ? 'confirmed' : ($_POST['booking_action'] === 'decline' ? 'declined' : '');
  if ($bid > 0 && $action !== '') {
    $bq = $pdo->prepare('SELECT b.*, l.title AS listing_title FROM bookings b JOIN listings l ON b.listing_id = l.id WHERE b.id = ? AND b.host_id = ?');
    $bq->execute([$bid, $user['id']]);
    $bk = $bq->fetch();
    if ($bk && $bk['status'] === 'pending') {
      $pdo->prepare('UPDATE bookings SET status = ? WHERE id = ?')->execute([$action, $bid]);
      $statusText = $action === 'confirmed' ? 'подтверждена' : 'отклонена';
      // Уведомление гостю (колокольчик)
      $pdo->prepare('INSERT INTO notifications (user_id, type, text, link, is_read, created_at) VALUES (?,?,?,?,0,NOW())')
        ->execute([$bk['guest_id'], 'booking', 'Ваша бронь «' . $bk['listing_title'] . '» ' . $statusText, '/dashboard?sub=bookings']);
      // Дубль в чат — гость видит решение в «Сообщениях»
      send_message($user['id'], $bk['guest_id'], $bk['listing_id'], 'Бронь «' . $bk['listing_title'] . '» ' . $statusText . '.');
      // Уведомление в «Макс» — гостю (если привязан)
      try { max_notify_decision($bk['guest_id'], $bk['listing_title'], $statusText); } catch (\Throwable $e) {}
      header('Location: /dashboard?sub=host_bookings&bok=1'); exit;
    }
  }
  header('Location: /dashboard?sub=host_bookings'); exit;
}

$page_title = 'Личный кабинет — СахGO';
require __DIR__ . '/../includes/header.php';
?>

<main style="padding:3rem 0 4rem">
<div style="max-width:1200px;margin:0 auto;padding:0 1rem">

  <!-- Header -->
  <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:2rem">
    <div style="display:flex;align-items:center;gap:0.875rem">
      <?= avatar_html($user, 'w-10 h-10', 'text-base') ?>
      <div>
        <span style="font-size:0.6875rem;text-transform:uppercase;letter-spacing:0.1em;color:#5A6B7D;font-weight:500">Личный кабинет</span>
        <h1 style="font-family:Manrope,sans-serif;font-weight:700;font-size:2rem;letter-spacing:-0.02em;margin:0;line-height:1.2"><?=h($user['name'])?></h1>
      </div>
    </div>
    <a href="/create" class="cta-btn" style="padding:0.625rem 1.25rem;gap:0.375rem">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
      Новое объявление
    </a>
  </div>

  <!-- Tabs -->
  <div style="display:flex;gap:0.25rem;margin-bottom:2rem;flex-wrap:wrap;border-bottom:1px solid #EEF2F6;padding-bottom:0.875rem">
    <?php foreach ($tabs as $k => $v): $active = ($sub === $k); ?>
    <a href="/dashboard<?=$k==='listings'?'':'?sub='.$k?>"
       style="display:inline-flex;align-items:center;padding:0.5rem 1rem;font-size:0.8125rem;font-weight:500;border-radius:8px;text-decoration:none;transition:all 0.15s ease;<?=$active?'background:#0A1A2A;color:#F7F9FB':'color:#5A6B7D;background:transparent'?>"
       onmouseover="if(!this.classList.contains('active')){this.style.background='#EEF2F6';this.style.color='#0A1A2A'}"
       onmouseout="if(!this.classList.contains('active')){this.style.background='transparent';this.style.color='#5A6B7D'}"
       class="<?=$active?'active':''?>"><?=$v?><?php if ($k==='host_bookings' && $pendingHostBookings > 0): ?> <span style="background:#DC2626;color:#fff;border-radius:999px;padding:0.0625rem 0.4375rem;font-size:0.6875rem;font-weight:700;margin-left:0.25rem"><?=$pendingHostBookings?></span><?php endif; ?></a>
    <?php endforeach; ?>
  </div>

  <?php if ($sub === 'listings'): ?>
    <?php if (empty($myListings)): ?>
      <div style="text-align:center;padding:5rem 1rem">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#C8D0DA" stroke-width="1.5" style="margin-bottom:1.25rem">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
        </svg>
        <p style="font-size:1rem;font-weight:600;color:#0A1A2A;margin:0 0 0.25rem">У вас пока нет объявлений</p>
        <p style="font-size:0.8125rem;color:#5A6B7D;margin:0 0 1.5rem">Создайте первое объявление и начните зарабатывать</p>
        <a href="/create" class="cta-btn" style="gap:0.375rem">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
          Создать объявление
        </a>
      </div>
    <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.25rem">
        <?php foreach ($myListings as $item): ?>
        <div class="listing-card">
          <div class="listing-img">
            <?php if (!empty($item['image'])): ?>
            <img src="/uploads/<?=h($item['image'])?>" alt="" loading="lazy">
            <?php else: ?>
            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#C8D0DA">
              <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            </div>
            <?php endif; ?>
          </div>
          <?php if (!empty($item['promo_type'])): ?>
          <span class="promo-badge" style="position:absolute;top:0.625rem;left:0.625rem;<?=$item['promo_type']==='top'?'background:#0A7BBA':($item['promo_type']==='highlight'?'background:#D97706':'background:#DC2626')?>">
            <?=$item['promo_type']==='top'?'TOP':($item['promo_type']==='highlight'?'PROMO':'Срочно')?>
          </span>
          <?php endif; ?>
          <div class="listing-body" style="gap:0.5rem">
            <div style="display:flex;align-items:center;gap:0.5rem;font-size:0.6875rem">
              <span class="badge" style="<?=$item['status']==='active'?'color:#166534;border-color:#BBF7D0;background:#F0FDF4':''?>"><?=$item['status']==='active'?'Активно':$item['status']?></span>
              <span style="color:#5A6B7D"><?=h($item['category_name'])?></span>
            </div>
            <div class="listing-price"><?=price_text($item)?><?php if (!price_is_negotiable($item) && (float)$item['price'] > 0): ?> <?=price_label($item['listing_type'])?><?php endif; ?></div>
            <div class="listing-title"><?=h($item['title'])?></div>
            <div class="listing-meta">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <span><?=$item['view_count']??0?></span>
              <span style="margin-left:0.25rem"><?=time_ago($item['created_at'])?></span>
            </div>
            <div style="display:flex;gap:0.375rem">
              <a href="/listing/<?=$item['id']?>" style="flex:1;text-align:center;display:inline-flex;align-items:center;justify-content:center;gap:0.25rem;border-radius:8px;border:1px solid #DFE4EA;padding:0.375rem 0.5rem;font-size:0.75rem;font-weight:500;color:#3A4A5C;text-decoration:none;transition:all 0.15s ease" onmouseover="this.style.background='#F7F9FB';this.style.borderColor='#C8D0DA'" onmouseout="this.style.background='';this.style.borderColor='#DFE4EA'">Смотреть</a>
              <a href="/edit/<?=$item['id']?>" style="flex:1;text-align:center;display:inline-flex;align-items:center;justify-content:center;gap:0.25rem;border-radius:8px;border:1px solid #DFE4EA;padding:0.375rem 0.5rem;font-size:0.75rem;font-weight:500;color:#3A4A5C;text-decoration:none;transition:all 0.15s ease" onmouseover="this.style.background='#F7F9FB';this.style.borderColor='#C8D0DA'" onmouseout="this.style.background='';this.style.borderColor='#DFE4EA'">Ред.</a>
              <a href="/promote?id=<?=$item['id']?>" style="flex:1;text-align:center;display:inline-flex;align-items:center;justify-content:center;gap:0.25rem;border-radius:8px;border:1px solid #FDE68A;padding:0.375rem 0.5rem;font-size:0.75rem;font-weight:500;color:#92400E;text-decoration:none;transition:all 0.15s ease" onmouseover="this.style.background='#FFFBEB';this.style.borderColor='#FCD34D'" onmouseout="this.style.background='';this.style.borderColor='#FDE68A'">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
              </a>
              <form method="post" onsubmit="return confirm('Удалить объявление?')" style="margin:0">
                <?= csrf_field() ?>
                <button name="delete" value="<?=$item['id']?>" style="display:inline-flex;align-items:center;justify-content:center;width:2rem;border-radius:8px;border:1px solid #FECACA;background:transparent;color:#DC2626;cursor:pointer;padding:0;height:100%;transition:all 0.15s ease" onmouseover="this.style.background='#FEF2F2'" onmouseout="this.style.background='transparent'">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php elseif ($sub === 'messages'): ?>
    <?php
    $tid = $user['id'];
    $threadStmt = $pdo->prepare("
      SELECT t.lid, t.other_id, u.name AS other_name, u.avatar_url AS other_avatar,
        l.title AS listing_title, t.last_text, t.last_at,
        (SELECT COUNT(*) FROM messages m2 WHERE m2.listing_id = t.lid AND m2.sender_id = t.other_id AND m2.receiver_id = ? AND m2.is_read = 0) AS unread
      FROM (
        SELECT m.listing_id AS lid,
          IF(m.sender_id = ?, m.receiver_id, m.sender_id) AS other_id,
          (SELECT m1.text FROM messages m1 WHERE m1.listing_id = m.listing_id AND IF(m.sender_id = ?, m1.receiver_id, m1.sender_id) = IF(m.sender_id = ?, m.receiver_id, m.sender_id) ORDER BY m1.created_at DESC LIMIT 1) AS last_text,
          MAX(m.created_at) AS last_at
        FROM messages m
        WHERE m.sender_id = ? OR m.receiver_id = ?
        GROUP BY m.listing_id, IF(m.sender_id = ?, m.receiver_id, m.sender_id)
        ORDER BY last_at DESC
      ) t
      JOIN users u ON t.other_id = u.id
      JOIN listings l ON t.lid = l.id
    ");
    $threadStmt->execute([$tid, $tid, $tid, $tid, $tid, $tid, $tid]);
    $threads = $threadStmt->fetchAll();
    $myId = $tid;
    ?>
    <style>
    .dm-layout{display:flex;gap:0;border:1px solid #EEF2F6;border-radius:12px;overflow:hidden;background:#fff;box-shadow:0 4px 16px rgba(15,23,32,0.06);min-height:28rem}
    .dm-threads{width:340px;flex-shrink:0;border-right:1px solid #EEF2F6;display:flex;flex-direction:column;overflow:hidden;background:#F7F9FB}
    .dm-threads-hd{padding:.75rem 1rem;font-weight:700;font-size:.9375rem;color:#0A1A2A;border-bottom:1px solid #EEF2F6}
    .dm-threads-list{flex:1;overflow-y:auto}
    .dm-thread{padding:.75rem 1rem;display:flex;align-items:center;gap:.625rem;cursor:pointer;border-bottom:1px solid #EEF2F6;transition:background .1s}
    .dm-thread:hover{background:#fff}
    .dm-thread.sel{background:#E8F4FB;border-left:3px solid #0A7BBA}
    .dm-thread-av{width:42px;height:42px;border-radius:50%;overflow:hidden;flex-shrink:0;background:#DFE4EA;display:flex;align-items:center;justify-content:center;font-weight:700;color:#5A6B7D}
    .dm-thread-av img{width:100%;height:100%;object-fit:cover}
    .dm-thread-info{flex:1;min-width:0}
    .dm-thread-top{display:flex;justify-content:space-between;align-items:center;gap:.375rem}
    .dm-thread-name{font-weight:600;font-size:.8125rem;color:#0A1A2A;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .dm-thread-time{font-size:.6875rem;color:#6B7B8D;white-space:nowrap}
    .dm-thread-listing{font-size:.6875rem;color:#5A6B7D;margin-top:1px}
    .dm-thread-preview{display:flex;justify-content:space-between;align-items:center;gap:.375rem;margin-top:2px}
    .dm-thread-txt{font-size:.75rem;color:#3A4A5C;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1}
    .dm-thread-badge{background:#F59E0B;color:#fff;font-size:.625rem;font-weight:700;border-radius:999px;min-width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;padding:0 5px;flex-shrink:0}
    .dm-chat{flex:1;display:flex;flex-direction:column;min-width:0}
    .dm-chat-empty{flex:1;display:flex;align-items:center;justify-content:center;color:#6B7B8D;font-size:.875rem;text-align:center;padding:2rem}
    .dm-msg-actions{display:none;position:absolute;top:-8px;right:-8px;z-index:2}
    .dm-msg-col:hover .dm-msg-actions{display:block}
    .dm-msg-del{width:20px;height:20px;border:0;border-radius:50%;background:#DC2626;color:#fff;font-size:14px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 4px rgba(0,0,0,0.15);transition:all .15s}
    .dm-msg-del:hover{background:#EEF2F6;color:#0A1A2A}
    .dm-act-menu{position:absolute;top:28px;right:-4px;background:#fff;border:1px solid #D1DAE3;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,0.1);z-index:10;display:none;min-width:140px;overflow:hidden}
    .dm-act-menu.open{display:block}
    .dm-act-item{display:block;width:100%;padding:.5rem .75rem;font-size:.8125rem;color:#DC2626;background:none;border:0;cursor:pointer;text-align:left}
    .dm-act-item:hover{background:#FEF2F2}
    .dm-msg-status{font-size:.625rem;color:#6B7B8D;margin-top:.125rem;padding:0 .25rem}
    .dm-msg-status.read{color:#00B04C}
    .dm-chat-hd{display:flex;align-items:center;gap:.625rem;padding:.75rem 1rem;border-bottom:1px solid #EEF2F6;background:#fff;flex-shrink:0}
    .dm-chat-hd-av{width:36px;height:36px;border-radius:50%;overflow:hidden;flex-shrink:0;background:#DFE4EA;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.875rem;color:#5A6B7D}
    .dm-chat-hd-av img{width:100%;height:100%;object-fit:cover}
    .dm-chat-hd-info{flex:1;min-width:0}
    .dm-chat-hd-name{font-weight:600;font-size:.875rem;color:#0A1A2A}
    .dm-chat-hd-listing{font-size:.6875rem;color:#5A6B7D}
    .dm-chat-hd-on{font-size:.6875rem;color:#16A34A;display:none}
    .dm-chat-hd-on.show{display:block}
    .dm-chat-msgs{flex:1;overflow-y:auto;padding:.75rem 1rem;display:flex;flex-direction:column;gap:.125rem;background:#fff}
    .dm-msg-row{display:flex;max-width:80%;position:relative;gap:.5rem;align-items:flex-end}
    .dm-msg-row.out{position:relative;align-self:flex-end;flex-direction:row-reverse}
    .dm-msg-row.in{position:relative;align-self:flex-start}
    .dm-msg-row.continues{margin-top:0}
    .dm-msg-col{display:flex;flex-direction:column;min-width:0}
    .dm-msg-row.out .dm-msg-col{align-items:flex-end}
    .dm-msg-row.in .dm-msg-col{align-items:flex-start}
    .dm-msg-bubble{padding:.5rem .875rem;border-radius:16px;font-size:.875rem;line-height:1.4;word-wrap:break-word;position:relative;max-width:100%;transition:box-shadow .15s}
    .dm-msg-row.out .dm-msg-bubble{background:#EAF6FF;color:#0A1A2A;border-bottom-right-radius:5px;box-shadow:0 1px 2px rgba(10,123,186,0.08)}
    .dm-msg-row.in .dm-msg-bubble{background:#F4F6F8;color:#0A1A2A;border-bottom-left-radius:5px;box-shadow:0 1px 2px rgba(10,26,42,0.04)}
    .dm-msg-row.continues.out .dm-msg-bubble{border-bottom-right-radius:16px}
    .dm-msg-row.continues.in .dm-msg-bubble{border-bottom-left-radius:16px}
    .dm-msg-meta{display:flex;align-items:center;gap:.3125rem;margin-top:.1875rem;font-size:.6875rem;color:#B8C2CC;padding:0 .25rem;white-space:nowrap}
    .dm-msg-meta-sep{color:#D1DAE3;font-size:.625rem}
    .dm-msg-status{font-size:.6875rem;color:#B8C2CC;line-height:1;white-space:nowrap}
    .dm-msg-status.read{color:#00B04C}
    .dm-msg-avatar{width:1.75rem;height:1.75rem;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.6875rem;font-weight:700;overflow:hidden;flex-shrink:0;align-self:flex-end;letter-spacing:-.02em}
    .dm-msg-avatar.c0{background:#E3F2FD;color:#1565C0}
    .dm-msg-avatar.c1{background:#F3E5F5;color:#7B1FA2}
    .dm-msg-avatar.c2{background:#E8F5E9;color:#2E7D32}
    .dm-msg-avatar.c3{background:#FFF3E0;color:#E65100}
    .dm-msg-avatar.c4{background:#E0F7FA;color:#00838F}
    .dm-msg-avatar.c5{background:#FCE4EC;color:#C62828}
    .dm-msg-avatar img{width:100%;height:100%;object-fit:cover}
    .dm-msg-row.continues .dm-msg-avatar{visibility:hidden}
    .dm-date-sep{text-align:center;font-size:.6875rem;color:#9AA5B1;margin:.75rem 0 .5rem;padding:.25rem .75rem;background:#F0F3F7;border-radius:100px;align-self:center;font-weight:500}
    .dm-typing{padding:.25rem .875rem;font-size:.75rem;color:#5A6B7D;display:none;font-style:italic;flex-shrink:0}
    .dm-typing.show{display:block}
    .dm-input-row{border-top:1px solid #EEF2F6;padding:.5rem .75rem;display:flex;gap:.375rem;align-items:center;background:#fff;flex-shrink:0}
    .dm-input-wrap{flex:1}
    .dm-input{width:100%;border:1px solid #DFE4EA;border-radius:22px;padding:.5rem 1rem;font-size:.875rem;outline:none;transition:border-color .15s;background:#F7F9FB;box-sizing:border-box}
    .dm-input:focus{border-color:#0A7BBA;background:#fff}
    .dm-send{width:2.5rem;height:2.5rem;border:0;border-radius:50%;background:#0A7BBA;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0}
    .dm-send:hover{background:#14566E}
    .dm-back{display:none;border:0;background:none;color:#5A6B7D;cursor:pointer;padding:.25rem;flex-shrink:0}
    @media(max-width:700px){.dm-layout{flex-direction:column;min-height:auto}
      .dm-threads{width:100%;border-right:0;max-height:50vh}.dm-back{display:block}
      .dm-chat:not(.show){display:none}.dm-chat.show{display:flex;position:fixed;top:0;left:0;right:0;bottom:0;z-index:100;background:#fff}}
    </style>

    <?php if (empty($threads)): ?>
      <div style="text-align:center;padding:5rem 1rem">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#C8D0DA" stroke-width="1.5" style="margin-bottom:1.25rem">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        <p style="font-size:1rem;font-weight:600;color:#0A1A2A;margin:0 0 0.25rem">Нет сообщений</p>
        <p style="font-size:0.8125rem;color:#5A6B7D;margin:0 0 1.5rem">Здесь появятся ваши переписки по объявлениям</p>
        <a href="/catalog" class="btn-outline">Смотреть объявления</a>
      </div>
    <?php else: ?>
    <div class="dm-layout" id="dmLayout">
      <!-- Thread list -->
      <div class="dm-threads" id="dmThreads">
        <div class="dm-threads-hd">Сообщения</div>
        <div class="dm-threads-list">
          <?php foreach ($threads as $th):
            $lid = (int)$th['lid']; $oid = (int)$th['other_id'];
            $oname = h($th['other_name']); $oav = $th['other_avatar'];
            $lname = h($th['listing_title']);
            $ltext = h(mb_strlen($th['last_text']??'') > 60 ? mb_substr($th['last_text']??'', 0, 60) . '…' : ($th['last_text'] ?? ''));
            $unr = (int)$th['unread'];
            $avhtml = $oav ? '<img src="'.h($oav).'" alt="">' : mb_substr($oname, 0, 1);
          ?>
          <div class="dm-thread" data-lid="<?=$lid?>" data-uid="<?=$oid?>" data-name="<?=h($th['other_name'])?>" data-avatar="<?=h($oav)?>" data-listing="<?=h($th['listing_title'])?>" onclick="dmOpen(this)">
            <div class="dm-thread-av"><?=$avhtml?></div>
            <div class="dm-thread-info">
              <div class="dm-thread-top">
                <span class="dm-thread-name"><?=$oname?></span>
                <span class="dm-thread-time"><?=time_ago($th['last_at'])?></span>
              </div>
              <div class="dm-thread-listing"><?=$lname?></div>
              <div class="dm-thread-preview">
                <span class="dm-thread-txt"><?=$ltext?></span>
                <?php if ($unr > 0): ?><span class="dm-thread-badge" id="dmBadge_<?=$lid?>_<?=$oid?>"><?=$unr?></span><?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <!-- Chat pane -->
      <div class="dm-chat" id="dmChat">
        <div class="dm-chat-empty">Выберите диалог</div>
      </div>
    </div>

    <script>
    var dmMyId=<?=$myId?>;
    var dmCsrf=<?=json_encode(csrf_token())?>;
    var dmCurLid=0,dmCurUid=0,dmPoll=null,dmTypingTimer=null;
    var dmCurName='',dmCurAvatar='',dmCurListing='';

    function dmOpen(el){
      dmCurLid=parseInt(el.dataset.lid);
      dmCurUid=parseInt(el.dataset.uid);
      dmCurName=el.dataset.name;
      dmCurAvatar=el.dataset.avatar;
      dmCurListing=el.dataset.listing;
      // Highlight thread
      document.querySelectorAll('.dm-thread.sel').forEach(function(t){t.classList.remove('sel')});
      el.classList.add('sel');
      // Build chat pane
      var av=dmCurAvatar?'<img src="'+dmCurAvatar+'" alt="">':dmCurName.charAt(0);
      var h='<div class="dm-chat-hd">';
      h+='<button class="dm-back" onclick="dmBack()"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg></button>';
      h+='<div class="dm-chat-hd-av">'+av+'</div>';
      h+='<div class="dm-chat-hd-info"><div class="dm-chat-hd-name">'+dmCurName+'</div><div class="dm-chat-hd-listing">'+dmCurListing+'</div></div>';
      h+='<div class="dm-chat-hd-on" id="dmOnline">В сети</div>';
      h+='</div><div class="dm-chat-msgs" id="dmMsgs"></div>';
      h+='<div class="dm-typing" id="dmTyping">печатает...</div>';
      h+='<div class="dm-input-row"><div class="dm-input-wrap"><input class="dm-input" id="dmInput" placeholder="Сообщение" onkeydown="dmSendKey(event)" oninput="dmType()"></div>';
      h+='<button class="dm-send" onclick="dmSend()"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg></button></div>';
      document.getElementById('dmChat').innerHTML=h;
      document.getElementById('dmChat').classList.add('show');
      // Clear badge
      var b=document.getElementById('dmBadge_'+dmCurLid+'_'+dmCurUid);if(b)b.remove();
      // Load messages
      dmLoadMsgs();
      // Poll
      if(dmPoll)clearInterval(dmPoll);
      dmPoll=setInterval(dmPollMsgs,5000);
    }

    function dmBack(){
      document.getElementById('dmChat').classList.remove('show');
      document.querySelectorAll('.dm-thread.sel').forEach(function(t){t.classList.remove('sel')});
      dmCurLid=0;dmCurUid=0;
      if(dmPoll){clearInterval(dmPoll);dmPoll=null}
    }

    function dmLoadMsgs(){
      if(!dmCurLid)return;
      fetch('/api/messages?lid='+dmCurLid+'&uid='+dmCurUid).then(function(r){return r.json()}).then(function(d){
        var c=document.getElementById('dmMsgs');
        var h='';var prevDate='',lastSender=0,lastDir='';
        (d.messages||[]).forEach(function(m){
          var mine=m.sender_id==dmMyId;
          var dt=m.created_at.split(' ')[0];
          var dtParts=dt.split('-');
          var dtDate=new Date(dtParts[0],dtParts[1]-1,dtParts[2]);
          var dtStr=dtDate.toLocaleDateString('ru-RU',{weekday:'long',day:'numeric',month:'long'});
          if(dt!=prevDate){h+='<div class="dm-date-sep">'+dtStr+'</div>';prevDate=dt;lastSender=0}
          var isDeleted=(m.is_deleted==1||m.is_deleted==='1'||parseInt(m.is_deleted)===1);
          var continues=(m.sender_id==lastSender && lastDir===(mine?'out':'in'));
          lastSender=m.sender_id;lastDir=mine?'out':'in';
          h+='<div class="dm-msg-row '+(mine?'out':'in')+(continues?' continues':'')+'">';
          if(isDeleted){h+='<div style="padding:.5rem .875rem;border-radius:16px;font-size:.8125rem;color:#9AA5B1;font-style:italic;background:#F4F6F8;border-bottom-left-radius:5px;max-width:60%">Сообщение удалено</div></div>';return}
          if(!mine){var ch=0;for(var k=0;k<dmCurName.length;k++)ch=(ch*31+dmCurName.charCodeAt(k))>>>0;h+='<div class="dm-msg-avatar c'+(ch%6)+'">';if(dmCurAvatar){h+='<img src="'+dmEsc(dmCurAvatar)+'" alt="">'}else{h+=dmEsc(dmCurName.substring(0,2))}h+='</div>'}
          h+='<div class="dm-msg-col">';
          if(mine) h+='<div class="dm-msg-actions"><button class="dm-msg-del" onclick="dmToggleAct(event,'+m.id+')" title="Действия">&#8943;</button><div class="dm-act-menu" id="dmAct'+m.id+'"><button class="dm-act-item" onclick="dmDelete('+m.id+')">Удалить</button></div></div>';
          h+='<div class="dm-msg-bubble">'+dmEsc(m.text)+'</div>';
          h+='<div class="dm-msg-meta"><span>'+m.created_at.split(' ')[1].substring(0,5)+'</span>';
          if(mine){h+='<span class="dm-msg-meta-sep">·</span><span class="dm-msg-status '+(m.is_read?'read':'')+'">'+(m.is_read?'Прочитано':'Доставлено')+'</span>'}
          h+='</div></div></div>';
        });
        c.innerHTML=h||'<div class="dm-chat-empty">Нет сообщений</div>';
        c.scrollTop=c.scrollHeight;
        // Online status
        if(d.other&&d.other.last_seen){
          var ls=new Date(d.other.last_seen.replace(' ','T')+'Z');
          var ago=(Date.now()-ls.getTime())/1000;
          var on=document.getElementById('dmOnline');
          if(on){on.textContent=ago<300?'В сети':'Был(а) '+dmTimeAgo(d.other.last_seen);on.classList.add('show')}
        }
        // Typing
        var ty=document.getElementById('dmTyping');
        if(d.typing){ty.textContent=dmCurName+' печатает...';ty.classList.add('show')}else{ty.classList.remove('show')}
      });
    }

    function dmPollMsgs(){if(dmCurLid)dmLoadMsgs()}

    function dmSend(){
      var inp=document.getElementById('dmInput');
      var txt=inp.value.trim();
      if(!txt||!dmCurLid||!dmCurUid)return;
      inp.value='';
      fetch('/api/send',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'lid='+dmCurLid+'&uid='+dmCurUid+'&text='+encodeURIComponent(txt)+'&_csrf='+encodeURIComponent(dmCsrf)}).then(function(r){return r.json()}).then(function(d){if(d.ok)dmLoadMsgs()});
    }

    function dmSendKey(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();dmSend()}}

    function dmToggleAct(e,mid){e.stopPropagation();var m=document.getElementById('dmAct'+mid);if(!m)return;var isOpen=m.classList.contains('open');document.querySelectorAll('.dm-act-menu.open').forEach(function(x){x.classList.remove('open')});if(!isOpen)m.classList.add('open')}

    function dmDelete(mid){
      document.querySelectorAll('.dm-act-menu.open').forEach(function(x){x.classList.remove('open')});
      fetch('/api/delete',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'mid='+mid+'&_csrf='+encodeURIComponent(dmCsrf)}).then(function(r){return r.json()}).then(function(d){if(d.ok)dmLoadMsgs()});
    }

    function dmType(){
      if(!dmCurLid)return;
      if(dmTypingTimer)clearTimeout(dmTypingTimer);
      fetch('/api/typing',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'lid='+dmCurLid+'&_csrf='+encodeURIComponent(dmCsrf)});
      dmTypingTimer=setTimeout(function(){},3000);
    }

    function dmEsc(s){return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}

    function dmTimeAgo(ts){
      var s=(Date.now()-new Date(ts.replace(' ','T')+'Z').getTime())/1000;
      if(s<60)return 'только что';
      if(s<3600)return Math.floor(s/60)+' мин назад';
      if(s<86400)return Math.floor(s/3600)+' ч назад';
      return Math.floor(s/86400)+' дн назад';
    }
    </script>
    <?php endif; ?>

  <?php elseif ($sub === 'favorites'): ?>
    <?php $favs = get_user_favorites($user['id']); ?>
    <?php if (empty($favs)): ?>
      <div style="text-align:center;padding:5rem 1rem">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#C8D0DA" stroke-width="1.5" style="margin-bottom:1.25rem">
          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
        </svg>
        <p style="font-size:1rem;font-weight:600;color:#0A1A2A;margin:0 0 0.25rem">Нет избранных объявлений</p>
        <p style="font-size:0.8125rem;color:#5A6B7D;margin:0 0 1.5rem">Добавляйте объявления в избранное кликом по сердечку</p>
        <a href="/catalog" class="btn-outline">Перейти в каталог</a>
      </div>
    <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.25rem">
        <?php foreach ($favs as $item): ?>
        <a href="/listing/<?=$item['id']?>" class="listing-card">
          <div class="listing-img">
            <?php if(!empty($item['image'])):?>
            <img src="/uploads/<?=h($item['image'])?>" loading="lazy">
            <?php else:?>
            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#C8D0DA">
              <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            </div>
            <?php endif;?>
          </div>
          <div class="listing-body">
            <div class="listing-price"><?=price_text($item)?><?php if (!price_is_negotiable($item) && (float)$item['price'] > 0): ?> <?=price_label($item['listing_type'])?><?php endif; ?></div>
            <div class="listing-title"><?=h($item['title'])?></div>
            <div class="listing-meta"><span><?=h($item['category_name']??'')?></span></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php elseif ($sub === 'bookings'): ?>
    <?php $bookings = $myBookings; ?>
    <?php if (empty($bookings)): ?>
      <div style="text-align:center;padding:5rem 1rem">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#C8D0DA" stroke-width="1.5" style="margin-bottom:1.25rem">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
        <p style="font-size:1rem;font-weight:600;color:#0A1A2A;margin:0 0 0.25rem">Нет бронирований</p>
        <p style="font-size:0.8125rem;color:#5A6B7D;margin:0">Когда вы забронируете жильё или тур, заявка появится здесь</p>
      </div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:0.75rem">
      <?php foreach ($bookings as $b):
        $bs = $b['status'];
        $badge = $bs === 'confirmed' ? 'background:#E8F5E9;color:#2E7D32' : ($bs === 'declined' ? 'background:#FDECEC;color:#C62828' : ($bs === 'blocked' ? 'background:#EEF2F6;color:#54677A' : 'background:#FFF8E1;color:#B26A00'));
        $blabel = $bs === 'confirmed' ? 'Подтверждена' : ($bs === 'declined' ? 'Отклонена' : ($bs === 'blocked' ? 'Занято вручную' : 'Ожидает подтверждения'));
      ?>
        <div style="background:#fff;border:1px solid #EEF2F6;border-radius:12px;padding:1.25rem;box-shadow:0 4px 12px rgba(15,23,32,0.06)">
          <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
              <a href="/listing/<?=$b['listing_id']?>" style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.0625rem;color:#0A1A2A;text-decoration:none"><?=h($b['listing_title'])?></a>
              <div style="font-size:0.8125rem;color:#5A6B7D;margin-top:0.25rem"><?=h($b['location']??'')?> &middot; хозяин: <?=h($b['host_name'])?></div>
              <?php if (!empty($b['check_in_date'])): ?>
              <div style="font-size:0.8125rem;color:#5A6B7D;margin-top:0.25rem"><?=date('d.m.Y', strtotime($b['check_in_date']))?> — <?=date('d.m.Y', strtotime($b['check_out_date']))?> · <?=(int)$b['guests_count']?> гост.</div>
              <?php endif; ?>
              <?php if ($bs === 'confirmed' && !empty($b['host_phone'])): ?>
              <div style="font-size:0.8125rem;color:#2E7D32;margin-top:0.25rem">Телефон хозяина: <a href="tel:<?=h(phone_display($b['host_phone']))?>" style="color:#2E7D32;font-weight:600"><?=h(phone_display($b['host_phone']))?></a></div>
              <?php endif; ?>
            </div>
            <div style="text-align:right">
              <span style="font-size:0.6875rem;font-weight:600;padding:0.1875rem 0.5rem;border-radius:999px;<?=$badge?>"><?=$blabel?></span>
              <div style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.0625rem;margin-top:0.375rem"><?=number_format((float)$b['total_price'],0,'.',' ')?> ₽</div>
              <div style="font-size:0.6875rem;color:#5A6B7D;margin-top:0.25rem"><?=$b['created_at']?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php elseif ($sub === 'host_bookings'): ?>
    <?php if (isset($_GET['bok'])): ?>
    <div class="flash success">Статус брони обновлён</div>
    <?php endif; ?>
    <?php if (empty($hb)): ?>
      <div style="text-align:center;padding:5rem 1rem">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#C8D0DA" stroke-width="1.5" style="margin-bottom:1.25rem">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
        <p style="font-size:1rem;font-weight:600;color:#0A1A2A;margin:0 0 0.25rem">Нет бронирований</p>
        <p style="font-size:0.8125rem;color:#5A6B7D;margin:0">Когда гость забронирует ваше объявление, заявка появится здесь</p>
      </div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:0.75rem">
      <?php foreach ($hb as $b):
        $bs = $b['status'];
        $badge = $bs === 'confirmed' ? 'background:#E8F5E9;color:#2E7D32' : ($bs === 'declined' ? 'background:#FDECEC;color:#C62828' : ($bs === 'blocked' ? 'background:#EEF2F6;color:#54677A' : 'background:#FFF8E1;color:#B26A00'));
        $blabel = $bs === 'confirmed' ? 'Подтверждена' : ($bs === 'declined' ? 'Отклонена' : ($bs === 'blocked' ? 'Занято вручную' : 'Ожидает подтверждения'));
      ?>
        <div style="background:#fff;border:1px solid #EEF2F6;border-radius:12px;padding:1.25rem;box-shadow:0 4px 12px rgba(15,23,32,0.06)">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem">
            <div style="min-width:0">
              <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap">
                <span style="font-size:0.8125rem;color:#5A6B7D">Гость: <?=h($b['guest_name'])?></span>
                <span style="font-size:0.6875rem;font-weight:600;padding:0.1875rem 0.5rem;border-radius:999px;<?=$badge?>"><?=$blabel?></span>
              </div>
              <a href="/listing/<?=$b['listing_id']?>" style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.0625rem;color:#0A1A2A;text-decoration:none"><?=h($b['listing_title'])?></a>
              <?php if (!empty($b['check_in_date'])): ?>
              <div style="font-size:0.8125rem;color:#5A6B7D;margin-top:0.25rem"><?=date('d.m.Y', strtotime($b['check_in_date']))?> — <?=date('d.m.Y', strtotime($b['check_out_date']))?> · <?=(int)$b['guests_count']?> гост.</div>
              <?php endif; ?>
              <?php if (!empty($b['guest_message'])): ?>
              <div style="font-size:0.8125rem;color:#5A6B7D;margin-top:0.5rem;background:#F7F9FB;border-radius:8px;padding:0.5rem 0.75rem">«<?=h($b['guest_message'])?>»</div>
              <?php endif; ?>
            </div>
            <div style="text-align:right;flex-shrink:0">
              <div style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.0625rem"><?=number_format((float)$b['total_price'],0,'.',' ')?> ₽</div>
              <div style="font-size:0.6875rem;color:#5A6B7D;margin-top:0.25rem"><?=$b['created_at']?></div>
              <?php if ($bs === 'confirmed' && !empty($b['guest_phone'])): ?>
              <div style="font-size:0.8125rem;color:#2E7D32;margin-top:0.5rem">Телефон гостя: <a href="tel:<?=h(phone_display($b['guest_phone']))?>" style="color:#2E7D32;font-weight:600"><?=h(phone_display($b['guest_phone']))?></a></div>
              <?php endif; ?>
              <div style="display:flex;gap:0.375rem;margin-top:0.625rem;justify-content:flex-end;flex-wrap:wrap">
                <button type="button" data-open-chat onclick="openChatThread(<?=(int)$b['listing_id']?>,<?=(int)$b['guest_id']?>,<?=h(json_encode($b['guest_name']))?>,<?=h(json_encode($b['listing_title']))?>,<?=h(json_encode($b['guest_avatar'] ?? ''))?>,<?=(float)$b['price']?>,<?=h(json_encode($b['listing_type'] ?? ''))?>)" style="background:#0A7BBA;color:#fff;border:0;border-radius:8px;padding:0.4375rem 0.875rem;font-size:0.75rem;font-weight:600;cursor:pointer">Открыть чат</button>
                <?php if ($bs === 'pending'): ?>
                <form method="post" style="display:inline"><?= csrf_field() ?>
                  <input type="hidden" name="bid" value="<?=$b['id']?>">
                  <button type="submit" name="booking_action" value="confirm" style="background:#16A34A;color:#fff;border:0;border-radius:8px;padding:0.4375rem 0.875rem;font-size:0.75rem;font-weight:600;cursor:pointer">Подтвердить</button>
                </form>
                <form method="post" style="display:inline"><?= csrf_field() ?>
                  <input type="hidden" name="bid" value="<?=$b['id']?>">
                  <button type="submit" name="booking_action" value="decline" style="background:#fff;color:#DC2626;border:1px solid #F3C1C1;border-radius:8px;padding:0.4375rem 0.875rem;font-size:0.75rem;font-weight:600;cursor:pointer">Отклонить</button>
                </form>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php elseif ($sub === 'calendar'): ?>
  <?php
  $calMonth = $_GET['m'] ?? date('Y-m');
  if (!preg_match('/^\d{4}-\d{2}$/', $calMonth)) $calMonth = date('Y-m');
  $calLid = (int)($_GET['lid'] ?? 0);
  $calTs = strtotime($calMonth . '-01');
  $calPrev = date('Y-m', strtotime('-1 month', $calTs));
  $calNext = date('Y-m', strtotime('+1 month', $calTs));
  $daysInMonth = (int)date('t', $calTs);
  $startDow = (int)date('N', $calTs) - 1; // Пн = 0
  $calStart = $calMonth . '-01';
  $calEnd = $calMonth . '-' . sprintf('%02d', $daysInMonth);
  $today = date('Y-m-d');
  // истёкшие pending хозяина освобождают даты
  $pdo->prepare("UPDATE bookings SET status='declined' WHERE host_id=? AND status='pending' AND created_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)")->execute([$user['id']]);
  $calSql = "SELECT b.*, l.title AS listing_title, l.price AS lprice, l.price_type AS ptype, l.listing_type AS ltype, ug.phone AS guphone, COALESCE(NULLIF(b.guest_name,''), (SELECT name FROM users WHERE id = b.guest_id), 'Клиент') AS ev_name FROM bookings b JOIN listings l ON b.listing_id = l.id LEFT JOIN users ug ON b.guest_id = ug.id WHERE b.host_id = ? AND b.status IN ('pending','confirmed','blocked') AND b.check_in_date <= ? AND b.check_out_date >= ?" . ($calLid > 0 ? ' AND b.listing_id = ' . (int)$calLid : '') . " ORDER BY b.check_in_date";
  $qe = $pdo->prepare($calSql);
  $qe->execute([$user['id'], $calEnd, $calStart]);
  $calEvents = $qe->fetchAll();
  $dayEvents = [];
  foreach ($calEvents as $ev) {
    $s = max(strtotime($ev['check_in_date']), strtotime($calStart));
    $e2 = min(strtotime($ev['check_out_date']), strtotime($calEnd));
    for ($d = $s; $d <= $e2; $d = strtotime('+1 day', $d)) {
      $dayEvents[date('Y-m-d', $d)][] = $ev;
    }
  }
  ?>
  <style>
    .cal-grid{width:100%;border-collapse:separate;border-spacing:4px;table-layout:fixed}
    .cal-grid th{font-size:0.6875rem;color:#7A8A9A;font-weight:600;padding:4px 0;text-align:center}
    .cal-day{background:#fff;border:1px solid #EEF2F6;border-radius:10px;height:76px;vertical-align:top;padding:4px;position:relative}
    .cal-day.past{background:#F7F9FB;opacity:0.65}
    .cal-day.today{border-color:#0A7BBA;box-shadow:0 0 0 1px #0A7BBA inset}
    .cal-day.empty{background:transparent;border:0}
    .cal-num{font-size:0.75rem;font-weight:700;color:#0A1A2A;margin-bottom:3px}
    .cal-day.past .cal-num{color:#9AAAB8}
    .cal-chip{font-size:0.66rem;line-height:1.3;border-radius:6px;padding:3px 5px;margin-bottom:3px;cursor:pointer;word-break:break-word}
    .cal-chip.confirmed{background:#DFF5E7;color:#1F7A45;border:1px solid #BBE5C8}
    .cal-chip.pending{background:#FFF3D6;color:#9A6700;border:1px solid #F5E1A4}
    .cal-chip.blocked{background:#E9EEF3;color:#41505E;border:1px solid #D5DDE6}
    .cal-more{font-size:0.62rem;color:#7A8A9A}
    .cal-modal .cinfo{font-size:0.9rem;line-height:1.6;color:#0A1A2A}
    .cal-modal .cinfo b{color:#5A6B7D;font-weight:600;font-size:0.8125rem;margin-right:0.25rem}
    .cal-modal .dbtn{display:inline-block;background:#F0F3F7;color:#0A1A2A;border:0;border-radius:8px;padding:0.4375rem 0.875rem;font-size:0.75rem;font-weight:600;cursor:pointer;text-decoration:none;margin-right:0.375rem}
    .cal-modal .dbtn.blue{background:#0A7BBA;color:#fff}
    .cal-modal .dbtn.red{background:#fff;color:#DC2626;border:1px solid #F3C1C1}
    .cal-add{position:absolute;bottom:3px;right:3px;width:20px;height:20px;border-radius:6px;border:1px dashed #C8D0DA;background:#fff;color:#7A8A9A;font-size:0.8rem;line-height:1;cursor:pointer}
    .cal-add:hover{background:#0A7BBA;color:#fff;border-color:#0A7BBA}
    .cal-modal-overlay{display:none;position:fixed;inset:0;background:rgba(18,30,43,0.5);z-index:120;align-items:center;justify-content:center;padding:1rem}
    .cal-modal-overlay.open{display:flex}
    .cal-modal{background:#fff;border-radius:14px;width:100%;max-width:26rem;padding:1.5rem;max-height:90vh;overflow:auto}
    .cal-modal label{display:block;font-size:0.8125rem;font-weight:600;color:#0A1A2A;margin:0.75rem 0 0.25rem}
    .cal-modal input,.cal-modal select{width:100%;box-sizing:border-box;padding:0.5rem 0.75rem;border:1px solid #DFE4EA;border-radius:8px;font-size:0.9rem}
  </style>
  <?php if (isset($_GET['bok'])): ?><div class="flash success">Календарь обновлён</div><?php endif; ?>
  <?php if (isset($_GET['cerr'])): ?><div class="flash error"><?= ($_GET['cerr'] === '2') ? 'Эти даты уже заняты подтверждённой бронью или ручной записью' : 'Проверьте даты и объявление' ?></div><?php endif; ?>

  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.75rem;margin-bottom:1rem">
    <div style="display:flex;align-items:center;gap:0.5rem">
      <a href="/dashboard?sub=calendar&m=<?=h($calPrev)?><?=$calLid?'&lid='.$calLid:''?>" style="text-decoration:none;padding:0.375rem 0.75rem;border:1px solid #DFE4EA;border-radius:8px;color:#0A1A2A;font-size:0.875rem">←</a>
      <b style="font-family:Manrope,sans-serif;font-size:1.0625rem"><?= ['01'=>'Январь','02'=>'Февраль','03'=>'Март','04'=>'Апрель','05'=>'Май','06'=>'Июнь','07'=>'Июль','08'=>'Август','09'=>'Сентябрь','10'=>'Октябрь','11'=>'Ноябрь','12'=>'Декабрь'][substr($calMonth,5,2)] ?> <?= (int)substr($calMonth,0,4) ?></b>
      <a href="/dashboard?sub=calendar&m=<?=h($calNext)?><?=$calLid?'&lid='.$calLid:''?>" style="text-decoration:none;padding:0.375rem 0.75rem;border:1px solid #DFE4EA;border-radius:8px;color:#0A1A2A;font-size:0.875rem">→</a>
      <?php if ($calMonth !== date('Y-m')): ?><a href="/dashboard?sub=calendar<?=$calLid?'&lid='.$calLid:''?>" style="text-decoration:none;font-size:0.8125rem;color:#0A7BBA;margin-left:0.5rem">Сегодня</a><?php endif; ?>
    </div>
    <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap">
      <form method="get" action="/dashboard">
        <input type="hidden" name="sub" value="calendar">
        <?php if ($calMonth !== date('Y-m')): ?><input type="hidden" name="m" value="<?=h($calMonth)?>"><?php endif; ?>
        <select name="lid" onchange="this.form.submit()" style="padding:0.375rem 0.625rem;border:1px solid #DFE4EA;border-radius:8px;font-size:0.8125rem">
          <option value="0">Все объявления</option>
          <?php foreach ($myListings as $ml): ?>
          <option value="<?=$ml['id']?>" <?=$calLid === (int)$ml['id'] ? 'selected' : ''?>><?=h($ml['title'])?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <button type="button" onclick="calAdd('')" style="background:#0A7BBA;color:#fff;border:0;border-radius:8px;padding:0.5rem 1rem;font-size:0.8125rem;font-weight:600;cursor:pointer">+ Занять даты</button>
    </div>
  </div>

  <div style="display:flex;gap:0.875rem;flex-wrap:wrap;margin-bottom:0.75rem;font-size:0.75rem;color:#54677A;align-items:center">
    <span><i style="display:inline-block;width:10px;height:10px;border-radius:3px;background:#34D399;margin-right:5px"></i>подтверждена</span>
    <span><i style="display:inline-block;width:10px;height:10px;border-radius:3px;background:#FBBF24;margin-right:5px"></i>ожидает</span>
    <span><i style="display:inline-block;width:10px;height:10px;border-radius:3px;background:#94A3B8;margin-right:5px"></i>занято вручную</span>
    <span class="muted">— нажми на день, чтобы увидеть брони</span>
  </div>

  <table class="cal-grid">
    <tr><th>Пн</th><th>Вт</th><th>Ср</th><th>Чт</th><th>Пт</th><th>Сб</th><th>Вс</th></tr>
    <?php
    $cell = 0;
    for ($week = 0; $week < 6; $week++):
      if ($cell >= $daysInMonth) break;
    ?>
    <tr>
      <?php for ($dow = 0; $dow < 7; $dow++):
        $cellIdx = $week * 7 + $dow;
        $dayNum = $cellIdx - $startDow + 1;
        if ($dayNum < 1 || $dayNum > $daysInMonth) { echo '<td class="cal-day empty"></td>'; $cell = max($cell, $dayNum); continue; }
        $cell = max($cell, $dayNum);
        $key = $calMonth . '-' . sprintf('%02d', $dayNum);
        $evs = $dayEvents[$key] ?? [];
        $isPast = $key < $today;
      ?>
      <td class="cal-day<?=$isPast ? ' past' : ''?><?=$key === $today ? ' today' : ''?>">
        <div class="cal-num"><?=$dayNum?></div>
        <?php foreach (array_slice($evs, 0, 3) as $ev): ?>
        <div class="cal-chip <?=$ev['status']?>" onclick="calInfo(<?=$ev['id']?>)" title="Подробнее"><?=h(mb_substr($ev['ev_name'], 0, 18))?></div>
        <?php endforeach; ?>
        <?php if (count($evs) > 3): ?><div class="cal-more">+<?=count($evs) - 3?></div><?php endif; ?>
        <?php if (!$isPast): ?><button type="button" class="cal-add" onclick="calAdd('<?=$key?>')" title="Занять даты">+</button><?php endif; ?>
      </td>
      <?php endfor; ?>
    </tr>
    <?php endfor; ?>
  </table>

  <!-- Попап: детали брони (имя, телефон, кнопки) -->
  <div id="calInfo" class="cal-modal-overlay" onclick="if(event.target===this)calInfoClose()">
    <div class="cal-modal">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem">
        <b style="font-family:Manrope,sans-serif" id="ciStatus"></b>
        <button type="button" onclick="calInfoClose()" style="background:none;border:0;font-size:1.25rem;cursor:pointer;color:#7A8A9A">×</button>
      </div>
      <div class="cinfo" id="ciBody"></div>
      <div style="display:flex;gap:0.5rem;margin-top:1rem;flex-wrap:wrap;align-items:center" id="ciBtns"></div>
    </div>
  </div>

  <!-- Модалка: добавить / править ручную запись -->
  <div id="calModal" class="cal-modal-overlay" onclick="if(event.target===this)calClose()">
    <div class="cal-modal">
      <form method="post" action="/dashboard">
        <?= csrf_field() ?>
        <input type="hidden" name="m" value="<?=h($calMonth)?>">
        <input type="hidden" name="bid" id="cmBid" value="">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <b id="cmTitle" style="font-family:Manrope,sans-serif">Занять даты вручную</b>
          <button type="button" onclick="calClose()" style="background:none;border:0;font-size:1.25rem;cursor:pointer;color:#7A8A9A">×</button>
        </div>
        <label>Объявление</label>
        <select name="listing_id" id="cmListing">
          <?php foreach ($myListings as $ml): ?>
          <option value="<?=$ml['id']?>"><?=h($ml['title'])?></option>
          <?php endforeach; ?>
        </select>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.625rem">
          <div><label>С даты</label><input type="date" name="date_from" id="cmFrom" required></div>
          <div><label>По дату</label><input type="date" name="date_to" id="cmTo" required></div>
        </div>
        <label>Гостей</label>
        <input type="number" name="guests_count" id="cmGuests" value="1" min="1" max="30">
        <label>Имя клиента (вне сайта)</label>
        <input type="text" name="guest_name" id="cmName" placeholder="напр. Иванов, с авито">
        <label>Телефон</label>
        <input type="text" name="guest_phone" id="cmPhone" placeholder="+7 …">
        <label>Цена, ₽ (0 — без цены)</label>
        <input type="text" name="total_price" id="cmPrice" value="0">
        <div style="display:flex;gap:0.5rem;margin-top:1rem;flex-wrap:wrap">
          <button type="submit" name="add_manual" id="cmBtnAdd" value="1" style="background:#0A7BBA;color:#fff;border:0;border-radius:8px;padding:0.5625rem 1.125rem;font-size:0.8125rem;font-weight:600;cursor:pointer">Занять</button>
          <button type="submit" name="edit_manual" id="cmBtnEdit" value="1" style="display:none;background:#16A34A;color:#fff;border:0;border-radius:8px;padding:0.5625rem 1.125rem;font-size:0.8125rem;font-weight:600;cursor:pointer">Сохранить</button>
          <button type="submit" name="delete_manual" id="cmBtnDel" value="1" style="display:none;background:#fff;color:#DC2626;border:1px solid #F3C1C1;border-radius:8px;padding:0.5625rem 1.125rem;font-size:0.8125rem;font-weight:600;cursor:pointer">Удалить</button>
          <button type="button" onclick="calClose()" style="background:#F0F3F7;color:#0A1A2A;border:0;border-radius:8px;padding:0.5625rem 1.125rem;font-size:0.8125rem;font-weight:600;cursor:pointer">Отмена</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  var CAL_EVENTS = <?= json_encode(array_map(function ($e) {
      return ['id'=>(int)$e['id'], 'lid'=>(int)$e['listing_id'], 'title'=>$e['listing_title'], 'from'=>$e['check_in_date'], 'to'=>$e['check_out_date'],
              'guests'=>(int)$e['guests_count'], 'name'=>$e['ev_name'], 'phone'=>(string)$e['guest_phone'],
              'price'=>(float)$e['total_price'], 'source'=>$e['source'], 'status'=>$e['status'],
              'gid'=>(int)($e['guest_id'] ?? 0), 'lprice'=>(float)($e['lprice'] ?? 0),
              'ptype'=>(string)($e['ptype'] ?? ''), 'ltype'=>(string)($e['ltype'] ?? ''),
              'tel'=>(!empty($e['guest_phone']) ? phone_display((string)$e['guest_phone']) : (!empty($e['guphone']) ? phone_display((string)$e['guphone']) : ''))];
    }, $calEvents), JSON_UNESCAPED_UNICODE) ?>;
  var CAL_CSRF = <?= json_encode(csrf_token()) ?>;
  var CAL_M = <?= json_encode($calMonth) ?>;

  function calOpen(){document.getElementById('calModal').classList.add('open');document.body.style.overflow='hidden'}
  function calClose(){document.getElementById('calModal').classList.remove('open');document.body.style.overflow=''}
  function calSetBtns(mode){
    document.getElementById('cmBtnAdd').style.display = mode==='add' ? '' : 'none';
    document.getElementById('cmBtnEdit').style.display = mode==='edit' ? '' : 'none';
    document.getElementById('cmBtnDel').style.display = mode==='edit' ? '' : 'none';
    document.getElementById('cmTitle').textContent = mode==='add' ? 'Занять даты вручную' : 'Правка ручной записи';
  }
  function calAdd(dateStr){
    calSetBtns('add');
    document.getElementById('cmBid').value = '';
    document.getElementById('cmFrom').value = dateStr || '';
    var to = '';
    if (dateStr) { var d = new Date(dateStr + 'T00:00:00'); d.setDate(d.getDate() + 1); to = d.toISOString().slice(0,10); }
    document.getElementById('cmTo').value = to;
    document.getElementById('cmName').value = '';
    document.getElementById('cmPhone').value = '';
    document.getElementById('cmGuests').value = 1;
    document.getElementById('cmPrice').value = 0;
    calOpen();
  }
  function calEdit(id){
    var ev = CAL_EVENTS.find(function(x){return x.id===id});
    if (!ev || ev.source!=='manual') return;
    calSetBtns('edit');
    document.getElementById('cmBid').value = ev.id;
    document.getElementById('cmListing').value = ev.lid;
    document.getElementById('cmFrom').value = ev.from;
    document.getElementById('cmTo').value = ev.to;
    document.getElementById('cmGuests').value = ev.guests || 1;
    document.getElementById('cmName').value = ev.name === 'Занято' ? '' : (ev.name || '');
    document.getElementById('cmPhone').value = ev.phone || '';
    document.getElementById('cmPrice').value = ev.price || 0;
    calOpen();
  }
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') calClose(); });

  var CAL_SHORT = ['янв','фев','мар','апр','мая','июн','июл','авг','сен','окт','ноя','дек'];
  function dhum(ds){var p=ds.split('-');var s=parseInt(p[2],10)+' '+CAL_SHORT[parseInt(p[1],10)-1];if(p[0]!==String(new Date().getFullYear()))s+=' '+p[0];return s}
  function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML}
  function money(n){return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g,' ')}
  function calInfo(id){
    var e=CAL_EVENTS.find(function(x){return x.id===id});
    if(!e)return;
    var st=e.status==='confirmed'?'Подтверждена':(e.status==='pending'?'Ожидает подтверждения':'Занято вручную');
    document.getElementById('ciStatus').textContent=st;
    var rows='<div><b>Объявление:</b> <a href="/listing/'+e.lid+'" style="color:#0A7BBA;text-decoration:none">'+esc(e.title)+'</a></div>';
    rows+='<div><b>Гость:</b> '+esc(e.name)+'</div>';
    if(e.tel)rows+='<div><b>Телефон:</b> <a href="tel:'+e.tel+'" style="color:#0A7BBA">'+esc(e.tel)+'</a></div>';
    rows+='<div><b>Даты:</b> '+dhum(e.from)+' — '+dhum(e.to)+'</div>';
    rows+='<div><b>Гостей:</b> '+e.guests+'</div>';
    rows+='<div><b>Цена:</b> '+(e.price>0?money(e.price)+' ₽':'без цены')+'</div>';
    document.getElementById('ciBody').innerHTML=rows;
    var btns='';
    if(e.source==='manual'){
      btns+='<button type="button" class="dbtn" onclick="calInfoClose();calEdit('+e.id+')">Правка</button>';
      btns+='<button type="button" class="dbtn red" onclick="calInfoClose();calDelete('+e.id+')">Удалить</button>';
    } else {
      btns+='<button type="button" class="dbtn blue" onclick="calChat('+e.id+')">Открыть чат</button>';
      if(e.status==='pending')btns+='<span style="font-size:0.75rem;color:#7A8A9A;align-self:center">подтвердить — во вкладке «Ко мне»</span>';
    }
    document.getElementById('ciBtns').innerHTML=btns;
    document.getElementById('calInfo').classList.add('open');
    document.body.style.overflow='hidden';
  }
  function calInfoClose(){document.getElementById('calInfo').classList.remove('open');document.body.style.overflow=''}
  function calChat(id){
    var e=CAL_EVENTS.find(function(x){return x.id===id});
    if(!e)return;
    openChatThread(e.lid, e.gid, e.name||'Клиент', e.title, '', e.lprice, e.ltype||'');
  }
  function calDelete(id){
    if(!confirm('Удалить запись?'))return;
    var f=document.createElement('form');
    f.method='post';f.action='/dashboard';
    f.innerHTML='<input type="hidden" name="_csrf" value="'+CAL_CSRF+'"><input type="hidden" name="delete_manual" value="1"><input type="hidden" name="bid" value="'+id+'"><input type="hidden" name="m" value="'+CAL_M+'">';
    document.body.appendChild(f);f.submit();
  }
  </script>

  <?php elseif ($sub === 'profile'): ?>
    <?php if (isset($_GET['ok'])): ?>
    <div class="flash success">Профиль обновлён</div>
    <?php endif; ?>
    <div style="max-width:30rem">
      <form method="post" enctype="multipart/form-data" style="background:#fff;border:1px solid #EEF2F6;border-radius:12px;padding:2rem;box-shadow:0 4px 12px rgba(15,23,32,0.06)">
        <?= csrf_field() ?>
        <input type="hidden" name="update_profile" value="1">
        <!-- Avatar -->
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem">
          <?= avatar_html($user, 'w-16 h-16', 'text-xl') ?>
          <div>
            <label style="display:inline-flex;align-items:center;gap:0.375rem;border-radius:8px;border:1px solid #DFE4EA;padding:0.5rem 0.875rem;font-size:0.75rem;font-weight:500;cursor:pointer;transition:all 0.15s ease;background:#fff;color:#3A4A5C" onmouseover="this.style.background='#F7F9FB';this.style.borderColor='#C8D0DA'" onmouseout="this.style.background='#fff';this.style.borderColor='#DFE4EA'">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              Сменить аватар
              <input type="file" name="avatar" accept="image/*" hidden onchange="this.form.submit()">
            </label>
            <p style="font-size:0.6875rem;color:#5A6B7D;margin:0.375rem 0 0">JPG, PNG, WebP</p>
          </div>
        </div>
        <div class="form-group">
          <label>Имя</label>
          <input type="text" name="name" value="<?=h($user['name'])?>" style="width:100%;box-sizing:border-box">
        </div>
        <div class="form-group">
          <label>Email</label>
          <input type="email" name="email" value="<?=h($user['email'])?>" style="width:100%;box-sizing:border-box">
        </div>
        <div class="form-group">
          <label>Телефон</label>
          <input type="text" name="phone" value="<?=h($user['phone'] ? phone_display($user['phone']) : '')?>" style="width:100%;box-sizing:border-box" <?php if ($user['role'] !== 'admin' && !empty($user['phone'])): ?>readonly onfocus="this.blur()" title="Телефон можно изменить только через администратора"<?php endif; ?>>
          <?php if ($user['role'] !== 'admin' && !empty($user['phone'])): ?><p style="font-size:0.6875rem;color:#5A6B7D;margin:0.25rem 0 0">Телефон можно изменить только через администратора</p><?php endif; ?>
        </div>
        <button type="submit" name="update_profile" value="1" class="cta-btn" style="width:100%;gap:0.375rem;padding:0.625rem 1.25rem">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          Сохранить
        </button>
      </form>

      <!-- Уведомления в «Макс» -->
      <div style="background:#fff;border:1px solid #EEF2F6;border-radius:12px;padding:2rem;box-shadow:0 4px 12px rgba(15,23,32,0.06);margin-top:1.5rem">
        <h3 style="font-family:Manrope,sans-serif;font-size:1rem;margin:0 0 0.75rem">Уведомления в «Макс»</h3>
        <?php if (!empty($user['max_user_id'])): ?>
          <p style="font-size:0.8125rem;color:#2E7D32;margin:0 0 0.75rem">✅ Привязано. Новые брони и сообщения будут приходить вам в «Макс».</p>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="unbind_max" value="1"><button type="submit" style="background:#fff;color:#DC2626;border:1px solid #F3C1C1;border-radius:8px;padding:0.5rem 1rem;font-size:0.8125rem;font-weight:600;cursor:pointer">Отвязать</button></form>
        <?php else: ?>
          <p style="font-size:0.8125rem;color:#3A4A5C;margin:0 0 0.75rem">Откройте приложение «Макс», найдите бота <b>«<?=h(max_bot_name())?>»</b> и отправьте ему сообщение:</p>
          <div style="background:#F7F9FB;border:1px dashed #C8D0DA;border-radius:8px;padding:0.625rem 1rem;font-family:Consolas,monospace;font-size:0.9375rem;color:#0A1A2A">сахгоу <?=h(max_bind_code($user['id']))?></div>
          <p style="font-size:0.75rem;color:#5A6B7D;margin:0.625rem 0 0">После этого уведомления о ваших бронях и сообщениях начнут приходить в «Макс».</p>
        <?php endif; ?>
      </div>

      <!-- Change Password -->
      <form method="post" style="background:#fff;border:1px solid #EEF2F6;border-radius:12px;padding:2rem;box-shadow:0 4px 12px rgba(15,23,32,0.06);margin-top:1.5rem">
        <?= csrf_field() ?>
        <input type="hidden" name="change_password" value="1">
        <h3 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.0625rem;margin:0 0 0.25rem">Смена пароля</h3>
        <p style="font-size:0.75rem;color:#5A6B7D;margin:0 0 1.25rem">Введите текущий и новый пароль</p>
        <?php if (isset($_GET['pwok'])): ?>
        <div class="flash success" style="margin-bottom:1rem">Пароль изменён</div>
        <?php endif; ?>
        <?php if ($pw_error): ?>
        <div style="background:#FEF2F2;border:1px solid #FECACA;color:#DC2626;border-radius:8px;padding:0.75rem 1rem;font-size:0.8125rem;margin-bottom:1rem"><?=h($pw_error)?></div>
        <?php endif; ?>
        <div class="form-group">
          <label>Текущий пароль</label>
          <input type="password" name="current_password" required style="width:100%;box-sizing:border-box">
        </div>
        <div class="form-group">
          <label>Новый пароль</label>
          <input type="password" name="new_password" required minlength="6" style="width:100%;box-sizing:border-box">
        </div>
        <button type="submit" class="btn-outline" style="width:100%;gap:0.375rem;padding:0.625rem 1.25rem;margin-top:0.5rem">
          Сменить пароль
        </button>
      </form>
    </div>
  <?php endif; ?>
</div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
