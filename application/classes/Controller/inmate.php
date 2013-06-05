<?php defined('SYSPATH') or die('No direct script access.');
  
class Controller_Inmate extends Controller 
{
	/**
	 * Public Action - Index
	 * 
	 * @description		Get the detials of an offender from the Mongo Document
	 * @param			$booking_id; Pass the booking id, which is structured as county_1234
	 */
	public function action_mugshot ( $id )
	{	
		$offender = Mango::factory('offender', array('booking_id' => $id))->load()->as_array(false);
		
		$offender['height'] = (int) ($offender['height']/12)."' ".($offender['height'] % 12).'"';
		$view = View::factory('pages/inmate');
        $view->offender = $offender;
        
        $this->request->title = 
        	'Jail Inmate '.ucfirst(strtolower($offender['firstname'])).' '.ucfirst(strtolower($offender['lastname']))
        	.' from '.ucfirst(strtolower($offender['scrape'])).' County, '.ucfirst(strtolower($offender['state']));

		$this->request->description = ucfirst(strtolower($offender['firstname'])).' '
			.ucfirst(strtolower($offender['lastname']))
        	.' from '.ucfirst(strtolower($offender['scrape'])).' County, '
        	.ucfirst(strtolower($offender['state'])).' arrested for: '.implode(', ', $offender['charges']);
        	
        $this->request->keywords = 
        	$offender['firstname'].' '.$offender['lastname'].', '
        	.$offender['lastname'].', '.$offender['lastname'].', '
        	.implode(', ', $offender['charges']);
        $this->request->canonical = 'http://mugshotjunkie.com/inmate/mugshot/'.$id;
        $this->request->image = 'http://sys.mugshotjunkie.com/offender/full_mugshot/'.$id;
        $this->request->response = $view->render();
	}

}