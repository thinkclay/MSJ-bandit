<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Citrus
 *
 * @package Scrape
 * @author Winter King
 * @url http://www.sheriffcitrus.org/public/ArRptQuery.aspx
 */
class Model_citrus extends Model_Scrape
{
	private $scrape 	= 'citrus';
	private $state		= 'florida';
    private $cookies 	= '/tmp/citrus_cookies.txt';
	
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
    	
    	# set report variables
		$index = $this->curl_index();
		$this->source = $index; 
        $this->anchor = 'ctl00_ParentContent_gvAr';
	    $this->anchorWithin = true;
		$this->headerRow = true;
		$this->stripTags = false;
		$this->maxRows = 11; // this will get rid of the last two rows
        $index_table = $this->extractTable();
		$booking_ids = array();		 
		# build my booking_ids array
		foreach($index_table as $profile)
		{
			if (!empty($profile['Booking#']))
			{
				$end = $profile['Booking#'];
				break;
				$booking_ids[] = $profile['Booking#'];	
			}
			else { return false; } // this condition should never be met
		}
		$start = $end - 150;
		# now loop and extract
		$flag = false;
		while ($flag == false)
		{
			$booking_ids[] = $start;
			$start++;
			if ($start >= $end)
			{
				$flag = true;					
			}
		}
		/*
		 * Use this method to get past offenders (booking id range)
		 * 
		 * 
		 * 
		$booking_ids = array();
		$start = 2500;
		$flag = false;
		$end = 2606;
		while ($flag == false)
		{
			$booking_ids[] = $start;
			$start++;
			if ($start >= $end)
			{
				$flag = true;					
			}
		}
		 * 
		 * 
		 */
		if (!empty($booking_ids))
		{
		  
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
	* curl_index - gets the index of current population
	*
	*@url http://www.sheriffcitrus.org/public/ArRptQuery.aspx
	*  
	*  
	*/
	function curl_index()
	{
		$url = 'http://www.sheriffcitrus.org/public/ArRptQuery.aspx';
		$headers = array('GET /public/ArRptQuery.aspx HTTP/1.1',
					'Host: www.sheriffcitrus.org',
					'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:2.0.1) Gecko/20100101 Firefox/4.0.1',
					'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
					'Accept-Language: en-us,en;q=0.5',
					'Accept-Encoding: gzip, deflate',
					'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
					'Keep-Alive: 115',
					'Connection: keep-alive',
					'Cookie: ASP.NET_SessionId=3hy5qd4scgdgabpti1coojck',
					'Cache-Control: max-age=0');
		$ch = curl_init();   
     	curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
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
		$url = 'http://www.sheriffcitrus.org/public/ArRptDetail1.aspx?bnbr=' . $booking_id;		
		//$fields = 'ctl00%24ScriptManager1=ctl00%24ParentContent%24upArRpt4%7Cctl00%24ParentContent%24gvAr&__EVENTTARGET=ctl00%24ParentContent%24gvAr&__EVENTARGUMENT=Select%240&__VIEWSTATE=%2FwEPDwUKMTg5NzU0MTA3Mw9kFgJmD2QWAgIDD2QWAgILD2QWCAIHD2QWAmYPZBYCAgcPZBYCAgEPPCsACgEADxYEHgtWaXNpYmxlRGF0ZQYAQAYrquDNCB4CU0QWAQYAQAYrquDNCGRkAgsPZBYCZg9kFgICBw9kFgICAQ88KwAKAQAPFgQfAAYAAHBVc%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%3D%3D&__EVENTVALIDATION=%2FwEWcAKKnd3hBQL%2FzOiMBAKLlZylDQLSm8W7AwLTm82GBwLI5v3nAwLI5ombCwLI5pW%2BAgLI5qHRDQLI5s30BALI5tmvDALI5uXCBwLI5rGrAgLI5t3ODQKt3LPXDgKt3N%2BKBgKt3OutAQKt3PfACAKt3IPkAwKt3K%2BfCwKt3LuyAgKt3MfVDQKt3JO%2BCAKt3L%2FRAwKSs636BAKSs7mdDAKSs8WwBwKSs9HrDgKSs%2F2OBgKSs4miAQKSs5XFCAKSs6H4AwKSs43BDgKSs5nkCQL3qo%2BNCwL3qpugAgL3qqfbDQL3qrP%2BBAL3qt%2BRDAL3quu0BwL3qvfvDgL3qoODBgL3qu%2FrBAL3qvuODALcgemXAQLcgfXKCALcgYHuAwLKodeJAgKI293WBQLb79fQBQLa79%2FtAQLBku%2BMBQLBkpvwDQLBkofVBALBkrO6CwLBkt%2BfAgLBksvECgLBkvepAQLBkqPABALBks%2BlCwKkqKG8CAKkqM1hAqSo%2BcYHAqSo5asOAqSokY8FAqSovfQNAqSoqdkEAqSo1b4LAqSogdUOAqSorboFApvHv5ECApvHq%2FYKApvH19sBApvHw4AIApvH72UCm8ebyQcCm8eHrg4Cm8ezkwUCm8efqggCm8eLjw8C%2Ft6d5g0C%2Ft6JywQC%2Ft61sAsC%2Ft6hlQIC%2Ft7N%2BgoC%2Ft753wEC%2Ft7lhAgC%2Ft6RaAL%2B3v2AAgL%2B3unlCgLV9fv8BwLV9eehDgLV9ZOFBQKxuouvDgLd3eylCgK51YiCDwLn6peyAgLn6q9gAsOtrM4JApXXifcPApnmr54CApXa0rgHApXaxtUPApXa%2BuIFApXa7p8MApXaoqwKApXalskCApXaytcIApXavvMDApXastICApXapo8FAr75hPcDEM7XnCg3eErEpyqTAgBNh0r6rMg%3D&ctl00%24ParentContent%24cpeInstructions_ClientState=true&ctl00%24ParentContent%24txtFromDt=5%2F15%2F2011&ctl00%24ParentContent%24txtLName=&ctl00%24ParentContent%24txtToDt=5%2F16%2F2011&__ASYNCPOST=true&';
		$headers = array(
			'Host: www.sheriffcitrus.org',
			'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:2.0.1) Gecko/20100101 Firefox/4.0.1',
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: en-us,en;q=0.5',
			'Accept-Encoding: gzip, deflate',
			'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
			'Keep-Alive: 115',
			'Connection: keep-alive',
			'Referer: http://www.sheriffcitrus.org/public/ArRptQuery.aspx',
		);
		$ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
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
		try
		{
			// manually set county because it does not vary.  I don't think at least.
			$county = 'citrus';
			// THIS MEANS NO IMAGE
			// <td colspan="1" align="center"><img id="ctl00_ParentContent_FormView1_imgAR" src="" alt="Photo Not Available" style="background-color: Transparent; border-color: rgb(153, 102, 51); border-width: 3px; border-style: inset; height: 160px; width: 128px;"></td>
			$booking_id = 'citrus_' . $booking_id;
			# database validation 
			$offender = Mango::factory('offender', array(
				'booking_id' => $booking_id
			))->load();	
			# validate against the database
			if (empty($offender->booking_id)) 
			{
				
				# extract profile details
				# required fields
				$check = preg_match('/lblLName.*>(.*)</Uis', $details, $match);
				if ($check)
				{
					$lastname = trim($match[1]);
					$check = preg_match('/lblFName.*>(.*)</Uis', $details, $match);
					if ($check)
					{
						$firstname = trim($match[1]);
						
						$check = preg_match('/lblArDtTm.*>(.*)</Uis', $details, $match);
						if ($check)
						{
							$booking_date = strtotime($match[1]);
							# extra fields
							$extra_fields = array();
							$middlename = null;
							$check = preg_match('/lblMName.*>(.*)</Uis', $details, $match);
							if ($check) 
							{
								$middlename = trim($match[1]);
								$extra_fields['middlename'] = $middlename; 
							}
							$dob = null;
							$check = preg_match('/lblDOB.*>(.*)</Uis', $details, $match);
							if ($check) 
							{
								
								$dob = strtotime(trim($match[1]));
								$extra_fields['dob'] = $dob; 
							}
							$race = null;
							$check = preg_match('/lblRace.*>(.*)</Uis', $details, $match);
							if ($check) 
							{
								$race = trim($match[1]); 
								# run it though the race mapper
								$race = $this->race_mapper($race);
								if ($race)
								{
							 		$extra_fields['race'] = $race;
								}
							}
							$gender = null;
							$check = preg_match('/lblSex.*>(.*)</Uis', $details, $match);
							if ($check) 
							{
								$gender = trim($match[1]);
								if ($gender == 'M') {$gender = 'MALE';} else if ($gender == 'F') { $gender = 'FEMALE'; }
								$extra_fields['gender'] = $gender; 
							}
							
							$check = preg_match('/Charge\sDescription.*\<td\>.*\<td\>(.*)\<\/td\>/Uis', $details, $match);
							$charges = array();
							$charges[] = $match[1];
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
									# begin image extraction
									
									$image_link = null;
									//imgAR" src="http://www.sheriffcitrus.org/PhotoDir/FullSize/2011/5/277187.jpg"
									$check = preg_match('/imgAR.*src\=\"(.*)\"/Uis', $details, $match);
									if ($check)
									{
										try
								        {
											$image_link = $match[1]; 
											# set image name
											$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
											# set image path
									        $imagepath = '/mugs/florida/citrus/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
									        # create mugpath
									        $mugpath = $this->set_mugpath($imagepath);
											//@todo find a way to identify extension before setting ->imageSource
											$this->imageSource    = $image_link;
									        $this->save_to        = $imagepath.$imagename;
									        $this->set_extension  = true;
											$this->cookie		  = $this->cookies;
									        $this->download('curl');
										} 
								        catch(Kohana_Exception $e)
										{
											unlink($this->save_to . '.jpg');
											return 102;	
										}
										if (file_exists($this->save_to . '.jpg') && (filesize($this->save_to . '.jpg') > 100) ) //validate the image was downloaded
										{
											#@TODO make validation for a placeholder here probably
											# ok I got the image now I need to do my conversions
									        # convert image to png.
									        try
									        {
									        	$this->convertImage($mugpath.$imagename.'.jpg');
										        $imgpath = $mugpath.$imagename.'.png';
												$img = Image::factory($imgpath);
												$img->crop(320, 398, 0, 0)->save();
										        $imgpath = $mugpath.$imagename.'.png';
									        } 
									        catch(Kohana_Exception $e)
											{
												unlink($this->save_to . '.jpg');
												return 102;	
											}
									        
											# now run through charge logic
											$chargeCount = count($fcharges);
											# run through charge logic	
											$mcharges 	= array(); // reset the array
									        if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
									        {
									            $mcharges 	= $this->charges_prioritizer($list, $fcharges);
												if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in citrus scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
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
								                	'county'		=> $county,
								                	'state'			=> strtolower($this->state),
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
										} else {  unlink($this->save_to . '.jpg'); return 102; } // get failed				
									} else { return 102; } // image link failed 
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
							} else { return 101; } // no charges found at all
						} else { return 101; } // booking_date validation failed
					} else { return 101; } // firstname validation failed
				} else { return 101; } // lastname validation	failed
			} else { return 103; } // database validation failed
		}
		catch(Exception $e)
		{
			if (file_exists($this->save_to . '.jpg'))
			{
				unlink($this->save_to . '.jpg');
			}	 
			return 101;	
		}
	} // end extraction
} // class end