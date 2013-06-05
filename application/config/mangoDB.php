<?php defined('SYSPATH') or die('No direct script access.');

return [

    'default' => [
        'connection' => [

            /** hostnames, separate multiple hosts by commas **/
            'hostnames' => 'localhost:27017',

            /** connection options (see http://www.php.net/manual/en/mongo.construct.php) **/
            'options'   => [
                'db'         => 'busted',
                'connectTimeoutMS'    => 2000,
                'connect'    => TRUE,
                'username'  => 'chosen',
                'password'  => 'Ch0s3nLollip0p!',
            ]
        ]
    ],

    'busted' => [
        'connection' => [

            /** hostnames, separate multiple hosts by commas **/
            'hostnames' => 'localhost:27017',

            /** connection options (see http://www.php.net/manual/en/mongo.construct.php) **/
            'options'   => [
                'db'         => 'busted',
                'connectTimeoutMS'    => 2000,
                'connect'    => TRUE,
                'username'  => 'chosen',
                'password'  => 'Ch0s3nLollip0p!',
            ]
        ]
    ],
];