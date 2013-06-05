<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Orange
 *
 * @package Scrape
 * @author Winter King
 * @todo   Getting a weird error sometimes: Premature end of JPEG file
 * @url http://www.orlandosentinel2.com/data/arrests/mug_shots/
 */
class Model_Orange extends Model_Scrape
{
	private $scrape 	= 'orange';
	private $state		= 'florida';
    private $cookies 	= '/tmp/orange_cookies.txt';
	
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
		$loops = (int)100;
		$page = 1;
		$total = 0;
		$booking_id = null;
		$details = $this->curl_handler(); // set cookie
		for ($i = 1; $i <= $loops; $i++) 
		{
			# set $details
			if (!$booking_id) // check for a booking_id
			{
				$details = $this->curl_handler();
			}
			else
			{
				 
				$details = $this->curl_handler($booking_id);
			}
			$extraction = $this->extraction($details);
            if ($extraction == 100) { $this->report->successful = ($this->report->successful + 1); $this->report->update(); }
            if ($extraction == 101) { $this->report->other = ($this->report->other + 1); $this->report->update(); }
            if ($extraction == 102) { $this->report->bad_images = ($this->report->bad_images + 1); $this->report->update(); }
            if ($extraction == 103) { $this->report->exists = ($this->report->exists + 1); $this->report->update(); }
            if ($extraction == 104) { $this->report->new_charges = ($this->report->new_charges + 1); $this->report->update(); }
            $this->report->total = ($this->report->total + 1); $this->report->update();
			
			$check = preg_match('/paginate\(([^)]*)\);">Next/Uis', $details, $match);
			if ($check)
			{
				$booking_id = $match[1];
			}
			else // break loop if  no booking_id was found
			{
				break;
			}
		}
		$this->report->failed = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
        $this->report->finished = 1;
        $this->report->stop_time = time();
        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
        $this->report->update();
        return true;	
	}
		
	/**
	* curl_handler - gets the detail pages 
	*
	*@url http://www.sherifforange.org/public/ArRptQuery.aspx
	*  
	*  
	*/
	function curl_handler($paginate = null)
	{
		//$paginate = '11019389';
		$url = 'http://www.orlandosentinel2.com/data/arrests/mug_shots/index.php';
		$headers = array('Host: www.orlandosentinel2.com',
						'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:2.0.1) Gecko/20100101 Firefox/4.0.1',
						'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
						'Accept-Language: en-us,en;q=0.5',
						//'Accept-Encoding: gzip, deflate',
						'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
						'Keep-Alive: 115',
						'Connection: keep-alive',
						'Referer: http://www.orlandosentinel2.com/data/arrests/mug_shots/index.php',
						'Cookie:  PHPSESSID=fm6f3fj8gvpql95g0p5ss8tl41');
		$ch = curl_init();   
     	curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
     	//curl_setopt($ch, CURLOPT_COOKIESESSION, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		//curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
  		//curl_setopt($ch, CURLOPT_AUTOREFERER, true);
  		curl_setopt($ch, CURLOPT_HEADER, TRUE); 
		//curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		
		if ($paginate) 
		{
			curl_setopt($ch, CURLOPT_POST, true);
			// paginate=11019235
			curl_setopt($ch, CURLOPT_POSTFIELDS, 'paginate='.$paginate);
			 
		}
		curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);
        //curl_setopt($ch, CURLOPT_NOBODY, TRUE); // remove bod
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
		$county = 'orange';
		# get the booking id from the image link first thing
		// http://www.orlandosentinel2.com/data/arrests/mug_shots/mugshots/11019195.jpg
		$check = preg_match('/mugshots\/(.*)\.jpg/Uis', $details, $match);
		if ($check)
		{
			$booking_id = 'orange_' . $match[1];
			# database validation 
			$offender = Mango::factory('offender', array(
				'booking_id' => $booking_id
			))->load();	
			# validate against the database
			if (empty($offender->booking_id)) 
			{
				# extract profile details
				# required fields
				//<h1 style="margin-top:10px; padding-bottom: 15px;">Rohrbacher, Rene Lynn</h1><b>Gender:</b>
				//<h1 style="margin-top:10px; padding-bottom: 15px;">Moses, David</h1><b>Gender:</b>
				$check = preg_match('/<h1 style\=\"margin\-top\:10px\;\spadding\-bottom\:\s15px\;\">(.*)<\/h1><b>Gender\:</Uis', $details, $match);
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
					if (!empty($firstname) && !empty($lastname))
					{
						# get booking date
						$check = preg_match('/<b>Booked\:<\/b>(.*)<br>/Uis', $details, $match); 
						if ($check)
						{
							$booking_date = strtotime($match[1]);
							# get extra fields
							
							$gender = null;
							$check = preg_match('/Gender\:<\/b>(.*)<br>/Uis', $details, $match);
							if ($check) 
							{
								$gender = strtoupper(trim($match[1]));
								$extra_fields['gender'] = $gender; 
							}
							
							# get charges
							//Charge(s):</b> <ul><li>No Valid Driver License (nvdl)</li></ul>
							//Charge(s):</b> <ul><li>Failure Of Defendant To Appear</li><li>Obstructing Or Opposing A Police Office</li><li>Providing False Id To Law Enforcement O</li><li>Resisting Officer Without Violence</li><li>Violation Of Probation</li></ul>
							
							$check = preg_match('/Charge\(s\)\:<\/b>\s<ul>(.*)<\/ul>/Uis', $details, $match);
							
							if ($check)
							{
								$explode = explode('</li><li>', $match[1]);
								$charges = array();
								foreach($explode as $charge)
								{
									$charge = preg_replace('/<\/li>/Uis', '', $charge);
									$charge = preg_replace('/<li>/Uis', '', $charge);
									$charge = trim($charge);
									$charges[] = $charge;
								}
								if (!empty($charges))
								{
									
									###
									# this creates a charges object for all charges that are not new for this county
									$charges_object = Mango::factory('charge', array('county' => $this->scrape, 'new' => 0))->load(false)->as_array(false);
									# I can loop through and pull out individual arrays as needed:
									foreach($charges_object as $row)
									{
										$list[$row['charge']] = $row['abbr'];
									}
									# this gives me a list array with key == fullname, value == abbreviated
									$ncharges = array();
									# Run my full_charges_array through the charges check
									$ncharges = $this->charges_check($charges, $list);
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
										
										//http://www.orlandosentinel2.com/data/arrests/mug_shots/mugshots/11019389.jpg
										$image_link = 'http://www.orlandosentinel2.com/data/arrests/mug_shots/mugshots/'.preg_replace('/orange\_/Uis', '', $booking_id).'.jpg';
										
										# set image name
										$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
										# set image path
								        $imagepath = '/mugs/florida/orange/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
								        # create mugpath
								        $mugpath = $this->set_mugpath($imagepath);
										//@todo find a way to identify extension before setting ->imageSource
										$this->imageSource    = $image_link;
								        $this->save_to        = $imagepath.$imagename;
								        //$this->set_extension  = true;
										//$this->cookie			= $this->cookies;
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
											if(strlen($fcharges[0]) >= 15)
											{
												$charges = $this->charge_cropper($fcharges[0], 400, 15);
												if(!$charges)
												{
													return 101;
												}
												else
												{
													//$charges = $this->charges_abbreviator($list, $charges[0], $charges[1]); 
                                                	$this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
													$offender = Mango::factory('offender', 
	                                                array(
	                                                    'scrape'        => $this->scrape,
	                                                    'state'         => strtolower($this->state),
	                                                    'county'        => $county,
	                                                    'firstname'     => $firstname,
	                                                    'lastname'      => $lastname,
	                                                    'booking_id'    => $booking_id,
	                                                    'booking_date'  => $booking_date,
	                                                    'scrape_time'   => time(),
	                                                    'image'         => $imgpath,
	                                                    'charges'       => $charges,                                      
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
													
												}
											}
											# now run through charge logic
											$chargeCount = count($fcharges);
											# run through charge logic	
											$mcharges 	= array(); // reset the array
									        if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
									        {
									            $mcharges 	= $this->charges_prioritizer($list, $fcharges);
												if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in orange scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
									            $mcharges 	= array_merge($mcharges);   
									            $charge1 	= $mcharges[0];
									            $charge2 	= $mcharges[1];    
									            $charges 	= $this->charges_abbreviator($list, $charge1, $charge2); 
									            $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
									        }
									        else if ( $chargeCount == 2 )
									        {
									            $fcharges 	= array_merge($fcharges);
									            $charge1 	= $fcharges[0];
									            $charge2 	= $fcharges[1];   
									            $charges 	= $this->charges_abbreviator($list, $charge1, $charge2);
									            $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);           
									        }
									        else 
									        {
									            $fcharges 	= array_merge($fcharges);
									            $charge1 	= $fcharges[0];    
									            $charges 	= $this->charges_abbreviator($list, $charge1);       
									            $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0]);   
									        }
											
											// Abbreviate FULL charge list
											$dbcharges = $this->charges_abbreviator_db($list, $dbcharges);
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
										} else { return 102; } // failed image download 
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
													$charge->abbr 	= $value;
													$charge->order 	= (int)0;
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
							} else { return 101; } // no matches found
						} else { return 101; } // booking date check failed
					} else { return 101; } // no first or lastname	
				} else { return 101; } // fullname validation failed 
			} else { return 103; } // database validation failed
		} else { return 101; } // no booking_id found
	} // end extraction	
} // class end