<?php
class Model_Mscrape extends Mango {
   
    protected $_fields = array(
        'name'           => array(
            'type'       => 'string',
            'required'   => true,
            'min_length' => 1,
            'max_length' => 127,
            'unique'     => true,
        ),       
        'state'          => array(
            'type'       => 'string',
            'required'   => true,
            'min_length' => 1,
            'max_length' => 127,
        ),     
        'booking_ids'    => array(
            'type'       => 'array',
            'min_length' => 1,
            'max_length' => 127,
        ),
    );
    protected $_db = 'busted';
}