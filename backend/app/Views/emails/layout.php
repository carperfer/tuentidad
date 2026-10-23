<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title><?= esc($subject) ?></title>
</head>
<body style="margin:0;padding:0;background:#e9eef5;font-family:Tahoma,Verdana,Arial,sans-serif;font-size:14px;color:#333;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#e9eef5;padding:24px 0;">
  <tr>
    <td align="center">
      <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#fff;border:1px solid #c9d4e3;border-radius:4px;">
        <tr>
          <td style="background:#3b6db3;padding:12px 20px;border-radius:4px 4px 0 0;">
            <span style="color:#fff;font-size:24px;font-weight:bold;letter-spacing:-1px;">tuentidad</span>
          </td>
        </tr>
        <tr>
          <td style="padding:20px;line-height:1.5;">
            <?= $content ?>
          </td>
        </tr>
        <tr>
          <td style="padding:12px 20px;border-top:1px solid #e3e7ee;color:#6f7b8b;font-size:11px;">
            Has recibido este email porque alguien ha usado esta dirección en tuentidad.es.
            Si no esperabas este mensaje, puedes ignorarlo.
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
