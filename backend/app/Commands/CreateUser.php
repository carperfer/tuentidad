<?php

namespace App\Commands;

use App\Libraries\UserCreator;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

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
            'email'      => (string) (CLI::getOption('email') ?? CLI::prompt('Email', null, 'required|valid_email')),
            'first_name' => (string) (CLI::getOption('nombre') ?? CLI::prompt('Nombre', null, 'required')),
            'last_name'  => (string) (CLI::getOption('apellidos') ?? CLI::prompt('Apellidos', null, 'required')),
            'birthdate'  => (string) (CLI::getOption('nacimiento') ?? CLI::prompt('Fecha de nacimiento (AAAA-MM-DD)', null, 'required')),
            'password'   => (string) (CLI::getOption('password') ?? $this->askPassword()),
        ];

        $creator = new UserCreator();
        $user    = $creator->create($data);

        if ($user === null) {
            foreach ($creator->errors() as $error) {
                CLI::error($error);
            }

            return EXIT_ERROR;
        }

        CLI::write("Usuario {$user->email} creado (id {$user->id}).", 'green');

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
