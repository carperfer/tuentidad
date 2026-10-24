<?php

namespace Tests\Support;

/**
 * Imágenes generadas con GD para los tests de fotos.
 */
final class TestImages
{
    /**
     * JPEG con la mitad izquierda roja y la derecha azul y, opcionalmente,
     * una cabecera EXIF con la orientación indicada.
     */
    public static function jpeg(string $path, int $width, int $height, ?int $orientation = null): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, intdiv($width, 2) - 1, $height - 1, (int) imagecolorallocate($image, 255, 0, 0));
        imagefilledrectangle($image, intdiv($width, 2), 0, $width - 1, $height - 1, (int) imagecolorallocate($image, 0, 0, 255));
        ob_start();
        imagejpeg($image, null, 95);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);

        if ($orientation !== null) {
            // TIFF big-endian con un único IFD que solo contiene Orientation (0x0112, SHORT)
            $tiff    = "MM\x00\x2A" . pack('N', 8) . pack('n', 1) . pack('nnN', 0x0112, 3, 1) . pack('n', $orientation) . "\x00\x00" . pack('N', 0);
            $payload = "Exif\x00\x00" . $tiff;
            $jpeg    = substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload . substr($jpeg, 2);
        }

        file_put_contents($path, $jpeg);

        return $path;
    }
}
