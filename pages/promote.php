<?php
// promote.php — продвижение объявления (promote-modal.tsx)
$cu = auth_required();

$pdo = db();
$prices = get_promo_prices();
$lid = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT id, title FROM listings WHERE id = ? AND user_id = ?');
$stmt->execute([$lid, $cu['id']]);
$listing = $stmt->fetch();
if (!$listing) { header('Location: /dashboard'); exit; }

$cta_url = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '');

// Check existing promotions
$stmt = $pdo->prepare('SELECT * FROM promotions WHERE listing_id = ? AND status IN ("draft","active","paused") LIMIT 1');
$stmt->execute([$lid]);
$activePromo = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promote'])) {
  csrf_check();
  $promoType = $_POST['promo_type'] ?? 'top';
  $duration = (int)($_POST['duration'] ?? 7);
  $budget = $prices[$promoType] * $duration;
  $starts = date('Y-m-d H:i:s');
  $expires = date('Y-m-d H:i:s', strtotime("+{$duration} days"));

  $stmt = $pdo->prepare('INSERT INTO promotions (listing_id, host_id, promo_type, status, starts_at, expires_at, budget_rub, payment_status, payment_amount) VALUES (?,?,?,?,?,?,?,?,?)');
  $stmt->execute([$lid, $cu['id'], $promoType, 'pending', $starts, $expires, $budget, 'pending', $budget]);

  $pdo->prepare('INSERT INTO notifications (user_id, type, text, link, is_read, created_at) VALUES (?,?,?,?,0,NOW())')->execute([$cu['id'], 'promo', "Заявка на продвижение «{$listing['title']}» принята. Ожидайте подтверждения.", '/dashboard']);

  // Notify all admins
  $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
  foreach ($admins as $a) {
    $pdo->prepare('INSERT INTO notifications (user_id, type, text, link, is_read, created_at) VALUES (?,?,?,?,0,NOW())')->execute([$a['id'], 'promo', "Новая заявка на продвижение от {$cu['name']}: «{$listing['title']}»", '/admin?tab=payments']);
  }

  header('Location: /dashboard');
  exit;
}

$page_title = 'Продвижение — СахGO';
require __DIR__ . '/../includes/header.php';
?>
<section class="py-12">
<div class="max-w-2xl mx-auto px-4">
  <div class="mb-8">
    <span class="text-xs uppercase tracking-[0.12em] text-accent font-medium">Продвижение</span>
    <h1 class="font-display text-4xl mt-1">Продвинуть объявление</h1>
  </div>

  <p class="text-sm text-muted-foreground mb-6">Объявление: <strong><?=h($listing['title'])?></strong></p>

  <?php if ($activePromo): ?>
    <div class="bg-green-50 border border-green-200 rounded-xl p-6 text-center mb-6">
      <span style="font-size:2rem">🚀</span>
      <p class="font-medium text-green-800 mt-2">Уже продвигается!</p>
      <p class="text-sm text-green-700">Тип: <?=$activePromo['promo_type']?> · До: <?=$activePromo['expires_at']?></p>
      <a href="/dashboard" class="btn-outline mt-3" style="display:inline-flex">В кабинет</a>
    </div>
  <?php else: ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
      <?php
      $plans = [
        ['top','🔝 Top','Наверху списка',$prices['top'].' ₽/день',['Максимальная видимость','Первое место в выдаче','Статистика показов']],
        ['highlight','💡 Highlight','Выделено цветом',$prices['highlight'].' ₽/день',['Жёлтый фон в выдаче','Выше обычных','Статистика кликов']],
        ['urgent','⚡ Срочно','Метка срочности',$prices['urgent'].' ₽/день',['Красная метка','Привлекает внимание','Повышает конверсию']],
      ];
      foreach ($plans as $p):
      ?>
      <label class="cursor-pointer">
        <input type="radio" name="promo_type" value="<?=$p[0]?>" <?=$p[0]==='top'?'checked':''?> style="display:none" onchange="selectPlan(this)">
        <div class="promo-card border-2 rounded-xl p-5 text-center transition-all" style="border-color:var(--border)">
          <div class="text-3xl mb-2"><?=$p[1]?></div>
          <div class="font-display text-lg"><?=$p[2]?></div>
          <div class="text-xs text-muted-foreground mt-1 mb-3"><?=$p[3]?></div>
          <ul class="text-xs text-muted-foreground space-y-1 text-left">
            <?php foreach($p[4] as $f):?><li>✓ <?=$f?></li><?php endforeach;?>
          </ul>
        </div>
      </label>
      <?php endforeach; ?>
    </div>

    <div class="form-group"><label>Длительность (дней)</label>
      <select name="duration" class="w-full"><?php foreach([7,14,30] as $d):?><option value="<?=$d?>"><?=$d?> дней</option><?php endforeach;?></select>
    </div>

    <div class="bg-muted/30 rounded-xl p-4 mb-6" id="priceEstimate">
      <div class="flex justify-between text-sm"><span>Тариф Top</span><span id="calcLabel">7 × <?=$prices['top']?> ₽</span></div>
      <hr class="my-2">
      <div class="flex justify-between font-medium"><span>Итого</span><span class="font-display text-lg" id="totalPrice"><?=number_format($prices['top']*7,0,',',' ')?> ₽</span></div>
    </div>

    <div class="flex gap-2">
      <a href="/dashboard" class="btn-outline" style="flex:1;text-align:center">← В кабинет</a>
      <button type="submit" name="promote" class="cta-btn" style="flex:3">Отправить заявку</button>
    </div>
  </form>
  <?php endif; ?>
</div>
</section>

<script>
var prices = <?=json_encode($prices)?>;
var typeNames = {top:'Top', highlight:'Highlight', urgent:'Срочно'};
function selectPlan(input) {
  document.querySelectorAll('.promo-card').forEach(function(c){c.style.borderColor='var(--border)';c.style.background=''});
  var card = input.closest('label').querySelector('.promo-card');
  card.style.borderColor = 'var(--accent)'; card.style.background = 'rgba(27,107,138,0.05)';
  updatePrice();
}
document.querySelector('select[name="duration"]').addEventListener('change', updatePrice);
function updatePrice() {
  var type = document.querySelector('input[name="promo_type"]:checked').value;
  var days = parseInt(document.querySelector('select[name="duration"]').value);
  var total = prices[type] * days;
  document.getElementById('calcLabel').textContent = days + ' × ' + prices[type].toLocaleString('ru-RU') + ' ₽';
  document.getElementById('totalPrice').textContent = total.toLocaleString('ru-RU') + ' ₽';
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
