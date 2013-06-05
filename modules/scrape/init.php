<?php defined('SYSPATH') or die('No direct access allowed.');

Route::set('api', 'api(/<action>(/<id>(/<id2>)))')
    ->defaults(array(
        'directory'  => '',
        'controller' => 'api',
        'action'     => 'index',
    ));