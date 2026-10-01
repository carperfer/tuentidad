<?php
/**
 * @var string $firstName
 * @var string $url
 * @var int    $minutes
 */
?>
<p>Hola, <?= esc($firstName) ?>:</p>
<p>Hemos recibido una petición para cambiar la contraseña de tu cuenta.</p>
<p style="text-align:center;margin:24px 0;">
  <a href="<?= esc($url, 'attr') ?>" style="background:#3b6db3;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;font-weight:bold;">Cambiar contraseña</a>
</p>
<p>El enlace caduca en <?= $minutes ?> minutos y solo se puede usar una vez. Si no has pedido el cambio, ignora este email: tu contraseña sigue siendo la misma.</p>
<p style="word-break:break-all;"><a href="<?= esc($url, 'attr') ?>" style="color:#2f5fa7;"><?= esc($url) ?></a></p>
