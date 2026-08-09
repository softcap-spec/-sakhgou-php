<?php
// help.php — v3 clean design
$page_title = 'Помощь — СахGO';
require __DIR__ . '/../includes/header.php';
?>

<section style="padding:3rem 0 4rem">
  <div style="max-width:44rem;margin:0 auto;padding:0 1rem">
    <span style="font-size:0.6875rem;text-transform:uppercase;letter-spacing:0.1em;color:#7A8A9A;font-weight:500">Помощь</span>
    <h1 style="font-family:Manrope,sans-serif;font-weight:700;font-size:2rem;letter-spacing:-0.02em;margin:0.25rem 0 2.5rem">Как это работает</h1>

    <div style="display:flex;flex-direction:column;gap:1rem">

      <!-- FAQ Card: Search -->
      <div style="background:#fff;border:1px solid #EEF2F6;border-radius:12px;padding:1.5rem;box-shadow:0 4px 12px rgba(15,23,32,0.06)">
        <div style="display:flex;align-items:flex-start;gap:0.875rem">
          <div style="width:2.25rem;height:2.25rem;border-radius:8px;background:#F7F9FB;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1B6B8A" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.34-4.34"/></svg>
          </div>
          <div>
            <h2 style="font-family:Manrope,sans-serif;font-weight:600;font-size:1rem;margin:0 0 0.5rem;color:#121E2B">Поиск объявлений</h2>
            <p style="font-size:0.8125rem;color:#3A4A5C;line-height:1.6;margin:0">Используйте поисковую строку на главной странице или перейдите в нужную категорию через меню навигации. Вы можете фильтровать объявления по категориям: Жильё, Туры, Рыбалка, Снаряжение, Прокат авто.</p>
          </div>
        </div>
      </div>

      <!-- FAQ Card: Create -->
      <div style="background:#fff;border:1px solid #EEF2F6;border-radius:12px;padding:1.5rem;box-shadow:0 4px 12px rgba(15,23,32,0.06)">
        <div style="display:flex;align-items:flex-start;gap:0.875rem">
          <div style="width:2.25rem;height:2.25rem;border-radius:8px;background:#F7F9FB;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1B6B8A" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
          </div>
          <div>
            <h2 style="font-family:Manrope,sans-serif;font-weight:600;font-size:1rem;margin:0 0 0.5rem;color:#121E2B">Размещение объявления</h2>
            <p style="font-size:0.8125rem;color:#3A4A5C;line-height:1.6;margin:0">Зарегистрируйтесь и перейдите в раздел &laquo;Подать объявление&raquo;. Укажите название, категорию, цену, описание и загрузите фото. После публикации объявление станет доступно всем посетителям.</p>
          </div>
        </div>
      </div>

      <!-- FAQ Card: Contact -->
      <div style="background:#fff;border:1px solid #EEF2F6;border-radius:12px;padding:1.5rem;box-shadow:0 4px 12px rgba(15,23,32,0.06)">
        <div style="display:flex;align-items:flex-start;gap:0.875rem">
          <div style="width:2.25rem;height:2.25rem;border-radius:8px;background:#F7F9FB;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1B6B8A" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          </div>
          <div>
            <h2 style="font-family:Manrope,sans-serif;font-weight:600;font-size:1rem;margin:0 0 0.5rem;color:#121E2B">Связь с продавцом</h2>
            <p style="font-size:0.8125rem;color:#3A4A5C;line-height:1.6;margin:0">Контактный телефон и местоположение указываются в карточке объявления. Свяжитесь с продавцом напрямую для уточнения деталей.</p>
          </div>
        </div>
      </div>

      <!-- FAQ Card: Support -->
      <div style="background:#fff;border:1px solid #EEF2F6;border-radius:12px;padding:1.5rem;box-shadow:0 4px 12px rgba(15,23,32,0.06)">
        <div style="display:flex;align-items:flex-start;gap:0.875rem">
          <div style="width:2.25rem;height:2.25rem;border-radius:8px;background:#F7F9FB;display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1B6B8A" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          </div>
          <div>
            <h2 style="font-family:Manrope,sans-serif;font-weight:600;font-size:1rem;margin:0 0 0.5rem;color:#121E2B">Поддержка</h2>
            <p style="font-size:0.8125rem;color:#3A4A5C;line-height:1.6;margin:0">Если у вас возникли вопросы или проблемы, напишите нам на почту <a href="mailto:support@sakhgo.ru" style="color:#1B6B8A;text-decoration:none;font-weight:500">support@sakhgo.ru</a>.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
