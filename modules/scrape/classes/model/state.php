<?php
class Model_State extends Mango {
    protected $_fields = array(
        'name'           => array(
            'type'       => 'string',
            'required'   => true,
            'min_length' => 1,
            'max_length' => 127,
        ),       
        'counties'        => array('type'=>'has_many')
    );
	
    protected $_db = 'busted';
}