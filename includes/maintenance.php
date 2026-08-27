<?php
// maintenance.php — заглушка режима «Технические работы» (HTTP 503)
// Самодостаточная: без внешних CDN, с SEO-мета. Отдаётся посетителям; админка и API работают.
http_response_code(503);
header('Retry-After: 3600');
?><!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0B1C2B">
<title>Технические работы — СахGO · Туристический маркетплейс Сахалина</title>
<meta name="description" content="СахGO — туры, жильё, рыбалка, прокат авто и снаряжения на Сахалине и Курилах без посредников. На сайте ведутся технические работы — мы скоро вернёмся.">
<meta name="robots" content="noindex, nofollow">
<link rel="canonical" href="https://сахгоу.рф/">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="icon" type="image/png" sizes="256x256" href="/favicon.png">
<link rel="apple-touch-icon" href="/favicon.png">
<meta property="og:type" content="website">
<meta property="og:site_name" content="СахGO">
<meta property="og:title" content="Технические работы — СахGO">
<meta property="og:description" content="Туристический маркетплейс Сахалина и Курил: туры, жильё, рыбалка, прокат авто и снаряжения. Мы скоро вернёмся.">
<meta property="og:url" content="https://сахгоу.рф/">
<meta property="og:image" content="https://сахгоу.рф/og-image.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="ru_RU">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Технические работы — СахGO">
<meta name="twitter:description" content="Туристический маркетплейс Сахалина и Курил. Мы скоро вернёмся.">
<meta name="twitter:image" content="https://сахгоу.рф/og-image.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  *{margin:0;padding:0;box-sizing:border-box}
  html,body{height:100%}
  body{
    font-family:'Manrope',-apple-system,'Segoe UI',Arial,sans-serif;
    background:#0B1C2B;color:#EAF3F8;min-height:100vh;
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    overflow:hidden;position:relative;
    -webkit-font-smoothing:antialiased;
  }
  /* ── фон: анимированные блобы ── */
  .blob{position:absolute;border-radius:50%;filter:blur(90px);opacity:.55;z-index:0;animation:drift 22s ease-in-out infinite alternate}
  .b1{width:44vw;height:44vw;background:radial-gradient(circle,#1B6B8A,transparent 65%);top:-14vw;left:-10vw}
  .b2{width:38vw;height:38vw;background:radial-gradient(circle,#0E7490,transparent 65%);bottom:-12vw;right:-8vw;animation-delay:-7s}
  .b3{width:26vw;height:26vw;background:radial-gradient(circle,#155E75,transparent 65%);bottom:6vh;left:16vw;animation-delay:-13s;opacity:.4}
  @keyframes drift{from{transform:translate(0,0) scale(1)}to{transform:translate(6vw,4vh) scale(1.15)}}
  .grid-lines{position:absolute;inset:0;z-index:0;opacity:.5;
    background-image:linear-gradient(rgba(148,197,222,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(148,197,222,.06) 1px,transparent 1px);
    background-size:56px 56px;mask-image:radial-gradient(ellipse 70% 60% at 50% 45%,#000 30%,transparent 75%);-webkit-mask-image:radial-gradient(ellipse 70% 60% at 50% 45%,#000 30%,transparent 75%)}
  /* ── плавающие теги ── */
  .tag{position:absolute;z-index:1;padding:7px 15px;border-radius:999px;font-size:13px;font-weight:500;color:#D7ECF7;
    background:rgba(255,255,255,.06);border:1px solid rgba(163,214,238,.22);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);
    white-space:nowrap;box-shadow:0 6px 22px rgba(0,0,0,.22);
    animation:tagFloat 6s ease-in-out infinite alternate,flicker 3.4s ease-in-out infinite}
  @keyframes tagFloat{from{transform:translateY(-12px)}to{transform:translateY(12px)}}
  @keyframes flicker{0%,100%{opacity:.4}50%{opacity:1}}
  .t1{top:9%;left:6%}.t2{top:16%;right:8%}.t3{top:6%;right:26%;animation-delay:-1s}
  .t4{bottom:18%;left:8%;animation-delay:-2s}.t5{bottom:9%;right:12%;animation-delay:-.6s}
  .t6{top:42%;left:3%;animation-delay:-1.6s}.t7{top:38%;right:3%;animation-delay:-2.4s}
  .t8{bottom:32%;left:16%;animation-delay:-.9s}.t9{top:24%;left:22%;animation-delay:-3s;display:none}
  .t10{bottom:12%;left:34%;animation-delay:-1.3s;display:none}
  @media(min-width:900px){.t9,.t10{display:block}}
  /* ── карточка ── */
  .card{position:relative;z-index:2;max-width:600px;width:calc(100% - 48px);text-align:center;padding:0 8px}
  .logo-chip{width:120px;height:120px;margin:0 auto 26px;border-radius:28px;background:#fff;
    display:flex;align-items:center;justify-content:center;box-shadow:0 18px 50px rgba(0,0,0,.35);
    animation:logoPulse 3.6s ease-in-out infinite}
  .logo-chip img{height:84px;width:auto}
  @keyframes logoPulse{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
  h1{font-size:2.6rem;font-weight:800;letter-spacing:-.02em;line-height:1.1}
  .badge{display:inline-flex;align-items:center;gap:8px;margin-top:16px;padding:7px 16px;border-radius:999px;
    background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.35);color:#FCD34D;font-size:13px;font-weight:600;letter-spacing:.02em}
  .badge .dot{width:8px;height:8px;border-radius:50%;background:#F59E0B;animation:blink 1.2s ease-in-out infinite}
  @keyframes blink{0%,100%{opacity:1;box-shadow:0 0 0 0 rgba(245,158,11,.5)}50%{opacity:.55;box-shadow:0 0 0 5px rgba(245,158,11,0)}}
  .lead{margin-top:18px;font-size:1.35rem;font-weight:600;color:#F2F9FD}
  .desc{margin-top:12px;font-size:.95rem;line-height:1.65;color:#A9C4D4;max-width:480px;margin-left:auto;margin-right:auto}
  .progress{width:min(340px,80%);height:5px;margin:30px auto 0;border-radius:999px;background:rgba(255,255,255,.1);overflow:hidden}
  .progress i{display:block;height:100%;width:42%;border-radius:999px;background:linear-gradient(90deg,#1B6B8A,#38BDF8);
    animation:shimmer 1.8s ease-in-out infinite}
  @keyframes shimmer{0%{transform:translateX(-120%)}100%{transform:translateX(340px)}}
  .hint{margin-top:14px;font-size:.8rem;color:#7FA1B5}
  footer{position:absolute;bottom:22px;left:0;right:0;z-index:2;text-align:center;font-size:.78rem;color:#6E8CA0}
</style>
</head>
<body>
  <div class="blob b1"></div><div class="blob b2"></div><div class="blob b3"></div>
  <div class="grid-lines"></div>

  <div class="tag t1">🏔 Туры по Сахалину</div>
  <div class="tag t2">🎣 Рыбалка</div>
  <div class="tag t3">🏡 Жильё у моря</div>
  <div class="tag t4">🌋 Курилы</div>
  <div class="tag t5">🚙 Джип-туры</div>
  <div class="tag t6">⛺ Снаряжение</div>
  <div class="tag t7">🚐 Прокат авто</div>
  <div class="tag t8">🛥 Морские выходы</div>
  <div class="tag t9">🐻 Медведи и вулканы</div>
  <div class="tag t10">🎿 Фрирайд</div>

  <main class="card">
    <div class="logo-chip"><img src="/logo.png" alt="СахGO"></div>
    <h1>СахGO</h1>
    <div class="badge"><span class="dot"></span> Технические работы</div>
    <p class="lead">Мы скоро вернёмся</p>
    <p class="desc">СахGO — туристический маркетплейс Сахалина и Курил: туры, жильё, рыбалка, прокат авто и снаряжения. Прямые контакты с местными проводниками и владельцами — без посредников.</p>
    <div class="progress"><i></i></div>
    <p class="hint">Прямо сейчас делаем сервис быстрее и удобнее. Загляните через несколько минут.</p>
  </main>

  <footer>© <span id="yr"></span> СахGO · Сахалинская область</footer>

  <script>document.getElementById('yr').textContent = new Date().getFullYear();</script>
</body>
</html>
