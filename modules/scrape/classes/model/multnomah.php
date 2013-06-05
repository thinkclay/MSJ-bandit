<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Model_Multnomah
 * 
 * @platform 	  
 * @package Scrape
 * @author Winter King
 * @url http://www.mcso.us/PAID/
 */
class Model_Multnomah extends Model_Scrape 
{
	private $scrape 	= 'multnomah';
	private $state		= 'oregon';
	private $user_agent = "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.15) Gecko/20110303 Firefox/3.6.15";
    private $cookies 	= '/tmp/multnomah_cookies.txt';
	
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
	

	/**
	* scrape - main scrape function calls the curls and handles paging
	* @oddities Amhaz, Youssef Hussein | Angelsilva, Felipe | Arciniegaandrade, Oscar L | Allen, Michael James | Bailey, Clarke Allen
	* @params $date - timestamp of begin date
	* @return $info - passes to the controller for reporting
	*/
    function scrape($fn = 'a', $ln = 'a') 
    {
		$home = $this->curl_main();
		echo $home;
		exit;
		
/*
		$doc = new DOMDocument();
        $doc->load("example.html");
        $items = $doc->getElementsByTagName('tag1');
*/

		# get __VIEWSTATE 
	    preg_match_all('/id\=\"\_\_VIEWSTATE\"\svalue\=\"([^"]*)"/Uis', $home,  $matches,  PREG_PATTERN_ORDER);       
	    # get __EVENTVALIDATION
	    preg_match_all('/id\=\"\_\_EVENTVALIDATION\"\svalue\=\"([^"]*)"/Uis', $home, $matches2, PREG_PATTERN_ORDER);
	   	if ($matches[1][0] && $matches2[1][0])
		{
			$vs = $matches[1][0];	
			$ev = $matches2[1][0];
			// change searchtype to 3 for past 7 days
			$index = $this->curl_main($vs, $ev, true, 3);
			# build a booking_id array
			$check = preg_match_all('/BookingDetail\.aspx\?ID\=([^"]*)\"/is', $index, $matches);
			if (!empty($matches[1]))
			{
				$booking_ids = $matches[1]; // drilll down and set booking_id array
				foreach($booking_ids as $booking_id)
				{
					$details = $this->curl_details($booking_id);
					$extraction = $this->extraction($details, $booking_id);
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
			} else { $this->report->info = 'ERROR: No BookingDetail.aspx link found'; $this->report->update(); return false; }
		} else { $this->report->info = 'ERROR: No Viewstate or Eventvalidation found'; $this->report->update(); return false;}
	} //end scrape function 
	
	
	/**
	* curl_main - main handler for curl requests
	*
	*@url http://www.mcso.us/PAID/
	*  
	*  
	*/
	function curl_main($ev = null, $vs = null, $post = true, $search_type = null)
	{
		$url = 'www.mcso.us/PAID/Home/SearchResults';
		$ch = curl_init();   
     	curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		$fields = 'SearchType=3';	
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
		curl_setopt($ch, CURLOPT_REFERER, $url); 
		curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $main = curl_exec($ch);
        curl_close($ch);
		return $main;
	}


	/**
	* curl_details - get offender details page
	*
	*@url http://www.mcso.us/PAID/BookingDetail.aspx
	*@fields $booking_id
	*  
	*/
	function curl_details($booking_id)
	{
		$url = 'http://www.mcso.us/PAID/BookingDetail.aspx?ID='.$booking_id;
		$ch = curl_init();   
     	curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		curl_setopt($ch, CURLOPT_REFERER, 'http://www.mcso.us/PAID/'); 
		curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
		$county = 'multnomah';
		#get booking ID and check the database
		$image_booking_id = $booking_id;
		$booking_id = 'multnomah_' . preg_replace('/[^a-zA-Z0-9\s]/', '', trim($booking_id));
		# database validation 
		$offender = Mango::factory('offender', array(
			'booking_id' => $booking_id
		))->load();	
		# validate against the database
		if (empty($offender->booking_id)) 
		{
			# extract profile details
			$check = preg_match('/\_labelName\"\sclass\=\"Data\"\>(.*)\<\/span/Uis', $details, $match);
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
				
	            if ($firstname && $lastname)
				{
					
					# now booking_date
					# _labelBookingDate" class="Data">4/7/2011 6:10 PM</span>
					$check = preg_match('/\_labelbookingdate[^>]*\>(.*)\<\/span\>/Uis', $details, $match);
					if ($check)
					{
						
						$exploded = explode(' ', strip_tags($match[1]));
						$booking_date = strtotime($exploded[0]);
						
						#get extra fields
						$extra_fields = array();
						//_labelAge" class="Data">27
						$check = preg_match('/labelAge\"[^>]*\>(.*)\<\/span/', $details, $age);
						if ($check) { $age = preg_replace("/\D/", "", $age[1]); $age = trim($age); $age = (int)$age; $extra_fields['age'] = $age;} // rip out numbers, trim and make it an int
						
						$check = preg_match('/labelGender\"[^>]*\>(.*)\<\/span/', $details, $gender);
						if ($check) { $extra_fields['gender'] = strtoupper(trim($gender[1]));  } // trim and upper
						
						$check = preg_match('/labelRace\"[^>]*\>(.*)\<\/span/', $details, $race);
						if ($check) { $race = trim($race[1]); }
						if (isset($race)) 
						{
							 $race = $this->race_mapper($race);
							 if ($race)
							 {
						 	 	$extra_fields['race'] = $race;
							 }
						}
						
						$check = preg_match('/labelHeight\"[^>]*\>(.*)\<\/span/', $details, $height);
						if ($check) { $extra_fields['height'] = $this->height_conversion(trim($height[1]));  } 
						
						$check = preg_match('/labelWeight\"[^>]*\>(.*)\<\/span/', $details, $weight);
						if ($check) { $extra_fields['weight'] =  preg_replace("/\D/", "", trim($weight[1])); $extra_fields['weight'] = (int)$extra_fields['weight']; /* cast as int just in case */ } 
						
						$check = preg_match('/labelHair\"[^>]*\>(.*)\<\/span/', $details, $hair_color);
						if ($check) { $extra_fields['hair_color'] = strtoupper(trim($hair_color[1]));  } 
						
						$check = preg_match('/labelEyes\"[^>]*\>(.*)\<\/span/', $details, $eye_color);
						if ($check) { $extra_fields['eye_color'] =  strtoupper(trim($eye_color[1])); } 
						
						# Validate and extract charges
						$check = preg_match_all('/\<table\sclass\=\"Grid[^>]*\>.*<\/table>/Uis', $details, $matches);
						if ($check)
						{
							$charges = array();
							
							foreach ($matches[0] as $match)
							{
								$check = preg_match_all('/<td.*\>(.*)<\/td>/Uis', $match, $matches2);
								$count = 0;
								foreach($matches2[1] as $charge)
								{
									//echo '<br />';
									$charge = preg_replace('/\(.*\)/Uis', '', $charge);
									//echo '<hr />';
									if ($count == 0 || ($count %3) == 0)
									{
										$charges[] = $charge;
									}
									$count++;
								}
								
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
									$image_link = 'http://www.mcso.us/PAID/ImageHandler.axd?mid='.$image_booking_id.'&size=F'; 
									# set image name
									$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
									# set image path
							        $imagepath = '/mugs/oregon/multnomah/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
							        # create mugpath
							        $mugpath = $this->set_mugpath($imagepath);
									//@todo find a way to identify extension before setting ->imageSource
									$this->imageSource    = $image_link;
							        $this->save_to        = $imagepath.$imagename;
							        $this->set_extension  = true;
									$this->cookie			= $this->cookies;
							        $this->download('curl');
									if (file_exists($this->save_to . '.jpg') && (filesize($this->save_to . '.jpg') > 10000)) //validate the image was downloaded
									{
										$this->convertImage($mugpath.$imagename.'.jpg');
								        $imgpath = $mugpath.$imagename.'.png';
										$img = Image::factory($imgpath);
								        $imgpath = $mugpath.$imagename.'.png';
										# now run through charge logic
										$chargeCount = count($fcharges);
										# run through charge logic
										$mcharges 	= array(); // reset the array
								        if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
								        {
											$mcharges 	= $this->charges_prioritizer($list, $fcharges);
											if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in Lexington scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
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
										
									} else { @unlink($this->save_to . '.jpg'); return 102; } // image was not downloaded successfully
								} else {
						            # add new charges to the charges collection
									foreach ($ncharges as $key => $value)
									{
										//$value = preg_replace('/\s/', '', $value);
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
								} // new charges found
							} else { return 101; } // no charges found
						} else { return 101; } // charges table not found
					} else { return 101; } // booking_date validation failed	
				} else { return 101; } // fn ln validation failed
			} else { return 101; } // fullname not found
		} else { return 103; } // database validation failed
	} // end extraction
} // class end