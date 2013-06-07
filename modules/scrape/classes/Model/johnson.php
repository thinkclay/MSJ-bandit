<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Johnson
 *
 * @TODO fix this to support bottom table extraction
 * @package Scrape
 * @author Winter King
 * @url http://www.jocosheriff.org/br/
 */
class Model_Johnson extends Model_Scrape 
{
	private $scrape 	= 'johnson';
	private $state		= 'kansas';
	private $county 	= 'johnson';
    private $cookies 	= '/tmp/cookies/johnson_cookies.txt';
	
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
		$index  = $this->curl_home();

		$check = preg_match('/\<IFRAME.*https\:\/\/(.*)\"/Uis', $index, $match);
		if ( ! $check)
			return false;
		$url = 'https://'.$match[1];
		$iframe = $this->curl_handler($url);
		$check = preg_match('/VIEWSTATE.*value\=\"(.*)\"/Uis', $iframe, $match);
		if ( ! $check)
			return false;
		$vs = $match[1];
		$check = preg_match('/EVENTVALIDATION.*value\=\"(.*)\"/Uis', $iframe, $match);
		if ( ! $check)
			return false;
		$ev = $match[1];
		foreach ($this->alphabet as $a1)
		{
			foreach ($this->alphabet as $a2)
			{
				if ( file_exists($this->cookies) ) { unlink($this->cookies); } //delete cookie file if it exists
				$index = $this->curl_index($ev, $vs, $a1.$a2);
				$check = preg_match('/NOTHING\sFOUND/Uis', $index, $match);
				if ($check)
					continue;		
				$check = preg_match_all('/details\.aspx\?cfn\=(.*)\'/Uis', $index, $matches);
				foreach ($matches[1] as $match)
				{
					$details = $this->curl_details($match);
					$extraction = $this->extraction($details);
					if ($extraction == 600) { $this->report->other = ($this->report->other + 1); $this->report->update(); break; }
					if ($extraction == 100) { $this->report->successful = ($this->report->successful + 1); $this->report->update(); }
		            if ($extraction == 101) { $this->report->other = ($this->report->other + 1); $this->report->update(); }
		            if ($extraction == 102) { $this->report->bad_images = ($this->report->bad_images + 1); $this->report->update(); }
		            if ($extraction == 103) { $this->report->exists = ($this->report->exists + 1); $this->report->update(); }
		            if ($extraction == 104) { $this->report->new_charges = ($this->report->new_charges + 1); $this->report->update(); }
		            $this->report->total = ($this->report->total + 1); $this->report->update();
				}
				
			}
		}	
		$this->report->failed = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
		$this->report->finished = 1;
        $this->report->stop_time = time();
        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
		
        $this->report->update();
		$this->print_r2($this->report->as_array());
        return true;
	}
	
	/**
	 * Handler curl
	 * 
	 * 
	 */
	function curl_handler($url)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		$result = curl_exec($ch);
		curl_close($ch);
		return $result;
	}
	
	/**
	* curl_index - gets the index of current day
	*
	* @url http://www.jocosheriff.org/br/
	*   
	*/
	function curl_home()
	{
		//$url = 'proxy.qwizzle.us?scrape=johnson&url='.urlencode('www.jocosheriff.org/br/');
		$url = 'www.jocosheriff.org/index.aspx?page=57';		
		$ch = curl_init();   
		//curl_setopt($ch, CURLOPT_PROXY, "http://184.154.132.82"); 
		//curl_setopt($ch, CURLOPT_PROXYPORT, 8080);
		//$post = 'url=www.test.com'; 
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		//curl_setopt($ch, CURLOPT_POST, true);
		//curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		//curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$index = curl_exec($ch);
		curl_close($ch);
		return $index;
	}
	
	/**
	 * Details curl handler
	 * 
	 */
	function curl_details($params)
	{
		
		$url = 'https://secure.jocosheriff.org/is/details.aspx?'.$params;		
		$ch = curl_init();   
		//$post = 'url=www.test.com'; 
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		//curl_setopt($ch, CURLOPT_POST, true);
		//curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		//curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$response = curl_exec($ch);
		curl_close($ch);
		sleep(5);
		return $response;	
	}
	
	/** 
	 * 
	 * 
	 * 
	 */
	function curl_index($ev, $vs, $ln)
	{
		$url = 	'https://secure.jocosheriff.org/is/index.aspx';
		$ch = curl_init();  
		$post = '__LASTFOCUS=&__VIEWSTATE='.urlencode($vs).'&__EVENTTARGET=&__EVENTARGUMENT=&__EVENTVALIDATION='.urlencode($ev).'&txtSearchLname='.$ln.'&txtsearchfname=&btnSearch=Search&SearchType=rbInCustody';
		$text = '__LASTFOCUS=&__VIEWSTATE=%2FwEPDwUKMTI5NTk1NzI5MA9kFgICAw9kFgICDQ8WAh4EVGV4dAWsATxUQUJMRSBBTElHTj0nTEVGVCcgV0lEVEg9JzEwMCUnIEJPUkRFUj0nMScgU1RZTEU9J0ZPTlQtRkFNSUxZOiBBUklBTCBOQVJST1c7Jz48VEQgQUxJR049J0NFTlRFUic%2BPEZPTlQgU0laRT0nMlBUJyBDT0xPUj0nI0ZGMDAwMCc%2BTk9USElORyBGT1VORDwvRk9OVD48L1REPjwvVEFCTEU%2BPC9UQUJMRT5kGAEFHl9fQ29udHJvbHNSZXF1aXJlUG9zdEJhY2tLZXlfXxYDBQtyYkluQ3VzdG9keQUMcmJSZWxIaXN0b3J5BQxyYlJlbEhpc3RvcnnNPeSW%2Bd%2Bg7kLHeKaieuAku3XHqg%3D%3D&__EVENTTARGET=&__EVENTARGUMENT=&__EVENTVALIDATION=%2FwEWBwKh4NyKDALtyrSeAgLtypyQCQKln%2FPuCgKgt7D9CgLlz63yDgK%2F1Ju8Cvqh36MJFSr6F3xwUUyzo1xA2VN4&txtSearchLname=aa&txtsearchfname=&btnSearch=Search&SearchType=rbInCustody';
		//curl_setopt($ch, CURLOPT_PROXY, "http://184.154.132.82"); 
		//curl_setopt($ch, CURLOPT_PROXYPORT, 8080);
		//$post = 'url=www.test.com'; 
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		curl_setopt($ch, CURLOPT_REFERER, 'secure.jocosheriff.org');
		//curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$index = curl_exec($ch);
		curl_close($ch);
		return $index;
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
		$check = preg_match('/CRIMINAL\sFILE\sNUMBER\:.*\<b\>(.*)\<\/b\>/Uis', $details, $match);
		var_dump($check);
		exit;
		if ( ! $check)
			return 101;
        $booking_id = $this->scrape . '_' . $match[1]; // set the booking_id to <scrapename>_<booking_id>
        // attempt to load the offender by booking_id
        $offender = Mango::factory('offender', array(
            'booking_id' => $booking_id
        ))->load(); 
        // if they are not loaded then continue with extraction, otherwise skip this offender
        if ( $offender->loaded() )
        	return 103; // database validation failed
    	// get first and lastnames
    	$check = preg_match('/NAME\:.*\<b\>(.*)\<\/b\>/Uis', $details, $match);
		if ( ! $check)
			return 101;
		$fullname = $this->clean_string_utf8($match[1]);
		$explode = explode(' ', $fullname);
		$firstname = $explode[0];
		$lastname = $explode[(count($explode) - 1)];
		// Format for weird characters
		// get booking date
		$check = preg_match('/BOOKING\sDATE\:\s(.*)\</Uis', $details, $match);
		if ( ! $check)
			return 101;
		// make sure to strtotime the booking date to get a unix timestamp
		$booking_date = strtotime($match[1]);
		if ($booking_date > strtotime('midnight', strtotime("+1 day")))
			return 101;
		
		// Rip out the table
		//<TABLE BORDER='1' WIDTH='100%' BGCOLOR='#F8F7F3' CELLPADDING='0' CELLSPACING='0' STYLE='FONT-FAMILY: ARIAL NARROW;' >
		$check = preg_match('/\<TABLE\sBORDER\=\'1\'\sWIDTH\=\'100\%\'\sBGCOLOR\=\'\#F8F7F3\'\sCELLPADDING\=\'0\'\sCELLSPACING\=\'0\'\sSTYLE\=\'FONT\-FAMILY\:\sARIAL\sNARROW\;\'\s>(.*)<\/TABLE\>/Uis', $details, $match);
		//echo $match[1];
		$check = preg_match_all('/<TR>.*<\/TR>/Uis', $match[1], $matches);
		unset($matches[0][0]);
		$charges = array();
		foreach ($matches[0] as $row)
		{
			$check = preg_match_all('/\<FONT.*>(.*)<\/FONT\>/Uis', $row, $matches);
			if ( ! $check)
			{	
				return 101;
			}
			$charge = $this->clean_string_utf8($matches[1][0]);
			$charge = str_replace('&NBSP;', '', $charge);
			$charges[] = $charge;
		}
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
		if ( ! empty($ncharges)) // skip the offender if ANY new charges were found
		{
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
		}
		
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
		$check = preg_match('/DOB\:.*\<B\>(.*)\<\/B\>/Uis', $details, $match);
		if ($check)
		{
			$extra_fields['dob'] = strtotime(str_replace('-', '/', $match[1]));
		}
		if ($extra_fields['dob'])
		{
			$extra_fields['age'] = floor(($booking_date - $extra_fields['dob']) / 31536000);	
		}
		$check = preg_match('/SEX\:.*\<B\>(.*)\<\/B\>/Uis', $details, $match);
		if ($check)
		{
			if ($match[1] == 'M')
			{
				$gender = 'MALE';
			}
			else if ($match[1] == 'F')
			{
				$gender = 'FEMALE';
			}
			$extra_fields['gender'] = $gender;	
		}
		$check = preg_match('/RACE\:.*\<B\>(.*)\<\/B\>/Uis', $details, $match);
		if ($check)
		{
			// this will map race names to our standard format for races
			// ie. African American becomes Black, 
			$extra_fields['race'] = $this->race_mapper($match[1]);	
		} 
		
	    $image_link = 'https://secure.jocosheriff.org/br/getfile.aspx?which=1';
		# set image name
		$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
		# set image path
		// normally this will be set to our specific directory structure
		// but I don't want testing images to pollute our production folders
		$imagepath = '/mugs/kansas/johnson/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
		$purgatory = '/mugs/kansas/johnson/purgatory/last-image';
        // $imagepath = '/mugs/'.$this->state.'/'.$this->county'/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
        # create mugpath
        $mugpath = $this->set_mugpath($imagepath);
		//@todo find a way to identify extension before setting ->imageSource
		$this->imageSource    = $image_link;
        $this->save_to        = $imagepath.$imagename;
        $this->set_extension  = true;
		$this->cookie		  = $this->cookies;
        $this->download('curl');
		if ( ! file_exists($imagepath.$imagename.'.jpg')) //validate the image was downloaded
		{
			return 102; 	
		}
		if (file_exists($purgatory . '.jpg'))
		{
			if (filesize($this->save_to.'.jpg') == filesize($purgatory . '.jpg'))
			{
				// This is bad, really bad.
				unlink($this->save_to.'.jpg');
				return 600;			
			}	
		}
		//echo "<br />";
		// download it to purgatory to check against last one
		$this->imageSource    = $image_link;
        $this->save_to        = $purgatory;
        $this->set_extension  = true;
		$this->cookie		  = $this->cookies;
        $this->download('curl');
		# ok I got the image now I need to do my conversions
        # convert image to png.
        $this->convertImage($mugpath.$imagename.'.jpg');
        $imgpath = $mugpath.$imagename.'.png';
		$img = Image::factory($imgpath);
    	// crop it if needed, keep in mind mug_stamp function also crops the image
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
	}	### END EXTRACTION ###
} // class end