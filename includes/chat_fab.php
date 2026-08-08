<?php
// chat_fab.php — плавающая кнопка чата + notification bell
// Подключается в footer.php перед </body>
$cu = auth_user();
if (!$cu) return; // только для залогиненных
$pdo = db();

// Unread messages count
$stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0');
$stmt->execute([$cu['id']]);
$unread_msgs = (int)$stmt->fetchColumn();

// Unread notifications count
$stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$stmt->execute([$cu['id']]);
$unread_notifs = (int)$stmt->fetchColumn();

// Recent notifications
$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$stmt->execute([$cu['id']]);
$notifs = $stmt->fetchAll();

// Chat previews
$stmt = $pdo->prepare('
  SELECT m.*, l.title AS listing_title, u.name AS other_name
  FROM messages m
  JOIN listings l ON m.listing_id = l.id
  JOIN users u ON IF(m.sender_id = ?, m.receiver_id, m.sender_id) = u.id
  WHERE (m.sender_id = ? OR m.receiver_id = ?)
  GROUP BY m.listing_id, IF(m.sender_id = ?, m.receiver_id, m.sender_id)
  ORDER BY m.created_at DESC LIMIT 10
');
$stmt->execute([$cu['id'], $cu['id'], $cu['id'], $cu['id']]);
$chats = $stmt->fetchAll();
?>
<style>
.fab-container { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 90; display: flex; flex-direction: column; gap: 0.5rem; }
.fab-btn { width: 3.5rem; height: 3.5rem; border-radius: 50%; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: transform 0.15s; position: relative; }
.fab-btn:hover { transform: scale(1.05); }
.fab-btn:active { transform: scale(0.95); }
.fab-chat { background: var(--primary); color: #fff; }
.fab-bell { background: #fff; color: var(--primary); border: 1px solid var(--border); }
.fab-badge { position: absolute; top: -2px; right: -2px; background: #dc2626; color: #fff; font-size: 0.625rem; font-weight: 700; min-width: 1.25rem; height: 1.25rem; border-radius: 9999px; display: flex; align-items: center; justify-content: center; padding: 0 0.25rem; }

.fab-panel { position: fixed; bottom: 6rem; right: 1.5rem; z-index: 91; width: 22rem; max-height: 28rem; background: #fff; border-radius: 0.75rem; box-shadow: 0 20px 60px -15px rgba(0,0,0,0.2); display: none; flex-direction: column; overflow: hidden; }
.fab-panel.open { display: flex; }
.fab-panel-header { padding: 1rem; border-bottom: 1px solid var(--border); font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
.fab-panel-body { flex: 1; overflow-y: auto; }
.fab-panel-item { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border); cursor: pointer; transition: background 0.1s; }
.fab-panel-item:hover { background: var(--secondary); }
.fab-panel-empty { padding: 2rem 1rem; text-align: center; color: var(--muted-fg); font-size: 0.875rem; }
.fab-panel-close { cursor: pointer; color: var(--muted-fg); font-size: 1.25rem; background: none; border: none; padding: 0; }
</style>

<div class="fab-container">
  <!-- Notification Bell -->
  <button class="fab-btn fab-bell" onclick="togglePanel('bell')" title="Уведомления">
    🔔
    <?php if ($unread_notifs > 0): ?><span class="fab-badge"><?= $unread_notifs ?></span><?php endif; ?>
  </button>

  <!-- Chat FAB -->
  <button class="fab-btn fab-chat" onclick="togglePanel('chat')" title="Сообщения">
    💬
    <?php if ($unread_msgs > 0): ?><span class="fab-badge"><?= $unread_msgs ?></span><?php endif; ?>
  </button>
</div>

<!-- Bell Panel -->
<div id="bellPanel" class="fab-panel">
  <div class="fab-panel-header">
    <span>Уведомления</span>
    <button class="fab-panel-close" onclick="togglePanel('bell')">×</button>
  </div>
  <div class="fab-panel-body">
    <?php if (empty($notifs)): ?>
      <div class="fab-panel-empty">Нет уведомлений</div>
    <?php else: ?>
      <?php foreach ($notifs as $n): ?>
      <div class="fab-panel-item" <?= $n['link'] ? 'onclick="window.location.href=\'' . h($n['link']) . '\'"' : '' ?>>
        <div class="text-sm"><?= h($n['text']) ?></div>
        <div class="text-xs text-muted-foreground mt-1"><?= time_ago($n['created_at']) ?></div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Chat Panel -->
<div id="chatPanel" class="fab-panel">
  <div class="fab-panel-header">
    <span>Сообщения</span>
    <button class="fab-panel-close" onclick="togglePanel('chat')">×</button>
  </div>
  <div class="fab-panel-body">
    <?php if (empty($chats)): ?>
      <div class="fab-panel-empty">Нет сообщений<br><br><a href="/catalog" style="color:var(--accent)">Найти объявления →</a></div>
    <?php else: ?>
      <?php foreach ($chats as $c): ?>
      <div class="fab-panel-item" onclick="window.location.href='/listing/<?= $c['listing_id'] ?>'">
        <div class="font-medium text-sm"><?= h($c['other_name']) ?></div>
        <div class="text-xs text-muted-foreground truncate"><?= h(mb_substr($c['text'], 0, 50)) ?></div>
        <div class="text-xs text-muted-foreground mt-0.5"><?= h($c['listing_title']) ?> · <?= time_ago($c['created_at']) ?></div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
function togglePanel(which) {
  var bell = document.getElementById('bellPanel');
  var chat = document.getElementById('chatPanel');
  if (which === 'bell') {
    bell.classList.toggle('open');
    chat.classList.remove('open');
  } else {
    chat.classList.toggle('open');
    bell.classList.remove('open');
  }
}
// Close on outside click
document.addEventListener('click', function(e) {
  if (!e.target.closest('.fab-container') && !e.target.closest('.fab-panel')) {
    document.getElementById('bellPanel').classList.remove('open');
    document.getElementById('chatPanel').classList.remove('open');
  }
});
</script>
