<?php
// 404.php — v3 clean design
http_response_code(404);
$page_title = '404 — Страница не найдена — СахGO';
require __DIR__ . '/../includes/header.php';
?>

<main style="flex:1;display:flex;align-items:center;justify-content:center;padding:3rem 1rem">
  <div style="max-width:26rem;text-align:center">
    <div style="margin-bottom:1.5rem">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#C8D0DA" stroke-width="1" style="opacity:0.6">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        <line x1="8" y1="11" x2="14" y2="11" style="display:none"/>
      </svg>
      <div style="font-family:Manrope,sans-serif;font-weight:800;font-size:5rem;line-height:1;color:#121E2B;margin:0.5rem 0;letter-spacing:-0.03em">404</div>
    </div>
    <h1 style="font-family:Manrope,sans-serif;font-weight:700;font-size:1.5rem;letter-spacing:-0.02em;margin:0 0 0.5rem;color:#121E2B">Страница не найдена</h1>
    <p style="font-size:0.8125rem;color:#7A8A9A;margin:0 0 2rem;line-height:1.6">Такой страницы не существует. Возможно, объявление снято с публикации или вы ошиблись адресом.</p>
    <div style="display:flex;gap:0.75rem;justify-content:center">
      <a href="/" class="cta-btn" style="gap:0.375rem">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        На главную
      </a>
      <a href="/catalog" class="btn-outline">Каталог</a>
    </div>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
