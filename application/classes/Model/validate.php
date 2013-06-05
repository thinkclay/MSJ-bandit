<?php

/* 
 * Abstract A1 Authentication User Model
 * To be extended and completed to user's needs
 *
 * Remember to validate data before saving to database. Some validation rules are already taken care of by the _fields declaration.
 * Min_length and max_length are set for both username and password, both these fields are already required, and the username
 * will be checked on uniqueness. However, you might want to add additional rules to validate if username is alphanumeric for example.
 */

class Model_Validate extends A1_Mango {

	protected $_fields = array(
		'username'   => array('type'=>'string','required' => TRUE,'unique' => TRUE, 'min_length' => 4,'max_length' => 50),
		'password'   => array('type'=>'string','required' => TRUE, 'min_length' => 5,'max_length' => 50)
	);
	
	/**
	* validate update - used for validating the submitted profile update form
	*
	* @return void
	* @author Winter King
	*/
	public function validate_login(& $array) 
	{
		// Initialise the validation library and setup some rules   
	  	$array = Validate::factory($array)
	  	->filter(TRUE, 'trim')
	  	->rules('username', $this->_fields['username'])
	  	->rules('password', $this->_fields['password']);

	    return $array;
    }
	
}