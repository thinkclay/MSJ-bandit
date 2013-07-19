<?php defined('SYSPATH') or die('No direct script access.');
 
class Task_Bandit extends Minion_Task
{
    protected $_options = [
        'county' => NULL,
        'state'  => NULL,
    ];
 
    /**
     * This task will run our scrapes from command line in a more secure format
     *
     * @return null
     */
    protected function _execute(array $params)
    {
        $state  = ucwords($this->_options['state']);
        $county = ucwords($this->_options['county']);

    	$class = 'Model_'.$state.'_'.$county;
    	$scrape = new $class;

    	$scrape->scrape();

    	$this->bid_dupe_check($county);
        $this->profile_dupe_check($county);
    }
    
    
    public function build_validation(Validation $validation)
    {
        return parent::build_validation($validation)
            ->rule('county', 'not_empty') 
            ->rule('state', 'not_empty');
    }
    
    
   /**
	* bid_dupe_check - Runs after every scrape to check fo duplicate booking_ids. If any are found, both are deleted.
	*
	* @return void
	*/
	public function bid_dupe_check($county = null)
	{
		if ($county)
		{

	    	$scrape    = new Model_Scrape;
			$start = $scrape->getTime();
	        $offenders = Mango::factory('offender', array('scrape' => $county))->load(false)->as_array(false);
	        $bid_dupes = [];

	        foreach ( $offenders as $offender )
	        {
	            $dupe_check = Mango::factory('offender', array('booking_id' => $offender['booking_id']))->load(false)->as_array(false);
	            $count = 0;

	            if ( count($dupe_check) > 1 )
	            {
	                // this means I found a duplicate
	                // loop through and delete each one individually
	                // email me which ones were deleted
	                $email = "Duplicate Offender Booking_ids:\n";
	                
	                foreach ( $dupe_check as $dupe )
					{
						$dupe_offender = Mango::factory('offender', array('_id' => $dupe['_id']))->load();
						$email .= "\nFirstname: $dupe_offender->firstname\n";
						$email .= "Lastname: $dupe_offender->lastname\n";
						$email .= "Booking ID: $dupe_offender->booking_id\n";
						$email .= "County: $dupe_offender->scrape\n";
						$scrapetime = date('m/d/Y h:i:s', $dupe_offender->scrape_time);
						$email .= "Scrape Time: $scrapetime\n";
						$email .= "\n#####################################\n";
						# check if image exists
						if (file_exists($dupe_offender->image . '.png'))
						{
							# delete it
							unlink($dupe_offender->image . '.png');
						}
						$dupe_offender->delete();
					}
					mail('clay@mugshotjunkie.com', 'Dupes found in ' . $county, $email);
	            }
	        }
        } else { return false; }
	}


	/**
	* profile_dupe_check - used directly after scrape has finished to flag duplicate offenders
    * based on firstname, lastname and booking_date.
	*
	* @return true - on success
	* @return false - on failure
	*/
	public function profile_dupe_check($county = null)
    {
    	if ($county)
		{
	    	$scrape    = new Model_Scrape;
			$start = $scrape->getTime();
	        $offenders = Mango::factory('offender', array('scrape' => $county))->load(false)->as_array(false);
	        $profile_dupes = array();
	        foreach ( $offenders as $offender )
	        {
	        	if (!isset($offender['firstname']) OR !isset($offender['lastname']) OR !isset($offender['booking_date']))
				{
					$bad_offender = Mango::factory('offender', array('_id' => $offender['_id']))->load();
					$bad_offender->delete();
				}
				else
				{
					//$dupe_check = Mango::factory('offender', array('scrape' => , 'firstname' => $offender['firstname'], 'lastname' => $offender['lastname'], 'booking_date' => $offender['booking_date']))->load(array('limit' => FALSE, 'criteria' => array('status' => array('$ne' => 'accepted'))))->as_array(false);
		            $dupe_check = Mango::factory('offender', array('scrape' => $county, 'firstname' => $offender['firstname'], 'lastname' => $offender['lastname'], 'booking_date' => $offender['booking_date']))->load(false)->as_array(false);
		            $count = 0;
		            if (count($dupe_check) > 1)
		            {
		                // this means I found a duplicate
		                // build my $profile_dups array

		                $dupes = array();
		                foreach($dupe_check as $dupe)
		                {
		                    $dupes[] = $dupe['_id'];
		                }
		                $profile_dupes[] = $dupes;
		            }
				}
	        }

	        // get rid of duplicates sets
	        $profile_dupes = array_map("unserialize", array_unique(array_map("serialize", $profile_dupes)));
	        foreach($offenders as $offender)
	        {
	            //$this->print_r2($offender);
	            foreach($profile_dupes as $key => $value)
	            {
	                foreach ($value as $key2 => $value2)
	                {
	                    if ($offender['_id'] == $value2)
	                    {
	                        $profile_dupes[$key][$key2] = $offender;
	                    }
	                }
	            }
	        }
			// foreach ($profile_dupes as $dupe_set)
			// {
			// 	$set = array();
			// 	foreach($dupe_set as $dupe_profile)
			// 	{
			// 		$set[] = $dupe_profile['_id'];
			// 	}
			// 	sort($set);
			// 	$dupes_object = Mango::factory('dupe', array('county' => $county, 'ids' => $set))->load();
			// 	if (!$dupes_object->loaded())
			// 	{
			// 		# ok this means we have new dupes so immediately set status => denied in the offenders model
			// 		foreach($set as $id)
			// 		{
			// 			//4d9237292eab7311450000b8
			// 			//4d930c182eab737626000004
			// 			$offender = Mango::factory('offender', array('_id' => $id))->load();
			// 			$offender->status = 'denied';
			// 			$offender->update();
			// 		}
			// 		# now add the new dupe set to the database
			// 		$dupes_object = Mango::factory('dupe')->create();
			// 		$dupes_object->county = $county;
			// 		$dupes_object->ids  = $set;
			// 		$dupes_object->update();
			// 	}
			// }
			return true;
			$end = $scrape->getTime();
			
			//echo "Time taken = ".number_format(($end - $start),2)." secs\n";
	        //$this->template = View::factory('admin/dupes-panel')->bind('profile_dupes', $profile_dupes);
        } 
        else 
        { 
            return false; 
        }
    }
}