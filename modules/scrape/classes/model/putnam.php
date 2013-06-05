<?php defined('SYSPATH') or die('No direct script access.');
 
 
 
/**
 * Model_Putnam
 *
 * @package Scrape
 * @author Bryan Galli & Winter King
 * @url http://smartweb.pcso.us/smartwebclient/jail.aspx
 * 
 */
class Model_Putnam extends Model_Scrape
{
    private $scrape     = 'putnam';
	private $county		= 'putnam';
    private $state      = 'florida';
    private $cookies    = '/tmp/putnam_cookies.txt';
	
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
    	$this->county = 'putnam';
		$home_url = 'http://smartweb.pcso.us/smartwebclient/jail.aspx';
		$home = $this->curl_home($home_url);
        $check = preg_match('/VIEWSTATE.*value\=\"(.*)\"/Uis', $home, $match);
        $check2 = preg_match('/EVENTVALIDATION.*value\=\"(.*)\"/Uis', $home, $match2);
        if ($check && $check2)
        {
        	$this->vs = $match[1];  
            $this->ev = $match2[1]; 
			$start_date = date('m/j/Y', strtotime('-7 day', time()));
			$end_date = date('m/j/Y', time());
	        $index = $this->curl_index($home_url, $start_date, $end_date);
			$check = preg_match('/\<div\sid\=\"JailInfo\"\>.*\<\/div\>/is', $index, $match);
			//echo $match[0];
			//echo $index;
			//$tidy = new Tidy();
			$tidy_config = array(
                 'clean' => true,
                 'output-xhtml' => true,
                 'show-body-only' => true,
                 'wrap' => 0,
            );
			//$tidy = tidy_parse_string($match[0], $tidy_config, 'UTF8');
			//$tidy->cleanRepair();
			//echo $tidy;
			$xmlDoc = new DOMDocument();
			$check = @$xmlDoc->loadHTML($match[0]);
			if ($check)
			{
				$xpath = new DOMXPath($xmlDoc);
				$tr_list = $xpath->query("//table[@class='JailView']/tr");
				$details_array = array();
				$count = 0;
				foreach ($tr_list as $row => $tr) 
				{
					$details_array[$count][$row%2] = $tr;
					if ($row%2 == 1)
						$count++;	
				}
				foreach($details_array as $key => $details)
				{
					$extraction = $this->extraction($details);
					if ($extraction == 100) { $this->report->successful = ($this->report->successful + 1); $this->report->update(); }
                    if ($extraction == 101) { $this->report->other = ($this->report->other + 1); $this->report->update(); }
                    if ($extraction == 102) { $this->report->bad_images = ($this->report->bad_images + 1); $this->report->update(); }
                    if ($extraction == 103) { $this->report->exists = ($this->report->exists + 1); $this->report->update(); }
                    if ($extraction == 104) { $this->report->new_charges = ($this->report->new_charges + 1); $this->report->update(); }
                    $this->report->total = ($this->report->total + 1); $this->report->update();
				}
			} else { return 101; }
			$this->report->failed = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
	        $this->report->finished = 1;
	        $this->report->stop_time = time();
	        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
	        $this->report->update();
	        return true;
    	}
	} //end scrape function
	

	function curl_home($url)
    {
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $home = curl_exec($ch);
        curl_close($ch);
        return $home;
    } 
	
	function curl_index($url, $start_date = null, $end_date = null)
    {
    	if ($start_date == null)
			$start_date = date('m/j/Y');
		if ($end_date == null)
			$end_date = date('m/j/Y');
		$start_date = strtotime($start_date);
		$end_date = strtotime($end_date);
       	$fields  = 'ScriptManager1=ScriptManager1%7CbtnSumit&txbLastName=&txbFirstName=&txbMiddleName=&dtBeginDate_DrpPnl_Calendar1='.rawurlencode(rawurlencode('<x PostData="'.date('Y', $start_date).'x'.date('m', $start_date).'x'.date('Y', $start_date).'x'.date('m', $start_date).'x'.date('j', $start_date).'">')).'%253C%2Fx%253E&dtBeginDate_hidden='.rawurlencode(rawurlencode('<DateChooser Value="'.date('Y', $start_date).'x'.date('m', $start_date).'x'.date('j', $start_date).'">')).'%253C%2FDateChooser%253E&dtBeginDate_input='.rawurlencode(date('m/j/Y', $start_date)).'&dtEndDate_DrpPnl_Calendar1='.rawurlencode(rawurlencode('<x PostData="'.date('Y', $end_date).'x'.date('m', $end_date).'x'.date('Y', $end_date).'x'.date('m', $end_date).'x'.date('j', $end_date).'">')).'%253C%2Fx%253E&dtEndDate_hidden='.rawurlencode(rawurlencode('<DateChooser Value="'.date('Y', $end_date).'x'.date('m', $end_date).'x'.date('j', $end_date).'">')).'%253C%2FDateChooser%253E&dtEndDate_input='.rawurlencode(date('m/j/Y', $end_date)).'&TypeSearch=2&__EVENTTARGET=&__EVENTARGUMENT=&__VIEWSTATE='.rawurlencode($this->vs).'&__EVENTVALIDATION='.rawurlencode($this->ev).'&__ASYNCPOST=true&btnSumit=Submit';
        $headers = array(
			'Host: smartweb.pcso.us',
			'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:8.0) Gecko/20100101 Firefox/8.0',
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'X-Requested-With: XMLHttpRequest',
			'MicrosoftAjax: Delta=true',
			'Referer: http://smartweb.pcso.us/smartwebclient/jail.aspx',
			'Pragma: no-cache',
        );
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        $index = curl_exec($ch);
        curl_close($ch);
        return $index;
    } 
	
    
    /**
	 * 
     * extraction - validates and extracts all data
	 * @notes This one is a little wierd.  I'm working with php DOMDocument();
     *		 There are two objects here, $details[0] and $details[1]
	 * 		 0 is the offender details and 1 is the charges
	 * 
     * 
     * @params $details  - offenders details page
     * @return $ncharges - numerical array of new charges found
     * @return false     - on failed extraction
     * @return true      - on successful extraction
     * 
     */
    function extraction($details)
    {
    	foreach($details[0]->childNodes as $td)
		{
			if($td->nodeName == 'td')
			{
				//$this->print_r2($td);
				foreach($td->childNodes as $element)
				{
					if ($element->nodeName == 'img') // this is the image link
					{
						$img_link = $element->getAttribute('src');
					}
					elseif($element->nodeName == 'table')
					{
						foreach($element->childNodes as $details_body)
						{
							if ($details_body->nodeName == 'tbody')
							{
								foreach($details_body->childNodes as $details_row)
								{
									if ($details_row->nodeName == 'tr')
									{
										
										$value = $this->clean_string_utf8($details_row->childNodes->item(0)->nodeValue, true);
										//var_dump($this->clean_string_utf8($value));
										//echo '<hr />';
										switch ($value) {
											case 'BOOKING NO:':
												$booking_id = $this->clean_string_utf8($details_row->childNodes->item(2)->nodeValue, true);
												$booking_id = $this->county .'_'. str_replace(' ', '', $booking_id);
												break;
											case 'BOOKING DATE:':
												$booking_date = strtotime($this->clean_string_utf8(@$details_row->childNodes->item(2)->nodeValue, true));
											case 'AGO ON BOOKING DATE:':
												$age = $this->clean_string_utf8(@$details_row->childNodes->item(2)->nodeValue, true);
												break;
											case 'ADDRESS GIVEN:':
												$address = @$details_row->childNodes->item(2)->nodeValue;
											default:
												break;
										}
									} 
								}
							}
						}
					}
				}
			}	
		}
		$extra_fields = array();
		$extra_fields['age'] = @$age;
		$extra_fields['address'] = @$address;
		if(isset($booking_id) && isset($booking_date))
		{
			$offender = Mango::factory('offender', array(
            	'booking_id' => $booking_id
        	))->load();	
			if(!$offender->loaded())
			{
				// get first and lastname
				$header_row = $this->clean_string_utf8(@$details[0]->childNodes->item(2)->childNodes->item(1)->childNodes->item(0)->childNodes->item(0)->childNodes->item(0)->nodeValue, true);
				if(@$header_row)
				{
					$explosion = explode('(',$header_row);
					$full_name = @$explosion[0];
					$race_sex = @$explosion[1];
					$explosion = explode(',', $full_name);
					$lastname = trim(@$explosion[0]);
					$explosion = explode(' ', trim($explosion[1]));
					$firstname = $explosion[0];
					if (isset($firstname) && isset($lastname))
					{
						if (isset($race_sex))
						{
							// get race and gender
							$explode = @explode('/', $race_sex);
							$race = @$this->race_mapper(trim(@$explode[0]));
							$gender  = trim(str_replace(')', '', @$explode[1]));
							
							// get charges
							$charges = array();
							for($i = 2; $i < $details[1]->childNodes->item(0)->childNodes->item(1)->childNodes->length; $i++)
							{
								$check = $this->clean_string_utf8($details[1]->childNodes->item(0)->childNodes->item(1)->childNodes->item($i)->childNodes->item(4)->nodeValue, true);
								if ($check)
								{
									$charges[] = $check;
								} else { return 101; }
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
								if(empty($ncharges))
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
									
									
									$image_link =  'http://smartweb.pcso.us/smartwebclient/' . str_replace('ViewImage', 'ViewImageFull', $img_link);
									# set image name
									$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
									# set image path
							        $imagepath = '/mugs/florida/putnam/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
							        # create mugpath
							        $mugpath = $this->set_mugpath($imagepath);
									//@todo find a way to identify extension before setting ->imageSource
									$this->imageSource    = $image_link;
							        $this->save_to        = $imagepath.$imagename;
							        $this->set_extension  = true;
									//$this->cookie		  = $this->cookies;
							        $this->download('curl');
									if (file_exists($this->save_to . '.jpg')) //validate the image was downloaded
                                    {
                                    	$this->convertImage($mugpath.$imagename.'.jpg');
                                        $imgpath = $mugpath.$imagename.'.png';
										# now run through charge logic
										$chargeCount = count($fcharges);
										# run through charge logic
								        if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
								        {
								            $mcharges 	= $this->charges_prioritizer($list, $fcharges);
											if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in wyandotee scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
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
										$dbcharges = array_unique($dbcharges); // make unique
										$dbcharges = array_merge($dbcharges); // reset keys
										# BOILERPLATE DATABASE INSERTS
										$offender = Mango::factory('offender', 
							                array(
							                	'scrape'		=> $this->scrape,
							                	'state'			=> strtolower($this->state),
							                	'county'		=> $this->county,
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
									} else { return 102; }
								} else {
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
                                                $charge->new    = (int)0;
                                                $charge->update();
                                            }   
                                       	}
                                 	}
                                    return 104; 
                                }
							} else { return 101; } // no charges found
						} else { return 101; }
					} else { return 101; }
				} else { return 101; }
			} else { return 103; } //offender already in DB
		} else { return 101; }
	}
}