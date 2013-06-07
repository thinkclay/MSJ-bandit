<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Kenton
 *
 * @TODO LBS are off somehow
 * @package Scrape
 * @author Winter King
 * @params takes a date range
 * @description this one is powered by JailTracker which is .NET (.aspx) which means viewstate and eventvalidation variables
 * @url http://www.jailtracker.com/kncdc/kenton_inmatelist.html
 */
class Model_Kenton extends Model_Scrape
{
	private $scrape 	= 'kenton';
	private $state		= 'ohio';
    private $cookies 	= '/var/www/public/cookiejar/kenton_cookies.txt';
	
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
    	# set flag and start loop for paging
		$hash = $this->get_hash();
		$index = $this->curl_index($hash);
		
		$index = json_decode($index, true);
		# build booking_id array
		$booking_ids = array();
		foreach($index['data'] as $key => $value)
		{
			$booking_ids[] = $value['ArrestNo']; 
		}
		foreach ($booking_ids as $key => $booking_id)
		{
			$details 	= $this->curl_details($hash, $booking_id);
			$details = json_decode($details, true);
			$extraction		= $this->extraction($hash, $details, $booking_id);
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
	//__EVENTTARGET=dgInmates%24ctl02%24ctl03&__EVENTARGUMENT=&__VIEWSTATE=%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%2BMTAzPC9iPiBpbm1hdGVzIGluIHRoaXMgZmFjaWxpdHkuZGQCEQ9kFgICAQ8PFgIeCEltYWdlVXJsZGRkGAEFHl9fQ29udHJvbHNSZXF1aXJlUG9zdEJhY2tLZXlfXxYBBQ5jaGtQYXN0SW5tYXRlc%2Bkj8QPuHJGbpuFxlDJWxUfq1c2U&__EVENTVALIDATION=%2FwEWDgLwxo%2FdBgKdlcWpAgK5h62pDwLwtbnZDAKAzfnNDgLChrRGAqWf8%2B4KAs3T19YCAt%2FgvIsJAt7gvIsJAt3gvIsJAtzgvIsJAujbjfANAufbjfANxKr1MrOF%2FtNKfw0F0y8HbSQfKg8%3D&txtLastName=&txtFirstName=&chkPastInmates=on&txtBeginDate=03%2F9%2F2011&txtEndDate=03%2F16%2F2011

	
	/**
	* curl_hash - used for the sole purpose of returning 
	* 			   the hash which will be used for curl_index
	*
	* @notes this is the only one I've needed to use the headers for
	* @return hash string
	*/
	function get_hash()
	{
		$url = 'https://jailtracker.com/jtclientweb/jailtracker/index/KENTON_COUNTY_KY';
		$headers = array(
			"Host: jailtracker.com", 
			"User-Agent: Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.15) Gecko/20110303 Firefox/3.6.15",
			"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
			"Accept-Language: en-us,en;q=0.5",
			"Accept-Encoding: gzip,deflate",
			"Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7",
			"Keep-Alive: 115",
		);
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
		$info = curl_getinfo($ch);
        $index = curl_exec($ch);
        $check = preg_match('/jtclientweb\/\(S\(([^)]*)\)\)/', $index, $hash);
		$hash = $hash[1];
		curl_close($ch);
		return $hash;
	}
	
	
	/**
	* curl_index 
	*
	* @notes  this is just to get the ev and vs
	* @params $bdate - begin date
	* @return json data
	*/
	function curl_index($hash)
	{
		
		$url = 'https://jailtracker.com/jtclientweb/(S('.urlencode($hash).'))/JailTracker/GetInmates?start=0&limit=1000000&sort=OriginalBookDateTime&dir=DESC';
		$headers = array(
			"Host: jailtracker.com", 
			'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.15) Gecko/20110303 Firefox/3.6.15', 'Accept: */*', 'Accept-Language: en-us,en;q=0.5', 
			'Accept-Encoding: gzip,deflate', 
			'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7', 
			'Keep-Alive: 115', 
			'Connection: keep-alive', 
			'X-Requested-With: XMLHttpRequest', 
			'Referer: https://jailtracker.com/jtclientweb/(S('.urlencode($hash).'))/jailtracker/index/KENTON_COUNTY_KY' 
			);
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $index = curl_exec($ch);
        curl_close($ch);
		return $index;
	}
	
	
	/**
	* curl_home
	*
	* @notes  
	*/
	function curl_home()
	{
		$url = 'http://www.jailtracker.com/kncdc/kenton_inmatelist.html';
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		//curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true );
        //curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
       // curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_TIMEOUT, false);
        $home = curl_exec($ch);
        curl_close($ch);
		return $home;
	}

	
	/**
	* curl_image
	*
	* 
	* @url 
	* 
	* @return $details - html
	*/
	function curl_image($hash, $booking_id)
	{
		$url = 'https://jailtracker.com/JTClientWeb/(S('.$hash.'))/JailTracker/GetImage/';
		$fields = 'arrestNo='.$booking_id;
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $image = curl_exec($ch);
        curl_close($ch);
		$image = json_decode($image, true);
		return $image;
	}
	
	
	/**
	* curl_details
	*
	* 
	* @url //http://www.kentoncountydetention.com/InmateList/InmateView.aspx?ID=2007008607
	* 
	* @return $details - html
	*/
	function curl_details($hash, $booking_id)
	{
		$url = 'https://jailtracker.com/JTClientWeb/(S('.$hash.'))/JailTracker/GetInmate?arrestNo='.$booking_id;
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $details = curl_exec($ch);
        curl_close($ch);
		return $details;
	}
	
	
	/**
	* curl_charges
	*
	* 
	* @url 	//https://jailtracker.com/JTClientWeb/(S(cnwrnhb4uf1v2h55rgy15s20))/JailTracker/GetCharges
	* 
	* @return $details - html
	*/
	function curl_charges($hash, $booking_id)
	{
		
		$url = 'https://jailtracker.com/JTClientWeb/(S('.$hash.'))/JailTracker/GetCharges';
		$fields = 'arrestNo='.$booking_id;
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
	    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        $charges = curl_exec($ch);
        curl_close($ch);
		$charges = json_decode($charges, true);
		return $charges;
	}
	
	
	/**
	* curl_cases
	*
	* 
	* @url	https://jailtracker.com/JTClientWeb/(S(cnwrnhb4uf1v2h55rgy15s20))/JailTracker/GetCases?arrestNo=790057
	* 
	* @return $details - html
	*/
	function curl_cases($hash)
	{
		
		$url = 'https://jailtracker.com/JTClientWeb/(S('.$hash.'))/JailTracker/GetInmate?arrestNo=790057';
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
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
	function extraction($hash, $details, $booking_id)
	{
		
		$county = 'kenton';
		$curl_bid   = $booking_id; // set the bid for image curl
		$booking_id = 'kenton_' . $booking_id;
		$fcharges 	= array();
		# extract profile details
		# required fields
		$extra_fields = array();
		@$firstname    = trim($details['data'][1]['Value']);
		@$lastname     = trim($details['data'][2]['Value']);
		foreach ($details['data'] as $key => $value)
		{
			if ($value['Field'] == 'Booking Date:')
			{
				@$booking_date = strtotime(trim($value['Value']));
			}
		}
		foreach ($details['data'] as $key => $value)
		{
			if ($value['Field'] == 'Middle Name:')
			{
				$extra_fields['middlename'] = trim($value['Value']);
			}
			if ($value['Field'] == 'Height:')
			{
				$extra_fields['height'] = $this->height_conversion($value['Value']);
			}
			if ($value['Field'] == 'Weight:')
			{
				$extra_fields['weight'] = preg_replace('/[^0-9]/', '', $value['Value']);
			}
			if ($value['Field'] == 'Birth Date:')
			{
				$extra_fields['dob'] = strtotime(trim($value['Value']));
				$age = ($extra_fields['dob'] < 0) ? ( $booking_date + ($extra_fields['dob'] * -1) ) : $booking_date - $extra_fields['dob'];
				$year = 60 * 60 * 24 * 365;
				$extra_fields['age'] = $age / $year;
			}
			if ($value['Field'] == 'Hair Color:')
			{
				$extra_fields['hair_color'] = trim($value['Value']);
			}
			if ($value['Field'] == 'Eye Color:')
			{
				$extra_fields['eye_color'] = trim($value['Value']);
			}
			if ($value['Field'] == 'Race:')
			{
				 $race = $this->race_mapper($value['Value']);
				 if ($race)
				 {
			 	 	$extra_fields['race'] = $race;
				 }
			}
			if ($value['Field'] == 'Sex:')
			{
				if ($value['Value'] == 'M')
				{
					$gender = 'MALE';
				} 
				else if ($value['Value'] == 'F')
				{
					$gender = 'FEMALE';
				}
				if (isset($gender))
				{
					$extra_fields['gender'] = $gender;
				}
			}
		}
		
		# validate against all required fields
		if (!empty($firstname) && !empty($lastname) && !empty($booking_date))
		{
			# database validation 
			$offender = Mango::factory('offender', array(
				'booking_id' => $booking_id
			))->load();
			
			# validate against the database
			if (empty($offender->booking_id)) 
			{
				# get charges
				$charges_object = $this->curl_charges($hash, $curl_bid);
				
				if($charges_object['totalCount'] > 0) 
				{
					# build charges array
					$charges = array();
					foreach($charges_object['data'] as $key => $value)
					{
						$charges[] = $value['ChargeDescription'];
					}
					
					$smashed_charges = array();
					foreach($charges as $charge)
					{
						// smash it
						$smashed_charges[] = preg_replace('/\s/', '', $charge);
					}
					$dbcharges = array();
					$dbcharges = $charges;
					
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
					# validate
					$ncharges2 = $this->charges_check($smashed_charges, $list);
					if (!empty($ncharges)) // this means it found new charges (unsmashed)
					{
					    if (empty($ncharges2)) // this means our smashed charges were found in the db
					    {
					        $ncharges = $ncharges2;
					    }
					}
					if (empty($ncharges)) // skip the offender if ANY new charges were found
					{
						# format charges
						$fcharges = array();
						foreach ($charges as $key => $value)
						{
							$fcharges[] = trim(strtoupper($value));	
						}
						# make unique and reset keys
						$fcharges = array_unique($fcharges);
						$fcharges = array_merge($fcharges);
						
						### BEGIN IMAGE EXTRACTION ###
						$img_object = $this->curl_image($hash, $curl_bid);
						if (empty($img_object['error']))
						{
							$imgLnk = 'https://jailtracker.com/JTClientWeb/(S('.$hash.'))/JailTracker/StreamInmateImage/'.$img_object['Image'];
							//https://jailtracker.com/JTClientWeb/(S(rwkzbfvm34srdjvloxeen445))/JailTracker/GetImage/
							# set image name
							$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
							# set image path
					        $imagepath = '/mugs/ohio/kenton/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
					        # create mugpath
					        $mugpath = $this->set_mugpath($imagepath);
							$this->imageSource    = $imgLnk;
					        $this->save_to        = $imagepath.$imagename;
					        $this->set_extension  = true;
					        $get = $this->download('gd');
							$dims = getimagesize($this->save_to . '.jpg');
							if ($dims[0] != 205) // check for placeholder which has a width of 205 always
							{
								# validate against broken image
								if ($get)
								{
									# ok I got the image now I need to do my conversions
							        # convert image to png.
							        $this->convertImage($mugpath.$imagename.'.jpg');
							        $imgpath = $mugpath.$imagename.'.png';
									$img = Image::factory($imgpath);
				                	$img->crop(150, 200)->save();
							        $imgpath = $mugpath.$imagename.'.png';
									# now run through charge logic
									$chargeCount = count($fcharges);
									# run through charge logic
									$mcharges 	= array(); // reset the array
							        if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
							        {
							            $mcharges 	= $this->charges_prioritizer($list, $fcharges);
										if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in kenton scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
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
									$county = Mango::factory('county', array('name' => $this->scrape, 'state' => $this->state))->load();
									if (!$county->loaded())
									{
										$county = Mango::factory('county', array('name' => $this->scrape, 'state' => $this->state))->create();
									}
									$county->booking_ids[] = $booking_id;
									$county->update();
									# END DATABASE INSERTS
								
									return 100;
										### END EXTRACTION ###
								} else { return 102; } // image validation failed
							} else { unlink($this->save_to . '.jpg'); return 102; } // placeholder check failed
						} else { return 102; } // image validation failed
					} else {
						# add new charges to the charges collection
						foreach ($ncharges as $key => $value)
						{
							$value = preg_replace('/\s/', '', $value);
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
				} else { return 101; } // no charges yet 
			} else { return 103; } // database validation failed
		} else { return 101; } // required profile fields validation failed
	} // end extraction					
} // class end