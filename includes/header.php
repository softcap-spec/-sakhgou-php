<?php
$page_title = $page_title ?? 'СахGO — маркетплейс путешествий по Сахалину и Курильским островам: туры, жильё, рыбалка, прокат авто и снаряжение';
$page_description = $page_description ?? 'Туристический маркетплейс путешествий по Сахалину и Курильским островам. Поиск и бронирование туров, жилья, рыбалки, снаряжения и проката авто. Отдых на Сахалине без посредников.';
$cu = auth_user();
$my_count = $cu ? ($cu['unread_notifications'] ?? '') : '';
// Чистый canonical без query-параметров
$canonical_path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#1B6B8A">
<link rel="manifest" href="/manifest.json">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="СахGO">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="apple-touch-icon" href="/favicon.png">
<title><?= h($page_title) ?></title>
  <link rel="icon" type="image/x-icon" href="/favicon.ico">
  <link rel="icon" type="image/png" sizes="256x256" href="/favicon.png">
<meta name="description" content="<?= h($page_description) ?>">
<meta name="keywords" content="туры Сахалин, отдых на Сахалине, рыбалка Сахалин, жильё Сахалин, Курилы, прокат авто Сахалин, снаряжение, маркетплейс Сахалин, туроператор Сахалин, экскурсии Сахалин, базы отдыха Сахалин">
<link rel="canonical" href="https://сахгоу.рф<?= h($canonical_path) ?>">
<meta property="og:title" content="<?= h($page_title) ?>">
<meta property="og:description" content="Туристический маркетплейс путешествий по Сахалину и Курильским островам. Поиск и бронирование туров, жилья, рыбалки, снаряжения и проката авто.">
<meta property="og:url" content="<?= h('https://сахгоу.рф' . $canonical_path) ?>">
<meta property="og:site_name" content="СахGO">
<meta property="og:locale" content="ru_RU">
<meta property="og:image" content="<?= h($og_image ?? 'https://сахгоу.рф/og-image.png') ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:type" content="website">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="<?= h($og_image ?? 'https://сахгоу.рф/og-image.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Manrope','Arial','sans-serif'],display:['Manrope','Arial','sans-serif']},colors:{background:'#F0F4F8',foreground:'#0A1A2A',card:'#FFFFFF','card-foreground':'#0A1A2A',popover:'#FFFFFF','popover-foreground':'#0A1A2A',primary:'#0A1A2A','primary-foreground':'#F0F4F8',secondary:'#E8EDF2','secondary-foreground':'#0A1A2A',muted:'#8BA0B5','muted-foreground':'#5A6B7D',accent:'#0A7BBA','accent-fg':'#FFFFFF',destructive:'#DC2626',success:'#16A34A',warn:'#EAB308',border:'#D1DAE3',input:'#FFFFFF',ring:'#0A7BBA'},borderRadius:{sm:'0.375rem',DEFAULT:'0.5rem',md:'0.5rem',lg:'0.75rem',xl:'0.75rem'}}}}</script>
<link rel="stylesheet" href="/includes/style.css?v=32">
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "name": "СахGO",
  "alternateName": "сахгоу.рф",
  "url": "https://сахгоу.рф",
  "description": "Маркетплейс путешествий по Сахалину и Курильским островам: туры, жильё и рыбалка",
  "potentialAction": {
    "@type": "SearchAction",
    "target": "https://сахгоу.рф/search?q={search_term_string}",
    "query-input": "required name=search_term_string"
  }
}
</script>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "СахGO",
  "url": "https://сахгоу.рф",
  "logo": "https://сахгоу.рф/logo.png",
  "sameAs": []
}
</script>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "LocalBusiness",
  "@id": "https://сахгоу.рф/#business",
  "name": "СахGO — маркетплейс Сахалина",
  "description": "Туристический маркетплейс: туры, жильё, рыбалка, прокат авто и снаряжения на Сахалине и Курилах",
  "url": "https://сахгоу.рф",
  "areaServed": {
    "@type": "State",
    "name": "Сахалинская область"
  },
  "hasOfferCatalog": {
    "@type": "OfferCatalog",
    "name": "Туристические услуги Сахалина",
    "itemListElement": [
      {"@type": "Offer", "itemOffered": {"@type": "Service", "name": "Туры по Сахалину и Курильским островам"}},
      {"@type": "Offer", "itemOffered": {"@type": "Service", "name": "Жильё и базы отдыха"}},
      {"@type": "Offer", "itemOffered": {"@type": "Service", "name": "Рыбалка и охота"}},
      {"@type": "Offer", "itemOffered": {"@type": "Service", "name": "Прокат авто и снаряжения"}}
    ]
  }
}
</script>
<style>
  *,::before,::after{--tw-border-spacing-x:0;--tw-border-spacing-y:0;--tw-translate-x:0;--tw-translate-y:0;--tw-rotate:0;--tw-skew-x:0;--tw-skew-y:0;--tw-scale-x:1;--tw-scale-y:1;--tw-scroll-snap-strictness:proximity;--tw-ring-offset-width:0px;--tw-ring-offset-color:#fff;--tw-ring-color:rgb(59 130 246 / 0.5);--tw-ring-offset-shadow:0 0 #0000;--tw-ring-shadow:0 0 #0000;--tw-shadow:0 0 #0000;--tw-shadow-colored:0 0 #0000}*,::before,::after{box-sizing:border-box;border-width:0;border-style:solid;border-color:#DFE4EA}html{line-height:1.5;-webkit-text-size-adjust:100%;tab-size:4;font-family:Manrope,Arial,sans-serif;overflow-y:scroll}body{margin:0;line-height:inherit;background-color:#F7F9FB;color:#121E2B;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}h1,h2,h3{letter-spacing:-0.02em}.font-display{font-family:Manrope,Arial,sans-serif;font-weight:700}
</style>
</head>
<body class="min-h-screen flex flex-col">
<div class="flex-1 flex flex-col">

<header class="sticky top-0 z-50">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16 gap-4">
    <a href="/" class="flex items-center gap-2 shrink-0">
      <img src="/logo.png" alt="СахGO" class="h-12 sm:h-14 w-auto">
    </a>
    <div class="hidden md:flex items-center gap-0.5 bg-white border border-[#DFE4EA] rounded-lg p-0.5">
      <?php foreach (['all'=>'Все','property'=>'Жильё','tour'=>'Туры','fishing'=>'Рыбалка','rental_gear'=>'Снаряжение','car_rental'=>'Прокат авто'] as $k=>$v):
        $cur_cat = (($page ?? '') === 'catalog') ? (string)($sub ?? '') : '';
        $active = ($k === 'all') ? ($cur_cat === '') : ($cur_cat === $k);
      ?>
      <a href="/catalog/<?=$k==='all'?'':$k?>" class="px-3 py-1.5 text-sm font-medium rounded-md transition-colors whitespace-nowrap <?=$active?'bg-accent text-white':'text-[#54677A] hover:text-foreground hover:bg-[#EEF2F6]'?>"><?=$v?></a>
      <?php endforeach; ?>
    </div>
    <div class="hidden sm:flex items-center gap-2 shrink-0">
      <?php if ($cu): ?>
        <?= avatar_html($cu, 'w-8 h-8', 'text-[0.65rem]') ?>
        <a href="/dashboard" class="inline-flex items-center justify-center h-8 rounded-lg px-3 text-sm font-semibold bg-accent text-white hover:bg-accent/90 transition-colors">Кабинет</a>
        <?php if ($cu['role']==='admin'): ?>
        <a href="/admin" class="inline-flex items-center justify-center h-8 rounded-lg px-3 text-sm font-medium text-[#54677A] hover:text-foreground hover:bg-[#EEF2F6] transition-colors">Админ</a>
        <?php endif; ?>
        <a href="/logout" class="inline-flex items-center justify-center h-8 rounded-lg px-3 text-sm font-medium text-[#7A8A9A] hover:text-foreground hover:bg-[#EEF2F6] transition-colors">Выйти</a>
      <?php else: ?>
        <a href="/login" class="inline-flex items-center justify-center h-8 rounded-lg px-3 text-sm font-medium text-[#54677A] hover:text-foreground hover:bg-[#EEF2F6] transition-colors">Войти</a>
        <a href="/register" class="inline-flex items-center justify-center h-8 rounded-lg px-3 text-sm font-semibold bg-accent text-white hover:bg-accent/90 transition-colors">Регистрация</a>
      <?php endif; ?>
    </div>
    <div class="flex items-center gap-2 sm:hidden">
      <?php if ($cu): ?>
        <?= avatar_html($cu, 'w-7 h-7', 'text-[0.6rem]') ?>
        <?php if (($cu['role'] ?? '') === 'admin'): ?><a href="/admin" class="inline-flex items-center justify-center h-8 rounded-lg px-3 text-sm font-medium text-[#54677A] border border-[#DFE4EA] transition-colors">Админ</a><?php endif; ?>
        <a href="/dashboard" class="inline-flex items-center justify-center h-8 rounded-lg px-3 text-sm font-semibold bg-accent text-white hover:bg-accent/90 transition-colors">Кабинет</a>
      <?php else: ?>
        <a href="/login" class="inline-flex items-center justify-center h-8 rounded-lg px-3 text-sm font-semibold bg-accent text-white hover:bg-accent/90 transition-colors">Войти</a>
      <?php endif; ?>
    </div>
  </div>
</header>
