<?php

namespace App\Libraries;

/**
 * Envío de emails con la plantilla común de tuentidad.
 */
class Mailer
{
    /**
     * @param array<string, mixed> $data Datos para la vista del contenido
     */
    public function send(string $to, string $subject, string $view, array $data = []): bool
    {
        $config = config('Email');
        $email  = service('email');

        $email->clear();
        $email->setFrom($config->fromEmail, $config->fromName);
        $email->setTo($to);
        $email->setSubject($subject);
        $email->setMailType('html');
        $email->setMessage(view('emails/layout', [
            'subject' => $subject,
            'content' => view($view, $data),
        ]));

        if (! $email->send(false)) {
            log_message('error', 'No se pudo enviar el email "{subject}" a {to}: {debug}', [
                'subject' => $subject,
                'to'      => $to,
                'debug'   => $email->printDebugger(['headers']),
            ]);

            return false;
        }

        return true;
    }
}
