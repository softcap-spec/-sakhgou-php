<?php
// stats.php — статистика объявления для продавца
$user = auth_required();
$listing_id = (int)($id ?? 0);
$pdo = db();

$st = $pdo->prepare('SELECT * FROM listings WHERE id = ?');
$st->execute([$listing_id]);
$item = $st->fetch();

if (!$item || ($item['user_id'] != $user['id'] && $user['role'] !== 'admin')) {
  http_response_code(404);
  $page_title = 'Не найдено — СахGO';
  require __DIR__ . '/../includes/header.php';
  echo '<section style="padding:5rem 0"><div style="max-width:46rem;margin:0 auto;padding:0 1rem;text-align:center"><p style="font-size:1rem;font-weight:600">Объявление не найдено или нет доступа</p><a href="/dashboard" class="cta-btn" style="display:inline-flex;margin-top:1rem">В кабинет</a></div></section>';
  require __DIR__ . '/../includes/footer.php';
  exit;
}

header('Cache-Control: no-store, must-revalidate');

$period = in_array($_GET['p'] ?? '30', ['7', '30', '90'], true) ? (int)($_GET['p'] ?? '30') : 30;
$to = date('Y-m-d');
$from = date('Y-m-d', strtotime('-' . ($period - 1) . ' days'));

bookings_expire_pendings($listing_id);

// дневные ряды
$series = stats_series($listing_id, ['view', 'phone', 'chat'], $from, $to);
$days = [];
for ($d = strtotime($from); $d <= strtotime($to); $d = strtotime('+1 day', $d)) {
  $ds = date('Y-m-d', $d);
  $days[$ds] = [
    'view' => $series[$ds]['view'] ?? 0,
    'phone' => $series[$ds]['phone'] ?? 0,
    'chat' => $series[$ds]['chat'] ?? 0,
  ];
}

// промо-периоды
$st = $pdo->prepare('SELECT promo_type, status, starts_at, expires_at FROM promotions WHERE listing_id = ? ORDER BY starts_at');
$st->execute([$listing_id]);
$promos = $st->fetchAll();

// брони за период
$st = $pdo->prepare("SELECT COUNT(*) AS total, SUM(status='confirmed') AS confirmed, SUM(status='pending') AS pending, SUM(status='declined') AS declined, COALESCE(SUM(CASE WHEN status='confirmed' THEN total_price END),0) AS revenue FROM bookings WHERE listing_id = ? AND created_at >= ?");
$st->execute([$listing_id, $from . ' 00:00:00']);
$bk = $st->fetch();

// избранное всего
$st = $pdo->prepare('SELECT COUNT(*) FROM favorites WHERE listing_id = ?');
$st->execute([$listing_id]);
$favorites = (int)$st->fetchColumn();

// KPI
$viewsTotal = array_sum(array_column($days, 'view'));
$phoneTotal = array_sum(array_column($days, 'phone'));
$chatTotal = array_sum(array_column($days, 'chat'));
$bookTotal = (int)($bk['total'] ?? 0);
$bookConfirmed = (int)($bk['confirmed'] ?? 0);
$revenue = (float)($bk['revenue'] ?? 0);
$conversion = $viewsTotal > 0 ? round($bookTotal / $viewsTotal * 100, 1) : 0.0;
$perDay = round($viewsTotal / $period, 1);

// сравнение промо: во время / до / после (средние просмотры в день)
function stats_avg_views(int $lid, string $f, string $t): ?float {
  if (strtotime($t) < strtotime($f)) return null;
  $s = stats_series($lid, ['view'], $f, $t);
  $sum = 0; $n = 0;
  for ($d = strtotime($f); $d <= strtotime($t); $d = strtotime('+1 day', $d)) {
    $ds = date('Y-m-d', $d);
    $sum += $s[$ds]['view'] ?? 0; $n++;
  }
  return $n > 0 ? round($sum / $n, 1) : null;
}
$promoCompare = [];
foreach ($promos as $pr) {
  $s = strtotime($pr['starts_at']); $e = strtotime($pr['expires_at']);
  $typeLabel = $pr['promo_type'] === 'top' ? 'Top' : ($pr['promo_type'] === 'highlight' ? 'Highlight' : ($pr['promo_type'] === 'urgent' ? 'Срочно' : $pr['promo_type']));
  $promoCompare[] = [
    'label' => $typeLabel . ' · ' . date('d.m', $s) . '—' . date('d.m', $e),
    'status' => $pr['status'],
    'during' => stats_avg_views($listing_id, $pr['starts_at'], $pr['expires_at']),
    'before' => stats_avg_views($listing_id, date('Y-m-d', $s - 14 * 86400), date('Y-m-d', $s - 86400)),
    'after' => $e < time() ? stats_avg_views($listing_id, date('Y-m-d', $e + 86400), date('Y-m-d', min($e + 14 * 86400, time()))) : null,
  ];
}

$page_title = 'Статистика — СахGO';
require __DIR__ . '/../includes/header.php';

$h2 = 'font-family:Manrope,sans-serif;font-weight:600;font-size:1.0625rem;color:#121E2B;margin:1.75rem 0 0.625rem;letter-spacing:-0.01em;padding-top:1.25rem;border-top:1px solid #EEF2F6';
?>

<section style="padding:2.5rem 0 4rem">
  <style>
    .st-wrap{max-width:1080px;margin:0 auto;padding:1.5rem 1rem 4rem}
    .st-back{display:inline-flex;align-items:center;gap:0.375rem;color:#7A8A9A;text-decoration:none;font-size:0.8125rem;margin-bottom:0.875rem}
    .st-back:hover{color:#1B6B8A}
    .st-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1.25rem}
    .st-title{font-family:Manrope,sans-serif;font-weight:800;font-size:1.4rem;letter-spacing:-0.02em;margin:0;color:#121E2B}
    .st-sub{color:#7A8A9A;font-size:0.8125rem;margin:0.25rem 0 0}
    .st-kpis{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:0.875rem;margin-bottom:1.25rem}
    .st-kpi{background:#fff;border:1px solid #EEF2F6;border-radius:14px;padding:1rem 1.125rem;box-shadow:0 4px 14px rgba(15,23,32,0.05)}
    .st-kpi .v{font-family:Manrope,sans-serif;font-weight:800;font-size:1.5rem;color:#121E2B;line-height:1.15}
    .st-kpi .l{font-size:0.75rem;color:#7A8A9A;margin-top:0.125rem}
    .st-card{background:#fff;border:1px solid #EEF2F6;border-radius:16px;padding:1.5rem;box-shadow:0 4px 14px rgba(15,23,32,0.05);margin-bottom:1.25rem}
    .st-card h2{font-family:Manrope,sans-serif;font-weight:700;font-size:1rem;color:#121E2B;margin:0 0 1rem}
    .st-seg{display:inline-flex;background:#EEF2F6;border-radius:10px;padding:0.1875rem}
    .st-seg a{padding:0.3125rem 0.75rem;font-size:0.75rem;font-weight:600;border-radius:8px;text-decoration:none;color:#54677A}
    .st-seg a.on{background:#fff;color:#121E2B;box-shadow:0 1px 3px rgba(15,23,32,0.12)}
    .st-legend{display:flex;gap:1rem;flex-wrap:wrap;font-size:0.75rem;color:#54677A;margin-bottom:0.625rem;align-items:center}
    .st-legend i{display:inline-block;width:12px;height:3px;border-radius:2px;margin-right:5px;vertical-align:middle}
    .st-table{width:100%;border-collapse:collapse;font-size:0.8125rem}
    .st-table th,.st-table td{padding:0.5rem 0.625rem;border-bottom:1px solid #EEF2F6;text-align:left;color:#3A4A5C}
    .st-table th{color:#7A8A9A;font-size:0.7rem;text-transform:uppercase;letter-spacing:0.04em}
  </style>
  <div class="st-wrap">
    <a class="st-back" href="/dashboard"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg> Кабинет</a>
    <div class="st-head">
      <div>
        <h1 class="st-title">Статистика · <?= h($item['title']) ?></h1>
        <p class="st-sub">Объявление №<?= (int)$item['id'] ?> · собрано с 29.08.2026</p>
      </div>
      <div class="st-seg">
        <?php foreach ([7, 30, 90] as $pr): ?>
        <a href="/stats/<?= (int)$listing_id ?>?p=<?=$pr?>" class="<?=$period === $pr ? 'on' : ''?>"><?=$pr?> дн.</a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="st-kpis">
      <div class="st-kpi"><div class="v"><?= number_format($viewsTotal, 0, ',', ' ') ?></div><div class="l">Просмотров за <?=$period?> дн.</div></div>
      <div class="st-kpi"><div class="v"><?=$perDay?></div><div class="l">Просмотров в день</div></div>
      <div class="st-kpi"><div class="v"><?= number_format($phoneTotal + $chatTotal, 0, ',', ' ') ?></div><div class="l">Контактов (звонок + чат)</div></div>
      <div class="st-kpi"><div class="v"><?= number_format($bookTotal, 0, ',', ' ') ?></div><div class="l">Заявок на бронь</div></div>
      <div class="st-kpi"><div class="v"><?=$conversion?>%</div><div class="l">Просмотр → заявка</div></div>
      <div class="st-kpi"><div class="v"><?= $bookConfirmed ?></div><div class="l">Подтверждено броней</div></div>
      <div class="st-kpi"><div class="v"><?=$favorites?></div><div class="l">В избранном</div></div>
    </div>

    <div class="st-card">
      <h2>Просмотры и контакты</h2>
      <div class="st-legend">
        <span><i style="background:#1B6B8A"></i>Просмотры</span>
        <span><i style="background:#F59E0B"></i>Звонки</span>
        <span><i style="background:#8B5CF6"></i>Чаты</span>
        <span><i style="background:rgba(167,139,250,0.25);height:12px"></i>период продвижения</span>
      </div>
      <svg viewBox="0 0 720 230" style="width:100%;height:auto;display:block">
        <?php
        $n = count($days);
        $keys = array_keys($days);
        $maxV = 1;
        foreach ($days as $d) $maxV = max($maxV, $d['view']);
        $L = 30; $R = 700; $T = 12; $B = 200;
        $x = function ($i) use ($n, $L, $R) { return $n > 1 ? $L + $i * (($R - $L) / ($n - 1)) : ($L + $R) / 2; };
        $y = function ($v) use ($maxV, $T, $B) { return $B - ($v / $maxV) * ($B - $T); };
        // зоны промо
        foreach ($promos as $pr) {
          $s = max(strtotime($pr['starts_at']), strtotime($from));
          $e2 = min(strtotime($pr['expires_at']), strtotime($to));
          if ($e2 < $s) continue;
          $x1 = $x(max(0, round(($s - strtotime($from)) / 86400)));
          $x2 = $x(min($n - 1, round(($e2 - strtotime($from)) / 86400)));
          echo '<rect x="' . $x1 . '" y="' . $T . '" width="' . max(2, $x2 - $x1) . '" height="' . ($B - $T) . '" fill="rgba(167,139,250,0.16)"/>';
        }
        // сетка
        for ($g = 0; $g <= 4; $g++) {
          $gy = $T + $g * (($B - $T) / 4);
          echo '<line x1="' . $L . '" y1="' . $gy . '" x2="' . $R . '" y2="' . $gy . '" stroke="#EEF2F6" stroke-width="1"/>';
        }
        // линии
        foreach ([['view', '#1B6B8A', 2], ['phone', '#F59E0B', 1.5], ['chat', '#8B5CF6', 1.5]] as $ln) {
          $pts = '';
          $i = 0;
          foreach ($days as $ds => $v) { $pts .= $x($i) . ',' . $y($v[$ln[0]]) . ' '; $i++; }
          echo '<polyline points="' . trim($pts) . '" fill="none" stroke="' . $ln[1] . '" stroke-width="' . $ln[2] . '" stroke-linejoin="round"/>';
        }
        // подписи X
        $step = max(1, (int)ceil($n / 6));
        $i = 0;
        foreach ($days as $ds => $v) {
          if ($i % $step === 0) echo '<text x="' . $x($i) . '" y="' . ($B + 16) . '" font-size="10" fill="#9AAAB8" text-anchor="middle">' . date('d.m', strtotime($ds)) . '</text>';
          $i++;
        }
        ?>
      </svg>
    </div>

    <div class="st-card">
      <h2>Эффект продвижения</h2>
      <?php if (!$promoCompare): ?>
        <p style="font-size:0.8125rem;color:#7A8A9A;margin:0">Это объявление ещё не продвигалось.</p>
      <?php else: ?>
        <table class="st-table">
          <tr><th>Продвижение</th><th>До (ср. в день)</th><th>Во время</th><th>После</th></tr>
          <?php foreach ($promoCompare as $c): ?>
          <tr>
            <td><?=h($c['label'])?> <span style="font-size:0.6875rem;color:#9AAAB8">(<?=h($c['status'])?>)</span></td>
            <td><?= $c['before'] ?? '—' ?></td>
            <td style="font-weight:700"><?= $c['during'] ?? '—' ?></td>
            <td><?= $c['after'] ?? '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>

    <div class="st-card">
      <h2>Заявки на бронь за <?=$period?> дн.</h2>
      <p style="font-size:0.8125rem;color:#3A4A5C;margin:0 0 0.625rem">Всего: <b><?= $bookTotal ?></b> · Подтверждено: <b><?= $bookConfirmed ?></b> · Ожидают: <b><?= (int)($bk['pending'] ?? 0) ?></b> · Отклонено: <b><?= (int)($bk['declined'] ?? 0) ?></b> · Сумма подтверждённых: <b><?= number_format($revenue, 0, ',', ' ') ?> ₽</b></p>
      <p style="font-size:0.75rem;color:#9AAAB8;margin:0">Подробности по каждой заявке — во вкладке «Ко мне», управление — в календаре.</p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
