<?php

namespace App\Libraries;

use DOMDocument;
use DOMXPath;
use InvalidArgumentException;

/**
 * Lee una página web para la tarjeta de un enlace: título, resumen, nombre
 * del sitio y foto (Open Graph, Twitter Cards o, si no hay, <title> y la
 * meta description). La foto se descarga, se reduce y se guarda aquí, así
 * que quien ve el tablón no carga nada de la otra web.
 *
 * Como el servidor hace la petición, solo se aceptan http y https en los
 * puertos 80 y 443 hacia direcciones públicas, se comprueba cada
 * redirección y se limitan el tiempo y el tamaño de las descargas.
 */
class LinkPreviewer
{
    public const IMAGE_SIDE     = 600;
    private const MAX_HTML      = 1_000_000;
    private const MAX_IMAGE     = 5_000_000;
    private const MAX_REDIRECTS = 3;
    private const TIMEOUT       = 5;
    private const USER_AGENT    = 'Mozilla/5.0 (compatible; TuentidadLinkPreview/1.0)';

    /**
     * Primer enlace de un texto: http(s)://… o www.…, sin la puntuación final.
     */
    public const URL_PATTERN = '~\b(?:https?://|www\.)[^\s<>"\']+~iu';

    public function __construct(private readonly PhotoProcessor $images)
    {
    }

    public static function firstUrl(string $text): ?string
    {
        if (! preg_match(self::URL_PATTERN, $text, $match)) {
            return null;
        }
        $url = rtrim($match[0], '.,;:!?)]}');
        if (str_starts_with(strtolower($url), strtolower('www.'))) {
            $url = 'https://' . $url;
        }

        return self::normalize($url);
    }

    /**
     * El enlace si se puede leer (http o https, con dominio, sin usuario ni
     * contraseña y en los puertos 80 o 443); si no, null.
     */
    public static function normalize(string $url): ?string
    {
        if (strlen($url) > 2048 || preg_match('/[\s\x00-\x1f\x7f]/', $url)) {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        if (isset($parts['port']) && ! in_array($parts['port'], [80, 443], true)) {
            return null;
        }
        $host = strtolower($parts['host']);
        if (! preg_match('/\A[a-z0-9.-]+\z/', $host) || ! str_contains($host, '.')) {
            return null;
        }

        return $scheme . '://' . $host . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . ($parts['path'] ?? '/')
            . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }

    /**
     * @return array{url: string, title: string, description: string, site_name: string, image: string|null}|null
     */
    public function preview(string $url): ?array
    {
        $url = self::normalize($url);
        if ($url === null) {
            return null;
        }
        $page = $this->fetch($url, self::MAX_HTML, 'text/html,application/xhtml+xml', true);
        if ($page === null || ! preg_match('~\A\s*(text/html|application/xhtml\+xml)~i', $page['type'])) {
            return null;
        }

        if (trim($page['body']) === '') {
            return null;
        }
        $meta = $this->parse($page['body'], $page['type'], $page['url']);
        if ($meta['title'] === '') {
            return null;
        }

        return [
            'url'         => $url,
            'title'       => $meta['title'],
            'description' => $meta['description'],
            'site_name'   => $meta['site_name'] !== '' ? $meta['site_name'] : preg_replace('/\Awww\./', '', (string) parse_url($page['url'], PHP_URL_HOST)),
            'image'       => $meta['image'] === null ? null : $this->storeImage($meta['image']),
        ];
    }

    /**
     * Descarga una dirección (siguiendo hasta 3 redirecciones, cada una
     * comprobada de nuevo). Con $partial se queda con el principio de las
     * páginas demasiado grandes (lo que importa está en <head>).
     *
     * @return array{body: string, type: string, url: string}|null
     */
    protected function fetch(string $url, int $maxBytes, string $accept, bool $partial = false): ?array
    {
        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
            $url = self::normalize($url);
            $ip  = $url === null ? null : $this->publicAddress((string) parse_url($url, PHP_URL_HOST));
            if ($ip === null) {
                return null;
            }

            $port     = parse_url($url, PHP_URL_PORT) ?? (str_starts_with($url, 'https:') ? 443 : 80);
            $body     = '';
            $headers  = [];
            $tooLarge = false;
            $curl     = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RESOLVE        => [parse_url($url, PHP_URL_HOST) . ":{$port}:{$ip}"],
                CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_ENCODING       => '',
                CURLOPT_USERAGENT      => self::USER_AGENT,
                CURLOPT_HTTPHEADER     => ["Accept: {$accept}", 'Accept-Language: es-ES,es;q=0.9,en;q=0.5'],
                CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                    if (str_contains($line, ':')) {
                        [$name, $value]                   = explode(':', $line, 2);
                        $headers[strtolower(trim($name))] = trim($value);
                    }

                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge, $maxBytes): int {
                    if (strlen($body) + strlen($chunk) > $maxBytes) {
                        $body .= substr($chunk, 0, $maxBytes - strlen($body));
                        $tooLarge = true;

                        return -1;
                    }
                    $body .= $chunk;

                    return strlen($chunk);
                },
            ]);
            $ok     = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            curl_close($curl);

            if ($ok === false && ! ($tooLarge && $partial)) {
                return null;
            }
            if ($status >= 300 && $status < 400 && isset($headers['location'])) {
                $url = self::absolute($headers['location'], $url);

                continue;
            }
            if ($status !== 200) {
                return null;
            }

            return ['body' => $body, 'type' => $headers['content-type'] ?? '', 'url' => $url];
        }

        return null;
    }

    /**
     * Dirección IPv4 pública del dominio, o null si alguna de las suyas es
     * privada, reservada o local (para no hacer peticiones a la red interna).
     */
    protected function publicAddress(string $host): ?string
    {
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) {
            return null;
        }

        foreach ($ips as $ip) {
            $public = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
            // 100.64.0.0/10: red compartida de los operadores
            if (! $public || (ip2long($ip) & 0xFFC00000) === (ip2long('100.64.0.0') & 0xFFC00000)) {
                return null;
            }
        }

        return $ips[0];
    }

    /**
     * @return array{title: string, description: string, site_name: string, image: string|null}
     */
    private function parse(string $html, string $contentType, string $pageUrl): array
    {
        $charset = null;
        if (preg_match('/charset=["\']?([\w-]+)/i', $contentType, $match)
            || preg_match('/<meta[^>]+charset=["\']?([\w-]+)/i', substr($html, 0, 4096), $match)) {
            $charset = strtoupper($match[1]);
        }
        if ($charset !== null && $charset !== 'UTF-8' && in_array($charset, array_map('strtoupper', mb_list_encodings()), true)) {
            $html = mb_convert_encoding($html, 'UTF-8', $charset);
        }

        // En entidades, libxml no reinterpreta el texto con el charset que declare la página
        $html     = mb_encode_numericentity(mb_scrub($html, 'UTF-8'), [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
        $document = new DOMDocument();
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new DOMXPath($document);

        $meta = static function (string ...$names) use ($xpath): string {
            foreach ($names as $name) {
                $nodes = $xpath->query("//meta[translate(@property, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')='{$name}' or translate(@name, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')='{$name}']/@content");

                foreach ($nodes ?: [] as $node) {
                    if (trim($node->nodeValue ?? '') !== '') {
                        return $node->nodeValue;
                    }
                }
            }

            return '';
        };

        $title = $meta('og:title', 'twitter:title');
        if ($title === '') {
            $titles = $xpath->query('//title');
            $title  = $titles !== false && $titles->length > 0 ? (string) $titles->item(0)?->textContent : '';
        }
        $image = $meta('og:image:secure_url', 'og:image', 'og:image:url', 'twitter:image', 'twitter:image:src');

        return [
            'title'       => self::clean($title, 200),
            'description' => self::clean($meta('og:description', 'twitter:description', 'description'), 300),
            'site_name'   => self::clean($meta('og:site_name'), 100),
            'image'       => $image === '' ? null : self::absolute(trim($image), $pageUrl),
        ];
    }

    private function storeImage(string $url): ?string
    {
        $image = $this->fetch($url, self::MAX_IMAGE, 'image/webp,image/png,image/jpeg');
        if ($image === null || ! str_starts_with(strtolower($image['type']), 'image/')) {
            return null;
        }

        $temp = tempnam(sys_get_temp_dir(), 'link');
        if ($temp === false) {
            return null;
        }

        try {
            file_put_contents($temp, $image['body']);

            return $this->images->processSingle($temp, self::IMAGE_SIDE);
        } catch (InvalidArgumentException) {
            return null;
        } finally {
            @unlink($temp);
        }
    }

    private static function clean(string $text, int $length): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . '…' : $text;
    }

    /**
     * Convierte un enlace relativo de la página en absoluto.
     */
    private static function absolute(string $reference, string $base): string
    {
        if (preg_match('~\A[a-z][a-z0-9+.-]*:~i', $reference)) {
            return $reference;
        }
        $parts  = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        if (str_starts_with($reference, '//')) {
            return $scheme . ':' . $reference;
        }
        $origin = $scheme . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if (str_starts_with($reference, '/')) {
            return $origin . $reference;
        }
        $path = $parts['path'] ?? '/';

        return $origin . substr($path, 0, (int) strrpos($path, '/') + 1) . $reference;
    }
}
