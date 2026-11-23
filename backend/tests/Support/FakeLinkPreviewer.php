<?php

namespace Tests\Support;

use App\Libraries\LinkPreviewer;
use App\Libraries\PhotoProcessor;

/**
 * Lector de enlaces sin red: responde con las páginas dadas y apunta las
 * direcciones pedidas.
 */
final class FakeLinkPreviewer extends LinkPreviewer
{
    /**
     * @var list<string>
     */
    public array $requested = [];

    /**
     * @param array<string, array{body: string, type: string}> $responses por dirección
     */
    public function __construct(private readonly array $responses = [])
    {
        parent::__construct(new PhotoProcessor(WRITEPATH . 'uploads/link-previews'));
    }

    protected function fetch(string $url, int $maxBytes, string $accept, bool $partial = false): ?array
    {
        $this->requested[] = $url;

        return isset($this->responses[$url]) ? [...$this->responses[$url], 'url' => $url] : null;
    }
}
