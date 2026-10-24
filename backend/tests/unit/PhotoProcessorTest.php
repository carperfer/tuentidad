<?php

namespace Tests\Unit;

use App\Libraries\PhotoProcessor;
use CodeIgniter\Test\CIUnitTestCase;
use InvalidArgumentException;
use Tests\Support\TestImages;

/**
 * @internal
 */
final class PhotoProcessorTest extends CIUnitTestCase
{
    private string $directory;
    private PhotoProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . '/tuentidad-photos-' . bin2hex(random_bytes(4));
        mkdir($this->directory);
        $this->processor = new PhotoProcessor($this->directory);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory . '/*') ?: []);
        rmdir($this->directory);

        parent::tearDown();
    }

    public function testLargePhotosAreReducedAndGetASquareThumbnail(): void
    {
        $source = TestImages::jpeg($this->directory . '/source.jpg', 3200, 1600);

        $stored = $this->processor->process($source);

        $this->assertSame(1600, $stored['width']);
        $this->assertSame(800, $stored['height']);
        $this->assertSame([1600, 800], array_slice((array) getimagesize($this->processor->largePath($stored['filename'])), 0, 2));
        $this->assertSame([240, 240], array_slice((array) getimagesize($this->processor->thumbPath($stored['filename'])), 0, 2));
    }

    public function testExifOrientationIsAppliedAndMetadataRemoved(): void
    {
        // Orientación 6: la cámara estaba girada y hay que rotar 90° en sentido horario
        $source = TestImages::jpeg($this->directory . '/source.jpg', 400, 200, 6);
        $this->assertSame(6, exif_read_data($source)['Orientation']);

        $stored = $this->processor->process($source);
        $path   = $this->processor->largePath($stored['filename']);

        $this->assertSame([200, 400], [$stored['width'], $stored['height']]);
        $this->assertStringNotContainsString('Exif', (string) file_get_contents($path));

        // La mitad izquierda (roja) del original queda arriba
        $image = imagecreatefromjpeg($path);
        $top   = imagecolorsforindex($image, imagecolorat($image, 100, 50));
        $this->assertGreaterThan(200, $top['red']);
        $this->assertLessThan(60, $top['blue']);
    }

    public function testFilesThatAreNotImagesAreRejected(): void
    {
        $source = $this->directory . '/fake.jpg';
        file_put_contents($source, '<?php echo "hola";');

        $this->expectException(InvalidArgumentException::class);
        $this->processor->process($source);
    }

    public function testDeleteRemovesBothVersions(): void
    {
        $stored = $this->processor->process(TestImages::jpeg($this->directory . '/source.jpg', 300, 300));

        $this->processor->delete($stored['filename']);

        $this->assertFileDoesNotExist($this->processor->largePath($stored['filename']));
        $this->assertFileDoesNotExist($this->processor->thumbPath($stored['filename']));
    }
}
