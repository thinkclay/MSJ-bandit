<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Account 
 * 
 * @notes - this is our interface to mango for user registration
 * @package 
 * @author  Wouter
 */
class Model_User extends Mango {
	
	protected $_fields = array(
		'name'         => array(
			'type'     => 'string',
			'required' => TRUE,
			'min_length' => 3,
			'max_length' => 127,
			'rules' => array(
			'alpha_numeric' => NULL
			)
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
		'device_token' => array(
			'type'       => 'string',
			'min_length' => 40,
			'max_length' => 40,
			'unique'     => TRUE,
		),
		'receipt_data' => array(
			'type'       => 'string'
		),
		'subscription_expiration' => array(
			'type'       => 'string'
		)
	);
	
	protected $_db = 'busted'; //don't use default db config

}
