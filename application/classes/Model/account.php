<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Account 
 * 
 * @notes - this is our interface to mango for user registration
 * @package 
 * @author  Wouter
 */
class Model_Account extends Mango {
	
	protected $_fields = array(
		'username'         => array(
			'type'     => 'string',
			'required' => TRUE,
			'min_length' => 3,
			'max_length' => 127,
			'rules' => array(
			'alpha_numeric' => NULL
			)
		),
		'role' => array(
			'type'     => 'mixed',
			'required' => TRUE
		),
		'email' => array(
			'type'       => 'string',
			'required'   => TRUE,
			'min_length' => 4,
			'max_length' => 127,
			'unique'     => TRUE,
		),
		'password'         => array(
			'type'     => 'string',
			'required' => TRUE,
			'min_length' => 3,
			'max_length' => 127,
			'rules' => array(
				//'alpha_numeric' => NULL
			),
		),
		'password_confirm'         => array(
			'type'     => 'string',
			//'required' => TRUE,
			'min_length' => 3,
			'max_length' => 127,
			'rules' => array(
				//'alpha_numeric' => NULL,
				'matches'	=> array('password')
			),
		),
	);
	
	protected $_db = 'busted'; //don't use default db config

}
