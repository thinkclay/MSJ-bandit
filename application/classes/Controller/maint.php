<?php defined('SYSPATH') or die('No direct script access.');
  
/**
 * Maintainance controller used to perform regular maintinance
 *
 * @package default
 * @author  Winter King
 */
class Controller_Maint extends Controller 
{	
	public function action_index ( )
	{
		exit;
		set_time_limit(0);
		$offenders = Mango::factory('offender', array('state' => 'TEXAS'))->load(false, null, null, array('state' => true))->as_array();
		echo count($offenders);
		foreach ($offenders as $offender)
		{
			$offender->state = strtolower($offender->state);
			$offender->update();
		}
		
		exit;
		
		//print_r($offenders);
		
		
	}
}	