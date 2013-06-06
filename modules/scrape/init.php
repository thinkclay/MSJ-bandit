<?php defined('SYSPATH') or die('No direct access allowed.');

Route::set('scraper', 'scrape(/<action>(/<id>))')
    ->defaults(array(
        'controller' => 'scrape',
        'action'     => 'index',
    ));


Route::set('api', 'api(/<action>(/<id>(/<id2>)))')
    ->defaults(array(
        'directory'  => '',
        'controller' => 'api',
        'action'     => 'index',
    ));