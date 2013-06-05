<?php defined('SYSPATH') OR die('No direct access allowed.'); 


/**
 * Custom error messaages are defined here
 *
 * @package Public
 * @author  Winter King
 * @note 	to set default error message use 'default' => 'message' 
 */
 return array(
'not_empty' => ':field must not be empty',
'matches' => ':field must be the same as :param1',
'regex' => ':field does not match the required format',
'exact_length' => ':field must be exactly :param1 characters long',
'min_length' => ':field must be at least :param1 characters long',
'max_length' => ':field must be less than :param1 characters long',
'in_array' => ':field must be one of the available options',
'digit' => ':field must be a digit',
'decimal' => ':field must be a decimal with :param1 places',
'range' => ':field must be within the range of :param1 to :param2',
'Upload::not_empty' => ':field image is required',
'Upload::type' => ':field image is not an image',
'Upload::size' => ':field image\'s filesize is too large',
'default' => 'default msg',
);
	return array 
	( /*
		'email' => array(
		    'email_available'  => 'This email address already exists',        
		    'email'    		   => 'Must be a valid email address',
		),
	 
		'username' => array(
			'username_available'    => 'The username already exists',
			'invalid'				=> 'Invalid username or password',   # @note this is actually the message for an invalid login attempt. 																 																 # 	 	 Defined in modules/auth/classes/model/auth/user.php
		),
		
		'password' => array(
		    'matches'  			=> 'Doesnt match',     
		    'password_confirm'  => 'Doesnt match mofo!',
		    'min_length'		=> 'Password must be at least 5 characters long', 
		    'not_empty'			=> 'Password must not be empty',
		),
		'password_confirm' => array(
			'matches'  		=> 'Password and Password Confirm fields do not match.  Please try again.',
			'not_empty'		=> 'Confirm Password must not be empty'   
		),
	 * 
	 */
	 
	 'password_again' => array(
		    'matches'  			=> 'Doesnt match', 
		    'min_length'		=> 'Password must be at least 5 characters long', 
		    'not_empty'			=> 'Password must not be empty',
		),
	); 