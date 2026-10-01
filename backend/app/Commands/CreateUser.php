<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Entities\User;

/**
 * Crea un usuario sin invitación (p. ej. el primero, o en desarrollo).
 */
class CreateUser extends BaseCommand
{
    protected $group       = 'tuentidad';
    protected $name        = 'tuentidad:usuario';
    protected $description = 'Crea un usuario activo sin invitación.';
    protected $usage       = 'tuentidad:usuario [--email E] [--nombre N] [--apellidos A] [--nacimiento AAAA-MM-DD] [--password P]';
    protected $options     = [
        '--email'      => 'Email',
        '--nombre'     => 'Nombre',
        '--apellidos'  => 'Apellidos',
        '--nacimiento' => 'Fecha de nacimiento (AAAA-MM-DD)',
        '--password'   => 'Contraseña (si se omite, se pide sin mostrarla)',
    ];

    public function run(array $params)
    {
        $data = [
            'email'      => mb_strtolower(trim((string) (CLI::getOption('email') ?? CLI::prompt('Email', null, 'required|valid_email')))),
            'first_name' => (string) (CLI::getOption('nombre') ?? CLI::prompt('Nombre', null, 'required')),
            'last_name'  => (string) (CLI::getOption('apellidos') ?? CLI::prompt('Apellidos', null, 'required')),
            'birthdate'  => (string) (CLI::getOption('nacimiento') ?? CLI::prompt('Fecha de nacimiento (AAAA-MM-DD)', null, 'required')),
            'password'   => (string) (CLI::getOption('password') ?? $this->askPassword()),
        ];

        $validation = service('validation')->setRules([
            'email'      => ['label' => 'Email', 'rules' => 'required|valid_email'],
            'first_name' => ['label' => 'Nombre', 'rules' => 'required|max_length[50]'],
            'last_name'  => ['label' => 'Apellidos', 'rules' => 'required|max_length[80]'],
            'birthdate'  => ['label' => 'Fecha de nacimiento', 'rules' => 'required|valid_birthdate'],
            'password'   => ['label' => 'Contraseña', 'rules' => 'required|strong_password[]'],
        ]);
        if (! $validation->run($data)) {
            foreach ($validation->getErrors() as $error) {
                CLI::error($error);
            }

            return EXIT_ERROR;
        }

        $users = auth()->getProvider();
        if ($users->findByCredentials(['email' => $data['email']]) !== null) {
            CLI::error("Ya existe un usuario con el email {$data['email']}.");

            return EXIT_ERROR;
        }

        $users->save(new User($data));
        $user = $users->findById($users->getInsertID());
        $users->addToDefaultGroup($user);
        $user->activate();

        CLI::write("Usuario {$data['email']} creado (id {$user->id}).", 'green');

        return EXIT_SUCCESS;
    }

    private function askPassword(): string
    {
        CLI::print('Contraseña: ');
        system('stty -echo');
        $password = trim((string) fgets(STDIN));
        system('stty echo');
        CLI::newLine();

        return $password;
    }
}
