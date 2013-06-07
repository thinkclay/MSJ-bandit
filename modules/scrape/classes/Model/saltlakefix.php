<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Saltlake
 *
 * @package 
 * @author Winter King
 * @params 
 * @TODO figure out why we're getting duplicates for saltlake
 * @TODO Some images seem to break gd.. need to look into this and how to check/bypass
 * @description 
 * @url http://iml.slsheriff.org/IML
 */
class Model_Saltlakefix extends Model_Scrape 
{
	function fix()
	{
		$criteria = array(
			'age' => 37,
			'scrape' => 'saltlake',
			'firstname' => 'KELLI',
			'lastname' => 'WALDRON',
			'gender' => 'FEMALE',
			'race' => 'WHITE',
			'birth_year' => array('$gte' => '1975', '$lte' => '1977'),
			'eye_color' => 'BLUE',
			'hair_color' => 'BLONDE'
		);	
		
		$exists = Mango::factory('offender')->load(1, null, null, array(), $criteria);
		
		print_r($exists->as_array());
		exit;
		$criteria = array(
			'scrape' => 'saltlake',
			//'booking_id' => 'saltlake_12007728'
		);
		$offenders = Mango::factory('offender')->load(false, null, null, array(), $criteria);
		foreach ($offenders as $offender)
		{
			//echo $offender->firstname;
			//echo "<br />"; 
			//echo date('Y', $offender->booking_date);
			$seconds = (31557600 * $offender->age);
			$birth_year = date('Y', $offender->booking_date - $seconds);
			$offender->birth_year = $birth_year;
			//unset($offender->birth_year);
			//echo $offender->birth_year;
			$offender->update();
			//echo $birth_year;
			//exit;
		}
		
		echo 'Fix Complete';
		//echo $offenders->count();
		//exit;
	}
	
} // class end