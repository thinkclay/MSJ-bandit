
<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Alachua
 *
 * @package Scrape
 * @author Winter King
 * @url http://oldweb.circuit8.org/inmatelist.php
 */
class Model_Alachua extends Model_Scrape
{
	private $scrape 	= 'alachua';
	private $state		= 'florida';
    private $cookies 	= '/tmp/alachua_cookies.txt';
	
	public function __construct()
    {
        set_time_limit(86400); //make it go forever 
        if ( file_exists($this->cookies) ) { unlink($this->cookies); } //delete cookie file if it exists        
        # create mscrape model if one doesn't already exist
        $mscrape = Mango::factory('mscrape', array('name' => $this->scrape, 'state' => $this->state))->load();
        if (!$mscrape->loaded())
        {
            $mscrape = Mango::factory('mscrape', array('name' => $this->scrape, 'state' => $this->state))->create();
        }
		# create report
        $this->report = Mango::factory('report', array('scrape' => $this->scrape,'successful' => 0,'failed' => 0,'new_charges' => 0,'total' => 0,'bad_images' => 0,'exists' => 0,'other' => 0,'start_time' => $this->getTime(),'stop_time' => null,'time_taken' => null,'week' => $this->find_week(time()),'year' => date('Y'),'finished' => 0))->create();
    }
	
	function print_r2($val)
	{
        echo '<pre>';
        print_r($val);
        echo  '</pre>';
	} 
	
	
	/** 
	* scrape - main scrape function calls the curls and handles paging
	*
	* @params $date - timestamp of begin date
	* @return $info - passes to the controller for reporting
	*/
    function scrape() 
    {
    	# set report variables
		$index  = $this->curl_index();
		//build my booking_id array
		$check = preg_match_all('/bookno\=(.*)\>/Uis', $index, $matches);
		if ($check)
		{
			$booking_ids = array();
			$booking_ids = $matches[1];
			$booking_ids = array_unique($booking_ids);
			$booking_ids = array_merge($booking_ids);
			$count = 1;
			foreach($booking_ids as $booking_id)
			{
				$details 	= $this->curl_details($booking_id);
				$extraction = $this->extraction($details, $booking_id);
                if ($extraction == 100) { $this->report->successful = ($this->report->successful + 1); $this->report->update(); }
                if ($extraction == 101) { $this->report->other = ($this->report->other + 1); $this->report->update(); }
                if ($extraction == 102) { $this->report->bad_images = ($this->report->bad_images + 1); $this->report->update(); }
                if ($extraction == 103) { $this->report->exists = ($this->report->exists + 1); $this->report->update(); }
                if ($extraction == 104) { $this->report->new_charges = ($this->report->new_charges + 1); $this->report->update(); }
                $this->report->total = ($this->report->total + 1); $this->report->update(); 
				$count++;
			}
			$this->report->failed = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
			$this->report->finished = 1;
	        $this->report->stop_time = time();
	        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
	        $this->report->update();
	        return true;   
		} else { return false; } // empty booking numbers
	}
	
	/**
	* curl_index - gets the index of current population
	* 
	*@url http://oldweb.circuit8.org/inmatelist.php
	*  
	*  
	*/
	function curl_index()
	{
		$url = 'http://oldweb.circuit8.org/inmatelist.php';
		$ch = curl_init();   
     	curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $index = curl_exec($ch);
        curl_close($ch);
		return $index;
	}
	
	/**
	* curl_details
	*
	* @notes  this is just to get the offender details page 
	* @params string $row 
	* @return string $details - details page in as a string
	*/
	function curl_details($booking_id)
	{
		$url = 'http://oldweb.circuit8.org/cgi-bin/jaildetail.cgi?bookno=' . $booking_id;		
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        $details = curl_exec($ch);
        curl_close($ch);
		return $details;
	}
		
	/**
	* extraction - validates and extracts all data
	*
	* 
	* @params $details  - offenders details page
	* @return $ncharges - numerical array of new charges found
	* @return false  	- on failed extraction
	* @return true 		- on successful extraction
	* 
	*/
	function extraction($details, $booking_id)
	{
		$county = 'alachua'; // manually set $county
		$booking_id = 'alachua_' . $booking_id;
		# database validation 
		$offender = Mango::factory('offender', array(
			'booking_id' => $booking_id
		))->load();	
		# validate against the database
		if (empty($offender->booking_id)) 
		{
			# extract profile details
			# required fields
			//<b>Hold<tr><td>ABEL, YOUSSEF FOREST<td>03/29/2011 06:05PM<td>
			$check = preg_match('/<b>Hold<tr><td>.*<td>(.*)<td>/Uis', $details, $match);
			if ($check)
			{
				
				$booking_date = strtotime($match[1]);
				$check = preg_match('/\<b\>Hold\<tr\>\<td\>(.*)\<td\>/Uis', $details, $match);
				if ($check)
				{
					$firstname = null;
					$lastname = null;
					$fullname  = strip_tags($match[1]);
					$fullname = preg_replace('/\./', '', $fullname);
					$fullname = trim($fullname);
		            # Explode and trim fullname
		            # Set first and lastname
		            $explode      = explode(',', trim($fullname));
		            $lastname     = strtoupper(trim($explode[0]));
		            $explode      = explode(' ', trim($explode[1]));
		            $firstname    = strtoupper(trim($explode[0]));
					//echo $firstname . ' ' . $lastname;
					# pull out all the charge tables
					//<table border=1><tr><td colspan=2><b>Case #
					
					$check = preg_match_all('/<table\sborder\=1><tr><td\scolspan\=2><b>Case.*<\/table>/Uis', $details, $matches);
					$check = true;
					if ($check)
					{
						# step through each charge_table individually
						$charges = array();
						$check = preg_match('/Charge\<td\>\<b\>Statute\<.*\<\/table\>/Uis', $details, $match);
						if ($check)
						{
							$check = preg_match('/\<td\>\<tr\>\<td\>.*colspan\=4\>(.*)\<td\>/Uis', $match[0], $match);
							$charges[] = $match[1];	
						}
						
						
						$smashed_charges = array();
						foreach($charges as $charge)
						{
 							// smash it
 							$smashed_charges[] = preg_replace('/\s/', '', $charge);
						}
							## end loop here
						
						if (empty($charges))
						{
							$status = 'denied';
							$charges[] = 'No Charge Available';
						}
							###
							# this creates a charges object for all charges that are not new for this county
							$charges_object = Mango::factory('charge', array('county' => $this->scrape, 'new' => 0))->load(false)->as_array(false);
							# I can loop through and pull out individual arrays as needed:
							$list = array();
							foreach($charges_object as $row)
							{
								$list[$row['charge']] = $row['abbr'];
							}
							# this gives me a list array with key == fullname, value == abbreviated
							$ncharges = array();
							# Run my full_charges_array through the charges check
							$ncharges = $this->charges_check($charges, $list);
							$ncharges2 = $this->charges_check($smashed_charges, $list);
							if (!empty($ncharges)) // this means it found new charges (unsmashed)
							{
							    if (empty($ncharges2)) // this means our smashed charges were found in the db
							    {
							        $ncharges = $ncharges2;
							    }
							}
							###
							
							# validate 
							if (empty($ncharges)) // skip the offender if ANY new charges were found
							{
								$fcharges 	= array();
								foreach($charges as $key => $value)
								{
									$fcharges[] = trim(strtoupper($value));	
								}
								# make it unique and reset keys
								$fcharges = array_unique($fcharges);
								$fcharges = array_merge($fcharges);
								if ($fcharges !== false)
								{
									$dbcharges = $fcharges;	
								} else {
									
								}
								# begin image extraction
								//http://oldweb.circuit8.org/tmp/ASO11JBN003104.jpg
								$image_link = 'http://oldweb.circuit8.org/tmp/'.preg_replace('/alachua\_/Uis', '', $booking_id).'.jpg';
								# set image name
								$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
								# set image path
						        $imagepath = '/mugs/florida/alachua/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
						        # create mugpath
						        $mugpath = $this->set_mugpath($imagepath);
								//@todo find a way to identify extension before setting ->imageSource
								$this->imageSource    = $image_link;
						        $this->save_to        = $imagepath.$imagename;
						        $this->set_extension  = true;
								$this->cookie			= $this->cookies;
						        $this->download('curl');
								if (file_exists($this->save_to . '.jpg')) //validate the image was downloaded
								{
									#@TODO make validation for a placeholder here probably
									# ok I got the image now I need to do my conversions
							        # convert image to png.
							        $this->convertImage($mugpath.$imagename.'.jpg');
							        $imgpath = $mugpath.$imagename.'.png';
									$img = Image::factory($imgpath);
				                	//$img->crop(225, 280)->save();
							        $imgpath = $mugpath.$imagename.'.png';
									# now run through charge logic
									$chargeCount = count($fcharges);
									# run through charge logic	
									$mcharges 	= array(); // reset the array
							        if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
							        {
							            $mcharges 	= $this->charges_prioritizer($list, $fcharges);
										if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in alachua scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
							            $mcharges 	= array_merge($mcharges);   
							            $charge1 	= $mcharges[0];
							            $charge2 	= $mcharges[1];    
							            $charges 	= $this->charges_abbreviator($list, $charge1, $charge2); 
							            $check = $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
										if ($check === false)
										{
										    unlink($imgpath);
										    return 101;
										}
							        }
							        else if ( $chargeCount == 2 )
							        {
							            $fcharges 	= array_merge($fcharges);
							            $charge1 	= $fcharges[0];
							            $charge2 	= $fcharges[1];   
							            $charges 	= $this->charges_abbreviator($list, $charge1, $charge2);
							            $check = $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
										if ($check === false)
										{
										    unlink($imgpath);
										    return 101;
										}           
							        }
							        else 
							        {
							            $fcharges 	= array_merge($fcharges);
							            $charge1 	= $fcharges[0];    
							            $charges 	= $this->charges_abbreviator($list, $charge1); 
										if ($charges == false)
										{
											$charges[0] = $fcharges[0];
										}      
							            $check = $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0]);
										if ($check === false)
										{
										    unlink($imgpath);
										    return 101;
										}   
							        }
									
									// Abbreviate FULL charge list
									//$dbcharges = $this->charges_abbreviator_db($list, $dbcharges);
									$dbcharges = array_unique($dbcharges);
									# BOILERPLATE DATABASE INSERTS
									$offender = Mango::factory('offender', 
						                array(
						                	'scrape'		=> $this->scrape,
						                	'state'			=> $this->state,
						                	'county'		=> $county,
						                    'firstname'     => $firstname,
						                    'lastname'      => $lastname,
						                    'booking_id'    => $booking_id,
						                    'booking_date'  => $booking_date,
						                    'scrape_time'	=> time(),
						                    'image'         => $imgpath,
						                    'charges'		=> $dbcharges,                                      
						            ))->create();
									#add extra fields
									$extra_fields = array();
									foreach ($extra_fields as $field => $value)
									{
										$offender->$field = $value;
									}
									if (isset($status))
									{
										$offender->status = $status;
									}
									$offender->update();
						           	# now check for the county and create it if it doesnt exist 
									$mscrape = Mango::factory('mscrape', array('name' => $this->scrape, 'state' => $this->state))->load();
									if (!$mscrape->loaded())
									{
										$mscrape = Mango::factory('mscrape', array('name' => $this->scrape, 'state' => $this->state))->create();
									}
									$mscrape->booking_ids[] = $booking_id;
									$mscrape->update();	 
                                    # END DATABASE INSERTS
									return 100;
										### END EXTRACTION ###
										
								} else { return 102; } // get failed
							} else {
								# add new charges to the charges collection
								foreach ($ncharges as $key => $value)
								{
									#check if the new charge already exists FOR THIS COUNTY
									$check_charge = Mango::factory('charge', array('county' => $this->scrape, 'charge' => $value))->load();
									if (!$check_charge->loaded())
									{
										if (!empty($value))
										{
											$charge = Mango::factory('charge')->create();	
											$charge->charge = $value;
											$charge->order = (int)0;
											$charge->abbr = $value; 
											$charge->county = $this->scrape;
											$charge->scrape = $this->scrape;
											$charge->new 	= (int)0;
											$charge->update();
										}	
									}
								}
					            return 104; 
							} // ncharges validation	
						//} else { $this->report->info = 'No charges found'; return 101; } // no charges found
					} else { $this->report->info = 'No charge tables found'; return 101; } // no charge tables found
				} else { $this->report->info = 'Empty name field'; return 101; } // empty name field
			} else { $this->report->info = 'Empty booking_date'; return 101; } // empty booking date field
		} else { return 103; } // database validation failed
	} // end extraction
} // class end