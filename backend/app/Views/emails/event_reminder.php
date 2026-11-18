<?php
/**
 * @var string $firstName
 * @var string $title
 * @var string $when
 * @var string $place
 * @var string $url
 */
?>
<p>Hola, <?= esc($firstName) ?>:</p>
<p>Te recordamos que <strong><?= esc($title) ?></strong> es <?= esc($when) ?><?= $place !== '' ? ', en ' . esc($place) : '' ?>.</p>
<p style="text-align:center;margin:24px 0;">
  <a href="<?= esc($url, 'attr') ?>" style="background:#3b6db3;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;font-weight:bold;">Ver el evento</a>
</p>
<p style="color:#6f7b8b;font-size:12px;">Te llega porque has dicho que vas o que quizás vas. Puedes cambiar tu respuesta en la página del evento.</p>
