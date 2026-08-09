<?php
$page_title = $page_title ?? 'СахGO — жильё, туры, рыбалка и снаряжение. Сахалин и Курилы — ближе, чем кажется';
$page_description = $page_description ?? 'Маркетплейс туруслуг, жилья и рыбалки для Сахалинской области и Курильских островов.';
$cu = auth_user();
$my_count = $cu ? ($cu['unread_notifications'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0ea5e9">
<title><?= h($page_title) ?></title>
<meta name="description" content="<?= h($page_description) ?>">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://сахгоу.рф<?= h($_SERVER['REQUEST_URI'] ?? '/') ?>">
<meta property="og:title" content="СахGO">
<meta property="og:description" content="Маркетплейс туруслуг, жилья и рыбалки для Сахалина и Курил.">
<meta property="og:url" content="https://сахгоу.рф">
<meta property="og:site_name" content="СахGO">
<meta property="og:locale" content="ru_RU">
<meta property="og:image" content="https://сахгоу.рф/og-image.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:type" content="website">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="https://сахгоу.рф/og-image.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Manrope','Arial','sans-serif'],display:['Manrope','Arial','sans-serif']},colors:{background:'#F2F6F9',foreground:'#121E2B',card:'#FFFFFF','card-foreground':'#121E2B',popover:'#FFFFFF','popover-foreground':'#121E2B',primary:'#121E2B','primary-foreground':'#F2F6F9',secondary:'#E8F0F6','secondary-foreground':'#121E2B',muted:'#8BA0B5','muted-foreground':'#54677A',accent:'#1B6B8A','accent-fg':'#FFFFFF',destructive:'#DC2626',success:'#16A34A',warn:'#EAB308',border:'#D8E3ED',input:'#FFFFFF',ring:'#1B6B8A'},borderRadius:{sm:'0.375rem',DEFAULT:'0.625rem',md:'0.5rem',lg:'0.625rem',xl:'0.875rem'}}}}</script>
<link rel="stylesheet" href="/includes/style.css?v=8">
<style>
  *,::before,::after{--tw-border-spacing-x:0;--tw-border-spacing-y:0;--tw-translate-x:0;--tw-translate-y:0;--tw-rotate:0;--tw-skew-x:0;--tw-skew-y:0;--tw-scale-x:1;--tw-scale-y:1;--tw-scroll-snap-strictness:proximity;--tw-ring-offset-width:0px;--tw-ring-offset-color:#fff;--tw-ring-color:rgb(59 130 246 / 0.5);--tw-ring-offset-shadow:0 0 #0000;--tw-ring-shadow:0 0 #0000;--tw-shadow:0 0 #0000;--tw-shadow-colored:0 0 #0000}*,::before,::after{box-sizing:border-box;border-width:0;border-style:solid;border-color:#D8E3ED}html{line-height:1.5;-webkit-text-size-adjust:100%;tab-size:4;font-family:Manrope,Arial,sans-serif;overflow-y:scroll}body{margin:0;line-height:inherit;background-color:#F2F6F9;color:#121E2B;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}h1,h2,h3{letter-spacing:-0.015em}.font-display{font-family:Manrope,Arial,sans-serif}
</style>
</head>
<body class="min-h-screen flex flex-col">
<div class="flex-1 flex flex-col">

<header class="sticky top-0 z-50 bg-background/95 backdrop-blur-md border-b">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16 gap-4">
    <a href="/" class="flex items-center gap-2 shrink-0">
      <img src="/logo.png" alt="СахGO" class="h-10 sm:h-12 w-auto">
    </a>
    <div class="hidden md:flex items-center gap-0.5 bg-white border rounded-lg p-0.5">
      <?php foreach (['all'=>'Всё','property'=>'Жильё','tour'=>'Туры','fishing'=>'Рыбалка','rental_gear'=>'Снаряжение','car_rental'=>'Прокат авто'] as $k=>$v): 
        $active = ($k==='all' && empty($_GET['cat'])) || (isset($_GET['cat']) && $_GET['cat']===$k);
      ?>
      <a href="/catalog/<?=$k==='all'?'':$k?>" class="px-3.5 py-1.5 text-sm font-medium rounded-md transition-colors whitespace-nowrap <?=$active?'bg-accent text-white':'text-muted-foreground hover:text-foreground hover:bg-secondary'?>"><?=$v?></a>
      <?php endforeach; ?>
    </div>
    <div class="hidden sm:flex items-center gap-2 shrink-0">
      <?php if ($cu): ?>
        <?= avatar_html($cu, 'w-8 h-8', 'text-[0.65rem]') ?>
        <a href="/dashboard" class="inline-flex items-center justify-center h-7 gap-1 rounded-md px-2.5 text-[0.8rem] font-medium bg-accent text-white hover:bg-accent/80 transition-all">Кабинет</a>
        <?php if ($cu['role']==='admin'): ?>
        <a href="/admin" class="inline-flex items-center justify-center h-7 gap-1 rounded-md px-2.5 text-[0.8rem] font-medium hover:bg-muted hover:text-foreground transition-all">Админ</a>
        <?php endif; ?>
        <a href="/logout" class="inline-flex items-center justify-center h-7 gap-1 rounded-md px-2.5 text-[0.8rem] font-medium hover:bg-muted hover:text-foreground transition-all">Выйти</a>
      <?php else: ?>
        <a href="/login" class="inline-flex items-center justify-center h-7 gap-1 rounded-md px-2.5 text-[0.8rem] font-medium hover:bg-muted hover:text-foreground transition-all">Войти</a>
        <a href="/register" class="inline-flex items-center justify-center h-7 gap-1 rounded-md px-2.5 text-[0.8rem] font-medium bg-accent text-white hover:bg-accent/80 transition-all">Регистрация</a>
      <?php endif; ?>
    </div>
    <div class="flex items-center gap-2 sm:hidden">
      <?php if ($cu): ?>
        <?= avatar_html($cu, 'w-7 h-7', 'text-[0.6rem]') ?>
        <a href="/dashboard" class="inline-flex items-center justify-center h-7 gap-1 rounded-md px-2.5 text-[0.8rem] font-medium bg-accent text-white hover:bg-accent/80 transition-all">Кабинет</a>
      <?php else: ?>
        <a href="/login" class="inline-flex items-center justify-center h-7 gap-1 rounded-md px-2.5 text-[0.8rem] font-medium bg-accent text-white hover:bg-accent/80 transition-all">Войти</a>
      <?php endif; ?>
    </div>
  </div>
</header>
