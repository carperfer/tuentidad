<?php

namespace App\Libraries;

use CodeIgniter\Shield\Entities\User;

/**
 * Crea usuarios activos sin invitación (primer usuario, desarrollo).
 */
class UserCreator
{
    /**
     * @var array<string, string>
     */
    private array $errors = [];

    /**
     * @param array<string, mixed> $input email, first_name, last_name, birthdate, password
     *
     * @return User|null El usuario creado, o null si hay errores (ver errors())
     */
    public function create(array $input): ?User
    {
        $data = [
            'email'      => is_string($input['email'] ?? null) ? mb_strtolower(trim($input['email'])) : '',
            'first_name' => is_string($input['first_name'] ?? null) ? trim($input['first_name']) : '',
            'last_name'  => is_string($input['last_name'] ?? null) ? trim($input['last_name']) : '',
            'birthdate'  => $input['birthdate'] ?? '',
            'password'   => $input['password'] ?? '',
        ];

        $validation = service('validation')->reset()->setRules([
            'email'      => ['label' => 'Email', 'rules' => 'required|valid_email|max_length[254]'],
            'first_name' => ['label' => 'Nombre', 'rules' => 'required|max_length[50]'],
            'last_name'  => ['label' => 'Apellidos', 'rules' => 'required|max_length[80]'],
            'birthdate'  => ['label' => 'Fecha de nacimiento', 'rules' => 'required|valid_birthdate'],
            'password'   => ['label' => 'Contraseña', 'rules' => 'required|string|max_length[255]|strong_password[]'],
        ]);
        if (! $validation->run($data)) {
            $this->errors = $validation->getErrors();

            return null;
        }

        $users = auth()->getProvider();
        if ($users->findByCredentials(['email' => $data['email']]) !== null) {
            $this->errors = ['email' => "Ya existe un usuario con el email {$data['email']}."];

            return null;
        }

        $users->save(new User($data));
        $user = $users->findById($users->getInsertID());
        $users->addToDefaultGroup($user);
        $user->activate();

        return $user;
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
