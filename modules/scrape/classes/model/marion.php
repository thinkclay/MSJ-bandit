<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Model_Marion
 * 
 * @notes the trick with this one is that there is no search filters at all.  
 * 		  Just a huge list of details/images/charges all in one HTTP request.
 * @platform www.locktrack.com	  
 * @package Scrape
 * @author Winter King
 * @url http://apps.co.marion.or.us/JailRosters/mccf_roster.html
 */
class Model_Marion extends Model_Scrape 
{
	private $scrape 	= 'marion';
	private $state		= 'oregon';
    private $cookies 	= '/tmp/marion_cookies.txt';
	
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
    function scrape($fn = 'a', $ln = 'a') 
    {
		$index = $this->curl_index();
		$check = preg_match_all('/<tbody>(.*)<\/tbody>/Uis', $index, $matches);
		# this gives me an array of each offender broken into tables
		foreach ($matches[0] as $details)
		{
			# run each on through the extractor
			$extraction = $this->extraction($details);
            if ($extraction == 100) { $this->report->successful = ($this->report->successful + 1); $this->report->update(); }
            if ($extraction == 101) { $this->report->other = ($this->report->other + 1); $this->report->update(); }
            if ($extraction == 102) { $this->report->bad_images = ($this->report->bad_images + 1); $this->report->update(); }
            if ($extraction == 103) { $this->report->exists = ($this->report->exists + 1); $this->report->update(); }
            if ($extraction == 104) { $this->report->new_charges = ($this->report->new_charges + 1); $this->report->update(); }
            $this->report->total = ($this->report->total + 1); $this->report->update();
		} 
		$this->report->failed = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
        $this->report->finished = 1;
        $this->report->stop_time = time();
        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
        $this->report->update();
        return true;
	}
	
	
	/**
	* curl_home - set the EV and VS
	*
	*@url http://jail.lfucg.com/
	*  
	* 
	*/
	function curl_index()
	{
		$url = 'http://apps.co.marion.or.us/JailRosters/mccf_roster.html';
		$ch = curl_init();   
     	curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $home = curl_exec($ch);
        curl_close($ch);
		return $home;
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
	function extraction($details)
	{
		$county = 'marion';
		# out the actual <table> with table_extractor
		$this->source = $details; 
        $this->anchor = '<body';
	    $this->anchorWithin = true;
		$this->headerRow = false;
		$this->stripTags = true;
		$profile_table = $this->extractTable();
		#get booking ID and check the database
		$booking_id = 'marion_' . trim($profile_table[1][1]);
		# database validation 
		$offender = Mango::factory('offender', array(
			'booking_id' => $booking_id
		))->load();	
		# validate against the database
		if (empty($offender->booking_id)) 
		{
			# extract profile details
			# Everything is on one line $profile_table[2][2];
			# except the fullname, get the in the $details var
			$check = preg_match('/<a[^>]*>(.*)</Uis', $details, $match);
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
	            $lastname     = trim($explode[0]);
	            $explode      = explode(' ', trim($explode[1]));
	            $firstname    = trim($explode[0]);
	            if ($firstname && $lastname)
				{
					# now booking_date
					if (!empty($profile_table[1][3]))
					{
						$booking_date = strtotime($profile_table[1][3]);
						if (isset($profile_table[2][2]))
						{
							$details_string = trim($profile_table[2][2]);
							$details_array = array();
							$details_array = explode('|', $details_string);
							# lets just validate everything here
							if (  !empty($details_array[1]) && !empty($details_array[2]) && !empty($details_array[3]) && !empty($details_array[4]) && !empty($details_array[5]) && !empty($details_array[6]) && !empty($details_array[7]) && !empty($details_array[8])  )
							{
								$extra_fields = array();
								
								$image  					= $details_array[1];
								$extra_fields['race']		= $this->race_mapper($details_array[2]);
								$extra_fields['gender']		= $details_array[3];
								$extra_fields['height'] 	= $details_array[3];
								$extra_fields['height']		= $this->height_conversion($details_array[3]);
								$extra_fields['weight'] 	= $details_array[5]; 
								$extra_fields['hair_color'] = $details_array[6];
								$extra_fields['eye_color']  = $details_array[7]; 
								# ok I got all my profile fields now get charges and validate
								// this gets tricky because the charges sometimes are arranged with multiple header rows
								// basically I need to figure out which rows are the actual charge descriptions
								
								# charge extraction logic
								# 1. find dkey ( key for the header row of charges )
								# 2. get the 4th value of every row after dkey
								# 3. if no 4th value, then stop
								# 4. continue until no more dkeys
								# 5. build charges array
								
								$dkey = array();
								$charge_rows = array();
								$check = preg_match_all('/Release.*<td.*<td.*<td.*<td.*>(.*)<\/td/Uis', $details, $matches);
								$charges = array();
								foreach($matches[1] as $charge)
								{
									$charges[] = $charge;
								}
								$smashed_charges = array();
								foreach($charges as $charge)
								{
									// smash it
									$smashed_charges[] = preg_replace('/\s/', '', $charge);
								}
								if (!empty($charges))
								{
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
										$dbcharges = $fcharges;
										
										# begin image extraction
										$image_link = 'http://apps.co.marion.or.us/JailRosters/'.$image; 
										# set image name
										$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
										# set image path
								        $imagepath = '/mugs/oregon/marion/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
								        # create mugpath
								        $mugpath = $this->set_mugpath($imagepath);
										//@todo find a way to identify extension before setting ->imageSource
										$this->imageSource    = $image_link;
								        $this->save_to        = $imagepath.$imagename;
								        $this->set_extension  = true;
								        $this->download('curl');
										if (file_exists($this->save_to . '.jpg') && (filesize($this->save_to . '.jpg') > 10000)) //validate the image was downloaded
										{
											#BUGFIX required imagemagic and Imagic php extension because GD library errored for some reason 
													
											#@TODO make validation for a placeholder here probably
											# ok I got the image now I need to do my conversions
									        # convert image to png.
									        $im = new Imagick();
		
									        /*** the image file ***/
									        $imgpath = $this->save_to . '.jpg';
									
									        /*** read the image into the object ***/
									        $im->readImage( $imgpath );
									
									        /**** convert to png ***/
									        $im->setImageFormat( "png" );
									
									        /*** write image to disk ***/
									        $im->writeImage( $this->save_to . '.png' );
											
											/*** unlink the original jpg ***/
											unlink($this->save_to . '.jpg');
											
											$imgpath = $mugpath.$imagename.'.png';
											# now run through charge logic
											$chargeCount = count($fcharges);
											# run through charge logic
											$mcharges 	= array(); // reset the array
									        if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
									        {
									            $mcharges 	= $this->charges_prioritizer($list, $fcharges);
												if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in marion scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
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
									            $check = $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0]);
												if ($check === false)
												{
												    unlink($imgpath);
												    return 101;
												}   
									        }
											
											// Abbreviate FULL charge list
											$dbcharges = $this->charges_abbreviator_db($list, $dbcharges);
											$dbcharges = array_unique($dbcharges);
											# BOILERPLATE DATABASE INSERTS
											$offender = Mango::factory('offender', 
								                array(
								                	'scrape'		=> $this->scrape,
								                	'state'			=> strtolower($this->state),
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
											foreach ($extra_fields as $field => $value)
											{
												$offender->$field = $value;
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
										} else { @unlink($this->save_to . '.jpg'); return 102; } // image was not downloaded sucessfully
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
													$charge->abbr = $value;
													$charge->order = (int)0;
													$charge->county = $this->scrape;
													$charge->scrape = $this->scrape;
													$charge->new 	= (int)0;
													$charge->update();
												}	
											}
										} 
							            return 104; 
									} // ncharges validation
								} else { return 101; } // no charges found
							} else { return 101; } // one of the profile details was not set	
						} else { return 101; } // details line not found
					} else { return 101; } // booking date fail
				} else { return 101; } // fn ln validation failed
			} else { return 101; } // fullname not found
		} else { return 103; } // database validation failed
	} // end extraction
} // class end