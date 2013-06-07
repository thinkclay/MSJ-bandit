<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Volusia
 *
 * @package Scrape
 * @author 	Winter King 
 * @url 	http://www.volusiamug.vcgov.org/search.cfm
 */
class Model_Volusia extends Model_Scrape
{
    private $scrape     = 'volusia'; //name of scrape goes here
	private $county 	= 'volusia'; // if it is a single county, put it here, otherwise remove this property
    private $state      = 'florida'; // state goes here
    private $cookies    = '/tmp/volusia_cookies.txt'; // replace with <scrape name>_cookies.txt
    
    public function __construct()
    {
        set_time_limit(86400); //make it go forever 
        //if ( file_exists($this->cookies) ) { unlink($this->cookies); } // Delete cookie file if it exists        
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
	* Scrape - main scrape function makes the curl calls and sends details to the extraction function
	*
	* @return true - on completed scrape
	* @return false - on failed scrape
	*/
    function scrape() 
    {
    	$bid_start = null;
		//echo $this->find_week(time());
		//@TODO Fix this because there i 
		$last_week = '/mugs/florida/volusia/'.date('Y').'/week_'.($this->find_week(time()) - 1).'/';
		$files = scandir($last_week);
		$old_booking_ids = array();
		foreach ($files as $file)
		{
			$check = preg_match('/\_([0-9]*)\./Uis', $file, $match);
			if ($check)
			{
				$old_booking_ids[] = $match[1];
			}
		}
		$booking_id = 890705;
		$stop_id = $booking_id + 10000;
		$flag = false;
		while ($flag == false)
		{
			$details = $this->curl_search('', '', '', '', $booking_id);
			if (strpos($details, '<form action="search.cfm" method="post">') === false)
			{
				$extraction = $this->extraction($details);
				if ($extraction == 100) { $this->report->successful = ($this->report->successful + 1); $this->report->update(); }
	            if ($extraction == 101) { $this->report->other = ($this->report->other + 1); $this->report->update(); }
	            if ($extraction == 102) { $this->report->bad_images = ($this->report->bad_images + 1); $this->report->update(); }
	            if ($extraction == 103) { $this->report->exists = ($this->report->exists + 1); $this->report->update(); }
	            if ($extraction == 104) { $this->report->new_charges = ($this->report->new_charges + 1); $this->report->update(); }
	            $this->report->total = ($this->report->total + 1); $this->report->update();
				$referer = 'http://www.volusiamug.vcgov.org/display.cfm?eventnumber='.$booking_id;
				$search = $this->curl_go_back($referer);
			} 
			$booking_id++;
			if ($booking_id >= $stop_id)
			{
				$flag = true;
			}
		}
		/*
    	$races = array('I', 'A', 'B', 'U', 'W');
		$sexes = array('F', 'M', 'U');
		$booking_ids = array();
		foreach ($this->alphabet as $fn)
		{
			foreach ($this->alphabet as $ln)
			{
				foreach ($races as $race)
				{
					foreach ($sexes as $sex)
					{
						$search = $this->curl_search($fn, $ln, $race, $sex);
						
						if (strpos($search, 'try again') === false)
						{
							if (strpos($search, 'Booking Number') !== false) // this means they sent us directly to a details page
							{
								// Run Jirans regex and pull out booking number
								$extraction = $this->extraction($search);
								if ($extraction == 100) { $this->report->successful = ($this->report->successful + 1); $this->report->update(); }
					            if ($extraction == 101) { $this->report->other = ($this->report->other + 1); $this->report->update(); }
					            if ($extraction == 102) { $this->report->bad_images = ($this->report->bad_images + 1); $this->report->update(); }
					            if ($extraction == 103) { $this->report->exists = ($this->report->exists + 1); $this->report->update(); }
					            if ($extraction == 104) { $this->report->new_charges = ($this->report->new_charges + 1); $this->report->update(); }
					            $this->report->total = ($this->report->total + 1); $this->report->update();
							}
							else // I have an index page
							{
								$pages = array();
								$flag = true;
								$check = preg_match('/\<select.*\<\/select/Uis', $search, $match);
								if ($check)
								{
									$check = preg_match_all('/\<option\svalue\=\"(.*)\"/Uis', $match[0], $matches);
									foreach ($matches[1] as $page)
									{
										$pages[] = $page;
									}
								}
								foreach ($pages as $key => $page)
								{
									
									if ($key > 0)
									{
										$search = $this->curl_page($page);
									}
									$check = preg_match_all('/eventnumber=(.*)\"/Uis', $search, $matches);
									if ($check)
									{
										foreach ($matches[1] as $booking_id)
										{
											$details = $this->curl_details($booking_id);
											$extraction = $this->extraction($details);
											if ($extraction == 100) { $this->report->successful = ($this->report->successful + 1); $this->report->update(); }
								            if ($extraction == 101) { $this->report->other = ($this->report->other + 1); $this->report->update(); }
								            if ($extraction == 102) { $this->report->bad_images = ($this->report->bad_images + 1); $this->report->update(); }
								            if ($extraction == 103) { $this->report->exists = ($this->report->exists + 1); $this->report->update(); }
								            if ($extraction == 104) { $this->report->new_charges = ($this->report->new_charges + 1); $this->report->update(); }
								            $this->report->total = ($this->report->total + 1); $this->report->update();
											$referer = 'http://www.volusiamug.vcgov.org/display.cfm?eventnumber='.$booking_id;
											$search = $this->curl_go_back($referer);
										}
									}
									else 
									{
										continue;
									}
								}
							}
						}
					}
				}
			} 
		}
		 */ 
        $this->report->failed = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
        $this->report->finished = 1;
        $this->report->stop_time = time();
        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
        $this->report->update();
        return true; 
    }
    
	function curl_search($fn = '', $ln = '', $race = '', $sex = '', $booking_id = '')
	{
		$url = 'http://www.volusiamug.vcgov.org/search.cfm';
		$post = 'firstname='.$fn.'&lastname='.$ln.'&bookno='.$booking_id.'&race='.$race.'&sex='.$sex.'&doblow=&dobhigh=&orderby=last_name%2C+first_name&search=Search';
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post); // add post fields
        $search = curl_exec($ch);
        curl_close($ch);
		return $search;
	}
	
	function curl_go_back($referer)
	{
		$url = 'http://www.volusiamug.vcgov.org/results.cfm';
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		curl_setopt($ch, CURLOPT_REFERER, $referer);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $search = curl_exec($ch);
        curl_close($ch);
		return $search;
	}
	function curl_page($page_num)
	{
		$url = 'http://www.volusiamug.vcgov.org/results.cfm';
		$post = 'gotopage1='.$page_num.'&go=+Go%21+';
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post); // add post fields
        $search = curl_exec($ch);
        curl_close($ch);
		return $search;
	}
	
      
    /**
    * curl_details
    * 
    * @url 
    *   
    */
    function curl_details($booking_id)
    {
        $url = 'http://www.volusiamug.vcgov.org/display.cfm?eventnumber='.$booking_id;
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
    function extraction($details)
    {
    	try
    	{
	    	$formatted_details = preg_replace("'\s+'", ' ', $details);
	    	$check = preg_match('/Booking\sNumber.*\<td\>(.*)\<\/td\>/Uis', $details, $match);
			if ( ! $check)
			{
				return 101;
			}
	        $booking_id = $this->scrape . '_' . $match[1]; // set the booking_id to <scrapename>_<booking_id>
	      	
			// Attempt to load the offender by booking_id
	        $offender = Mango::factory('offender', array(
	            'booking_id' => $booking_id
	        ))->load();
	        // If they are not loaded then continue with extraction, otherwise skip this offender
	        if ( ! $offender->loaded() ) 
	        {
	        	// get first and lastnames
				$check = preg_match('/First\sName.*\<td\>(.*)\<\/td\>/Uis', $details, $match);
				if ( ! $check)
				{
					return 101;
				}
				$firstname = trim(strtoupper($match[1]));
				$check = preg_match('/Last\sName.*\<td\>(.*)\<\/td\>/Uis', $details, $match);
				if ( ! $check)
				{
					return 101;
				}
				$lastname = trim(strtoupper($match[1]));
				
				$check = preg_match('/Book\sdate.*\<td\>(.*)\<\/td\>/Uis', $details, $match);
				if ( ! $check)
				{
					return 101;
				}
				$booking_date = $this->clean_string_utf8(htmlspecialchars_decode(str_replace('&nbsp;', ' ', trim(strtotime($match[1]))), ENT_QUOTES));
				if ($booking_date > strtotime('midnight', strtotime("+1 day")))
				{
					return 101;
				}
				
				$check = preg_match('/\<td\>\s(\<table\sclass\=\"default\-charges.*\<\/table\>)/Uis', $formatted_details, $match);
				$explode = explode('<tr>', $match[1]);
				if (count($explode) < 3)
				{
					return 101;
				}
				$charges = array();
				foreach ($explode as $key => $value)
				{
					if ($key > 1)
					{
						$check = preg_match('/\<center\>(.*)\<\/center\>/Uis', $value, $match);
						if ($check)
						{
							$charges[] = $this->clean_string_utf8(htmlspecialchars_decode(str_replace('&nbsp;', ' ', trim($match[1])), ENT_QUOTES));
						}
					}
				}
				// the next lines between the ### are boilerplate used to check for new charges
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
					// make unique and reset keys
					$charges = array_unique($charges);
					$charges = array_merge($charges);
					$fcharges = array();
					// trim, uppercase and scrub htmlspecialcharacters
					foreach($charges as $charge)
					{
						$fcharges[] = htmlspecialchars_decode(strtoupper(trim($charge)), ENT_QUOTES);
					}	
					$dbcharges = $fcharges;
					// now clear an $extra_fields variable and start setting all extra fields
					$extra_fields = array();
					$check = preg_match('/Date\sof\sBirth.*\<td\>(.*)\<\/td\>/Uis', $formatted_details, $match);
					if ($check)
					{
						$extra_fields['dob'] = strtotime($match[1]);
					}
					$dob = strtotime($match[1]);
					$check = preg_match('/Sex.*\<td\>(.*)\<\/td\>/Uis', $formatted_details, $match);
					if ($check)
					{
						if ($match[1] == 'M')
						{
							$extra_fields['gender'] = 'MALE';
						}
						else if ($match[1] == 'F')
						{
							$extra_fields['gender'] = 'FEMALE';
						}
					}
					$check = preg_match('/Race.*\<td\>(.*)\<\/td\>/Uis', $formatted_details, $match);
					if ($check)
					{
						$race = $this->race_mapper($match[1]);
						if ($race)
						{
							$extra_fields['race'] = $race;
						}
					}
					// Get the image link and download it\
					// <img width="360" src="/pictures/44/1.vol.827344.001">
					$check = preg_match('/\<img.*src\=\"(.*)\"/Uis', $formatted_details, $match);
					if ( ! $check)
					{
						return 102;
					}
					$image_link = 'www.volusiamug.vcgov.org' . $match[1];
					# set image name
					$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
					# set image path
					// normally this will be set to our specific directory structure
					// but I don't want testing images to pollute our production folders
					$imagepath = '/mugs/florida/volusia/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
			        # create mugpath
			        $mugpath = $this->set_mugpath($imagepath);
					//@todo find a way to identify extension before setting ->imageSource
					$this->imageSource    = $image_link;
			        $this->save_to        = $imagepath.$imagename;
			        $this->set_extension  = true;
					//$this->cookie		  = $this->cookies;
			        $this->download('curl');
			        if ( ! file_exists($imagepath.$imagename.'.jpg')) //validate the image was downloaded
					{
						return 102;
					}
					# ok I got the image now I need to do my conversions
			        # convert image to png.
			        $this->convertImage($mugpath.$imagename.'.jpg');
			        $imgpath = $mugpath.$imagename.'.png';
					// get a count
					$chargeCount = count($fcharges);
					// run through charge logic	
					// this is all boilerplate
					$mcharges 	= array(); // reset the array
			        if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
			        {
			        	$mcharges 	= $this->charges_prioritizer($list, $fcharges);
						if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in marion scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
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
		                	'state'			=> $this->state,
		                   	'county'		=> strtolower($this->county), // this may differ on sites with multiple counties
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
					if ( ! $mscrape->loaded() )
					{
						$mscrape = Mango::factory('mscrape', array('name' => $this->scrape, 'state' => $this->state))->create();
					}
					$mscrape->booking_ids[] = $booking_id;
					$mscrape->update();	 
	                # END DATABASE INSERTS
	                return 100;
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
	        } else { return 103; } // database validation failed
        } 
    	catch(Exception $e)
		{
			return 101;	
		}
    } // end extraction
} // class end