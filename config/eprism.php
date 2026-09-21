<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Initial administrator
    |--------------------------------------------------------------------------
    |
    | The one account AdminUserSeeder creates (see DatabaseSeeder and the
    | `eprism:reset-data` command). Read through config() rather than env() at
    | seed time: once `php artisan optimize` has cached the config — as the
    | production entrypoint does — env() returns null outside config files, which
    | would seed an admin with no name, email or password.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
