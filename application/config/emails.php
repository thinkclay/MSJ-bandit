<?php defined('SYSPATH') or die('No direct script access.');

return [
    'admins' => ['clay@mugshotjunkie.com, rc@mugshotjunkie.com'],

    // The system email to send FROM
    'system' => 'clay@mugshotjunkie.com',

    // Identify our email templates
    'templates' => [
        'mail.exception.generic' => [
            'name'          => 'An Exception was thrown on a scrape',
            'subject'       => 'An Exception was thrown on a scrape',
            'description'   => 'This email gets sent out whenever an exception is thrown'
        ]
    ]
];