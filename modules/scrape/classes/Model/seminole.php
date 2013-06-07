<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Seminole
 *
 * @package Scrape
 * @author Winter King
 * @url http://webbond.seminolesheriff.org/Search.aspx
 */
class Model_Seminole extends Model_Scrape
{
	private $scrape 	= 'seminole';
	private $state		= 'florida';
    private $cookies 	= '/tmp/seminole_cookies.txt';
	
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
	* @return $info - passes to the controller for reporting
	*/
    function scrape() 
    {
		$index = $this->curl_index();
		$check = preg_match('/id\=\"\_\_VIEWSTATE\"\svalue\=\"([^"]*)"/', $index,  $match);       
		if ($check)
		{
			$vs = $match[1];
			$check = preg_match('/id\=\"\_\_EVENTVALIDATION\"\svalue\=\"([^"]*)"/', $index,  $match);       
			if ($check)
			{
				$ev = $match[1];
				$search = '_'; // this search will return ALL 
				$index = $this->curl_index($vs, $ev, $search);
				$flag = false;
				# need to handle paging here
				do 
				{
					# build booking_id array
					//echo $index;
					$check = preg_match_all('/btnNormal\"\shref\=\"(.*)\"/Uis', $index, $matches);
					if ( ! $check)
						break; // break paging loop
					$booking_ids = array();
					foreach($matches[1] as $match)
					{
						$booking_ids[] = preg_replace('/[^0-9]*/Uis', '', $match);
					}
					if (!empty($booking_ids))
					{
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
					} else { $flag = true; } // no more pages
					# need to page here
					$index = $this->curl_page($index);
					if($index == false) { $flag = true; }
				} while($flag == false);
				$this->report->failed = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
		        $this->report->finished = 1;
		        $this->report->stop_time = time();
		        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
		        $this->report->update();
		        return true;  
			} else { return false; } // no event validation found
		} else { return false; } // no viewstate found
	} // end scrape method

	
	/**
	* curl_index - gets the index of current population
	*
	*@url http://webbond.seminolesheriff.org/Search.aspx
	*  
	*  
	*/
	function curl_index($vs = null, $ev = null, $search = null)
	{
		
		$url 	 = 'http://webbond.seminolesheriff.org/Search.aspx';
		$headers = array('GET /public/ArRptQuery.aspx HTTP/1.1',
					'Host: www.sheriffseminole.org',
					'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:2.0.1) Gecko/20100101 Firefox/4.0.1',
					'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
					'Accept-Language: en-us,en;q=0.5',
					'Accept-Encoding: gzip, deflate',
					'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
					'Keep-Alive: 115',
					'Connection: keep-alive',
					'Cookie: ASP.NET_SessionId=hhwz3u45zmuiddbhqwy2y1rw',
					'Cache-Control: max-age=0');
		$ch = curl_init();   
     	curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if ($ev && $vs && $search)
		{
			$fields  = '__EVENTTARGET=&__EVENTARGUMENT=&__LASTFOCUS=&__VIEWSTATE='.urlencode($vs).'&__EVENTVALIDATION='.urlencode($ev).'&ctl00%24ContentPlaceHolder1%24ddllist=Last+Name&ctl00%24ContentPlaceHolder1%24txtTST='.urlencode($search).'&ctl00%24ContentPlaceHolder1%24Button1=Search';
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
		}
		//curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $index = curl_exec($ch);
        curl_close($ch);
		return $index;
	}
	
	
	/**
	* curl_details
	*
	* @notes  this is to get the offender details page 
	* @params string $booking_id
	* @return string $details - details page in as a string
	*/
	function curl_details($booking_id)
	{
		
		$url = 'http://webbond.seminolesheriff.org/InmateInfo.aspx?bkgnbr=' . $booking_id;		
		$ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        $details = curl_exec($ch);
        curl_close($ch);
		return $details;
	}
	
	/**
	* curl_page
	*
	* @params string $booking_id
	* @return string $index - new index after page
	*/
	function curl_page($index)
	{
		$check = preg_match('/id\=\"\_\_VIEWSTATE\"\svalue\=\"([^"]*)"/', $index,  $match);       
		if ($check)
		{
			$vs = $match[1];
			$check = preg_match('/id\=\"\_\_EVENTVALIDATION\"\svalue\=\"([^"]*)"/', $index,  $match);       
			if ($check)
			{
				$ev = $match[1];
				$fields = '__EVENTTARGET=ctl00%24ContentPlaceHolder1%24mGrid&__EVENTARGUMENT=Page%24Next&__LASTFOCUS=&__VIEWSTATE='.urlencode($vs).'&__EVENTVALIDATION='.urlencode($ev).'&ctl00%24ContentPlaceHolder1%24ddllist=Last+Name&ctl00%24ContentPlaceHolder1%24txtTST=_';
				$url = 'http://webbond.seminolesheriff.org/Search.aspx';		
				$ch = curl_init();
		        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		        curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
				curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
		        $index = curl_exec($ch);
		        curl_close($ch);
				return $index;
			} else { return false; } // no EV
		} else { return false; } // no VS
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
		$county = 'seminole';
		$booking_id = 'seminole_' . $booking_id;
		# database validation 
		$offender = Mango::factory('offender', array(
			'booking_id' => $booking_id
		))->load();	
		# validate against the database
		if (empty($offender->booking_id)) 
		{
			# extract profile details
			# required fields
			
			$check = preg_match('/FirstNameLabel[^>]*>(.*)</Uis', $details, $match);
			if ($check)
			{
				$firstname = '';
				$firstname = strtoupper(trim($match[1]));
				$check = preg_match('/LastNameLabel[^>]*>(.*)</Uis', $details, $match);
				if ($check)
				{
					$lastname = '';
					$lastname = strtoupper(trim($match[1]));
					//ArrestDate">03/11/2011 12:46:00 AM</span>
					$check = preg_match('/ArrestDate\">(.*)<\//Uis', $details, $match);
					if ($check)
					{
						$booking_date = strtotime($match[1]);
						
						#get extra fields
						$extra_fields = array();
						$dob = null;
						//DOBLabel">10/13/1961</span>
						$check = preg_match('/DOBLabel[^>]*>(.*)</Uis', $details, $match);
						if ($check) 
						{
							$dob = strtotime(trim($match[1]));
							$extra_fields['dob'] = $dob; 
						}
						$check = preg_match('/divCharges\"\>\<table\scellpadding\=\'0\'\scellspacing\=\'0\'\sstyle\=\'width\:100\%\'\>(.*)table\>/Uis', $details, $match);
						$check = preg_match_all('/\<td\>(.*)\<\/td>/Uis', $match[1], $matches);
						if (isset($matches[1]))
						{
							foreach ($matches[1] as $charge)
							{
								if ($charge != '&nbsp;')
								{
									$charges[] = trim(strtoupper($charge));
								}
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
									$dbcharges = $fcharges;
									# begin image extraction
									//http://webbond.seminolesheriff.org/bookingphotos/201100003098.jpg
									$image_link = 'http://webbond.seminolesheriff.org/bookingphotos/'. preg_replace('/seminole\_/Uis', '', $booking_id) .'.jpg';
									# set image name
									$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
									# set image path
							        $imagepath = '/mugs/florida/seminole/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
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
											if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in seminole scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
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
									} else { return 102; } // image download failed 
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
												$charge->abbr   = $value;
												$charge->order  = (int)0;
												$charge->county = $this->scrape;
												$charge->new 	= (int)0;
												$charge->update();
											}	
										}
									} 
						            return 104; 
								} // ncharges validation
							} else { return 101; } // no charges found	
						} else { return 101; } // no charges found
					} else { return 101; } // no booking_date
				} else { return 101; } // lastname validation failed
			} else { return 101; } // firstname validation	failed
		} else { return 103; } // database validation failed
	} // end extraction
} // class end