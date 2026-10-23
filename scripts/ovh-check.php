<?php

/**
 * Diagnóstico de un solo uso del alojamiento OVH para tuentidad.
 *
 * 1. Sube este archivo a la carpeta web (www/ en OVH) por SFTP.
 * 2. Ábrelo en el navegador: https://tuentidad.es/ovh-check.php
 * 3. El archivo se borra solo al terminar; si no pudiera, bórralo a mano.
 *
 * Usa el .env de la carpeta superior para probar la conexión a la base de datos.
 * No muestra contraseñas ni claves.
 */

declare(strict_types=1);

$results = [];

// La aplicación está junto a public/ o, en OVH, en ../tuentidad/ junto a www/
$root = dirname(__DIR__);
foreach (glob(dirname(__DIR__) . '/*/app/Config/Paths.php') ?: [] as $paths) {
    if (! is_file("{$root}/app/Config/Paths.php")) {
        $root = dirname($paths, 3);
    }
}

function check(array &$results, string $group, string $name, ?bool $ok, string $detail): void
{
    $results[$group][] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
}

// --- PHP -------------------------------------------------------------------

check($results, 'PHP', 'Versión', version_compare(PHP_VERSION, '8.2', '>='), PHP_VERSION . ' (mínimo 8.2, recomendado 8.3)');
check($results, 'PHP', 'SAPI', null, PHP_SAPI);
check($results, 'PHP', 'HTTPS', ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https', 'Necesario para las cookies Secure');

foreach (['intl', 'mbstring', 'json', 'mysqli', 'curl', 'fileinfo'] as $ext) {
    check($results, 'Extensiones obligatorias', $ext, extension_loaded($ext), extension_loaded($ext) ? 'cargada' : 'NO cargada');
}

$images = array_filter(['gd', 'imagick'], 'extension_loaded');
check($results, 'Extensiones para fotos', 'gd o imagick', $images !== [], $images === [] ? 'ninguna' : implode(', ', $images));
check($results, 'Extensiones para fotos', 'exif', extension_loaded('exif'), extension_loaded('exif') ? 'cargada' : 'NO cargada');

foreach (['upload_max_filesize' => '10M', 'post_max_size' => '64M', 'memory_limit' => '256M', 'max_execution_time' => null] as $ini => $recommended) {
    $value = (string) ini_get($ini);
    check($results, 'Límites', $ini, null, $value . ($recommended !== null ? " (recomendado ≥ {$recommended})" : ''));
}

// --- Aplicación ------------------------------------------------------------

check($results, 'Aplicación', 'Carpeta', null, $root);
check($results, 'Aplicación', 'vendor/', is_file("{$root}/vendor/autoload.php"), is_file("{$root}/vendor/autoload.php") ? 'presente' : 'falta: sube el paquete completo');

foreach (['writable', 'writable/cache', 'writable/logs', 'writable/session', 'writable/uploads'] as $dir) {
    $writable = is_dir("{$root}/{$dir}") && is_writable("{$root}/{$dir}");
    check($results, 'Aplicación', $dir, $writable, $writable ? 'escribible' : 'NO escribible o no existe');
}

$env = null;
if (is_file("{$root}/.env") && is_file("{$root}/vendor/codeigniter4/framework/system/Config/DotEnv.php")) {
    require_once "{$root}/vendor/codeigniter4/framework/system/Config/DotEnv.php";
    $env = (new CodeIgniter\Config\DotEnv($root))->parse() ?? [];
}
check($results, 'Aplicación', '.env', $env !== null, $env !== null ? 'presente' : 'falta: se genera al desplegar con DESPLIEGUE=app (secrets de GitHub)');

if ($env !== null) {
    check($results, 'Aplicación', 'CI_ENVIRONMENT', ($env['CI_ENVIRONMENT'] ?? '') === 'production', $env['CI_ENVIRONMENT'] ?? 'sin definir');
    check($results, 'Aplicación', 'encryption.key', ! empty($env['encryption.key']), empty($env['encryption.key']) ? 'sin definir' : 'definida');
    check($results, 'Aplicación', 'app.baseURL', null, $env['app.baseURL'] ?? 'sin definir');

    // --- Base de datos -----------------------------------------------------
    mysqli_report(MYSQLI_REPORT_OFF);
    $db = @new mysqli(
        $env['database.default.hostname'] ?? '',
        $env['database.default.username'] ?? '',
        $env['database.default.password'] ?? '',
        $env['database.default.database'] ?? '',
        (int) ($env['database.default.port'] ?? 3306),
    );
    if ($db->connect_errno) {
        check($results, 'Base de datos', 'Conexión', false, $db->connect_error ?? 'error desconocido');
    } else {
        check($results, 'Base de datos', 'Conexión', true, 'correcta');
        check($results, 'Base de datos', 'Versión de MySQL', version_compare($db->server_info, '8.0', '>='), $db->server_info);
        $db->close();
    }

    // --- SMTP (solo alcance de red, sin autenticar) ------------------------
    $host = $env['email.SMTPHost'] ?? '';
    $port = (int) ($env['email.SMTPPort'] ?? 0);
    if ($host !== '' && $port > 0) {
        $prefix = ($env['email.SMTPCrypto'] ?? '') === 'ssl' ? 'ssl://' : '';
        $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 5);
        check($results, 'Email', "SMTP {$host}:{$port}", $socket !== false, $socket !== false ? 'accesible' : "no accesible: {$errstr}");
        if ($socket !== false) {
            fclose($socket);
        }
    }
}

// --- Autoborrado -------------------------------------------------------------

$deleted = @unlink(__FILE__);

$failures = 0;
foreach ($results as $items) {
    foreach ($items as $item) {
        $failures += $item['ok'] === false ? 1 : 0;
    }
}

$e = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

header('Cache-Control: no-store');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Diagnóstico OVH · tuentidad</title>
<style>
  body { font: 14px/1.5 system-ui, sans-serif; max-width: 760px; margin: 24px auto; padding: 0 16px; color: #222; background: #f4f6fa; }
  h1 { font-size: 20px; }
  h2 { font-size: 15px; margin: 24px 0 8px; }
  table { width: 100%; border-collapse: collapse; background: #fff; }
  td { padding: 6px 10px; border-bottom: 1px solid #e3e7ee; vertical-align: top; }
  td:first-child { width: 32px; text-align: center; }
  td:nth-child(2) { width: 220px; font-family: ui-monospace, monospace; }
  .ok { color: #2e7d32; } .ko { color: #c62828; } .info { color: #607080; }
  .aviso { padding: 10px 14px; border-radius: 4px; background: #fff3cd; }
  .resumen { padding: 10px 14px; border-radius: 4px; background: <?= $failures === 0 ? '#e8f5e9' : '#fdecea' ?>; }
</style>
</head>
<body>
<h1>Diagnóstico del alojamiento OVH</h1>
<p class="resumen"><?= $failures === 0 ? 'Todo correcto.' : $e("{$failures} comprobación(es) fallida(s).") ?></p>
<p class="aviso"><?= $deleted ? 'Este archivo se ha borrado del servidor. Para repetir el diagnóstico vuelve a subirlo a www/.' : '⚠ No se pudo borrar este archivo: bórralo a mano de www/.' ?></p>
<?php foreach ($results as $group => $items): ?>
<h2><?= $e($group) ?></h2>
<table>
<?php foreach ($items as $item): ?>
  <tr>
    <td class="<?= $item['ok'] === null ? 'info' : ($item['ok'] ? 'ok' : 'ko') ?>"><?= $item['ok'] === null ? 'ℹ' : ($item['ok'] ? '✓' : '✗') ?></td>
    <td><?= $e($item['name']) ?></td>
    <td><?= $e($item['detail']) ?></td>
  </tr>
<?php endforeach ?>
</table>
<?php endforeach ?>
</body>
</html>
