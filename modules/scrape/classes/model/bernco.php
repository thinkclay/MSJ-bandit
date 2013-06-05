<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Bernco
 *
 * @package Scrape
 * @author 	Winter
 * @url 	http://www.bernco.gov/
 */
class Model_Bernco extends Model_Scrape
{
    private $scrape     = 'bernco';
	private $county 	= 'bernalillo';
    private $state      = 'new mexico';
    private $cookies    = '/tmp/bernco_cookies.txt';
    
    public function __construct()
    {
        set_time_limit(86400); //make it go forever
        error_reporting(~E_WARNING); 
        if ( file_exists($this->cookies) ) { unlink($this->cookies); } //delete cookie file if it exists        
        # create mscrape model if one doesn't already exist
        $mscrape = Mango::factory('mscrape', array('name' => $this->scrape, 'state' => $this->state))->load();
        if (!$mscrape->loaded())
        {
            $mscrape = Mango::factory('mscrape', array('name' => $this->scrape, 'state' => $this->state))->create();
        }
        # create report
        //$this->report = Mango::factory('report', array('scrape' => $this->scrape,'successful' => 0,'failed' => 0,'new_charges' => 0,'total' => 0,'bad_images' => 0,'exists' => 0,'other' => 0,'start_time' => $this->getTime(),'stop_time' => null,'time_taken' => null,'week' => $this->find_week(time()),'year' => date('Y'),'finished' => 0))->create();
    }
    
    function print_r2($val)
    {
        echo '<pre>';
        print_r($val);
        echo  '</pre>';
    } 
   
    /**
    * scrape - main scrape function makes the curl calls and sends details to the extraction function
    *
    * @return true - on completed scrape
    * @return false - on failed scrape
    */
    function scrape() 
    {
        $home = $this->curl_home(); // get to the initial index page
		$check1 = preg_match('/VIEWSTATE\"\s.*value\=\"(.*)"/Uis', $home,  $match1);
		$check2 = preg_match('/EVENTVALIDATION\"\s.*value\=\"(.*)"/Uis', $home,  $match2);
		if ($check1 === 1 AND $check2 === 1)
		{
			$this->vs = $match1[1];
			$this->ev = $match2[1];
			$this->fn = '_';
			$this->ln = '_';
			$index = $this->curl_index();
			//HREF=ChargesInter.aspx?id=100174106&bo=130655166>
			$check = preg_match_all('/href\=ChargesInter\.aspx\?(.*)\>/Uis', $index, $matches);
			if ($check)
			{
				$offender_links = $matches[1];
				$offender_links = array_reverse($offender_links);
				foreach ($offender_links as $offender_link)
				{
					
					$details = $this->curl_details($offender_link);
					$extraction = $this->extraction($details, $offender_link);
                    /*
                    if ($extraction == 100) { $this->report->successful = ($this->report->successful + 1); $this->report->update(); }
                    if ($extraction == 101) { $this->report->other = ($this->report->other + 1); $this->report->update(); }
                    if ($extraction == 102) { $this->report->bad_images = ($this->report->bad_images + 1); $this->report->update(); }
                    if ($extraction == 103) { $this->report->exists = ($this->report->exists + 1); $this->report->update(); }
                    if ($extraction == 104) { $this->report->new_charges = ($this->report->new_charges + 1); $this->report->update(); }
                    $this->report->total = ($this->report->total + 1); $this->report->update();
					 */ 
                }
			}

/*
	        $this->report->failed = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
	        $this->report->finished = 1;
	        $this->report->stop_time = time();
	        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
	        $this->report->update();
 */ 
	        return true; 
        }  
    }

   /**
    * curl_home - gets the home page (usually just to set a cookie)
    * 
    * @url http://www.bernco.gov/inmate-info-2778/
    * 
    */
    function curl_home()
    {
    	$url = 'http://app.bernco.gov/custodylist/CustodySearchInter.aspx';
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $index = curl_exec($ch);
        curl_close($ch);
        return $index;
    } 
    
	/**
    * curl_index - curl to the index page and send post data
    * 
    * @url http://app.bernco.gov/custodylist/CustodySearchInter.aspx?submitted=true
    * 
    */
    function curl_index()
    {
    	$post = '__LASTFOCUS=&__VIEWSTATE='.urlencode($this->vs).'&__EVENTTARGET=&__EVENTARGUMENT=&__VIEWSTATEENCRYPTED=&__EVENTVALIDATION='.urlencode($this->ev).'&Lname='.$this->ln.'&Fname='.$this->fn.'&btnSubmit=Submit';
        $url = 'http://app.bernco.gov/custodylist/CustodySearchInter.aspx';
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
    * curl_details - gets the details page based on a booking_id and a bo
    * 
    * @url http://app.bernco.gov/custodylist/ChargesInter.aspx?id=100174106&bo=130655166
    *   
    */
    function curl_details($offender_link)
    {
        $url = 'http://app.bernco.gov/custodylist/ChargesInter.aspx?'.$offender_link;
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
    function extraction($details, $offender_link)
    {
    	$details = preg_replace('/\s\s+/', ' ', $details);
		// id=100174106&bo=130655166 
		$broken_string = explode('=', $offender_link);
        $booking_id = 'bernco_' . $broken_string[2];
        // database validation 
        $offender = Mango::factory('offender', array(
            'booking_id' => $booking_id
        ))->load(); 
        // validate against the database
        if (empty($offender->booking_id)) 
        {
        	/*
			 Name: 
                <span id="DataList1_ctl00_NAME"><b>ABBEY, ANICA </b>
			*/
			$check = preg_match('/Arrival\sDate\:.*\<b\>(.*)\<\/b\>/Uis', $details, $match);
			if ($check)
			{
				$booking_date = strtotime($match[1]);
				$check = preg_match('/Name\:.*\<b\>(.*)\<\/b\>/Uis', $details, $match);
				if ($check)
				{
					$explode = explode(',', $match[1]);	
					$lastname = strtoupper(trim($explode[0]));
					$explode = explode(' ', trim($explode[1]));
					if (isset($explode[1]))
					{
						$middlename = strtoupper(trim($explode[1]));
					}
					$firstname = strtoupper(trim($explode[0]));
					//echo $first_name . ' ' . @$middle_name . ' ' . $last_name; 
					// get charges
					$check = preg_match_all('/\>Description\:.*\<b\>(.*)\<\/b\>/Uis', $details, $matches);
					$charges = array();
					if ($check)
					{
						array_shift($matches[1]);
						$charges = $matches[1];
					}
					if (empty($charges))
					{
						// Get the warrent descriptions
						$check = preg_match_all('/Warrant\_Comment\".*\>\<b\>(.*)\<\/b\>\</Uis', $details, $matches);
						if ($check)
						{
							$charges = $matches[1];
						}
					}
					if ( ! empty($charges))
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
						
						# validate 
						if (empty($ncharges)) // skip the offender if ANY new charges were found
						{
							$fcharges = $charges;	
							# make it unique and reset keys
							$dbcharges = $fcharges;
							# begin image extraction
							$extra_fields = array();
							$check = preg_match('/DOB\:.*\<b\>(.*)\<\/b\>/Uis', $details, $match);
							if ($check)
							{
								$extra_fields['dob'] = strtotime($match[1]);	
							}
							$check = preg_match('/Age\:.*\<b\>(.*)\<\/b\>/Uis', $details, $match);
							if ($check)
							{
								$extra_fields['age'] = $match[1];
							}
							$check = preg_match('/Sex\:.*\<b\>(.*)\<\/b\>/Uis', $details, $match);
							if ($check)
							{
								$extra_fields['gender'] = strtoupper(trim($match[1]));
							}		
							$check = preg_match('/Race\:.*\<b\>(.*)\<\/b\>/Uis', $details, $match);
							if ($check)
							{
								$extra_fields['race'] = $this->race_mapper($match[1]);	
							} 
							// Inmate_Image.aspx?PersonID='100174106'&amp;ImageID=521461
							
							$check = preg_match('/(Inmate_Image\.aspx\?PersonID\=.*)\"/Uis', $details, $match);
							if ($check)
							{
								$image_link = str_replace('\'', '%27', 'http://app.bernco.gov/custodylist/' . $match[1]);
								$image_link = str_replace('&#39;', '%27', $image_link);
								$image_link = str_replace('&amp;', '&', $image_link);
								//$image_link = 'http://app.bernco.gov/custodylist/' . $match[1];
								# set image name
								$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
								# set image path
						        $imagepath = '/mugs/newmexico/bernco/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
						        # create mugpath
						        $mugpath = $this->set_mugpath($imagepath);
								//@todo find a way to identify extension before setting ->imageSource
								// lets do this one manually to bypass security
								
								$headers = array(
									'Host: app.bernco.gov',
									'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:9.0.1) Gecko/20100101 Firefox/9.0.1',
									'Accept: image/png,image/*;q=0.8,*/*;q=0.5',
									'Accept-Language: en-us,en;q=0.5',
									'Accept-Encoding: gzip, deflate',
									'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
									'Connection: keep-alive',
									'Referer: http://app.bernco.gov/custodylist/ChargesInter.aspx?' . $offender_link,
									'Pragma: no-cache',
									'Cache-Control: no-cache',
									//'Cookie: 	__utma=251114773.79551429.1327099748.1327628330.1327701548.4; __utmz=251114773.1327099748.1.1.utmcsr=google|utmccn=(organic)|utmcmd=organic|utmctr=(not%20provided)',
								);
								//$string = 'http://app.bernco.gov/custodylist/Inmate_Image.aspx?PersonID=%27100073100%27&ImageID=516884';
								
								$ch = curl_init($image_link);
								$fp = fopen($imagepath.$imagename.'.jpg', 'wb');
								curl_setopt($ch, CURLOPT_FILE, $fp);
								curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
						        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
						        //curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
								curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
   	 							//curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
								curl_exec($ch);
								curl_close($ch);
								fclose($fp);
								if (file_exists($imagepath.$imagename.'.jpg')) //validate the image was downloaded
								{
									#@TODO make validation for a placeholder here probably
									# ok I got the image now I need to do my conversions
							        # convert image to png.
							        try
							        {
							        	$this->convertImage($mugpath.$imagename.'.jpg');	
							        } 
							        catch(Kohana_Exception $e)
									{
										unlink($mugpath.$imagename.'.jpg');
										return 101;
									}
							        $imgpath = $mugpath.$imagename.'.png';
									try
							        {
							        	$img = Image::factory($imgpath);
				                		$img->crop(400, 480)->save();
							        } 
							        catch(Kohana_Exception $e)
									{
										unlink($mugpath.$imagename.'.jpg');
										return 101;
									}
							        $imgpath = $mugpath.$imagename.'.png';
									//$check = $this->face_crop($imgpath);										
									# now run through charge logic
									$chargeCount = count($fcharges);
									# run through charge logic	
									$mcharges 	= array(); // reset the array
							        if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
							        {
							        	//// !!!!!!!!!!!HACK!!!!!!!!!!!!!!!
							        	$fcharges = array($fcharges[0], $fcharges[1]);
										
							            //$mcharges 	= $this->charges_prioritizer($list, $fcharges);
										//if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in johnson scrape', "******Debug Me****** \n-=" . $firstname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
							            $mcharges 	= array_merge($fcharges);   
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
						                	'state'			=> $this->state,
						                   	'county'		=> strtolower($this->county),
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
								} else { return 102; } // get failed
							} else { return 102; } // image link not found
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
				} else { return 101; }
			} else { return 101; }
        } else { return 103; } // database validation failed
    } // end extraction
} // class end