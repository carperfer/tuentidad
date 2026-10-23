<?php

namespace Tests\Support;

use CodeIgniter\Test\Mock\MockEmail;

/**
 * Email simulado que guarda los mensajes enviados para inspeccionarlos.
 */
class CapturingEmail extends MockEmail
{
    /**
     * @var list<array{to: list<string>, subject: string, body: string}>
     */
    public array $sent = [];

    public function send($autoClear = true)
    {
        if ($this->returnValue) {
            $this->sent[] = [
                'to'      => $this->recipients,
                'subject' => (string) ($this->tmpArchive['subject'] ?? ''),
                'body'    => (string) $this->body,
            ];
        }

        return parent::send($autoClear);
    }
}
