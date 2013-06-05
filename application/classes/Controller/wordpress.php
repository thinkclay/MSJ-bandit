<?php defined('SYSPATH') or die('No direct script access.');
  
class Controller_Wordpress extends Controller 
{	
	/**
	 * Public Action - Slider
	 * 
	 * @description		Get the detials of an offender from the Mongo Document
	 * @param			$skip
	 * @param			$state
	 * @param			$county
	 * @todo 			Right now we are only searching by state so I commented the 
	 * 					county variable out of the query.  At one point I decided we needed
	 * 					county and scrape fields for our offenders collection.
	 * 					However, this broke the query because it would only pull ones that didn't actually
	 * 					have a county field in them.  This change ocurred for lexington scrape/county on 6/6/11.
	 * 
	 */
	public function action_slider ( $skip = 0, $state = null, $county = false, $region = false )
	{
		$skip = (int) $skip;
		$view = View::factory('widgets/slider');
		$criteria = array();

		if ( $state )
			$criteria['state'] = strtolower($state);
		
		if ( $county )
			$criteria['county'] = strtolower($county);
		
		if ( $region )
			$critera['scrape'] = strtolower($region);

		$criteria['status'] = array('$ne' => 'denied');

		$offenders = Mango::factory('offender')->load(10, array('booking_date' => -1), $skip, array(), $criteria)->as_array(false);

		$view->offenders = $offenders;
        $this->request->response = $view->render();
    }
	public function action_demographics ()
	{
		$criteria = array();		
		$races = array("WHITE", "BLACK", "ASIAN OR PACIFIC ISLANDER", "HISPANIC", "AMERICAN INDIAN");
		$this->request->response = View::factory('widgets/demographics/overview')->bind('races', $races);
	}
	
	public function action_test()
	{
		$region = 'lexington';
		$race = 'BLACK';
		$criteria = array('scrape' => $region, 'race' => $race);
		// 1. Make sure to set load(false) to get all instead of one
		// 2. Calling the count() function will invoke the mongo count (db.offenders.count())
		// 3. Echoing the result will give us our count
		//
		// If we make $region and $race dynamic we can make a controller for ajax to get
		// its statistic data from.  Keep in mind that not all offenders have a 'race' field
		// so this won't be accurate across the board for every scrape.  
		
		$offenders = Mango::factory('offender', $criteria)->load(false)->count();
		echo $offenders.' out of '.Mango::factory('offender')->load(false)->count();
		exit;
	}
}	