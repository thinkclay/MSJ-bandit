<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * api - Secure database access point
 *
 * @package scrape
 * @author  Winter King 
 */ 
class Controller_Api extends Controller
{
	private function str_int_to_int($array)
	{
		foreach ($array as $key => $value)
		{
			if (is_array($value)) 
		    {  // or whatever other criterium
		        $value = $this->str_int_to_int($value);  // same function
		        $array[$key] = $value;
		    }
			elseif (is_numeric($value))
			{
				$array[$key] = (int)$array[$key];
			}
		}
		return $array;
	}
	
	public function action_test()
	{
		
	}
	
	/**
	 * action_query - 	Main method used to run a query on the DB
	 *					Query must be in bson format just like you would query Mongo													
	 * 					state field is required and must be a single value											 
	 * @example sys.mugshotjunkie.com/api/query/<api_key>?data={"state":"florida","booking_date":{"$gt":"1314632694"}}&limit=10&skip=10
	 * @return json 
	 * @author Winter King 
	 */
	public function action_query($key = false)
	{
		if ($key === false)
			die('<h2>Unauthorized Access</h2>');
		$api_model = Mango::Factory('api', array('api_key'=>$key))->load();
		if ($api_model->loaded() === false)
			die('<h2>Unauthorized Access</h2>');
		if ( ! isset($_GET['data']) )
			die('<h2>Data not set</h2>');
		$query = $_GET['data'];
		$query_arr = json_decode(str_replace("\\", "", $query), true);
		if ($query_arr === null)
			die('<h2>Invalid JSON String</h2>');
		if ( ! isset($query_arr['state']) )
			die('<h2>State is a required field</h2>');
		// query authorization
		$api_arr = $api_model->as_array();
		if ( ! is_array($query_arr['state']))
		{
			if ( ! in_array($query_arr['state'], $api_arr['acl']) )
				die('<h2>Unauthorized Access to query on state: ' . $query_arr['state'] . '</h2>');
		} 
		else 
		{
			foreach ($query_arr['state'] as $in)
			{
				foreach ($in as $state)
				{
					if ( ! in_array($state, $api_arr['acl']) )
						die('<h2>Unauthorized Access to query on state: ' . $state . '</h2>');	
				}
					
			}
		}
		if ( is_array($query_arr['state']))
		{			
			//$query_arr['state'] = array('$in' => $query_arr['state']);	
		}
		$query_arr = $this->str_int_to_int($query_arr);
		$limit = 100;
		if (isset($_GET['limit']))
		{
			$limit = (int)$_GET['limit'];
			if ($limit > 100)
			{
				$limit = 100;
			}
		}
		$skip = null;
		if (isset($_GET['skip']))
		{
			$skip = (int)$_GET['skip'];
		}
		$results = Mango::factory('offender')->load($limit, array('booking_date'=>-1), $skip, array('firstname'=>true, 'lastname'=>true, 'middlename'=>true, 'booking_id'=>true, 'booking_date'=>true, 'image'=>true, 'dob'=>true, 'age'=>true, 'gender'=>true, 'race'=>true, 'height'=>true, 'weight'=>true, 'eye_color'=>true, 'hair_color'=>true, 'state'=>true, 'county'=>true, 'charges'=>true), $query_arr)->as_array(false);
		
		foreach ($results as $key => $offender)
		{
			unset($results[$key]['_id']);
			$results[$key]['img_src'] = 'sys.mugshotjunkie.com/offender/slider_mugshot/' . $results[$key]['booking_id'];
			unset($results[$key]['image']);
		}
		
 		$this->request->headers['Content-Type'] = 'application/json';
		$json = json_encode($results);
		
		echo str_replace('sys.mugshotjunkie.com\/offender\/slider_mugshot\/', 'sys.mugshotjunkie.com/offender/slider_mugshot/', $json);
	}
	
	/**
	 * keygenerator -	used to generate and api key.  Does a number of validations to ensure a valid request.
	 * 					Creates a database document with the key used for api request validation.
	 *
	 * @example sys.mugshotjunkie.com/api/keygen?counties=["county1", "county2"]&states=["state1", "state2"]
	 * @return void
	 * @author Winter King
	 */
	public function action_keygen()
	{
		$states_list = Kohana::$config->load('states')->as_array();
		if ($_SERVER['SERVER_ADDR'] != $_SERVER['REMOTE_ADDR'])
		{
			die('Unauthorized Access' . "\r\n");	
		}
		if (isset($_GET['states']))
		{
			$states = str_replace("\\", "", $_GET['states']);
			$check = json_decode($states);
			if ($check === null)
				die("Something is wrong with the json.\r\n");
		}
		if (isset($states))
		{
			// check for valid states
			$states_array = json_decode($states, true);
			foreach($states_array as $locale)
			{
				$locale = urldecode($locale);
				$locale = ucwords($locale);
				if ( ! in_array($locale, $states_list))
				{
					die('Invalid locale: ' . $locale . "\r\n");
				}
			}	
		} else {
			die("No states requested\r\n");
		}	
		$api_key = uniqid();
		Mango::factory('api', array('api_key' => $api_key, 'acl' => json_decode($states, true)))->create();
		echo $api_key . "\r\n";  
	}
}