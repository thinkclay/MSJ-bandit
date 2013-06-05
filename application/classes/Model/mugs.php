<?php
class Model_Mugs extends Mango {
    protected $_fields = array(
        'scrape'         => array(
            'type'     => 'string',
            'required' => false,
            'min_length' => 1,
            'max_length' => 127,
        ),  
        'week'         => array(
            'type'     => 'int',
            'required' => false,
            'min_length' => 1,
            'max_length' => 127,
        ),
        'year'         => array(
            'type'     => 'int',
            'required' => false,
            'min_length' => 1,
            'max_length' => 127,
        ),
        'total'         => array(
            'type'     => 'int',
            'required' => TRUE,
            'min_length' => 1,
            'max_length' => 127,
        ),
        'successful'    => array(
            'type'      => 'int',
            'min_length' => 1,
            'max_length' => 127,
        ),
        'new_charges'         => array(
            'type'     => 'array',
            'required' => FALSE,
        ),
    );
    protected $_db = 'busted';
}