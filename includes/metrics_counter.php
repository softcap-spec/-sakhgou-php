<?php
// metrics_counter.php — Яндекс.Метрика: счётчик + ymGoal helper.
// Выводится только если в config.php задана константа YANDEX_METRIKA_ID.
if (!defined('YANDEX_METRIKA_ID') || !YANDEX_METRIKA_ID) return;
$ymId = (int)YANDEX_METRIKA_ID;
?>
<script>window.ymID = <?= $ymId ?>;
function ymGoal(name){ if (typeof ym !== 'undefined') ym(window.ymID, 'reachGoal', name); }
</script>
<!-- Yandex.Metrika counter -->
<script type="text/javascript">
   (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
   m[i].l=1*new Date();
   for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
   k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
   (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

   ym(<?= $ymId ?>, "init", {
        clickmap:true,
        trackLinks:true,
        accurateTrackBounce:true,
        webvisor:true
   });
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/<?= $ymId ?>" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->
