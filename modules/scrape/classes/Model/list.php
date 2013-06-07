<?php
class Model_List extends Mango {
	protected $_fields = array(
		'charge'         => array(
			'type'     => 'string',
			'required' => TRUE,
			'min_length' => 1,
			'max_length' => 256,
		),
		'scrape'         => array(
			'type'     => 'string',
			'required' => TRUE,
			'min_length' => 1,
			'max_length' => 127,
		)
		
	);
	protected $_db = 'busted';
}