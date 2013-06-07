<?php
class Model_Testmodel extends Mango {
	protected $_fields = array(
		'county'         => array(
			'type'       => 'string',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 127,
		),	
		'state'         => array(
			'type'       => 'string',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 127,
		),	
		'scrapes'        => array(
			'type'       => 'int',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 127,
		),
		'week'           => array(
			'type'       => 'int',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 127,
		),
		'year'           => array(
			'type'     	 => 'int',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 127,
		),
		'total'          => array(
			'type'       => 'int',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 127,
		),
		'new'			 => array(
			'type'		 => 'int',
			'required'	 => false,
			'min_length' => 1,
			'max_length' => 127,
		), 
		'failed'		 => array(
			'type'		 => 'int',
			'required'	 => false,
			'min_length' => 1,
			'max_length' => 127,
		), 	
		'charge_mismatch'	=> array(
			'type'		 => 'int',
			'required'	 => false,
			'min_length' => 1,
			'max_length' => 127,
		), 
		'already_exists' => array(
			'type'		 => 'int',
			'required'	 => false,
			'min_length' => 1,
			'max_length' => 127,
		), 
		'times' 	 => array(
			'type'    	 => 'set',
		),
	);
	protected $_db = 'busted';
}