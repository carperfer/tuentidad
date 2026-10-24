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
        $routes->get('friends', 'Friends::index');
        $routes->get('friends/activity', 'Friends::activity');
        $routes->get('friends/search', 'Friends::search');
        $routes->delete('friends/(:num)', 'Friends::remove/$1');
        $routes->get('friend-requests', 'Friends::requests');
        $routes->post('friend-requests', 'Friends::request');
        $routes->post('friend-requests/(:num)/accept', 'Friends::accept/$1');
        $routes->post('friend-requests/(:num)/reject', 'Friends::reject/$1');
        $routes->delete('friend-requests/(:num)', 'Friends::cancel/$1');
        $routes->get('profiles/(:num)', 'Profiles::show/$1');
        $routes->get('profiles/(:num)/avatar', 'Profiles::avatar/$1');
        $routes->put('profile', 'Profiles::update');
        $routes->post('profile/avatar', 'Profiles::uploadAvatar');
        $routes->get('users/(:num)/albums', 'Albums::index/$1');
        $routes->post('albums', 'Albums::create');
        $routes->get('albums/(:num)', 'Albums::show/$1');
        $routes->put('albums/(:num)', 'Albums::update/$1');
        $routes->delete('albums/(:num)', 'Albums::delete/$1');
        $routes->post('albums/(:num)/photos', 'Albums::upload/$1');
        $routes->get('photos/(:num)/image', 'Albums::image/$1');
        $routes->get('photos/(:num)/thumb', 'Albums::thumb/$1');
        $routes->delete('photos/(:num)', 'Albums::deletePhoto/$1');
        $routes->get('invitations', 'Invitations::index');
        $routes->post('invitations', 'Invitations::create');
    });
});
