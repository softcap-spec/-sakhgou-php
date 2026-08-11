<?php
// promote.php — v3 clean design
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
  $budget = $prices[$promoType][$duration] ?? 0;
  $starts = date('Y-m-d H:i:s');
  $expires = date('Y-m-d H:i:s', strtotime("+{$duration} days"));

  $stmt = $pdo->prepare('INSERT INTO promotions (listing_id, host_id, promo_type, status, starts_at, expires_at, budget_rub, payment_status, payment_amount) VALUES (?,?,?,?,?,?,?,?,?)');
  $stmt->execute([$lid, $cu['id'], $promoType, 'pending', $starts, $expires, $budget, 'pending', $budget]);

  $pdo->prepare('INSERT INTO notifications (user_id, type, text, link, is_read, created_at) VALUES (?,?,?,?,0,NOW())')->execute([$cu['id'], 'promo', "Заявка на продвижение &laquo;{$listing['title']}&raquo; принята. Ожидайте подтверждения.", '/dashboard']);

  $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
  foreach ($admins as $a) {
    $pdo->prepare('INSERT INTO notifications (user_id, type, text, link, is_read, created_at) VALUES (?,?,?,?,0,NOW())')->execute([$a['id'], 'promo', "Новая заявка на продвижение от {$cu['name']}: &laquo;{$listing['title']}&raquo;", '/admin?tab=payments']);
  }

  header('Location: /dashboard');
  exit;
}

$page_title = 'Продвижение — СахGO';
require __DIR__ . '/../includes/header.php';
?>

<section style="padding:3rem 0 4rem">
<div style="max-width:40rem;margin:0 auto;padding:0 1rem">

  <!-- Header -->
  <div style="margin-bottom:2rem">
    <span style="font-size:0.6875rem;text-transform:uppercase;letter-spacing:0.1em;color:#7A8A9A;font-weight:500">Продвижение</span>
    <h1 style="font-family:Manrope,sans-serif;font-weight:700;font-size:2rem;letter-spacing:-0.02em;margin:0.25rem 0 0">Продвинуть объявление</h1>
  </div>

  <p style="font-size:0.875rem;color:#7A8A9A;margin:0 0 1.5rem">Объявление: <strong style="color:#121E2B"><?=h($listing['title'])?></strong></p>

  <!-- How it works -->
  <div style="background:linear-gradient(135deg,#F0F7FA,#F7F9FB);border:1px solid #DDE8F0;border-radius:12px;padding:1.5rem;margin-bottom:2rem">
    <h2 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.125rem;margin:0 0 0.75rem">Как работает продвижение</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem">
      <div style="display:flex;gap:0.625rem;align-items:flex-start">
        <div style="width:1.75rem;height:1.75rem;border-radius:50%;background:#1B6B8A;color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;flex-shrink:0">1</div>
        <div><div style="font-size:0.8125rem;font-weight:600;color:#121E2B">Выберите тариф</div><div style="font-size:0.75rem;color:#7A8A9A;margin-top:0.125rem">Top, Highlight или Срочно — в зависимости от целей</div></div>
      </div>
      <div style="display:flex;gap:0.625rem;align-items:flex-start">
        <div style="width:1.75rem;height:1.75rem;border-radius:50%;background:#1B6B8A;color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;flex-shrink:0">2</div>
        <div><div style="font-size:0.8125rem;font-weight:600;color:#121E2B">Отправьте заявку</div><div style="font-size:0.75rem;color:#7A8A9A;margin-top:0.125rem">Администратор подтвердит и активирует продвижение</div></div>
      </div>
      <div style="display:flex;gap:0.625rem;align-items:flex-start">
        <div style="width:1.75rem;height:1.75rem;border-radius:50%;background:#1B6B8A;color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;flex-shrink:0">3</div>
        <div><div style="font-size:0.8125rem;font-weight:600;color:#121E2B">Получите результат</div><div style="font-size:0.75rem;color:#7A8A9A;margin-top:0.125rem">Объявление попадёт в топ выдачи и привлечёт больше покупателей</div></div>
      </div>
    </div>
  </div>

  <!-- Tiers explained -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.75rem;margin-bottom:2rem">
    <div style="background:#FFF7E6;border:1px solid #FDE68A;border-left:4px solid #F59E0B;border-radius:10px;padding:1rem">
      <div style="font-family:Manrope,sans-serif;font-weight:700;font-size:1rem;color:#92400E">⭐ Top</div>
      <div style="font-size:0.75rem;color:#92400E;line-height:1.5;margin-top:0.375rem">Объявление закрепляется в самом верху каталога. Золотая рамка и приоритет над всеми остальными — максимум просмотров.</div>
    </div>
    <div style="background:#F5F0FF;border:1px solid #DDD6FE;border-left:4px solid #8B5CF6;border-radius:10px;padding:1rem">
      <div style="font-family:Manrope,sans-serif;font-weight:700;font-size:1rem;color:#5B21B6">💎 Highlight</div>
      <div style="font-size:0.75rem;color:#5B21B6;line-height:1.5;margin-top:0.375rem">Объявление получает фиолетовую рамку в выдаче. Выделяется среди обычных — привлекает внимание и поднимает конверсию.</div>
    </div>
    <div style="background:#FEF0F0;border:1px solid #FECACA;border-left:4px solid #EF4444;border-radius:10px;padding:1rem">
      <div style="font-family:Manrope,sans-serif;font-weight:700;font-size:1rem;color:#991B1B">🔥 Срочно</div>
      <div style="font-size:0.75rem;color:#991B1B;line-height:1.5;margin-top:0.375rem">Красная метка «Срочно» на объявлении. Покупатели видят, что продавец заинтересован в быстрой сделке — это ускоряет продажу.</div>
    </div>
  </div>

  <?php if ($activePromo): ?>
    <div style="text-align:center;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:12px;padding:2rem;margin-bottom:1.5rem">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="1.5" style="margin-bottom:0.75rem">
        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
      </svg>
      <p style="font-weight:600;color:#166534;margin:0.5rem 0 0.25rem;font-size:1rem">Уже продвигается!</p>
      <p style="font-size:0.8125rem;color:#166534;margin:0">Тип: <?=$activePromo['promo_type']?> &middot; До: <?=$activePromo['expires_at']?></p>
      <a href="/dashboard" class="btn-outline" style="display:inline-flex;margin-top:1rem">В кабинет</a>
    </div>
  <?php else: ?>
  <form method="post" style="background:#fff;border:1px solid #EEF2F6;border-radius:12px;padding:2rem;box-shadow:0 4px 12px rgba(15,23,32,0.06)">
    <?= csrf_field() ?>

    <!-- Duration -->
    <div style="margin-bottom:1.5rem">
      <label style="display:block;font-size:0.8125rem;font-weight:500;color:#3A4A5C;margin-bottom:0.5rem">Срок продвижения</label>
      <div style="display:flex;gap:0.5rem">
        <?php foreach ([7=>'7 дней',14=>'14 дней',30=>'30 дней'] as $d => $lbl): ?>
        <label style="flex:1;text-align:center;padding:0.625rem 0.5rem;border-radius:8px;border:1px solid #DFE4EA;font-size:0.8125rem;font-weight:500;cursor:pointer;transition:all 0.15s ease;<?=$d===7?'background:rgba(27,107,138,0.04);border-color:#1B6B8A;color:#1B6B8A':''?>" data-duration="<?=$d?>" onclick="selectDuration(<?=$d?>,this)">
          <input type="radio" name="duration" value="<?=$d?>" <?=$d===7?'checked':''?> hidden>
          <?=$lbl?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Plan cards -->
    <?php
    $planTypes = ['top','highlight','urgent'];
    $planIcons = [
      'top'       => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="15 10 12 3 3 21 12 12 9 21 21 8 12 14"/></svg>',
      'highlight' => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M15 14c.7 0 1.2-.5 1.2-1.2 0-.4-.2-.7-.5-.9L10 8c-.3-.2-.7-.2-1 0l-5.7 3.9c-.3.2-.5.5-.5.9C2.8 13.5 3.3 14 4 14h11z"/><path d="M4 14v5a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5"/></svg>',
      'urgent'    => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
    ];
    $planNames = ['top'=>'Top','highlight'=>'Highlight','urgent'=>'Срочно'];
    $planDescs = ['top'=>'Наверху списка','highlight'=>'Выделено цветом','urgent'=>'Метка срочности'];
    $planFeats = [
      'top'       => ['Максимальная видимость','Первое место в выдаче','Статистика показов'],
      'highlight' => ['Яркий фон в выдаче','Выше обычных','Статистика кликов'],
      'urgent'    => ['Красная метка','Привлекает внимание','Повышает конверсию'],
    ];
    ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.75rem;margin-bottom:1.5rem">
      <?php foreach ($planTypes as $t): ?>
      <label style="cursor:pointer" data-type="<?=$t?>">
        <input type="radio" name="promo_type" value="<?=$t?>" <?=$t==='top'?'checked':''?> hidden onchange="selectPlan(this)">
        <div class="promo-card-v3" style="border:2px solid <?=$t==='top'?'#1B6B8A':'#DFE4EA'?>;border-radius:12px;padding:1.25rem;text-align:center;transition:all 0.2s ease;<?=$t==='top'?'background:rgba(27,107,138,0.03)':''?>">
          <div style="color:<?=$t==='top'?'#1B6B8A':'#7A8A9A'?>;margin-bottom:0.5rem"><?=$planIcons[$t]?></div>
          <div style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.0625rem;color:#121E2B"><?=$planNames[$t]?></div>
          <div style="font-size:0.75rem;color:#7A8A9A;margin:0.25rem 0 0.5rem"><?=$planDescs[$t]?></div>
          <div style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.125rem;color:#1B6B8A;margin-bottom:0.75rem" class="package-price" id="price_<?=$t?>"><?=number_format($prices[$t][7],0,',',' ')?> ₽</div>
          <ul style="list-style:none;padding:0;margin:0;font-size:0.75rem;color:#7A8A9A;text-align:left">
            <?php foreach($planFeats[$t] as $f):?>
            <li style="padding:0.25rem 0;display:flex;align-items:center;gap:0.375rem">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
              <?=$f?>
            </li>
            <?php endforeach;?>
          </ul>
        </div>
      </label>
      <?php endforeach; ?>
    </div>

    <!-- Summary -->
    <div style="background:#F7F9FB;border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem">
      <div style="display:flex;justify-content:space-between;font-size:0.8125rem;padding:0.25rem 0">
        <span style="color:#7A8A9A">Пакет</span>
        <span style="font-weight:500;color:#121E2B" id="packageLabel">Top &middot; 7 дней</span>
      </div>
      <div style="height:1px;background:#EEF2F6;margin:0.625rem 0"></div>
      <div style="display:flex;justify-content:space-between;font-size:0.9375rem">
        <span style="font-weight:600">Итого</span>
        <span style="font-family:Manrope,sans-serif;font-weight:700;color:#121E2B" id="totalPrice"><?=number_format($prices['top'][7],0,',',' ')?> ₽</span>
      </div>
    </div>

    <div style="display:flex;gap:0.75rem">
      <a href="/dashboard" class="btn-outline" style="flex:1;text-align:center;justify-content:center">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:0.25rem"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        В кабинет
      </a>
      <button type="submit" name="promote" class="cta-btn" style="flex:3;gap:0.375rem">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        Отправить заявку
      </button>
    </div>
  </form>
  <?php endif; ?>
</div>
</section>

<script>
var prices = <?=json_encode($prices)?>;
var typeNames = {top:'Top', highlight:'Highlight', urgent:'Срочно'};

function selectPlan(input) {
  var cards = document.querySelectorAll('.promo-card-v3');
  cards.forEach(function(c){c.style.borderColor='#DFE4EA';c.style.background=''});
  var card = input.closest('label').querySelector('.promo-card-v3');
  card.style.borderColor = '#1B6B8A';
  card.style.background = 'rgba(27,107,138,0.03)';
  // Update icon color
  card.querySelector('svg').parentElement.style.color = '#1B6B8A';
  updatePrice();
}

function selectDuration(days, el) {
  var labels = el.parentElement.querySelectorAll('label');
  labels.forEach(function(l){l.style.cssText='flex:1;text-align:center;padding:0.625rem 0.5rem;border-radius:8px;border:1px solid #DFE4EA;font-size:0.8125rem;font-weight:500;cursor:pointer;transition:all 0.15s ease'});
  el.style.borderColor = '#1B6B8A';
  el.style.background = 'rgba(27,107,138,0.04)';
  el.style.color = '#1B6B8A';
  updateCardPrices();
  updatePrice();
}

function updateCardPrices() {
  var days = document.querySelector('input[name="duration"]:checked').value;
  ['top','highlight','urgent'].forEach(function(t) {
    var el = document.getElementById('price_' + t);
    if (el && prices[t] && prices[t][days]) {
      el.textContent = prices[t][days].toLocaleString('ru-RU') + ' ₽';
    }
  });
}

function updatePrice() {
  var type = document.querySelector('input[name="promo_type"]:checked').value;
  var days = document.querySelector('input[name="duration"]:checked').value;
  var total = (prices[type] && prices[type][days]) ? prices[type][days] : 0;
  document.getElementById('packageLabel').textContent = typeNames[type] + ' · ' + days + ' дней';
  document.getElementById('totalPrice').textContent = total.toLocaleString('ru-RU') + ' ₽';
}

updateCardPrices();

// Init duration pills
document.querySelectorAll('[data-duration]').forEach(function(el){
  el.addEventListener('click', function(){selectDuration(parseInt(el.dataset.duration), el)});
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
