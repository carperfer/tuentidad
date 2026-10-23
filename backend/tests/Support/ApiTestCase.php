<?php

namespace Tests\Support;

use CodeIgniter\Config\Services;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;

/**
 * Base para los tests de la API: base de datos limpia, emails capturados
 * y límites de peticiones reiniciados en cada test.
 *
 * @internal
 */
abstract class ApiTestCase extends CIUnitTestCase
{
    use AuthenticationTesting;
    use DatabaseTestTrait;
    use FeatureTestTrait {
        call as protected featureCall;
    }

    public const PASSWORD = 'Gato.Azul.Saltarin.88';

    protected $migrate = true;
    protected $refresh = true;
    protected $namespace;
    protected CapturingEmail $email;

    /**
     * Si es false, las peticiones que modifican datos se envían sin token CSRF.
     */
    protected bool $sendCsrf = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Cada test empieza sin sesión, sin límites consumidos y con servicios nuevos
        $this->resetServices();
        $_SESSION = [];
        $this->mockCache();
        $this->mockSession();

        $this->email = new CapturingEmail(config('Email'));
        Services::injectMock('email', $this->email);
    }

    /**
     * Añade el token CSRF de la sesión a las peticiones que modifican datos,
     * como hace la SPA con /api/csrf.
     *
     * @param array<string, mixed>|null $params
     */
    public function call(string $method, string $path, ?array $params = null)
    {
        if ($this->sendCsrf && strtoupper($method) !== 'GET') {
            $this->headers[csrf_header()] = csrf_hash();
        }

        return $this->featureCall($method, $path, $params);
    }

    protected function createUser(string $email = 'ana@example.com', string $firstName = 'Ana'): User
    {
        $users = auth()->getProvider();
        $users->save(new User([
            'email'      => $email,
            'password'   => self::PASSWORD,
            'first_name' => $firstName,
            'last_name'  => 'Pruebas',
            'birthdate'  => '1990-01-01',
        ]));
        $user = $users->findById($users->getInsertID());
        $user->activate();

        return $user;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function postJson(string $path, array $data = []): TestResponse
    {
        return $this->withBodyFormat('json')->post($path, $data);
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(TestResponse $response): array
    {
        return json_decode($response->getJSON() ?: 'null', true) ?? [];
    }

    /**
     * Extrae de un email enviado el token del enlace con el prefijo dado.
     */
    protected function tokenFromEmail(string $pathPrefix, int $index = -1): string
    {
        $sent = $this->email->sent;
        $this->assertNotEmpty($sent, 'No se ha enviado ningún email');
        $message = $index < 0 ? $sent[count($sent) + $index] : $sent[$index];

        $this->assertMatchesRegularExpression("#{$pathPrefix}/([a-f0-9]{64})#", $message['body']);
        preg_match("#{$pathPrefix}/([a-f0-9]{64})#", $message['body'], $matches);

        return $matches[1];
    }
}
