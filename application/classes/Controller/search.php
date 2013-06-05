<?php defined('SYSPATH') or die('No direct script access.');
  
class Controller_Search extends Controller 
{
	/**
	* search - takes post data for search filter and returns a mango object of offenders
	* 
	* @param - state
	* @param - scrape
	* @param - race
	* @param - gender
	* @param - booking_date
	* @param - gender
	* 
	* @return mango object
	*/
	public function action_index() 
	{
		$view = View::factory('pages/search/landing');
		
		if ($_POST)
		{
			$search = array( 'status' => array('$ne' => 'denied') );
			
			foreach ( $_POST as $k => $v )
				if ( $v AND $v !== '' ) 
					$search[$k] = strtoupper($v);
			
			$offenders = Mango::factory('offender')->load(10, array('booking_date' => -1), 0, array(), $search)->as_array(false);
			$view->offenders = $offenders;
		}
		
        $this->request->response = $view->render();
	}
	
	
	/**
	* browse - optionally pass data and return offender
	* 
	* @param - state
	* @param - scrape
	* @param - race
	* @param - gender
	* @param - booking_date
	* @param - gender
	* 
	* @return mango object
	*/
	public function action_browse() 
	{
		$view = View::factory('pages/search/browse');
		
		if ($_GET)
		{
			$offenders = Mango::factory('offender', $_GET)->load(20)->as_array(false);
			
			foreach ($_GET as $k => $v) 
			{
				$keys = ' '.$k.', '.$v;
			}
			
			$this->request->title = 'Browse Mug by'.$keys;
			$this->request->description = $keys.' Jail Mug Shots';
	        $this->request->keywords = $keys;
		}
		
		else
		{
			$offenders = Mango::factory('offender', array('state'=>'michigan'))->load(20)->as_array(false);
			$this->request->title = 'Browse the latest Offender Mugshots';
			$this->request->description = 'Jail Mug Shots by date and most recent';
	        $this->request->keywords = 'browse mugshots, search mugshots, recent mugshots';
		}
		
		$view->offenders = $offenders;
		$this->request->canonical = 'http://mugshotjunkie.com/browse-mugshots/';
        $this->request->response = $view->render();
	}
	
	
	public function action_nav ()
	{
		$view = View::factory('widgets/search/nav-global');
        $this->request->response = $view->render();
	}
}	