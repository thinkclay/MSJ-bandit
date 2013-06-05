<?php defined('SYSPATH') OR die('No Direct Script Access');


class Model_Brass_Scrape extends Brass
{

    public $_fields = [
        'name' => [
            'editable'  => 'admin',
            'label'     => 'Display Name',
            'type'      => 'string',
            'required'  => TRUE
        ],
        'active' => [
            'editable'  => 'admin',
            'label'     => 'Active?',
            'input'     => 'checkbox',
            'type'      => 'bool',
        ],
        'title' => [
            'editable'  => 'admin',
            'label'     => 'Job Title',
            'type'      => 'string',
        ],
        'description' => [
            'editable'  => 'user',
            'label'     => 'Post Description',
            'type'      => 'string',
            'input'     => 'textarea',
        ],
        'state' => [
            'editable'  => 'admin',
            'label'     => 'State',
            'type'      => 'string',
            'required'  => TRUE
        ],
        'county' => [
            'editable'  => 'admin',
            'label'     => 'County',
            'type'      => 'string',
        ]
    ];

}