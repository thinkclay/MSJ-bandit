<?php
class Model_Api extends Mango {
    
	protected $_fields = array(
		'api_key'  => array(
            'type'       => 'string',
            'required'   => true,
            'min_length' => 1,
            'max_length' => 127,
        ),
        'acl' => array(
			'type'		=> 'set',
			'required' 	=> true,
		),
	);
	
    protected $_db = 'busted';
}