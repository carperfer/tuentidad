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
    $routes->post('deploy/backup', 'Deploy::backup');
    $routes->post('deploy/reminders', 'Deploy::reminders');
    $routes->post('deploy/digests', 'Deploy::digests');

    $routes->group('', ['filter' => 'apiauth'], static function (RouteCollection $routes): void {
        $routes->get('dashboard', 'Dashboard::index');
        $routes->get('account/export', 'Account::export');
        $routes->delete('account', 'Account::delete');
        $routes->get('friends', 'Friends::index');
        $routes->get('friends/activity', 'Friends::activity');
        $routes->get('friends/search', 'Friends::search');
        $routes->delete('friends/(:num)', 'Friends::remove/$1');
        $routes->get('users/(:num)/friends', 'Friends::ofUser/$1');
        $routes->get('users/(:num)/mutual-friends', 'Friends::mutual/$1');
        $routes->get('users/(:num)/photos', 'Albums::recentPhotos/$1');
        $routes->get('friend-requests', 'Friends::requests');
        $routes->post('friend-requests', 'Friends::request');
        $routes->post('friend-requests/(:num)/accept', 'Friends::accept/$1');
        $routes->post('friend-requests/(:num)/reject', 'Friends::reject/$1');
        $routes->delete('friend-requests/(:num)', 'Friends::cancel/$1');
        $routes->get('profiles/(:num)', 'Profiles::show/$1');
        $routes->get('profiles/(:num)/avatar', 'Profiles::avatar/$1');
        $routes->put('profile', 'Profiles::update');
        $routes->put('profile/status', 'Profiles::updateStatus');
        $routes->put('profile/settings', 'Profiles::updateSettings');
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
        $routes->get('photos/(:num)', 'Photos::show/$1');
        $routes->get('photos/(:num)/taggable', 'Photos::taggable/$1');
        $routes->post('photos/(:num)/tags', 'Photos::tag/$1');
        $routes->delete('photo-tags/(:num)', 'Photos::untag/$1');
        $routes->post('photos/(:num)/comments', 'Photos::comment/$1');
        $routes->delete('photo-comments/(:num)', 'Photos::deleteComment/$1');
        $routes->get('users/(:num)/tagged-photos', 'Photos::tagged/$1');
        $routes->get('videos', 'Videos::feed');
        $routes->post('videos', 'Videos::create');
        $routes->delete('videos/(:num)', 'Videos::delete/$1');
        $routes->get('users/(:num)/videos', 'Videos::ofUser/$1');
        $routes->get('calendar', 'Events::calendar');
        $routes->get('events', 'Events::index');
        $routes->post('events', 'Events::create');
        $routes->get('events/invitable', 'Events::invitable');
        $routes->get('events/(:num)', 'Events::show/$1');
        $routes->put('events/(:num)', 'Events::update/$1');
        $routes->delete('events/(:num)', 'Events::delete/$1');
        $routes->post('events/(:num)/invitations', 'Events::inviteMore/$1');
        $routes->put('events/(:num)/rsvp', 'Events::rsvp/$1');
        $routes->post('events/(:num)/comments', 'Events::comment/$1');
        $routes->delete('event-comments/(:num)', 'Events::deleteComment/$1');
        $routes->get('chat', 'Chat::status');
        $routes->get('chat/conversations', 'Chat::conversations');
        $routes->get('chat/(:num)/messages', 'Chat::messages/$1');
        $routes->post('chat/(:num)/messages', 'Chat::send/$1');
        $routes->get('notifications', 'Notifications::index');
        $routes->get('notifications/unread', 'Notifications::unread');
        $routes->post('notifications/read', 'Notifications::markRead');
        $routes->post('notifications/(:num)/read', 'Notifications::markOneRead/$1');
        $routes->get('invitations', 'Invitations::index');
        $routes->post('invitations', 'Invitations::create');
    });
});
