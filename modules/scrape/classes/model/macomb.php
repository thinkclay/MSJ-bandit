<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Macomb
 *
 * @package Scrape
 * @author 	Justin Bowers
 * @url 	http://itasw0aepv01.macombcountymi.gov/jil/faces/InmateSearch.jsp
 */
class Model_Macomb extends Model_Scrape
{
    private $scrape     = 'macomb'; //name of scrape goes here
	private $county 	= 'macomb'; // if it is a single county, put it here, otherwise remove this property
    private $state      = 'macomb'; // state goes here
    private $cookies    = '/tmp/macomb_cookies.txt'; // replace with <scrape name>_cookies.txt
    private $cookies2    = '/tmp/macomb_cookies2.txt'; // replace with <scrape name>_cookies.txt
    
    public function __construct()
    {
        set_time_limit(86400); //make it go forever 
        if ( file_exists($this->cookies) ) { unlink($this->cookies); } //delete cookie file if it exists
        if ( file_exists($this->cookies2) ) { unlink($this->cookies2); } //delete cookie file if it exists            
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
    function scrape() 
    {
    	$this->curl_home();
		foreach ($this->alphabet as $letter)
		{
			$index = $this->curl_search($letter);
			//echo $index;
			$cookies0 = file_get_contents($this->cookies);
			$check = preg_match('/JSESSIONID\s(.*)\s/Uis', $cookies0, $match);
			if ( ! $check)
			{
				return false;
			}
			$jsession_cookie = $match[1];
			//$ientity = $matches[1][0];
			//$post_fullname = urlencode(trim(strtoupper(preg_replace('/\s*$/','',$matches[2][0]))));
			//$curl_details = $this->curl_details($jsession_cookie, $ientity, $post_fullname);
			$check = preg_match_all('/\'\IEntity\'\:\'(.*)\'.*\'AliasName\'\:\'(.*)\'.*BiogInmNumId\">(.*)<\/span>/Uis', $index, $matches);
			if ($check)
			{
				$ientities = $matches[1];
				$fullnames = $matches[2];
				$booking_ids = $matches[3];
				foreach ($ientities as $key => $ientity)
				{
					$this->curl_search('', $booking_ids[$key]);
					$details = $this->curl_details($jsession_cookie, $ientity, $fullnames[$key]);
					if (strpos($details, '500 Internal Server Error') !== false)
					{
						sleep(2);
						$this->curl_home();
						$this->curl_search('', $booking_ids[$key]);
						$details = $this->curl_details($jsession_cookie, $ientity, $fullnames[$key]);
					}
					$extraction = $this->extraction($details, $ientity, $fullnames[$key]);
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
        return true; 
    }
	
	/**
    * curl_home
    * 
    * @url 	http://itasw0aepv01.macombcountymi.gov/jil/faces/InmateSearch.jsp
    * 
    */
    function curl_home()
    {
    	//$post = '_id22%3AtxtLastName=aa&_id22%3AtxtFirstName=&_id22%3AtxtInmateNumber=&oracle.adf.faces.FORM=_id22&oracle.adf.faces.STATE_TOKEN=1&event=&source=_id22%3AbtnInmateSearch'; // build out the post string here if needed
        $url = 'http://itasw0aepv01.macombcountymi.gov/jil/faces/InmateSearch.jsp';  // this will be the url to the index page
        $headers = array(
        	'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 
        	'Accept-Encoding: deflate',
        	'Accept-Language: en-US,en;q=0.5',
        	'Connection: keep-alive',
        	//'DNT: 1',
        	'Host: itasw0aepv01.macombcountymi.gov',
        	'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:19.0) Gecko/20100101 Firefox/19.0',
		);
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		//curl_setopt($ch, CURLOPT_POST, true);
		//curl_setopt($ch, CURLOPT_POSTFIELDS, $post); // add post fields
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }


	/**
    * curl_list
    * 
    * @url
    * 
    */
    function curl_search($lastname = '', $inmate_num = '')
    {
    	$post = '_id22%3AtxtLastName='.$lastname.'&_id22%3AtxtFirstName=&_id22%3AtxtInmateNumber='.$inmate_num.'&oracle.adf.faces.FORM=_id22&oracle.adf.faces.STATE_TOKEN=1&event=&source=_id22%3AbtnInmateSearch'; // build out the post string here if needed
        $url = 'http://itasw0aepv01.macombcountymi.gov/jil/faces/InmateSearch.jsp';  // this will be the url to the index page
        $headers = array(
        	'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        	'Accept-Encoding: deflate',
        	'Accept-Language: en-US,en;q=0.5',
        	'Connection: keep-alive',
        	//'Cookie: JSESSIONID=cf5bd10430d55956e1a39b4c4c2296ad9eb39d0bf3ed.e38ObhaNbNePc40Lbh8Ob3aRaxyOe0; oracle.uix=0^^GMT-4:00',
        	//'DNT: 1',
        	'Host: itasw0aepv01.macombcountymi.gov',
        	'Referer: http://itasw0aepv01.macombcountymi.gov/jil/faces/InmateSearch.jsp,',
        	'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:19.0) Gecko/20100101 Firefox/19.0',
		);
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post); // add post fields
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
    //function curl_details()
    function curl_details($jsession_cookie, $ientity, $post_fullname)
	//function curl_details($booking_id, $fullname)
    {
    	$post = '_id22%3AtxtLastName=__&_id22%3AtxtFirstName=&_id22%3AtxtInmateNumber=&_id22%3A_id42%3ArangeStart=0&oracle.adf.faces.FORM=_id22&oracle.adf.faces.STATE_TOKEN=2&event=&source=_id22%3A_id42%3A0%3AcmlInmDetail&IEntity='.$ientity.'&Rownum=1&AliasName='.$post_fullname; // build out the post string here if needed
        $url = 'http://itasw0aepv01.macombcountymi.gov/jil/faces/InmateSearch.jsp';  // this will be the url to the index page
        $headers = array(
        	'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        	'Accept-Encoding: deflate',
        	'Accept-Language: en-US,en;q=0.5',
        	'Connection: keep-alive',
        	//'Cookie: JSESSIONID=cf5bd10430d55956e1a39b4c4c2296ad9eb39d0bf3ed.e38ObhaNbNePc40Lbh8Ob3aRaxyOe0; oracle.uix=0^^GMT-4:00',
        	//'DNT: 1',
        	'Host: itasw0aepv01.macombcountymi.gov',
        	'Referer: http://itasw0aepv01.macombcountymi.gov/jil/faces/InmateSearch.jsp;jsessionid='. $jsession_cookie,
        	//'Referer: http://itasw0aepv01.macombcountymi.gov/jil/faces/InmateSearch.jsp;' .$cookies2,
        	'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:19.0) Gecko/20100101 Firefox/19.0',
		);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies2);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post); // add post fields
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
    function extraction($details, $ientity, $fullname)
    {
		//InmNmbrId" class="x6" style=" color:rgb(00,00,00);">340674</span>
		$check = preg_match('/InmNmbrId\".*\>(.*)\</Uis', $details, $match);
		if ( ! $check)
		{
			return 101;
		}
        $booking_id = $this->scrape . '_' . $match[1]; // set the booking_id to <scrapename>_<booking_id>
        // attempt to load the offender by booking_id
        $offender = Mango::factory('offender', array(
            'booking_id' => $booking_id
        ))->load(); 
        // if they are not loaded then continue with extraction, otherwise skip this offender
        if ( $offender->loaded() ) 
        {
        	return 103; // database validation failed
		} 
    	// get first and lastnames
		// Format for weird characters
		$fullname = $this->clean_string_utf8(htmlspecialchars_decode(trim($fullname), ENT_QUOTES));
		// usually you'll need to explode to set firstname and lastname
		// dont forget to trim and scrub any htmlspecial characters!
		$explode = explode(' ', $fullname);
 
		$firstname = htmlspecialchars_decode(trim($explode[0]));
		$lastname = htmlspecialchars_decode(trim($explode[(count($explode)-1)]));
	
		// get booking date
		// InmIncarDtId" class="x6" style=" color:rgb(00,00,00);">12/05/2012
		$check = preg_match('/InmIncarDtId\".*\>(.*)\</Uis', $details, $match);
		if ( ! $check)
		{
			return 101; 	
		}
		// Make sure to strtotime the booking date to get a unix timestamp
		$booking_date = strtotime($match[1]);
		// Check if its in the future which would be an error
		if ($booking_date > strtotime('midnight', strtotime("+1 day")))
		{
			return 101;
		}
		$check = preg_match('/Sentenced\sCharges.*\<table.*><\/table>.*(<table.*<\/table\>)/Uis', $details, $match);
		if ( ! $check)
		{
			return 101;	
		}
		$check = preg_match_all('/<tr.*<\/tr>/Uis', $match[1], $matches);
		if ( ! $check)
		{
			return 101;	
		}
		$charges = array();
		foreach ($matches[0] as $key => $row)
		{
			if ($key == 0)
				continue;
			$check = preg_match('/SChrgId\">(.*)<\/span>/Uis', $row, $match);
			if ( ! $check)
			{
				return 101;
			}
			$charge = $this->charge_trim($match[1]);
			$charges[] = $this->clean_string_utf8(htmlspecialchars_decode(str_replace('&nbsp;', ' ', trim($charge)), ENT_QUOTES));
		}
		// the next lines between the ### are boilerplate used to check for new charges
		if ( ! $charges)
		{
			return 101;
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
		//InmGndrId" class="x6" style=" color:rgb(00,00,00);">M</span>
		$check = preg_match('/InmGndrI\".*>(.*)<\/span>/Uis', $details, $match);
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
		 
		
		// now get the image link and download it
		//http://itasw0aep002.macombcountymi.gov/jil/faces/displayimageblob?img=0L9YC6B000USJ4JA
	    $image_link = 'http://itasw0aep002.macombcountymi.gov/jil/faces/displayimageblob?img='.$ientity;
		# set image name
		$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
		# set image path
		// normally this will be set to our specific directory structure
		// but I don't want testing images to pollute our production folders
		$imagepath = '/mugs/michigan/macomb/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
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
        $this->convertImage($mugpath.$imagename.'.jpg');
        $imgpath = $mugpath.$imagename.'.png';
		$img = Image::factory($imgpath);
    	// crop it if needed, keep in mind mug_stamp function also crops the image
    	//$img->crop(400, 480)->save();
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
			if ( ! isset($fcharges[0]))
			{
				return 101;
			}
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