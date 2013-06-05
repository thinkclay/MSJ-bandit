<?php defined('SYSPATH') OR die('No Direct Script Access');


class Model_Brass_Offender extends Brass
{

    public $_fields = [
        'booking_id' => [
            'editable'  => 'admin',
            'label'     => 'Booking ID',
            'type'      => 'string',
            'required'  => TRUE,
            'unique'    => TRUE,
        ],
        'updated' => [
            'type'      => 'int',
            'required'  => TRUE
        ],
        'scrape_time' => [
            'type'      => 'int',
            'required'  => TRUE
        ],
        'scrape' => [
            'type'      => 'string',
            'required'  => TRUE
        ],

        'booking_date' => [
            'editable'  => 'admin',
            'label'     => 'Booking Date',
            'type'      => 'int'
        ],

        'firstname' => [
            'editable'  => 'admin',
            'label'     => 'First Name',
            'type'      => 'string'
        ],
        'middlename'  => [
            'editable'  => 'admin',
            'label'     => 'Middle Name',
            'type'      => 'string'
        ],
        'lastname'  => [
            'editable'  => 'admin',
            'label'     => 'Last Name',
            'type'      => 'string'
        ],

        'gender' => [
            'editable'  => 'admin',
            'label'     => 'Gender',
            'type'      => 'string'
        ],

        'address' => [
            'editable'  => 'admin',
            'label'     => 'Address',
            'type'      => 'string'
        ],
        'city' => [
            'editable'  => 'admin',
            'label'     => 'City',
            'type'      => 'string'
        ],
        'county' => [
            'editable'  => 'admin',
            'label'     => 'County',
            'type'      => 'string'
        ],
        'zip' => [
            'editable'  => 'admin',
            'label'     => 'Zip',
            'type'      => 'string'
        ],
        'state' => [
            'type'      => 'string',
            'required'  => TRUE
        ],

        'charges'   => [
            'type'       => 'array',
            'required'   => TRUE
        ],
        'image' => [
            'editable'  => 'admin',
            'label'     => 'Image',
            'type'      => 'string'
        ],

        'age' => [
            'editable'  => 'admin',
            'label'     => 'Last Name',
            'type'      => 'int'
        ],
        'dob' => [
            'editable'  => 'admin',
            'label'     => 'DOB',
            'type'      => 'int'
        ],
        'birth_year' => [
            'editable'  => 'admin',
            'label'     => 'Birth Year',
            'type'      => 'string'
        ],

        'race' => [
            'editable'  => 'admin',
            'label'     => 'Race',
            'type'      => 'string'
        ],
        'complexion' => [
            'editable'  => 'admin',
            'label'     => 'Complexion',
            'type'      => 'string'
        ],
        'eye_color' => [
            'editable'  => 'admin',
            'label'     => 'Eye Color',
            'type'      => 'string'
        ],
        'hair_color' => [
            'editable'  => 'admin',
            'label'     => 'Hair Color',
            'type'      => 'string'
        ],
        'height' => [
            'editable'  => 'admin',
            'label'     => 'Height',
            'type'      => 'int'
        ],
        'weight' => [
            'editable'  => 'admin',
            'label'     => 'Weight',
            'type'      => 'int'
        ],

        'misc' => [
            'type'      => 'string',
        ],
        'rating' => [
            'type'      => 'set',
        ],
        'status' => [
            'editable'  => 'admin',
            'label'     => 'Status',
            'type'      => 'string'
        ]
    ];

}