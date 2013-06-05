<?php
class Model_Offender extends Mango {
    
	protected $_fields = array(
		'scrape'         => array(
			'type'       => 'string',
			'required'   => true,
			'min_length' => 1,
			'max_length' => 127,
		),
		'firstname'      => array(
    		'type'       => 'string',
    		'required'   => true,
    		'min_length' => 1,
    		'max_length' => 127,
    	),
		'lastname'       => array(
			'type'       => 'string',
			'required'   => true,
			'min_length' => 1,
			'max_length' => 127,
		),
		'middlename'      => array(
    		'type'       => 'string',
    		'required'   => false,
    		'min_length' => 1,
    		'max_length' => 127,
    	),
		'booking_id'     => array(
			'type'       => 'string',
			'required'   => true,
			'min_length' => 1,
			'max_length' => 127,
			'unique'     => true,
		),
		'booking_date'   => array(
			'type'       => 'int',
			'required'   => true,
			'min_length' => 1,
			'max_length' => 127,
		),
		'image'          => array(
			'type'       => 'string',
			'required'   => true,
			'min_length' => 1,
			'max_length' => 127,
		),
		'scrape_time'	 => array(
			'type'		 => 'int',
			'required'   => true,
			'min_length' => 1,
			'max_length' => 127,
		),
		'dob' 			 => array(
			'type'  	 => 'int',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 127,
		), 
		'age'			 => array(
			'type'  	 => 'int',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 127,
		), 
		'birth_year'	 => array(
			'type'  	 => 'string',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 127,
		), 
		'gender'		 => array(
			'type'  	 => 'string',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 127,
		), 
		'race'			 => array(
			'type'  	 => 'string',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 127,
		), 
		'height'		 => array(
			'type'  	 => 'int',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 127,
		), 
		'weight'		 => array(
			'type'  	 => 'int',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 127,
		), 
		'eye_color'		 => array(
			'type'  	 => 'string',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 127,
		), 
		'hair_color'		 => array(
			'type'  	 => 'string',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 127,
		), 
		'city'		 => array(
			'type'  	 => 'string',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 255,
		),
		'state'		 => array(
			'type'  	 => 'string',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 255,
		),
		'zip'		 => array(
			'type'  	 => 'string',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 255,
		),
		'address'		 => array(
			'type'  	 => 'string',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 255,
		),
		'county'		 => array(
			'type'  	 => 'string',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 255,
		),
		'misc'		 => array(
			'type'  	 => 'string',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 127,
		),
		'rating'	=> array(
			'type'  	 => 'set',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 2,
		), 
		'complexion'	=> array(
			'type'  	 => 'string',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 127,
		), 
		'charges'	=> array(
			'type'  	 => 'array',
			'required'   => false,
			'min_length' => 1,
			'max_length' => 2,
		),
		'status'  => array(
            'type'       => 'string',
            'required'   => false,
            'min_length' => 1,
            'max_length' => 127,
        ),
	);
 
    protected $_db = 'busted';
}