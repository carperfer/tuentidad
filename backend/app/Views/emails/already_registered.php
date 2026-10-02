<?php
/**
 * @var string $loginUrl
 * @var string $forgotUrl
 */
?>
<p>Hemos recibido una solicitud de amistad desde la portada de tuentidad con este email, pero ya tienes una cuenta.</p>
<p style="text-align:center;margin:24px 0;">
  <a href="<?= esc($loginUrl, 'attr') ?>" style="background:#3b6db3;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;font-weight:bold;">Iniciar sesión</a>
</p>
<p>¿No recuerdas tu contraseña? <a href="<?= esc($forgotUrl, 'attr') ?>" style="color:#2f5fa7;">Cámbiala aquí</a>.</p>
