<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_marionfl
 *
 * @package Scrape
 * @author Winter King
 * @TODO this one seems to go forever.  fix it
 * @url http://jail.marionso.com/default.aspx
 */
class Model_Marionfl extends Model_Scrape
{
	private $scrape		= 'marionfl';
	private $state		= 'florida';
    private $cookies 	= '/tmp/marionfl_cookies.txt';
	
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
		# pass index $from and $to in this format: 2011-04-12T00:00:00
		$days_ago = (int)7; 
		$from = date('Y-m-d', time() - (86400 * $days_ago)) . 'T00:00:00';
		$to	  = date('Y-m-d', time()) . 'T00:00:00';
		$index = $this->curl_index($from, $to);
		$index = preg_replace('/[s|a]:/', '', $index); // remove the SOAP namespace php conflict
		$xml = new SimpleXMLElement(@$index);
		
		if ( $xml )
		{
			# SimpleXML returns an object array so I need to flatten it with this handy function
			$xml = $this->simpleXMLToArray($xml);
			
			#build a details array 
			$details_array = array();
			$details_array = $xml['Body']['SearchBookingResponse']['SearchBookingResult']['clsBooking'];
			#loop through each and query actual details page with the booking id 
			foreach($details_array as $details)
			{
				$data = array();
				$details_page = $this->curl_details($details['BookingNumber']);
				$details_page = preg_replace('/[s|a]:/', '', $details_page); // remove the SOAP namespace php conflict
				$details_xml = @new SimpleXMLElement(@$details_page);
				if ($details_xml)
				{
					//$this->print_r2($details_xml);
					# SimpleXML returns an object array so I need to flatten it with this handy function
					$details_xml = $this->simpleXMLToArray($details_xml);
					$details['details_page'] = $details_xml;
					$extraction = $this->extraction($details);
					
					if ($extraction == 100) { $this->report->successful = ($this->report->successful + 1); $this->report->update(); }
                    if ($extraction == 101) { $this->report->other = ($this->report->other + 1); $this->report->update(); }
                    if ($extraction == 102) { $this->report->bad_images = ($this->report->bad_images + 1); $this->report->update(); }
                    if ($extraction == 103) { $this->report->exists = ($this->report->exists + 1); $this->report->update(); }
                    if ($extraction == 104) { $this->report->new_charges = ($this->report->new_charges + 1); $this->report->update(); }
                    $this->report->total = ($this->report->total + 1); $this->report->update();
				}
			}
			$this->report->failed = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
	        $this->report->finished = 1;
	        $this->report->stop_time = time();
	        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
	        $this->report->update();
	        return true;   
		} else { return false; }
	}
	
	
	/**
	* curl_index
	*
	* @url 
	* 
	*/
	function curl_index($from, $to)
	{
		$url = 'http://jail.marionso.com/PublicJailViewerServer.svc';
		
		$fields = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><SearchBooking xmlns="http://tempuri.org/"><pLastName></pLastName><pFirstName></pFirstName><pBookingFromDate>'.$from.'</pBookingFromDate><pBookingFromto>'.$to.'</pBookingFromto><pReleasedFromDate>0001-01-01T00:00:00</pReleasedFromDate><pReleasedFromTo>0001-01-01T00:00:00</pReleasedFromTo><pCustody>ALL</pCustody></SearchBooking></s:Body></s:Envelope>';
		
		
		$headers = array(
			'POST /PublicJailViewerServer.svc HTTP/1.1',
			'Host: jail.marionso.com',
			'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:2.0) Gecko/20100101 Firefox/4.0',
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: en-us,en;q=0.5',
			'Accept-Encoding: gzip, deflate',
			'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
			'Keep-Alive: 115',
			'Content-Type: text/xml; charset=utf-8',
			'SOAPAction: "http://tempuri.org/IPublicJailViewerServer/SearchBooking"',
			'Referer:http://jail.marionso.com/ClientBin/PublicJailViewer_Silverlight_Application.xap',
			'Connection: keep-alive'
		);
		$ch = curl_init();   
     	curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $index = curl_exec($ch);
        curl_close($ch);
		return $index;
	}
	
	
	/**
	* curl_details
	*
	* @notes  this is just to get the ev and vs
	* @params $bdate - begin date
	* @return json data
	*/
	function curl_details($booking_id)
	{
		$url = 'http://jail.marionso.com/PublicJailViewerServer.svc';	
		$fields = '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><GetDetail xmlns="http://tempuri.org/"><pBookingNumber>'.$booking_id.'</pBookingNumber></GetDetail></s:Body></s:Envelope>';	
		$headers = array(
			'POST /PublicJailViewerServer.svc HTTP/1.1',
			'Host: jail.marionso.com',
			'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:2.0) Gecko/20100101 Firefox/4.0',
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: en-us,en;q=0.5',
			'Accept-Encoding: gzip, deflate',
			'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
			'Keep-Alive: 115',
			'Connection: keep-alive',
			'Referer:http://jail.marionso.com/ClientBin/PublicJailViewer_Silverlight_Application.xap',
			'Content-Length: 188',
			'Content-Type: text/xml; charset=utf-8',
			'SOAPAction: "http://tempuri.org/IPublicJailViewerServer/GetDetail"',	
		);
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
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
	function extraction($details)
	{
		$county = 'marion';
		$booking_id = 'marionfl_' . $details['BookingNumber'];
		# database validation 
		$offender = Mango::factory('offender', array(
			'booking_id' => $booking_id
		))->load();	
		# validate against the database
		if (empty($offender->booking_id)) 
		{
			
			# extract profile details
			# required fields
			$lastname = trim(strtoupper($details['LastName']));
			$firstname = trim(strtoupper($details['FirstName']));
			$booking_date = (int)strtotime(trim($details['BookingDate']));
			if ($firstname && $lastname && $booking_date)
			{
				# extra fields
				
				$extra_fields = array();	
				$extra_fields['dob'] = strtotime(trim($details['DOB']));
				$extra_fields['age'] = floor(($booking_date - $extra_fields['dob']) / 31556926);
				if (!is_array($details['MiddleName'])) { $extra_fields['middlename'] = $details['MiddleName']; }
				if (!is_array($details['Race'])) 
				{
					$race = $this->race_mapper($details['Race']);
					 if ($race)
					 {
				 	 	$extra_fields['race'] = $race;
					 }
				}
				if (!is_array($details['Sex']))
				{
					if ($details['Sex'] == 'F')
					{
						$extra_fields['gender'] = 'FEMALE';
					}
					else if ($details['Sex'] == 'M')
					{
						$extra_fields['gender'] = 'MALE';
					}
				}
				$booking_details = $details['details_page']['Body']['GetDetailResponse']['GetDetailResult']['objBookingDetail'];
				
				if (!is_array($booking_details['Eye'])) { $extra_fields['eye_color'] = $booking_details['Eye']; }
				if (!is_array($booking_details['Hair'])) { $extra_fields['hair_color'] = $booking_details['Hair']; }
				if (!is_array($booking_details['Height'])) 
				{
					$extra_fields['height'] = $this->height_conversion($booking_details['Height']); 
				}
				if (!is_array($booking_details['Weight'])) { $extra_fields['weight'] = $booking_details['Weight']; }
				
				# CHARGES EXTRACTION
				$charges_array = array();
				$charges_array = $details['details_page']['Body']['GetDetailResponse']['GetDetailResult']['objChargeCollection'];
				$charge_array = array();
				$charges = array();
				foreach ($charges_array as $charge_array)
				{
					if (!isset($charge_array['ViolationDescription'])) // this means there are multiple charges
					{
						foreach ($charge_array as $key => $value)
						{
							$charges[] = $value['ViolationDescription'];
						}
					}
					else 
					{
						$charges[] = $charge_array['ViolationDescription'];
					}	
				}
				$smashed_charges = array();
				foreach($charges as $charge)
				{
					// smash it
					$smashed_charges[] = preg_replace('/\s/', '', $charge);
				}
				$flag = false;
				foreach ($charges as $charge)
				{
					if (is_array($charge))
					{
						$flag = true;
					}
				}
				if ($flag == false)
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
						$dbcharges = $fcharges;
						# Begin image extraction
						//http://jail.marionso.com/Inmate.aspx?1100003460
						
						$image_link = 'http://jail.marionso.com/Inmate.aspx?' . preg_replace('/marionfl\_/', '', $booking_id); 
						# set image name
						
						$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
						# set image path
						
				        $imagepath = '/mugs/florida/marionfl/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
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
							if (filesize($this->save_to . '.jpg') > 16000) 
							{
								#@TODO probly need a placeholder validation here
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
								if ($check)
								{
									
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
										
								} else { unlink($this->save_to . '.png'); return 102; } // image failure
							} else {  unlink($this->save_to . '.jpg'); return 102; } // placeholder validation failed
						} else { return 102; } // image waas not downlooaded
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
									$charge->new 	= (int)0;
									$charge->update();
								}	
							}
						}
			            return 104;
					} // ncharges validation
				} else { return 101; } // 
			} else { return 101; } // firstname or lastname validation failed
		} else { return 103; } // database validation failed
	} // end extraction
} // class end