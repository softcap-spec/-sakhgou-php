<?php
// cookie_consent.php — баннер согласия на cookie/аналитику (ст. 9 ФЗ № 152-ФЗ).
// Показывается новым посетителям; выбор сохраняется в cookie sg_consent (1 год).
// Яндекс.Метрика загружается только при sg_consent=accepted (см. metrics_counter.php).
?>
<div id="sgCookieBanner" style="display:none;position:fixed;left:0;right:0;bottom:0;z-index:70;background:#0A1A2A;color:#fff;box-shadow:0 -4px 24px rgba(0,0,0,0.22)">
  <div style="max-width:72rem;margin:0 auto;padding:1rem 1rem 1.25rem;display:flex;flex-wrap:wrap;align-items:center;gap:0.875rem 1.5rem">
    <div style="flex:1;min-width:240px;font-size:0.8125rem;line-height:1.65;color:#E6EDF3">
      Мы используем файлы cookie и сервис веб-аналитики Яндекс.Метрика для корректной работы сайта и анализа посещаемости. Нажимая «Принять», вы соглашаетесь с обработкой данных в соответствии с <a href="/privacy" style="color:#7CC4F0;text-decoration:underline">Политикой конфиденциальности</a>. Вы можете отказаться от аналитических cookie.
    </div>
    <div style="display:flex;align-items:center;gap:0.625rem;flex-shrink:0">
      <button type="button" onclick="sgSetConsent('accepted')" style="background:#1B6B8A;color:#fff;border:0;border-radius:8px;padding:0.625rem 1.375rem;font-size:0.8125rem;font-weight:600;cursor:pointer;font-family:inherit">Принять</button>
      <button type="button" onclick="sgSetConsent('declined')" style="background:transparent;color:#E6EDF3;border:1px solid rgba(255,255,255,0.35);border-radius:8px;padding:0.625rem 1.125rem;font-size:0.8125rem;cursor:pointer;font-family:inherit">Отказаться</button>
    </div>
  </div>
</div>
<script>
(function () {
  if (document.cookie.indexOf('sg_consent=') !== -1) return;
  var b = document.getElementById('sgCookieBanner');
  if (b) b.style.display = 'block';
})();
function sgSetConsent(v) {
  document.cookie = 'sg_consent=' + v + '; path=/; max-age=31536000; SameSite=Lax';
  var b = document.getElementById('sgCookieBanner');
  if (b) b.style.display = 'none';
  if (v === 'accepted' && typeof sgLoadMetrika === 'function') sgLoadMetrika();
}
</script>
