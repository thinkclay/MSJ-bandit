<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Indianriver
 *
 * @package Scrape
 * @author 	Justin Bowers
 * @url 	http://www.ircsheriff.org
 */
class Model_Indianriver extends Model_Scrape
{
    private $scrape     = 'indianriver'; //name of scrape goes here
	private $county 	= 'indianriver'; // if it is a single county, put it here, otherwise remove this property
    private $state      = 'indianriver'; // state goes here
    private $cookies    = '/tmp/indianriver_cookies.txt'; // replace with <scrape name>_cookies.txt
    
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
    * scrape - main scrape function makes the curl calls and sends details to the extraction function
    *
    * @return true - on completed scrape
    * @return false - on failed scrape
    */
    function scrape($booking_date = null) 
    {
    	if ( ! $booking_date)
    	{
    		$booking_date = date('m/d/Y', (time()-86400));	
    	}
		$search = $this->curl_search($booking_date);		
		$check = preg_match_all('/inmate\-records\-search\/(.*)\"/Uis', $search, $matches); // get an array of booking_ids
		//$get_string='http://www.ircsheriff.org/includes/udfs/ajax.cfm?ajax_request=true&method=getInmateRecordsSearchResults&subjectName=&bookingNumber=&dateOfBirth=&bookingDate=02/17/2013&releaseDate=&';
		//$check = preg_match('/bookingDate\=([0-9]+\/[0-9]+\/[0-9]+)\&/Uis', $get_string, $matches); // get an array of booking_ids
		//echo '<pre>';
		//print_r($matches);
		//echo '</pre>';
		//exit;
		if ($check == true)
		{
			$booking_ids = $matches[1];
/*			
			foreach ($booking_ids as $key => $booking_id)
			{
				if ($key == 6)
				{
					echo 'Skipped';
					continue;
				}	
					
				echo $booking_id . '<br />';
				echo 'key: ' . $key;
				echo '<hr />';
				if ($key > 10)
				{
					echo 'FINSHED!';
					break;
				}
			}
			exit;
 */
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
		}
        $this->report->failed = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
        $this->report->finished = 1;
        $this->report->stop_time = time();
        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
        $this->report->update();
        return true; 
    }
    
	/**
    * curl_search
    * 
    * @url
    * 
    */
    function curl_search($booking_date)
    {
    	//$booking_date = '02/20/2013';
        $url = 'http://www.ircsheriff.org/includes/udfs/ajax.cfm?ajax_request=true&method=getInmateRecordsSearchResults&subjectName=&bookingNumber=&dateOfBirth=&bookingDate='.$booking_date.'&releaseDate=&';  // this will be the url to the index page
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
        curl_close($ch);
        return $response;
    } 
     
    /**
    * curl_details
    * 
    * @url 
    *   
    */
    function curl_details($booking_id)
    {
        $url = 'http://www.ircsheriff.org/community/online-tools/inmate-records-search/'.$booking_id;
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
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
		$booking_id = $this->scrape . '_' . $booking_id; // set the booking_id to <scrapename>_<booking_id>
        // attempt to load the offender by booking_id
        $offender = Mango::factory('offender', array('booking_id' => $booking_id))->load(); 
        // if they are not loaded then continue with extraction, otherwise skip this offender
        if ( $offender->loaded() ) 
        {
        	return 103; // database validation failed
		} 
    	// get first and lastnames
    	/* <h3>Subject : 
				Anderson, Donald 
				
					Eric
				
			</h3>
    	*/
		$check = preg_match('/\<h3\>Subject\s\:(.*)\</Uis', $details, $match);
		if ( ! $check)
		{
			return 101;
		}
		
		// Format for weird characters
		$fullname = $this->clean_string_utf8(htmlspecialchars_decode(trim($match[1]), ENT_QUOTES));
		$fullname = preg_replace ("/\s\s+/", " ", $match[1]);
		// usually you'll need to explode to set firstname and lastname
		// dont forget to trim and scrub any htmlspecial characters!
		$string = str_split($fullname);
		$explode = explode(',', $fullname);
		$lastname = trim($explode[0]);
		$explode2 = explode(' ', $explode[1]);
		$firstname = $explode2[1];
		//booking was created."><img src="includes/media/images/gui/icon-tooltip.gif" alt="" /></a></td>
		//<td>Sunday, February 24, 2013 at 12:16 AM (Eastern)</td>
		$check = preg_match('/booking\swas\screated.*(monday|tuesday|wednesday|thursday|friday|saturday|sunday),\s(.*)\s\(/Uis', $details, $match);
		if ( ! $check)
		{
			return 101;
		}
		// Format for weird characters
		$booking_date = $this->clean_string_utf8(htmlspecialchars_decode(trim($match[2]), ENT_QUOTES));
		$booking_date = str_replace('AT ', '' , $booking_date);
		$booking_date = strtotime($booking_date);
		if ($booking_date > strtotime('midnight', strtotime("+1 day")))
		{
			return 101;
		}
		// get all the charges with preg_match_all funciton
		$check = preg_match('/Booking\sCharges\<\/h3\>.*\<tbody\>(.*)\<\/tbody\>/Uis', $details, $matches);
		if ( ! $check)
		{
			return 101;	
		}
		$check = preg_match_all('/\<tr.*\>(.*)\<\/tr\>/Uis', $matches[1], $matches);
		if ( ! $check)
		{
			return 101;	
		}
		//$this->print_r2($matches[1][0]);
		//exit;
		// set the charges variable
		$charges = array();
		foreach ($matches[1] as $key => $value) 
		{
			$check = preg_match('/\<strong\>.*\<\/strong\>.*\<strong\>(.*)\<\/strong\>/Uis', $value, $match);
			if ( ! $check)
			{
				continue;
			}
			$charge = $this->charge_trim($match[1]);
			$charges[] = $this->clean_string_utf8(htmlspecialchars_decode(str_replace('&nbsp;', ' ', trim($match[1])), ENT_QUOTES));
		}
		//$this->print_r2($charges);
		//exit;
		
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
		/*
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
			// this will map race names to our standard format for races
			// ie. African American becomes Black, 
			$extra_fields['race'] = $this->race_mapper($match[1]);	
		} 
		*/
		// now get the image link and download it
		//http://www.ircsheriff.org/nwDocsFolder/0XN16IRZ.mug
		$check = preg_match('/nwDocsFolder\/(.*)\.mug.*subject\sphoto/Uis', $details, $match);
		if ( ! $check)
		{
			return 102; // Image link not found
			
		}
	    $image_link = 'http://www.ircsheriff.org/nwDocsFolder/' . $match[1] . '.mug';
		# set image name
		$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
		# set image path
		// normally this will be set to our specific directory structure
		// but I don't want testing images to pollute our production folders
		$imagepath = '/mugs/florida/indianriver/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
        // $imagepath = '/mugs/'.$this->state.'/'.$this->county'/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
        # create mugpath
        $mugpath = $this->set_mugpath($imagepath);
		//@todo find a way to identify extension before setting ->imageSource
		$this->imageSource    = $image_link;
        $this->save_to        = $imagepath.$imagename;
        $this->set_extension  = true;
		$this->cookie			= $this->cookies;
        $this->download('curl');
		if ( ! file_exists($imagepath.$imagename.'.jpg')) //validate the image was downloaded
		{
			return 102;
		}
		# ok I got the image now I need to do my conversions
        # convert image to png.
		try
		{
			$this->convertImage($mugpath.$imagename.'.jpg');
		} catch(ErrorException $e) {
			
		}
        $imgpath = $mugpath.$imagename.'.png';
		$img = Image::factory($imgpath);
    	// crop it if needed, keep in mind mug_stamp function also crops the image
    	
    	$img->crop(318, 476, 0)->save();
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
			### END EXTRACTION ###
    } // end extraction
} // class end