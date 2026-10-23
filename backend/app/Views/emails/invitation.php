<?php
/**
 * @var string $inviterName
 * @var string $url
 * @var int    $days
 */
?>
<p><strong><?= esc($inviterName) ?></strong> te ha invitado a tuentidad, la red social privada para estar en contacto con tus amigos de verdad.</p>
<p style="text-align:center;margin:24px 0;">
  <a href="<?= esc($url, 'attr') ?>" style="background:#3b6db3;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;font-weight:bold;">Crear mi cuenta</a>
</p>
<p>La invitación caduca en <?= $days ?> días. Si el botón no funciona, copia este enlace en tu navegador:</p>
<p style="word-break:break-all;"><a href="<?= esc($url, 'attr') ?>" style="color:#2f5fa7;"><?= esc($url) ?></a></p>
