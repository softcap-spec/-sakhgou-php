<?php
// 404.php
http_response_code(404);
$page_title = '404 — Страница не найдена — СахGO';
require __DIR__ . '/../includes/header.php';
?>
<main class="py-32">
  <div class="max-w-md mx-auto px-4 text-center">
    <div class="text-7xl mb-6">🔍</div>
    <h1 class="font-display text-4xl mb-4">Страница не найдена</h1>
    <p class="text-muted-foreground mb-8">Такой страницы не существует. Возможно, объявление снято с публикации или вы ошиблись адресом.</p>
    <a href="/" class="inline-flex items-center justify-center rounded-lg bg-accent text-white hover:bg-accent/80 h-10 px-6 font-medium transition-all">На главную</a>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
