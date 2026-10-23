<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->group('api', ['namespace' => 'App\Controllers\Api'], static function (RouteCollection $routes): void {
    $routes->get('health', 'Health::index');
    $routes->get('csrf', 'Csrf::index');

    $routes->get('auth/me', 'Auth::me');
    $routes->post('auth/login', 'Auth::login');
    $routes->post('auth/logout', 'Auth::logout');
    $routes->post('auth/register', 'Registration::register');
    $routes->post('auth/forgot-password', 'Passwords::forgot');
    $routes->post('auth/reset-password', 'Passwords::reset');
    $routes->get('invitations/(:segment)', 'Invitations::show/$1');

    $routes->get('welcome-profiles', 'WelcomeProfiles::index');
    $routes->post('welcome-profiles/(:num)/friend-requests', 'WelcomeProfiles::request/$1');

    $routes->post('deploy/migrate', 'Deploy::migrate');
    $routes->post('deploy/first-user', 'Deploy::firstUser');

    $routes->group('', ['filter' => 'apiauth'], static function (RouteCollection $routes): void {
        $routes->get('dashboard', 'Dashboard::index');
        $routes->get('profiles/(:num)', 'Profiles::show/$1');
        $routes->get('profiles/(:num)/avatar', 'Profiles::avatar/$1');
        $routes->put('profile', 'Profiles::update');
        $routes->post('profile/avatar', 'Profiles::uploadAvatar');
        $routes->get('invitations', 'Invitations::index');
        $routes->post('invitations', 'Invitations::create');
    });
});
