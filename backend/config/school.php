<?php

return [
    'name' => env('APP_NAME', 'Brighter Day Preparatory Elementary, Junior & Senior High School'),
    'address' => env('SCHOOL_ADDRESS', 'Monrovia, Liberia'),
    'phone' => env('SCHOOL_PHONE'),
    'email' => env('MAIL_FROM_ADDRESS'),
    'login_url' => env('SCHOOL_LOGIN_URL', env('FRONTEND_URL', 'http://localhost:5173').'/login'),
];
