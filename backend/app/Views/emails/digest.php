<?php
/**
 * @var string       $firstName
 * @var list<string> $items
 * @var int          $more
 * @var int          $messages
 * @var string       $url
 * @var string       $settings
 */
?>
<p>Hola, <?= esc($firstName) ?>:</p>
<p>Esto es lo que te espera en tuentidad:</p>
<ul>
<?php foreach ($items as $item): ?>
  <li><?= esc($item) ?></li>
<?php endforeach ?>
<?php if ($more > 0): ?>
  <li>y <?= $more ?> <?= $more === 1 ? 'aviso más' : 'avisos más' ?></li>
<?php endif ?>
<?php if ($messages > 0): ?>
  <li><?= $messages === 1 ? '1 mensaje de chat sin leer' : $messages . ' mensajes de chat sin leer' ?></li>
<?php endif ?>
</ul>
<p style="text-align:center;margin:24px 0;">
  <a href="<?= esc($url, 'attr') ?>" style="background:#3b6db3;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;font-weight:bold;">Ver mis avisos</a>
</p>
<p style="color:#6f7b8b;font-size:12px;">Recibes este resumen porque lo activaste. Puedes cambiar la frecuencia o desactivarlo en <a href="<?= esc($settings, 'attr') ?>" style="color:#6f7b8b;">Mi cuenta</a>.</p>
