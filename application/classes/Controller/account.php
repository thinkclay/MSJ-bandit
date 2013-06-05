<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Account - Contains standard functionality for every account type
 *
 * @package Public
 * @author  Winter King
 */
class Controller_Account extends Controller_Private
{
	public $secure_actions = 
		array(
			'index'		=> 'login',
			'logout'	=> 'login',
			'create'	=> 'login',
			
		);
		
	public $auth_required = true;
    
    public function print_r2($val)
	{
        echo '<pre>';
        print_r($val);
        echo  '</pre>';
	}
    
    public function action_check_login()
    {
        if ($this->a1->logged_in())
        {
            echo 'logged in';
        }
        else 
        {
            echo 'not logged in';
        }
    }
    
    /**
     * Index - Defalt view for Dashboard
     *
     * @return  void
     * @author  Clay McIlrath
     * @todo    put this logic into the account model 
     
	public function action_index()
	{
	    $uid = Auth::instance()->get_user()->id;        
        if ( Auth::instance()->logged_in("agent") ) 
        {
            $this->template->view   = View::factory('/pages/agent/landing');
            $this->template->uid    = $uid;   
        }
        else if ( Auth::instance()->logged_in("agent_pending") ) 
        {
            $this->template->view   = View::factory('/pages/agent/pending');            
        }
        
        else if ( Auth::instance()->logged_in("customer") ) 
        {
            $this->template->view   = View::factory('/pages/account/landing');            
        }
        $this->template->title      = "Qwizzles | Account"; 
        $this->template->view->user = Auth::instance()->get_user();
	}
*/
 
 	/* index - used to redirect to appropriate page based on account status
	*
	* @return void
	* @author Winter King
	*/
	function action_index() 
	{
    	$user = $this->a1->get_user();
		$this->print_r2($user);
		exit;
    	if (in_array('admin', $user->role))
            Request::instance()->redirect('admin');
        else 
        {
            $user = Mango::factory('account' )->load();
            $this->template->title         = "Account Index";   
            $this->template->h1            = "Account Index";
            $this->template->h2            = "Welcome: " . $user->username;
            $this->template->view          = '<br/>hi this is the account index.. I will need some logic here soon';
            $this->template->view         .= '<br/>this will most likely just redirect depending on status';        
        }	
	}


	/**
	* admin - create and admin user with A1 using Mango 
	*
	* @return void
	* @author Winter King
	*/
	public function action_create()
	{
exit;		
$this->auth_required = false;
		//echo 'hello';
		//exit;
		# create a new account with role admin and login
		$this->template->title = 'create';
		$this->template->h1 = 'Account Creation';
		$post = array(
			'username'	 => 'thinkclay',
			'email'      => 'thinkclay@gmail.com',
			'role'		 => array('admin', 'login'),
			'password'   => 'Lollip0p!',
			'password_confirm' => 'Lollip0p!', 
		);
		
		$account = Mango::factory('account');
		
		# this is how to validate with Mango
		try
		{
			// validate data
			$post = $account->check($post);
			$a1 = a1::instance();
			$account->username = $post['username'];
			$account->email    = $post['email'];
			$account->password = $a1->hash($post['password']);
			$account->role	   = $post['role'];
			$account->create();
			$this->template->view = 'SUCCESS!';
		}
		catch (Validate_Exception $e)
		{
			//$errors = $account->error($e);
			//$errors = Kohana::Validate->errors('messages/errors');
			$errors = 'validaiton failed';
			$this->template->view = $errors;
		}
	}	


	/**
	* register - used to control which registraion to send the user to
	*
	* @operation connector script for registration module 
	* @params accepts a a role type	
	* @return void
	* @author Winter King 
	*/
	public function action_register($role)
	{
		//insert logic for deciding which role to register for
		if ($role == 'customer')
		{
			//Request::instance()->redirect('register/customer');	
		}
		if ($role == 'agent')
		{
			//Request::instance()->redirect('register/agent');
		}
		if ($role == 'broker')
		{
			//Request::instance()->redirect('register/broker');
		}
	}
	
	
	
	
    
    /**
     * Login - Function for user login
     *
	 * @TODO	validate user input before attempting login!
	 * @TODO 	display custom error messages against validation
     * @return  void
     * @author  Winter King
     */ 	
	public function action_login()
	{
		# redirect to account index if already logged in
		$this->auth_required = false;
		$this->template->title         = "Log In";  	
		$this->template->h1			   = " Admin";
		$this->template->h2			   = 'Please Login';
		$this->template->view = View::factory('forms/login');	
		if ($_POST) 
		{
			#@TODO validate input here!!!!
			$username = $_POST['username'];
			$password = $_POST['password'];
			$this->a1->login($username, $password);
			if ($this->a1->logged_in())
			{
				Request::instance()->redirect('account');
			}
		}
		
	}


    /**
     * Logout - Function for user logout
     *
     * @return  void
     * @author  Clay McIlrath
     */
	public function action_logout()
	{
		$a1 = a1::instance();
		$a1->logout();
		Request::instance()->redirect('account');
	}
}
