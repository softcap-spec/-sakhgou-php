<?php
// contacts.php — реквизиты и контакты оператора (ст. 9, 10 Закона «О защите прав потребителей»; ст. 8 ФЗ «О рекламе»)
$page_title = 'Контакты и реквизиты — СахGO';
$page_description = 'Реквизиты и контакты оператора СахGO: наименование, ОГРН, ИНН, юридический адрес, режим работы, телефон, e-mail.';
require __DIR__ . '/../includes/header.php';
$req = get_requisites();

$h2 = 'font-family:Manrope,sans-serif;font-weight:600;font-size:1.0625rem;color:#121E2B;margin:1.75rem 0 0.625rem;letter-spacing:-0.01em;padding-top:1.25rem;border-top:1px solid #EEF2F6';
$p  = 'font-size:0.8125rem;color:#3A4A5C;line-height:1.75;margin:0 0 0.875rem';
$li = 'font-size:0.8125rem;color:#3A4A5C;line-height:1.7;margin:0 0 0.5rem';
?>

<section style="padding:3rem 0 4rem">
  <div style="max-width:46rem;margin:0 auto;padding:0 1rem">
    <span style="font-size:0.6875rem;text-transform:uppercase;letter-spacing:0.1em;color:#7A8A9A;font-weight:500">Правовая информация</span>
    <h1 style="font-family:Manrope,sans-serif;font-weight:700;font-size:2rem;letter-spacing:-0.02em;margin:0.25rem 0 2rem">Контакты и реквизиты</h1>

    <div style="background:#fff;border:1px solid #EEF2F6;border-radius:12px;padding:2rem 2rem 2.5rem;box-shadow:0 4px 12px rgba(15,23,32,0.06)">
      <h2 style="<?=$h2?>;margin-top:0;padding-top:0;border-top:0">Оператор</h2>
      <p style="<?=$p?>"><strong><?=h($req['name'])?></strong></p>

      <div style="display:grid;grid-template-columns:auto 1fr;gap:0.5rem 1.5rem;margin:0 0 1.5rem">
        <?php
        $rows = [
          'ОГРН' => $req['ogrn'],
          'ИНН' => $req['inn'],
          'КПП' => $req['kpp'],
          'Юридический адрес' => $req['legal_address'],
          'Почтовый адрес' => $req['postal_address'],
          'Режим работы' => $req['work_hours'],
          'Телефон' => $req['phone'],
        ];
        foreach ($rows as $label => $value):
        ?>
        <div style="font-size:0.8125rem;color:#7A8A9A;padding:0.375rem 0"><?=$label?></div>
        <div style="font-size:0.8125rem;color:#121E2B;padding:0.375rem 0"><?= $value !== '' ? h($value) : '<span style="color:#B8C2CC">—</span>' ?></div>
        <?php endforeach; ?>
        <div style="font-size:0.8125rem;color:#7A8A9A;padding:0.375rem 0">E-mail</div>
        <div style="font-size:0.8125rem;padding:0.375rem 0"><a href="mailto:<?=h($req['email'])?>" style="color:#0A7BBA;text-decoration:none"><?=h($req['email'])?></a></div>
      </div>

      <h2 style="<?=$h2?>">Обращения субъектов персональных данных</h2>
      <p style="<?=$p?>">Для реализации прав, предусмотренных ст. 14, 15, 22 Федерального закона от 27.07.2006 № 152-ФЗ «О персональных данных» (получение сведений об обработке, уточнение, блокирование, уничтожение, отзыв согласия), направьте обращение на e-mail <a href="mailto:<?=h($req['email'])?>" style="color:#0A7BBA;text-decoration:none"><?=h($req['email'])?></a>.</p>

      <h2 style="<?=$h2?>">Уведомления о противоправном контенте</h2>
      <p style="<?=$p?>">Любое лицо вправе направить администрации уведомление о противоправном контенте на e-mail <a href="mailto:<?=h($req['email'])?>" style="color:#0A7BBA;text-decoration:none"><?=h($req['email'])?></a> с указанием конкретной страницы (URL) и описанием нарушения. Порядок рассмотрения — в <a href="/terms" style="color:#0A7BBA;text-decoration:none">Пользовательском соглашении</a> (раздел 5).</p>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
