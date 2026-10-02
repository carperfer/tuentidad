<?php

namespace App\Controllers\Api;

use App\Libraries\InvitationSender;
use App\Libraries\Mailer;
use App\Models\InvitationModel;
use App\Models\WelcomeProfileModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Perfiles de bienvenida de la portada: la puerta de entrada para quien
 * no conoce a nadie dentro. Pedir amistad a uno envía una invitación
 * por email y, al registrarse, la amistad queda hecha.
 */
class WelcomeProfiles extends ApiController
{
    /**
     * Misma respuesta en todos los casos (email nuevo, ya registrado o bot),
     * para no revelar qué emails tienen cuenta.
     */
    private const DONE = '¡Hecho! Revisa tu correo para continuar.';

    public function index(): ResponseInterface
    {
        return $this->respond(['profiles' => model(WelcomeProfileModel::class)->listPublic()]);
    }

    public function request(int $id): ResponseInterface
    {
        if (($limit = $this->throttle('welcome-ip-' . $this->request->getIPAddress(), 5, HOUR)) !== null) {
            return $limit;
        }

        $profile = model(WelcomeProfileModel::class)
            ->select('welcome_profiles.user_id, users.first_name, users.last_name')
            ->join('users', 'users.id = welcome_profiles.user_id')
            ->where('welcome_profiles.id', $id)
            ->first();
        if ($profile === null) {
            return $this->message('Este perfil no existe.', 404);
        }

        $input = $this->input();

        // Campo trampa: invisible para las personas, los bots suelen rellenarlo
        if (! empty($input['website'])) {
            return $this->message(self::DONE);
        }

        $data = [
            'email'          => $this->normalizeEmail($input['email'] ?? ''),
            'accept_privacy' => empty($input['accept_privacy']) ? '' : '1',
        ];
        $valid = $this->validateData($data, [
            'email'          => ['label' => 'Email', 'rules' => 'required|valid_email|max_length[254]'],
            'accept_privacy' => [
                'label'  => 'Privacidad',
                'rules'  => 'required',
                'errors' => ['required' => 'Tienes que aceptar la política de privacidad.'],
            ],
        ]);
        if (! $valid) {
            return $this->invalid($this->validator->getErrors());
        }

        if (($limit = $this->throttle("welcome-email-{$data['email']}", 3, DAY)) !== null) {
            return $limit;
        }

        model(InvitationModel::class)->purgeStale();

        $name   = trim("{$profile['first_name']} {$profile['last_name']}");
        $config = config('Tuentidad');

        if (auth()->getProvider()->findByCredentials(['email' => $data['email']]) !== null) {
            (new Mailer())->send($data['email'], 'Ya tienes cuenta en tuentidad', 'emails/already_registered', [
                'loginUrl'  => $config->url(),
                'forgotUrl' => $config->url('recuperar-contrasena'),
            ]);

            return $this->message(self::DONE);
        }

        $result = (new InvitationSender())->send(
            (int) $profile['user_id'],
            $data['email'],
            "{$profile['first_name']} ha aceptado tu solicitud de amistad en tuentidad",
            'emails/welcome_request',
            ['profileName' => $name, 'profileFirstName' => $profile['first_name']],
            ['consented_at' => date('Y-m-d H:i:s')],
        );

        if ($result === InvitationSender::FAILED) {
            return $this->message('No hemos podido enviarte el email. Inténtalo más tarde.', 502);
        }

        return $this->message(self::DONE);
    }
}
