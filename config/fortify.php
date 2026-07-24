<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'middleware' => ['web'],
    'auth_middleware' => 'auth',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'views' => true,
    'home' => '/',
    'prefix' => '',
    'domain' => null,
    'lowercase_usernames' => false,
    'limiters' => [
        'login' => null,
    ],
    'paths' => [
        'login' => null,
        'logout' => null,
        'register' => null,
    ],
    'redirects' => [
        'login' => '/',
        'logout' => '/',
        'register' => '/',
    ],
    'features' => [
        Features::registration(),
    ],
];
