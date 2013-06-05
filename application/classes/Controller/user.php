<?php defined('SYSPATH') or die('No direct script access.');
  
class Controller_User extends Controller 
{
	private $_request = null;

	/**
	 * Sanitize Request 
	 *
	 * Sanitizes/decodes the uri
	 *
	 * Goes and adds request info to data array.
	 */
	private function _sanitize_request($request)
	{
		$data = array();
		
		foreach ( $request as $k => $v )
		{
			$data[$k] = urldecode($v);
		}
		
		return $data;
	}
	
	private function _success($user)
	{
	    $data = array();
	    
        foreach ( $user as $k => $v )
        {
            $data[$k] = stripslashes($v);
            
            echo "<pre>".$data[$k]."</pre>";
            
            echo "<pre>".json_encode($data[$k])."</pre>";
        }
	       
/* 	    return json_encode(array_merge(array('status' => 'success'), $data), JSON_HEX_QUOT); */
	}
	
	/**
	 * Before 
	 *
	 * Everything within this function runs before all other functions. 
	 *
	 * First we make sure the correct api key is passed in the url in order to use api.
	 * Then, we call the santize function to clean up the url.
	 */
	public function before()
	{
	    ini_set('magic_quotes_gpc', 'Off');
		ini_set('magic_quotes_runtime', 'Off');
		ini_set('magic_quotes_sybase', 'Off');
		
        $this->auto_render = FALSE;
        $this->request->headers = array('Content-Type' => 'application/json');
        
		$api_key = 'db0e453e3849e8f821aa2437280acaf7';
		
		if ( ! isset($_REQUEST['api_key']) OR $_REQUEST['api_key'] != $api_key )
			die('invalid or no api key was passed');
			
		unset($_REQUEST['api_key']);
		
		$this->_request = $this->_sanitize_request($_REQUEST);
	}

	/**
	 * Action Create
	 *
	 * Creates a user in Mongo 
	 *
	 * Pulls in sanitized request parameters fromt he url and creates the user in mongo.
	 * Returns JSON so there is something to see. 
	 */
	public function action_create()
	{	
	   
        if ( isset($this->_request['email']) )
        {
            $existing = Mango::factory('user', array('email' => $this->_request['email']))->load();
            
            if ( $existing->loaded() )
            {
                $this->request->status = 409;

                echo json_encode(array(
                    'status' => 'error',
                    'message' => 'This user already exists'
                ));
                
                return;
            }
            else
            {
                
                $account = Mango::factory('user', $this->_request)->create();
                
                echo json_encode(array_merge(array('status' => 'success'), $account->as_array()), JSON_HEX_QUOT);		
            }
        }
        else
        {   
            $this->request->status = 400;
            
            echo json_encode(array(
                'status' => 'error',
                'message' => 'You need to pass an email'
            ));
            
            return;
        }           
	}
	
	/**
	 * Action Read
	 *
	 * Reads User Info from Mongo 
	 *
	 * Returns JSON record info based on the parameters in url.
	 */
	public function action_read()
	{  
        $this->request->headers = array('Content-Type' => 'text/html');
        
	   if ( $this->_request )
	   {
	       $account = Mango::factory('user', $this->_request)->load();
	       
	       echo $this->_success($account->as_array());
	   }
	   else
	   {
            $this->request->status = 400;
            
            /*
echo json_encode(array(
                'status' => 'error',
                'message' => 'You need to pass parameters to fetch a record'
            ));
*/
            
            return;
	   }
	}
	
	/**
	 * Action Update
	 *
	 * Updates a user document in Mongo
	 *
	 * We process this in two steps.. it works the same as action_read() 
	 * by fetching a record based on those params, however, we use a second step
	 * by passing the "update" field as another param with a json object that 
	 * we can actually update the database with
	 */
	public function action_update()
	{   
/* 	   $this->request->headers = array('Content-Type' => 'text/html'); */
/*
		if ( ! isset($this->_request['update']) )
		{
            $this->request->status = 400;
            
            echo json_encode(array(
                'status' => 'error',
                'message' => 'You need to pass an update param with json data to update the document'
            ));
            
            return;
		}
*/
					
/*
		if ( json_decode($this->_request['update']) == null )
		{ 
    		$this->request->status = 400;
            
            echo json_encode(array(
                'status' => 'error',
                'message' => 'Could not parse json'
            ));
            
            return;
		}	
*/
		
        $data = array();
        
		foreach ( $this->_request as $k => $v )
		{	
			$data[$k] = $v;
		}
		
/*
		var_dump(MangoDB::instance('busted')->update(
		  'users',
		  array('email' => $this->_request['email']),
		  array('$set' => $data)
        ));
*/
		
/* 		$account = Mango::factory('user', array('email' => $this->_request['email']))->load(); */
		
/* 		var_dump($account->as_array()); */
		

        
        MangoDB::instance('busted')->update(
		  'users',
		  array('email' => $this->_request['email']),
		  array('$set' => $data)
        );
        
		$account = MangoDB::instance('busted')->find_one(
		  'users',
		  array('email' => $this->_request['email'])
        );
        
		/*
$account = Mango::factory('user', array('email' =>$this->_request['email']))->load();
		unset($this->_request['email']);	
		
		var_dump($account->as_array());	
*/
/* 		$account->update(); */
		
		
		
		echo json_encode(array_merge(array('status' => 'success'), $account));
	}
	
	/**
	 * Action Delete
	 *
	 * Deletes User  from Mongo 
	 *
	 * Deletes a record based on the parameters passed in the url. Returns JSON to see what has happened. 
	 */
	public function action_delete()
	{
		$account = Mango::factory('user', $this->_request)->load();
		
		$account->delete();
		
		echo json_encode(array_merge(array('status' => 'success'), $account->as_array()));
	}
}