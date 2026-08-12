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
  'bookings'   => 'Бронирования',
  'host_bookings' => 'Ко мне',
  'profile'    => 'Профиль',
];

// POST: update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
  csrf_check();
  $name = trim($_POST['name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $email = trim($_POST['email'] ?? '');
  if (!empty($name)) {
    // Non-admin can't change phone; admin can change everything
    if ($user['role'] !== 'admin') {
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
        <span style="font-size:0.6875rem;text-transform:uppercase;letter-spacing:0.1em;color:#7A8A9A;font-weight:500">Личный кабинет</span>
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
       style="display:inline-flex;align-items:center;padding:0.5rem 1rem;font-size:0.8125rem;font-weight:500;border-radius:8px;text-decoration:none;transition:all 0.15s ease;<?=$active?'background:#121E2B;color:#F7F9FB':'color:#7A8A9A;background:transparent'?>"
       onmouseover="if(!this.classList.contains('active')){this.style.background='#EEF2F6';this.style.color='#121E2B'}"
       onmouseout="if(!this.classList.contains('active')){this.style.background='transparent';this.style.color='#7A8A9A'}"
       class="<?=$active?'active':''?>"><?=$v?></a>
    <?php endforeach; ?>
  </div>

  <?php if ($sub === 'listings'): ?>
    <?php if (empty($myListings)): ?>
      <div style="text-align:center;padding:5rem 1rem">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#C8D0DA" stroke-width="1.5" style="margin-bottom:1.25rem">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
        </svg>
        <p style="font-size:1rem;font-weight:600;color:#121E2B;margin:0 0 0.25rem">У вас пока нет объявлений</p>
        <p style="font-size:0.8125rem;color:#7A8A9A;margin:0 0 1.5rem">Создайте первое объявление и начните зарабатывать</p>
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
          <span class="promo-badge" style="position:absolute;top:0.625rem;left:0.625rem;<?=$item['promo_type']==='top'?'background:#1B6B8A':($item['promo_type']==='highlight'?'background:#D97706':'background:#DC2626')?>">
            <?=$item['promo_type']==='top'?'TOP':($item['promo_type']==='highlight'?'PROMO':'Срочно')?>
          </span>
          <?php endif; ?>
          <div class="listing-body" style="gap:0.5rem">
            <div style="display:flex;align-items:center;gap:0.5rem;font-size:0.6875rem">
              <span class="badge" style="<?=$item['status']==='active'?'color:#166534;border-color:#BBF7D0;background:#F0FDF4':''?>"><?=$item['status']==='active'?'Активно':$item['status']?></span>
              <span style="color:#7A8A9A"><?=h($item['category_name'])?></span>
            </div>
            <div class="listing-price"><?=number_format((float)$item['price'],0,'.',' ')?> <?=price_label($item['listing_type'])?></div>
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
    // Thread list — like Avito inbox
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
    ?>
    <?php if (empty($threads)): ?>
      <div style="text-align:center;padding:5rem 1rem">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#C8D0DA" stroke-width="1.5" style="margin-bottom:1.25rem">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        <p style="font-size:1rem;font-weight:600;color:#121E2B;margin:0 0 0.25rem">Нет сообщений</p>
        <p style="font-size:0.8125rem;color:#7A8A9A;margin:0 0 1.5rem">Здесь появятся ваши переписки по объявлениям</p>
        <a href="/catalog" class="btn-outline">Смотреть объявления</a>
      </div>
    <?php else: ?>
      <div style="max-width:640px;margin:0 auto">
        <?php foreach ($threads as $th):
          $lid = $th['lid']; $oid = $th['other_id'];
          $oname = h($th['other_name']); $oav = $th['other_avatar'];
          $lname = h($th['listing_title']);
          $ltext = h(mb_strlen($th['last_text']) > 80 ? mb_substr($th['last_text'], 0, 80) . '…' : ($th['last_text'] ?? ''));
          $unread = (int)$th['unread'];
          $avhtml = $oav ? '<img src="' . h($oav) . '" style="width:48px;height:48px;border-radius:50%;object-fit:cover;flex-shrink:0">' : '<div style="width:48px;height:48px;border-radius:50%;background:#DFE4EA;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.125rem;color:#7A8A9A;flex-shrink:0">' . mb_substr($oname, 0, 1) . '</div>';
          $clickJS = 'openThread(' . intval($lid) . ', ' . intval($oid) . ', ' . json_encode($th['other_name'], JSON_HEX_APOS|JSON_HEX_QUOT) . ', ' . json_encode($th['listing_title'], JSON_HEX_APOS|JSON_HEX_QUOT) . ', ' . json_encode($th['other_avatar'] ?? '', JSON_HEX_APOS|JSON_HEX_QUOT) . ')';
        ?>
        <div onclick="<?=$clickJS?>" style="display:flex;align-items:center;gap:0.875rem;padding:1rem;background:#fff;border:1px solid #EEF2F6;border-radius:12px;cursor:pointer;transition:all 0.12s ease;box-shadow:0 2px 8px rgba(15,23,32,0.04);margin-bottom:0.5rem" onmouseover="this.style.borderColor='#1B6B8A';this.style.boxShadow='0 4px 16px rgba(27,107,138,0.12)'" onmouseout="this.style.borderColor='#EEF2F6';this.style.boxShadow='0 2px 8px rgba(15,23,32,0.04)'">
          <?=$avhtml?>
          <div style="flex:1;min-width:0">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:0.5rem">
              <span style="font-weight:600;font-size:0.9375rem;color:#121E2B"><?=$oname?></span>
              <span style="font-size:0.6875rem;color:#9AAAB8;white-space:nowrap;flex-shrink:0"><?=time_ago($th['last_at'])?></span>
            </div>
            <div style="font-size:0.75rem;color:#7A8A9A;margin-top:2px"><?=$lname?></div>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;margin-top:4px">
              <span style="font-size:0.8125rem;color:#3A4A5C;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:420px"><?=$ltext?></span>
              <?php if ($unread > 0): ?>
              <span style="background:#F59E0B;color:#fff;font-size:0.6875rem;font-weight:700;border-radius:999px;min-width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;padding:0 6px;flex-shrink:0"><?=$unread?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <script>
      // Pass myUid from PHP to JS so openThread works from dashboard
      if (typeof myUid === 'undefined') var myUid = <?=$user['id']?>;
      </script>
    <?php endif; ?>

  <?php elseif ($sub === 'favorites'): ?>
    <?php $favs = get_user_favorites($user['id']); ?>
    <?php if (empty($favs)): ?>
      <div style="text-align:center;padding:5rem 1rem">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#C8D0DA" stroke-width="1.5" style="margin-bottom:1.25rem">
          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
        </svg>
        <p style="font-size:1rem;font-weight:600;color:#121E2B;margin:0 0 0.25rem">Нет избранных объявлений</p>
        <p style="font-size:0.8125rem;color:#7A8A9A;margin:0 0 1.5rem">Добавляйте объявления в избранное кликом по сердечку</p>
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
            <div class="listing-price"><?=number_format((float)$item['price'],0,'.',' ')?> <?=price_label($item['listing_type'])?></div>
            <div class="listing-title"><?=h($item['title'])?></div>
            <div class="listing-meta"><span><?=h($item['category_name']??'')?></span></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php elseif ($sub === 'bookings'): ?>
    <?php $bookings = get_user_bookings($user['id']); ?>
    <?php if (empty($bookings)): ?>
      <div style="text-align:center;padding:5rem 1rem">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#C8D0DA" stroke-width="1.5" style="margin-bottom:1.25rem">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
        <p style="font-size:1rem;font-weight:600;color:#121E2B;margin:0 0 0.25rem">Нет бронирований</p>
      </div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:0.75rem">
      <?php foreach ($bookings as $b): ?>
        <div style="background:#fff;border:1px solid #EEF2F6;border-radius:12px;padding:1.25rem;box-shadow:0 4px 12px rgba(15,23,32,0.06)">
          <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
              <a href="/listing/<?=$b['listing_id']?>" style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.0625rem;color:#121E2B;text-decoration:none"><?=h($b['listing_title'])?></a>
              <div style="font-size:0.8125rem;color:#7A8A9A;margin-top:0.25rem"><?=h($b['location']??'')?> &middot; хозяин: <?=h($b['host_name'])?></div>
            </div>
            <div style="text-align:right">
              <div style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.0625rem"><?=number_format((float)$b['total_price'],0,'.',' ')?> ₽</div>
              <div style="font-size:0.6875rem;color:#7A8A9A;margin-top:0.25rem"><?=$b['created_at']?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php elseif ($sub === 'host_bookings'): ?>
    <?php $hb = get_host_bookings($user['id']); ?>
    <?php if (empty($hb)): ?>
      <div style="text-align:center;padding:5rem 1rem">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#C8D0DA" stroke-width="1.5" style="margin-bottom:1.25rem">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
        <p style="font-size:1rem;font-weight:600;color:#121E2B;margin:0 0 0.25rem">Нет бронирований у вас</p>
      </div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:0.75rem">
      <?php foreach ($hb as $b): ?>
        <div style="background:#fff;border:1px solid #EEF2F6;border-radius:12px;padding:1.25rem;box-shadow:0 4px 12px rgba(15,23,32,0.06)">
          <div style="display:flex;justify-content:space-between;align-items:flex-start">
            <div>
              <span style="font-size:0.8125rem;color:#7A8A9A">Гость: <?=h($b['guest_name'])?></span><br>
              <a href="/listing/<?=$b['listing_id']?>" style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.0625rem;color:#121E2B;text-decoration:none"><?=h($b['listing_title'])?></a>
            </div>
            <div style="text-align:right">
              <div style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.0625rem"><?=number_format((float)$b['total_price'],0,'.',' ')?> ₽</div>
              <div style="font-size:0.6875rem;color:#7A8A9A;margin-top:0.25rem"><?=$b['created_at']?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>

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
            <p style="font-size:0.6875rem;color:#7A8A9A;margin:0.375rem 0 0">JPG, PNG, WebP</p>
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
          <input type="text" name="phone" value="<?=h($user['phone']??'')?>" style="width:100%;box-sizing:border-box" <?php if ($user['role'] !== 'admin'): ?>readonly onfocus="this.blur()" title="Телефон можно изменить только через администратора"<?php endif; ?>>
          <?php if ($user['role'] !== 'admin'): ?><p style="font-size:0.6875rem;color:#7A8A9A;margin:0.25rem 0 0">Телефон можно изменить только через администратора</p><?php endif; ?>
        </div>
        <button type="submit" name="update_profile" value="1" class="cta-btn" style="width:100%;gap:0.375rem;padding:0.625rem 1.25rem">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
          Сохранить
        </button>
      </form>

      <!-- Change Password -->
      <form method="post" style="background:#fff;border:1px solid #EEF2F6;border-radius:12px;padding:2rem;box-shadow:0 4px 12px rgba(15,23,32,0.06);margin-top:1.5rem">
        <?= csrf_field() ?>
        <input type="hidden" name="change_password" value="1">
        <h3 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.0625rem;margin:0 0 0.25rem">Смена пароля</h3>
        <p style="font-size:0.75rem;color:#7A8A9A;margin:0 0 1.25rem">Введите текущий и новый пароль</p>
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
