<?php

namespace App\Libraries;

use GdImage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Convierte una imagen subida en las dos versiones que se guardan:
 * una grande para el visor y una miniatura cuadrada, ambas en JPEG.
 *
 * Solo usa GD (disponible en el hosting de OVH). Al volver a generar la
 * imagen se descartan todos los metadatos EXIF (ubicación, cámara...);
 * antes se lee la orientación para que las fotos de móvil no salgan giradas.
 */
class PhotoProcessor
{
    public const LARGE_SIZE = 1600;
    public const THUMB_SIZE = 240;

    // Con 256 MB de memoria, GD necesita unos 5 bytes por píxel para abrir el original
    private const MAX_PIXELS = 25_000_000;
    private const QUALITY    = 85;
    private const FILENAME   = '/\A[a-f0-9]{32}\z/';

    public function __construct(private readonly string $directory)
    {
    }

    /**
     * @return array{filename: string, width: int, height: int}
     *
     * @throws InvalidArgumentException si el archivo no es una imagen admitida
     */
    public function process(string $sourcePath): array
    {
        $info = @getimagesize($sourcePath);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new InvalidArgumentException('Usa imágenes JPG, PNG o WebP.');
        }
        [$width, $height, $type] = $info;
        if ($width < 1 || $height < 1 || $width * $height > self::MAX_PIXELS) {
            throw new InvalidArgumentException('La imagen no puede superar los 25 megapíxeles.');
        }

        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG  => @imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
        };
        if ($source === false) {
            throw new InvalidArgumentException('No se ha podido leer la imagen.');
        }

        // Se reduce antes de girar para no tener dos copias del original en memoria
        $large = $this->resize($source, self::LARGE_SIZE);
        imagedestroy($source);
        if ($type === IMAGETYPE_JPEG) {
            $large = $this->orient($large, $sourcePath);
        }
        $thumb = $this->squareThumb($large);

        $this->ensureDirectory();
        $filename = bin2hex(random_bytes(16));
        $this->save($large, $this->largePath($filename));
        $this->save($thumb, $this->thumbPath($filename));

        $result = ['filename' => $filename, 'width' => imagesx($large), 'height' => imagesy($large)];
        imagedestroy($large);
        imagedestroy($thumb);

        return $result;
    }

    public function largePath(string $filename): string
    {
        return $this->path($filename, '');
    }

    public function thumbPath(string $filename): string
    {
        return $this->path($filename, '_t');
    }

    public function delete(string $filename): void
    {
        foreach ([$this->largePath($filename), $this->thumbPath($filename)] as $path) {
            if (is_file($path) && ! unlink($path)) {
                throw new RuntimeException('No se ha podido borrar el archivo de la foto.');
            }
        }
    }

    private function path(string $filename, string $suffix): string
    {
        if (! preg_match(self::FILENAME, $filename)) {
            throw new InvalidArgumentException('Nombre de foto no válido.');
        }

        return $this->directory . DIRECTORY_SEPARATOR . $filename . $suffix . '.jpg';
    }

    /**
     * Copia la imagen sobre fondo blanco (los PNG pueden tener transparencia)
     * reduciéndola para que su lado mayor no pase de $maxSide.
     */
    private function resize(GdImage $image, int $maxSide): GdImage
    {
        $width  = imagesx($image);
        $height = imagesy($image);
        $scale  = min(1, $maxSide / max($width, $height));
        $newW   = max(1, (int) round($width * $scale));
        $newH   = max(1, (int) round($height * $scale));

        $copy = $this->canvas($newW, $newH);
        imagecopyresampled($copy, $image, 0, 0, 0, 0, $newW, $newH, $width, $height);

        return $copy;
    }

    private function squareThumb(GdImage $image): GdImage
    {
        $width  = imagesx($image);
        $height = imagesy($image);
        $side   = min($width, $height);
        $size   = min(self::THUMB_SIZE, $side);

        $thumb = $this->canvas($size, $size);
        imagecopyresampled($thumb, $image, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), $size, $size, $side, $side);

        return $thumb;
    }

    /**
     * Aplica la orientación EXIF (valores 1-8) a los píxeles.
     */
    private function orient(GdImage $image, string $sourcePath): GdImage
    {
        $exif        = function_exists('exif_read_data') ? @exif_read_data($sourcePath) : false;
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        // Ángulo antihorario de imagerotate y si además hay que reflejar en horizontal
        [$angle, $flip] = match ($orientation) {
            2       => [0, true],
            3       => [180, false],
            4       => [180, true],
            5       => [270, true],
            6       => [270, false],
            7       => [90, true],
            8       => [90, false],
            default => [0, false],
        };

        if ($angle !== 0) {
            $rotated = imagerotate($image, $angle, 0);
            if ($rotated === false) {
                throw new RuntimeException('No se ha podido girar la imagen.');
            }
            imagedestroy($image);
            $image = $rotated;
        }
        if ($flip) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        return $image;
    }

    private function canvas(int $width, int $height): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === false) {
            throw new RuntimeException('No hay memoria suficiente para procesar la imagen.');
        }
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));

        return $canvas;
    }

    private function save(GdImage $image, string $path): void
    {
        imageinterlace($image, true);
        if (! imagejpeg($image, $path, self::QUALITY)) {
            throw new RuntimeException('No se ha podido guardar la foto.');
        }
    }

    private function ensureDirectory(): void
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0750, true) && ! is_dir($this->directory)) {
            throw new RuntimeException('No se ha podido crear el directorio de fotos.');
        }
    }
}
