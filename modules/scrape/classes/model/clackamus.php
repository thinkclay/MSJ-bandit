<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Clackamus
 *
 * @package Scrape
 * @author Winter King
 * @url http://web3.co.clackamas.or.us/sheriff/roster/default.asp
 */
class Model_Clackamus extends Model_Scrape
{
	private $scrape 	= 'clackamus';
	private $state		= 'oregon';
    private $cookies 	= '/tmp/clackamus_cookies.txt';
		
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
	* @return true - on completed scrape
    * @return false - on failed scrape
	*/
    function scrape() 
    {  
    	# set report variables
		$index = $this->curl_index();
		# build booking_id array
		$booking_ids = array();
		$check = preg_match_all('/BookNo\:\"(.*)\"/Uis', $index, $matches);
		if ($check)
		{
			$booking_ids = $matches[1];
			foreach ($booking_ids as $booking_id)
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
		} else { return false; } // inmate booking_id links not found	
	}  // function end


	/**
	* curl_index - gets the index of current population
	*
	*  
	*  
	*/
	function curl_index()
	{
		$url = 'http://www.clackamas.us/sheriff/jail/roster/inmatecontent.jsp?fn=&ln=';
		$ch = curl_init();   
     	curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		//curl_setopt($ch, CURLOPT_POST, true);
		//curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $index = curl_exec($ch);
        curl_close($ch);
		return $index;
	}
	
	
	/**
	* curl_details - gets the index of current population
	*
	* 
	*  
	*/
	function curl_details($booking_id)
	{
		$url = 'http://www.clackamas.us/sheriff/jail/roster/inmate.jsp?in=' . $booking_id;
		$ch = curl_init();   
     	curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $details = curl_exec($ch);
        curl_close($ch);
		return $details;
	}
	

	//https://jailtracker.com/JTClientWeb/(S(cnwrnhb4uf1v2h55rgy15s20))/JailTracker/GetImage/
	
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
		$county = 'clackamus';
		$booking_id = 'clackamus_' . $booking_id;
		# database validation 
		$offender = Mango::factory('offender', array(
			'booking_id' => $booking_id
		))->load();	
		# validate against the database
		if (empty($offender->booking_id)) 
		{
			# extract profile details
			# required fields
			//<strong class="title">Inmate Information for  ADAMS, CLIFFORD WESLEY          </strong>
			$check = preg_match('/Inmate\sInformation\sfor(.*)\<\/strong\>/Uis', $details, $match);
			if ($check)
			{
				$firstname = null;
				$lastname = null;
				$fullname  = strip_tags(trim($match[1]));
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
					//<strong>Arrest Date:</strong> 4/11/2011<br><br>
					$check = preg_match('/Arrest\sDate\:<\/strong>(.*)<br>/Uis', $details, $match);
					if ($check)
					{
						$booking_date = strtotime(trim($match[1]));
						# extra fields
						$extra_fields = array();
						
						$check = preg_match('/DOB\:<\/strong>(.*)<br>/Uis', $details, $match);
						if ($check) 
						{
							$extra_fields['dob'] = strtotime(trim($match[1]));
							$extra_fields['age'] = floor(($booking_date - $extra_fields['dob']) / 31556926);
						}
						
						$check = preg_match('/Sex\:<\/strong>(.*)<br>/Uis', $details, $match);
						if ($check) { $extra_fields['gender'] = trim(strtoupper($match[1])); }
						
						$check = preg_match('/Race\:<\/strong>(.*)<br>/Uis', $details, $match);
						if ($check) 
						{
							$race = $this->race_mapper(trim($match[1]));
							if ($race)
							{
						 		$extra_fields['race'] = $race;
							} 
						}
						
						$check = preg_match('/Height\:<\/strong>(.*)<br>/Uis', $details, $match);
						if ($check) 
						{
							$height= $this->height_conversion(trim($match[1]));
							if ($height)
							{
						 		$extra_fields['height'] = $height;
							} 
						}
						
						$check = preg_match('/Weight\:<\/strong>(.*)<br>/Uis', $details, $match);
						if ($check) { $extra_fields['weight'] = (int)preg_replace('/\D/', '', $match[1]); }

						$check = preg_match('/Hair\:<\/strong>(.*)<br>/Uis', $details, $match);
						if ($check) { $extra_fields['hair_color'] = trim($match[1]); }
						
						$check = preg_match('/Eyes\:<\/strong>(.*)<br>/Uis', $details, $match);
						if ($check) { $extra_fields['eye_color'] = trim($match[1]); }
						
						 
						$charges = array();
						$check = preg_match('/<table.*>.*Charge.*<\/table\>/Uis', $details, $match);
						if ($check)
						{
							$check = preg_match_all('/<tr.*>.*<\/tr>/Uis', $match[0], $matches);
							array_shift($matches[0]);
							foreach($matches[0] as $row)
							{
								$check = preg_match_all('/<td.*>.*<\/td>/Uis', $row, $matches);
								$charge = preg_replace('/.*\-/Uis', '', $matches[0][0]);
								$charge = preg_replace('/<\/td>/Uis', '', $charge);
								$charge = preg_replace('/<td.*>/Uis', '', $charge);
								$charges[] = trim(strtoupper($charge));
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
								//http://web3.co.clackamas.or.us/sheriff/roster/pic.asp?bn=2010035340
								$image_link = array();
								//http://www.clackamas.us/sheriff/jail/roster/inc/pic.jsp?id=2011032163
								$image_link = 'http://www.clackamas.us/sheriff/jail/roster/inc/pic.jsp?id=' . preg_replace('/clackamus\_/', '', $booking_id); 
								# set image name
								$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
								# set image path
						        $imagepath = '/mugs/oregon/clackamus/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
						        # create mugpath
						        $mugpath = $this->set_mugpath($imagepath);
								//@todo find a way to identify extension before setting ->imageSource
								$this->imageSource    = $image_link;
						        $this->save_to        = $imagepath.$imagename;
						        $this->set_extension  = true;
								$this->cookie			= $this->cookies;
						        $this->download('curl');
								
								### ADD CHECK FOR SIZE HERE
								
								if (file_exists($this->save_to . '.jpg') ) //validate the image was downloaded
								{
									if (filesize($this->save_to . '.jpg') > 16000) 
									{
										#@TODO make validation for a placeholder here probably
										# ok I got the image now I need to do my conversions
								        # convert image to png.
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
											if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in clackamus scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
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
									} else {  unlink($this->save_to . '.jpg'); return 102; } // placeholder validation failed 		
								} else { return 101; } // get failed		
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
											$charge->scrape = $this->scrape;
											$charge->county = $this->scrape;
											$charge->new 	= (int)0;
											$charge->update();
										}	
									}
								} 
					            return 104;
							} // ncharges validation	
						} else { return 101; } // no charges found at all
					} else { return 101; } // booking_date validation failed
				} else { return 101; } // firstname validation failed
			} else { return 101; } // lastname validation	failed
		} else { return 103; } // database validation failed
	} // end extraction
} // class end