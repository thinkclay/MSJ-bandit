<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Davis
 *
 * @TODO fix the error with mango core: 
 * 		 ErrorException [ 4096 ]: Object of class Model_Davis could not be converted to string ~ MODPATH/mango/classes/mango/core.php [ 1278 ]	 
 * @package Scrape
 * @author Winter King
 * @params 
 * @description Cold fusion site uses json data for index with paging
 * @url http://www.co.davis.ut.us/sheriff/divisions/jail/current_inmate_roster/default.cfm
 */
class Model_Davis extends Model_Scrape
{
	private $scrape	 	= 'davis';
	private $state		= 'utah';
    private $cookies 	= '/tmp/davis_cookies.txt';
	
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
	* @params $page = page number
	* @return boolean true - on completed scrape
    * @return boolean false - on failed scrape
	*/
    function scrape() 
    {
    	# set flag and start loop for paging
    	$flag = false;
		$page = 1;
		while ($flag == false)
		{
			#get index
			$index = $this->curl_index($page);	
			# loop through [DATA] and build booking_id array
			if (is_array($index['QUERY']['DATA']))
			{
				$booking_ids = array();
				foreach ($index['QUERY']['DATA'] as $key => $value)
				{
					if(!empty($value[0]))
					{
						$booking_ids[] = $value[0];	
					}
					//$this->curl_details();
				}
				if (empty($booking_ids)) { $flag == true; } // end of paging
				else // loop through booking_ids
				{
					foreach ($booking_ids as $key => $booking_id)
					{
						$details = $this->curl_details($booking_id);
						# begin extraction
						$extraction = $this->extraction($details);
						if ($extraction == 100) { $this->report->successful = ($this->report->successful + 1); $this->report->update(); }
		                if ($extraction == 101) { $this->report->other = ($this->report->other + 1); $this->report->update(); }
		                if ($extraction == 102) { $this->report->bad_images = ($this->report->bad_images + 1); $this->report->update(); }
		                if ($extraction == 103) { $this->report->exists = ($this->report->exists + 1); $this->report->update(); }
		                if ($extraction == 104) { $this->report->new_charges = ($this->report->new_charges + 1); $this->report->update(); }
		                $this->report->total = ($this->report->total + 1); $this->report->update();			
					}
				} 
				$page++;	
			}
			if ($page == 25) { $flag = true; }
		}
		$this->report->failed = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
		$this->report->finished = 1;
        $this->report->stop_time = time();
        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
        $this->report->update();
        return true;   	
	}


	/**
	* curl_index - this will return a json index of passed page number
	*
	* 
	* @params $page = page number
	* @return json data
	*/
	function curl_index($page)
	{
		$url = 'http://www.co.davis.ut.us/sheriff/divisions/jail/current_inmate_roster/booking.cfc?method=get_bookings_summary&returnFormat=json&argumentCollection=%7B%22page%22%3A'.$page.'%2C%22pageSize%22%3A50%2C%22gridsortcolumn%22%3A%22%22%2C%22gridsortdirection%22%3A%22%22%2C%22last_name_filter%22%3A%22%22%2C%22first_name_filter%22%3A%22%22%7D&_cf_nodebug=true&_cf_nocache=true&_cf_rc=';
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        $index = curl_exec($ch);
        curl_close($ch);
		$index = preg_replace('/dcpre/', '', $index);
		$index = json_decode($index, true);
		return $index;
	}


	/**
	* curl_details - 
	*
	* 
	* @return 
	*/
	function curl_details($booking_id)
	{
		$url = 'http://www.co.davis.ut.us/sheriff/divisions/jail/current_inmate_roster/booking_detail.cfm?booking_number='.$booking_id.'&_cf_containerId=booking_detail_window_body&_cf_nodebug=true&_cf_nocache=true&_cf_rc=1';
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
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
	function extraction($details)
	{
		
		$county = 'davis';
		$ncharges 	= array();
		$fcharges 	= array();
		$mcharges 	= array();	
		$dbcharges  = array();
		# extract profile details
		# get fullname
 		$check = preg_match('/Name\:[^>]*\>[^<]*\<[^>]*\>([^<]*)\</', $details, $fullname);
		if ($check) 
		{
			$fullname = trim($fullname[1]);
			
			# check for the comma.  Must be a comma to ensure first/last names
			if(strpos($fullname, ',') !== false )
			{
				# check for a period (this can mess up image name)
				if (strpos($fullname, '.') === false )
				{
					# get first and last names
					$explode   = explode(',', $fullname);
					$lastname  = trim(strtoupper($explode[0]));
					$firstname = trim($explode[1]);
					$firstname = explode(' ', $firstname);
					$firstname = trim(strtoupper($firstname[0]));
					# get booking_id
					$check = preg_match('/Booking\sNumber\:[^<]*\<[^<]*\<[^>]*\>([^<]*)\</', $details, $booking_id);
					if ($check)
					{
						$booking_id = trim($booking_id[1]);
						$booking_id = 'davis_' . $booking_id;
						# database validation
						$offender = Mango::factory('offender', array(
							'booking_id' => $booking_id
						))->load();	
						# validate against the database
						if (empty($offender->booking_id)) 
						{
							#get booking date
							$check = preg_match('/Booking\sDate\:[^<]*\<[^<]*\<[^>]*\>([^<]*)\</', $details, $booking_date);
							if ($check)
							{	
								$booking_date = trim(strtotime($booking_date[1]));
								# get charges
								$check = preg_match('/Type.*\<\/th\>.*\<td\sclass\=\"border\-light\-all\"\>(.*)\<\/table/Uis', $details, $match);
								$check = preg_match('/^(.*)\<\/td/Uis', $match[1], $match);
								$charges = array();
								$charges[] = trim($match[1]);
								$smashed_charges = array();
								foreach($charges as $charge)
								{
								 // smash it
								 $smashed_charges[] = preg_replace('/\s/', '', $charge);
								}
								
								# validate charges table
								if (!empty($charges))
								{
									# make sure to always reset arrays!
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
										$fcharges = array();
										foreach ($charges as $key => $value)
										{
											$fcharges[] = trim(strtoupper($value));	
										}
										$dbcharges = $fcharges;
										# make it unique and reset keys
										$fcharges = array_unique($fcharges);
										$fcharges = array_merge($fcharges);
										# get extra fields
										$extra_fields = array();
										$check = preg_match('/Age\:[^<]*\<[^<]*\<[^>]*\>([^<]*)\</', $details, $age);
										if ($check) { $age = trim($age[1]); $age = (int)$age; }
										if (isset($age)) { $extra_fields['age'] = $age; }
										
										$check = preg_match('/Gender\:[^<]*\<[^<]*\<[^>]*\>([^<]*)\</', $details, $gender);
										if ($check) { $gender = trim(strtoupper($gender[1])); }
										if (isset($gender)) { $extra_fields['gender'] = $gender; }
										
										# begin image extraction
										//http://www.daviscountyutah.gov/booking/2011/F201101604.jpg
										$check = preg_match('/http\:\/\/www\.daviscountyutah\.gov\/booking\/[^"]*/', $details, $image_link);
										if ($check)
										{
											
											$image_link = $image_link[0]; 
											# set image name
											$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
											# set image path
									        $imagepath = '/mugs/utah/davis/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
									        # create mugpath
									        $mugpath = $this->set_mugpath($imagepath);
											//@todo find a way to identify extension before setting ->imageSource
											$this->imageSource    = $image_link;
									        $this->save_to        = $imagepath.$imagename;
									        $this->set_extension  = true;
									        $get = $this->download('gd');
											# validate against broken image
											
											if ($get) 
											{
												# ok I got the image now I need to do my conversions
										        # convert image to png.
										        $this->convertImage($mugpath.$imagename.'.jpg');
										        $imgpath = $mugpath.$imagename.'.png';
												$img = Image::factory($imgpath);
	                                        	
												
	                                        	$img->crop(400, 450)->save();
												# now run through charge logic
												$chargeCount = count($fcharges);
												# run through charge logic
										        if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
										        {
										            $mcharges 	= $this->charges_prioritizer($list, $fcharges);
													if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in Davis scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
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
												$dbcharges = $this->charges_abbreviator_db($list, $dbcharges);
												$dbcharges = array_unique($dbcharges);
												# DATABASE BOILERPLATE INSERTS
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
											} else { return 102; } //image download failed
										} else { return 102; } //regex validation failed - no image link found
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
								} else { return 101; } // empty charges table
							} else { return 101; } // booking_date validation
						} else { return 103; } // database validation
					} else { return 101; } // booking_id check	
				} else { return 101; } // period in fullname		
			} else { return 101; } // fullname comma validation failed
		} else { return 101; } // fullname validation failed
	} // end extraction					
} // class end