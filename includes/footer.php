<?php
// footer.php — сахгоу.рф v3
?>
</div><!-- close flex-1 -->
<footer class="border-t border-[#EBEEF2] bg-white mt-auto">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-8 mb-8">
      <div class="col-span-2 md:col-span-1">
        <img src="/logo.png" alt="СахGO" class="h-14 mb-3">
        <p class="text-xs text-[#7A8A9A] leading-relaxed max-w-xs">Маркетплейс туруслуг, рыбалки и жилья для Сахалинской области и Курильских островов.</p>
      </div>
      <div>
        <h4 class="text-xs font-semibold text-[#3A4A5C] mb-3">Разделы</h4>
        <div class="space-y-2 text-sm">
          <a href="/catalog/property" class="block text-[#7A8A9A] hover:text-foreground transition-colors">Жильё посуточно</a>
          <a href="/catalog/tour" class="block text-[#7A8A9A] hover:text-foreground transition-colors">Туры и экскурсии</a>
          <a href="/catalog/fishing" class="block text-[#7A8A9A] hover:text-foreground transition-colors">Рыбалка</a>
          <a href="/catalog/rental_gear" class="block text-[#7A8A9A] hover:text-foreground transition-colors">Аренда снаряжения</a>
          <a href="/catalog/car_rental" class="block text-[#7A8A9A] hover:text-foreground transition-colors">Прокат авто</a>
        </div>
      </div>
      <div>
        <h4 class="text-xs font-semibold text-[#3A4A5C] mb-3">Помощь</h4>
        <div class="space-y-2 text-sm">
          <a href="/help" class="block text-[#7A8A9A] hover:text-foreground transition-colors">Как это работает</a>
          <a href="/help" class="block text-[#7A8A9A] hover:text-foreground transition-colors">Частые вопросы</a>
          <a href="/privacy" class="block text-[#7A8A9A] hover:text-foreground transition-colors">Конфиденциальность</a>
          <a href="/terms" class="block text-[#7A8A9A] hover:text-foreground transition-colors">Условия</a>
        </div>
      </div>
      <div>
        <h4 class="text-xs font-semibold text-[#3A4A5C] mb-3">Партнёрам</h4>
        <div class="space-y-2 text-sm">
          <a href="/create" class="block text-[#7A8A9A] hover:text-foreground transition-colors">Разместить объявление</a>
          <a href="/promote" class="block text-[#7A8A9A] hover:text-foreground transition-colors">Продвижение</a>
        </div>
      </div>
    </div>
    <div class="border-t border-[#EBEEF2] pt-5 flex flex-wrap justify-between gap-3 text-xs text-[#9AAAB8]">
      <span>© <?=date('Y')?> SakhGo · Сделано на Сахалине</span>
      <span class="flex gap-3">
        <a href="/privacy" class="hover:text-foreground transition-colors">Конфиденциальность</a>
        <a href="/terms" class="hover:text-foreground transition-colors">Условия</a>
      </span>
    </div>
  </div>
</footer>
<?php require_once __DIR__ . '/chat_fab.php'; ?>
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function() {
    navigator.serviceWorker.register('/sw.js').catch(function(){});
  });
}
</script>
<?php require_once __DIR__ . '/metrics_counter.php'; ?>
</div></body></html>
