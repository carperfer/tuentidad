<?php

namespace Tests\Unit;

use App\Libraries\LinkPreviewer;
use App\Libraries\PhotoProcessor;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\FakeLinkPreviewer;
use Tests\Support\TestImages;

/**
 * Lectura de las páginas para las tarjetas de enlaces.
 *
 * @internal
 */
final class LinkPreviewerTest extends CIUnitTestCase
{
    public function testFindsTheFirstLinkOfAText(): void
    {
        $this->assertSame('https://example.com/a?b=1', LinkPreviewer::firstUrl('Mira esto: https://example.com/a?b=1. ¡Qué bueno!'));
        $this->assertSame('https://www.example.es/', LinkPreviewer::firstUrl('(en www.example.es)'));
        $this->assertSame('http://example.com/', LinkPreviewer::firstUrl('HTTP://Example.COM'));
        $this->assertNull(LinkPreviewer::firstUrl('Sin enlaces, solo texto.'));
    }

    public function testOnlyAcceptsPublicWebAddresses(): void
    {
        $this->assertNull(LinkPreviewer::normalize('ftp://example.com/'));
        $this->assertNull(LinkPreviewer::normalize('https://user:pass@example.com/'));
        $this->assertNull(LinkPreviewer::normalize('https://example.com:8080/'));
        $this->assertNull(LinkPreviewer::normalize('https://intranet/'));

        // Sin hacer ninguna petición: direcciones locales, privadas y reservadas
        $previewer = new LinkPreviewer(new PhotoProcessor(WRITEPATH . 'uploads/link-previews'));

        foreach (['http://127.0.0.1/', 'http://10.1.2.3/', 'http://192.168.1.1/', 'http://169.254.169.254/latest/meta-data/', 'http://100.64.0.1/', 'http://localhost.localdomain/'] as $url) {
            $this->assertNull($previewer->preview($url), $url);
        }
    }

    public function testReadsOpenGraphAndStoresAReducedImage(): void
    {
        $jpeg      = (string) file_get_contents(TestImages::jpeg(tempnam(sys_get_temp_dir(), 'img'), 1200, 800));
        $previewer = new FakeLinkPreviewer([
            'https://example.com/noticia' => ['type' => 'text/html; charset=utf-8', 'body' => <<<'HTML'
                <html><head>
                <title>Título de respaldo</title>
                <meta property="og:title" content="Gran noticia &amp; más">
                <meta property="OG:DESCRIPTION" content="  Un resumen
                    en dos líneas  ">
                <meta property="og:image" content="/img/portada.jpg">
                </head><body></body></html>
                HTML],
            'https://example.com/img/portada.jpg' => ['type' => 'image/jpeg', 'body' => $jpeg],
        ]);

        $preview = $previewer->preview('https://example.com/noticia');

        $this->assertSame('Gran noticia & más', $preview['title']);
        $this->assertSame('Un resumen en dos líneas', $preview['description']);
        $this->assertSame('example.com', $preview['site_name']);
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{32}\.jpg\z/', $preview['image']);
        $size = getimagesize(WRITEPATH . 'uploads/link-previews/' . $preview['image']);
        $this->assertSame([600, 400], [$size[0], $size[1]]);
        unlink(WRITEPATH . 'uploads/link-previews/' . $preview['image']);
    }

    public function testFallsBackToTheTitleAndConvertsTheCharset(): void
    {
        $previewer = new FakeLinkPreviewer([
            'https://example.com/'         => ['type' => 'text/html', 'body' => mb_convert_encoding('<html><head><meta charset="iso-8859-1"><title>Página de España</title><meta name="description" content="Más información"></head></html>', 'ISO-8859-1', 'UTF-8')],
            'https://example.com/vacia'    => ['type' => 'text/html', 'body' => '<html><body>Hola</body></html>'],
            'https://example.com/foto.jpg' => ['type' => 'image/jpeg', 'body' => 'no es html'],
        ]);

        $preview = $previewer->preview('https://example.com/');
        $this->assertSame(['Página de España', 'Más información', null], [$preview['title'], $preview['description'], $preview['image']]);
        $this->assertNull($previewer->preview('https://example.com/vacia'));
        $this->assertNull($previewer->preview('https://example.com/foto.jpg'));
    }
}
