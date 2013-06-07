<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Lakeca - Scrape model for Lake county California
 *
 * @package Scrape
 * @author Winter King
 * @url http://www.lakesheriff.com/Recent_Arrests.htm
 */
class Model_Lakeca extends Model_Scrape
{
    private $scrape     = 'lakeca';
    private $state      = 'california';
    private $cookies    = '/tmp/lakeca_cookies.txt';
    
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
    	# make a 7 day array
    	$days = array(time(), time() - 86400, time() - (86400 * 2), time() - (86400 * 3), time() - (86400 * 4), time() - (86400 * 5), time() - (86400 * 6));
		foreach ($days as $day)
		{
			$index = $this->curl_index($day);	
			# build my booking_ids array
			//<a href='bookingdetail.asp?booknum=42547'>42547</a>
			$booking_ids = array();
			$check = preg_match_all('/booknum\=(.*)\'/Uis', $index, $matches);
			if ($check)
			{
				$booking_ids = $matches[1];
			}
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
			
			//echo $index;
			
		}
		$this->report->failed = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
        $this->report->finished = 1;
        $this->report->stop_time = time();
        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
        $this->report->update();
    }
    
      
    /**
    * curl_index - gets the index based on a date
    * 
    * @url http://acm.co.lake.ca.us/sheriff/arrests.asp
    * @post ArrestDate=06%2F06%2F2011  
    */
    function curl_index($date)
    {
    	$post = 'ArrestDate=' . date('m', $date) . '%2F' . date('d', $date) . '%2F' . date('Y', $date);
        $url = 'http://acm.co.lake.ca.us/sheriff/arrests.asp';
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        $index = curl_exec($ch);
        curl_close($ch);
        return $index;
    } 
      
    /**
    * curl_details - gets the details page based on a booking_id
    * 
    * @url http://acm.co.lake.ca.us/sheriff/bookingdetail.asp?booknum=42533
    *   
    */
    function curl_details($booking_id)
    {
        $url = 'http://acm.co.lake.ca.us/sheriff/bookingdetail.asp?booknum=' . $booking_id;
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
    
    
    /**
    * extraction - validates and extracts all data
    *
    * 
    * @params $details  - offenders details page
    * @return $ncharges - numerical array of new charges found
    * @return false     - on failed extraction
    * @return true      - on successful extraction
    * 
    */
    function extraction($details, $booking_id)
    {
    	$county = 'lake';
		/*
		# lets try out some xpath magic
		$doc = new DOMDocument();
		$doc->preserveWhiteSpace = FALSE;
		$xpath = new DOMXpath($doc);
		*/
        $booking_id = 'lakeca_' . $booking_id;
        # database validation 
        $offender = Mango::factory('offender', array(
            'booking_id' => $booking_id
        ))->load(); 
        # validate against the database
        if (empty($offender->booking_id)) 
        {
            # extract profile details
            # required fields
            $check = preg_match('/Inmate\sID\:.*<b>(.*)<\/b>/Uis', $details, $match);
            if ($check)
            {
                $fullname = trim(strtoupper($match[1]));
                $explode = explode(' ', $fullname);
                # remove empty values
                foreach ($explode as $key => $value)
                {
                    if(empty($value))
                    {
                        unset($explode[$key]);
                    }
                }
				$explode = array_merge($explode); // reset keys
				$firstname = trim(strtoupper($explode[0]));
				if (count($explode > 2))
				{
					$middlename = trim(strtoupper($explode[1]));
				}
				
				$lastname = trim(strtoupper($explode[(count($explode) - 1)]));
                if (!empty($firstname) && !empty($lastname))
                {
					$check = preg_match('/Inmate\sID\:.*<td[^>]*>.*<\/td>.*<td[^>]*>(.*)<\/td>/Uis', $details, $match);
                    if ($check)
					{
						if ( trim(strip_tags($match[1])) == preg_replace('/lakeca\_/Uis', '', $booking_id) )
						{
							# get inmate_id this is just for this specific site for the image
							$check = preg_match('/Inmate\sID\:.*<td[^>]*>.*<\/td>.*<td[^>]*>.*<\/td>.*<td[^>]*>(.*)<\/td>/Uis', $details, $match);
		                    if ($check)
							{
								$inmate_id = trim(strip_tags($match[1]));
								# get booking date
								$check = preg_match('/Released\:.*<td[^>]*>(.*)<\/td>/Uis', $details, $match);
								if ($check)
								{
									$explode = explode('&nbsp;', strip_tags($match[1]));
									$booking_date = strtotime(trim(strip_tags($explode[0])));
									# now get charges
									# get charges table
									$charges = array();
									
									$check = preg_match_all('/<table.*>.*<\/table>/Uis', $details, $matches);
									if ($check)
									{
										$check = preg_match_all('/<tr.*>.*<\/tr>/Uis', $matches[0][4], $matches);
										array_shift($matches[0]);
										array_shift($matches[0]);
										array_shift($matches[0]);
										array_shift($matches[0]);
										array_shift($matches[0]);
										array_pop($matches[0]);
										foreach($matches[0] as $charge_row)
										{
											$check = preg_match_all('/<td.*>.*<\/td>/Uis', $charge_row, $matches);
											if ($check)
											{
												$charges[] = strip_tags($matches[0][1]);
											} else { return 101; }
										}
									} else { return 101; }
				                    $smashed_charges = array();
									foreach($charges as $charge)
									{
										// smash it
										$smashed_charges[] = preg_replace('/\s/', '', $charge);
									}
									if (!empty($charges))
	                                {
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
	                                    ###
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
	                                        $fcharges   = array();
	                                        foreach($charges as $key => $value)
	                                        {
	                                            $fcharges[] = trim(strtoupper($value)); 
	                                        }
	                                        # make it unique and reset keys
	                                        $fcharges = array_unique($fcharges);
	                                        $fcharges = array_merge($fcharges);
	                                        $dbcharges = $fcharges;
											#get extra fields
											$extra_fields = array();
											/*
											 * Weight:
	          </font></td>
	        </tr>
	
	        <tr>
	
	<td valign="top"><font face="arial">
	<b>11/30/1986</b>
											 * 
											 */ 
											$check = preg_match('/Weight\:.*<td[^>]*>(.*)<\/td>/Uis', $details, $match);
											if ($check)
			                                {
			                                    $extra_fields['dob'] = trim(strip_tags(trim($match[1])));
			                                }
											$check = preg_match('/Weight\:.*<td[^>]*>.*<\/td>.*<td[^>]*>(.*)<\/td>/Uis', $details, $match);
											if ($check)
			                                {
			                                    $extra_fields['race'] = $this->race_mapper(trim(strip_tags(trim($match[1]))));	
			                                }
											$check = preg_match('/Weight\:.*<td[^>]*>.*<\/td>.*<td[^>]*>.*<\/td>.*<td[^>]*>(.*)<\/td>/Uis', $details, $match);
											if ($check)
			                                {
			                                    $extra_fields['gender'] = $this->gender_mapper(trim(strip_tags(trim($match[1]))));	
			                                }
			                                $check = preg_match('/Weight\:.*<td[^>]*>.*<\/td>.*<td[^>]*>.*<\/td>.*<td[^>]*>.*<\/td>.*<td[^>]*>(.*)<\/td>/Uis', $details, $match);
											if ($check)
			                                {
			                                    $extra_fields['eye_color'] = trim(strip_tags($match[1]));	
			                                }
											$check = preg_match('/Weight\:.*<td[^>]*>.*<\/td>.*<td[^>]*>.*<\/td>.*<td[^>]*>.*<\/td>.*<td[^>]*>.*<\/td>.*<td[^>]*>(.*)<\/td>/Uis', $details, $match);
											if ($check)
			                                {
			                                    $extra_fields['hair_color'] = trim(strip_tags($match[1]));	
			                                }
											$check = preg_match('/Weight\:.*<td[^>]*>.*<\/td>.*<td[^>]*>.*<\/td>.*<td[^>]*>.*<\/td>.*<td[^>]*>.*<\/td>.*<td[^>]*>.*<\/td>.*<td[^>]*>(.*)<\/td>/Uis', $details, $match);
											if ($check)
			                                {
			                                    $extra_fields['height'] = $this->height_conversion(trim(strip_tags($match[1])));	
			                                }
											$check = preg_match('/Weight\:.*<td[^>]*>.*<\/td>.*<td[^>]*>.*<\/td>.*<td[^>]*>.*<\/td>.*<td[^>]*>.*<\/td>.*<td[^>]*>.*<\/td>.*<td[^>]*>.*<\/td>.*<td[^>]*>(.*)<\/td>/Uis', $details, $match);
											if ($check)
			                                {
			                                    $extra_fields['weight'] = preg_replace('[\D]', '', trim(strip_tags($match[1])));	
			                                }
											
											# begin image extraction
											//http://acm.co.lake.ca.us/sheriff/getimage.asp?id=68270											
	                                        
                                            $image_link = 'http://acm.co.lake.ca.us/sheriff/getimage.asp?id=' . $inmate_id;
                                            # set image name
                                            $imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
                                            # set image path
                                            $imagepath = '/mugs/california/lakeca/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
                                            # create mugpath
                                            $mugpath = $this->set_mugpath($imagepath);
                                            //@todo find a way to identify extension before setting ->imageSource
                                            $this->imageSource    = $image_link;
                                            $this->save_to        = $imagepath.$imagename;
                                            $this->set_extension  = true;
                                            $this->cookie         = $this->cookies;
                                            $this->download('curl');
											if (file_exists($this->save_to . '.jpg')) //validate the image was downloaded
                                            {
                                            	$check = getimagesize($this->save_to . '.jpg');
												if ($check === false)
												{
													unlink($this->save_to . '.jpg');
													return 102;
												}
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
                                                $mcharges   = array(); // reset the array
                                                if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
                                                {
                                                    $mcharges   = $this->charges_prioritizer($list, $fcharges);
                                                    if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in lakeca scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
                                                    $mcharges   = array_merge($mcharges);   
                                                    $charge1    = $mcharges[0];
                                                    $charge2    = $mcharges[1];    
                                                    $charges    = $this->charges_abbreviator($list, $charge1, $charge2); 
                                                    $check = $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
													if ($check === false)
													{
													    unlink($imgpath);
													    return 101;
													}
                                                }
                                                else if ( $chargeCount == 2 )
                                                {
                                                    $fcharges   = array_merge($fcharges);
                                                    $charge1    = $fcharges[0];
                                                    $charge2    = $fcharges[1];   
                                                    $charges    = $this->charges_abbreviator($list, $charge1, $charge2);
                                                    $check = $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
													if ($check === false)
													{
													    unlink($imgpath);
													    return 101;
													}           
                                                }
                                                else 
                                                {
                                                    $fcharges   = array_merge($fcharges);
                                                    $charge1    = $fcharges[0];    
                                                    $charges    = $this->charges_abbreviator($list, $charge1);       
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
                                                        'scrape'        => $this->scrape,
                                                        'state'         => strtolower($this->state),
                                                        'county'        => $county,
                                                        'firstname'     => $firstname,
                                                        'lastname'      => $lastname,
                                                        'booking_id'    => $booking_id,
                                                        'booking_date'  => $booking_date,
                                                        'scrape_time'   => time(),
                                                        'image'         => $imgpath,
                                                        'charges'       => $dbcharges,                                      
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
                                            } else { return 102; } 
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
	                                                    $charge->new    = (int)0;
	                                                    $charge->update();
	                                                }   
	                                            }
	                                        }
	                                        return 104;
										}
									} else { return 101; } // no charges
								} else { return 101; } // no booking_date matched
							} else { return 101; } // no inmate_id found
						} else { return 101; } // booking_id mismatch
					} else { return 101; } // mo booking_id
				} else { return 101; } // fn ln failed
			} else { return 101; } // no booking_id found       
        } else { return 103; } // database validation failed
    } // end extraction
} // class end