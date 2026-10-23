<?php
/**
 * @var string $profileName
 * @var string $profileFirstName
 * @var string $url
 * @var int    $days
 */
?>
<p><strong><?= esc($profileName) ?></strong> ha aceptado tu solicitud de amistad en tuentidad.</p>
<p>Crea tu cuenta para entrar: en cuanto termines, <?= esc($profileFirstName) ?> ya estará entre tus amigos. Desde dentro podrás invitar a tus amigos de verdad.</p>
<p style="text-align:center;margin:24px 0;">
  <a href="<?= esc($url, 'attr') ?>" style="background:#3b6db3;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;font-weight:bold;">Crear mi cuenta</a>
</p>
<p>El enlace caduca en <?= $days ?> días. Si el botón no funciona, copia este enlace en tu navegador:</p>
<p style="word-break:break-all;"><a href="<?= esc($url, 'attr') ?>" style="color:#2f5fa7;"><?= esc($url) ?></a></p>
<p style="color:#6f7b8b;font-size:12px;"><?= esc($profileFirstName) ?> es un perfil de demostración de tuentidad, no una persona real.</p>
