<?php defined('SYSPATH') or die('No direct script access.');
  
class Controller_Private extends Controller_Template
{

	public $template = 'wrapper'; 
  
    // Controls access for the whole controller, if not set to FALSE we will only allow user roles specified
    // Can be set to a string or an array, for example 'login' or array('login', 'admin')
    // Note that in second(array) example, user must have both 'login' AND 'admin' roles set in database
    public $auth_required = FALSE;
 
    // Controls access for separate actions
    // 'admin' => 'admin' will only allow users with the role admin to access action_admin
    // 'moderator' => array('login', 'admin') only allows users with roles 'login' and 'moderator' to access action_moderator
    public $secure_actions = FALSE;
    
   
	// The before() method is called before your controller action.
	// In our template controller we override this method so that we can
	// set up default values. These variables are then available to our
	// controllers if they need to be modified.
	public function before()
	{
		parent::before(); 
		#THESE ARE A MUST!
		$this->a1 = A1::instance();	
		$this->request = Request::instance();
		// Initialize Properties (eg. title, keywords, styles, etc))
		if ($this->auto_render)
		{
            $this->template->h1			= 'Admin Dashboard';
            $this->template->styles 	= array('public/styles/style.css' => 'screen');
		    $this->template->scripts	= array('<script>google.load("jquery", "1.5.1"); google.load("jqueryui", "1.8.11");</script>');

        }
	}
	
	public function after()
	{
		parent::after();
		
		
		
		// here is where I can do my secure actions and access control stuff
		// Check user auth and role
		// gets action name from the URI
        $action_name = Request::instance()->action;
        if ( $this->auth_required === true )
        {
            if ( ! A1::instance()->logged_in() )
                Request::instance()->redirect('account/login');
        }
		
		/*
		
        if ( is_array($this->secure_actions) AND array_key_exists($action_name, $this->secure_actions)) 
        {  // this means that either the action is secure so check the user roles against the action role
            if ( A1::instance()->logged_in() )
            {
                $user = A1::instance()->get_user();
                if(!in_array($this->secure_actions[$action_name], $user->role))
                {
                    // ok the user doesn't have authorization to be here so send them to login
                    Request::instance()->redirect('account/login');       
                }
                
            }
            else 
            {
                // not logged in so sent them to login page
                Request::instance()->redirect('account/login');
            }
        }
		
		*/
		
	}
    
}