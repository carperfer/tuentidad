<?php

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * Resumen de la actividad propia para la página de inicio.
 */
class Dashboard extends ApiController
{
    public function index(): ResponseInterface
    {
        $userId   = (int) auth()->user()->id;
        $sent     = db_connect()->table('invitations')->where('inviter_id', $userId)->countAllResults();
        $accepted = db_connect()->table('invitations')
            ->where('inviter_id', $userId)
            ->where('accepted_at IS NOT NULL', null, false)
            ->countAllResults();
        $friends = db_connect()->table('friendships')->where('user_id', $userId)->countAllResults();

        return $this->respond([
            'invitations_sent'     => $sent,
            'invitations_accepted' => $accepted,
            'friends_count'        => $friends,
        ]);
    }
}
