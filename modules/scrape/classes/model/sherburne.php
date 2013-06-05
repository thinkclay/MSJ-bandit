<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Sherburne
 *
 * @package Scrape
 * @author 	
 * @url 	http://www.clickcomplete.com/SherburneJail/default.cfm/PID=1.3
 */
class Model_Sherburne extends Model_Scrape
{
    private $scrape     = 'sherburne'; //name of scrape goes here
	private $county 	= 'sherburne'; // if it is a single county, put it here, otherwise remove this property
    private $state      = 'minnesota'; // state goes here
    private $cookies    = '/tmp/sherburne_cookies.txt'; // replace with <scrape name>_cookies.txt
    
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
    function scrape() 
    {
  		$search = $this->curl_search(strtotime('-3 days'));
		$check = preg_match_all('/href\=\"(.*)\"\sclass\=\"rowresults\"/Ui', $search, $matches); // get an array of booking_ids
		if ($check)
		{
			$links = $matches[1];
			foreach ($links as $link)
			{
				$explode = explode('?', $link);
			 	$explode = explode('&',$explode[1]);
				$explode = explode('=', $explode[count($explode) - 1]);
				$booking_id = $explode[1];
				$details = $this->curl_details($link);
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
    * curl_index
    * 
    * @url
    * 
    */
    function curl_index()
    {
    	//$post = 'firstname=a&lastname=a'; // build out the post string here if needed
        $url = 'http://www.clickcomplete.com/SherburneJail/default.cfm/PID=1.3';  // this will be the url to the index page
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		//curl_setopt($ch, CURLOPT_POST, true);
		//curl_setopt($ch, CURLOPT_POSTFIELDS, $post); // add post fields
        $index = curl_exec($ch);
        curl_close($ch);
        return $index;
    } 
      
	function curl_search($start_date, $end_date = null)
	{
		if ($end_date === null)
		{
			$end_date = time();
		}
		$post = 'searchDate='.urlencode(date('m/d/Y', $start_date)).'&searchEndDate='.urlencode(date('m/d/Y', $end_date)).'&Crit1_FieldName=JI.Name_Last&Crit1_FieldType=CHAR&Crit1_Operator=BEGINS_WITH&Crit1_Value=&Crit2_FieldName=JI.Name_First&Crit2_FieldType=CHAR&Crit2_Operator=BEGINS_WITH&Crit2_Value=&Crit7_FieldName=dbo.%5Bfn_GetAgeInYears%5D%28Date_Born%2C%2709%2F24%2F2012%27%29&Crit7_FieldType=INT&Crit7_Value_integer=Age+must+be+numeric.&Crit7_Operator=EQUAL&Crit7_Value=&Crit3_FieldName=JI.Sex&Crit3_FieldType=CHAR&Crit3_Operator=Equal&Crit3_Value=&Crit4_FieldName=JI.Race&Crit4_FieldType=CHAR&Crit4_Operator=EQUAL&Crit4_Value=&Crit5_FieldName=JCh.Felony_Misdemeanor&Crit5_FieldType=CHAR&Crit5_Operator=EQUAL&Crit5_Value=&Crit6_FieldName=JCh.Bond_Amount&Crit6_FieldType=FLOAT&Crit6_Operator=EQUAL&Crit6_Value=';
		$url = 'http://www.clickcomplete.com/SherburneJail/default.cfm?PID=1.3';
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post); // add post fields
        $index = curl_exec($ch);
        curl_close($ch);
        return $index;
	}
	  
   /**
    * curl_details
    * 
    * @url 
    *   
    */
    function curl_details($url)
    {
        $url = 'http://www.clickcomplete.com'.$url;
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
        $booking_id = $this->scrape . '_' . $booking_id; // set the booking_id to <scrapename>_<booking_id>
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
		$check = preg_match('/class\=\"desc\".*>First\sName.*\<td.*>(.*)\<\/td\>/Uis', $details, $match);
		if ( ! $check)
		{
			return 101;
		}
		$firstname = $this->clean_string_utf8(htmlspecialchars_decode(trim($match[1]), ENT_QUOTES));
		$check = preg_match('/class\=\"desc\".*>Last\sName.*\<td.*>(.*)\<\/td\>/Uis', $details, $match);
		if ( ! $check)
		{
			return 101;
		}
		$lastname = $this->clean_string_utf8(htmlspecialchars_decode(trim($match[1]), ENT_QUOTES));	
		$check = preg_match('/class\=\"desc\".*>Booking\sDate.*\<td.*>(.*)\<\/td\>/Uis', $details, $match);
		if ( ! $check)
		{
			return 101;
		}
		$booking_date = $this->clean_string_utf8(htmlspecialchars_decode(trim(strtotime($match[1]))), ENT_QUOTES);
		if ($booking_date > strtotime('midnight', strtotime("+1 day")))
		{
			return 101;
		}
		
		// get all the charges with preg_match_all funciton
		$check = preg_match('/class\=\"desc\".*>Charge\<.*\<td.*>(.*)\<\/td\>/Uis', $details, $match);
		if ( ! $check)
		{
			return 101;	
		}
		// set the charges variable
		$charges = array();
		$charges[] = $this->clean_string_utf8(htmlspecialchars_decode(trim($this->charge_trim($match[1])), ENT_QUOTES));

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
		$check = preg_match('/class\=\"desc\".*>Age\<.*\<td.*>(.*)\<\/td\>/Uis', $details, $match);
		if ($check)
		{
			$extra_fields['age'] = preg_replace("/[^0-9]/", '', $match[1]); // ditch anything that is not a
		}
		
		$check = preg_match('/class\=\"desc\".*>Gender\<.*\<td.*>(.*)\<\/td\>/Uis', $details, $match);
		if ($check)
		{
			$extra_fields['gender'] = $this->clean_string_utf8(htmlspecialchars_decode(strtoupper(trim($match[1])), ENT_QUOTES));
		}
		$check = preg_match('/class\=\"desc\".*>Race\<.*\<td.*>(.*)\<\/td\>/Uis', $details, $match);
		if ($check)
		{
			// this will map race names to our standard format for races
			// ie. African American becomes Black, 
			$extra_fields['race'] = $this->race_mapper($match[1]);	
		} 
		$check = preg_match('/class\=\"desc\".*>Eye\sColor\<.*\<td.*>(.*)\<\/td\>/Uis', $details, $match);
		if ($check)
		{
			// this will map race names to our standard format for races
			// ie. African American becomes Black, 
			
			$extra_fields['eye_color'] =   $this->clean_string_utf8(htmlspecialchars_decode(strtoupper(trim($match[1])), ENT_QUOTES));
		}
		$check = preg_match('/class\=\"desc\".*>Address\<.*\<td.*>(.*)\<\/td\>/Uis', $details, $match);
		if ($check)
		{
			$extra_fields['address'] = preg_replace("'\s+'", ' ', $this->clean_string_utf8(htmlspecialchars_decode(strtoupper(trim($match[1])), ENT_QUOTES)));
		} 
		$check = preg_match('/class\=\"desc\".*>Middle\sName\<.*\<td.*>(.*)\<\/td\>/Uis', $details, $match);
		if ($check)
		{
			$extra_fields['middlename'] =  $this->clean_string_utf8(htmlspecialchars_decode(strtoupper(trim($match[1])), ENT_QUOTES));
		} 
		
		// now get the image link and download it
		$check = preg_match('/jmsimages\/(.*)\"/Uis', $details, $match);
		if ( ! $check)
		{
			return 102; // Image link not found
		}
		$image_link = 'http://www.clickcomplete.com/SherburneJail/jmsimages/' . $match[1];
		# set image name
		$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
		# set image path
		// normally this will be set to our specific directory structure
		// but I don't want testing images to pollute our production folders
		$imagepath = '/mugs/minnesota/sherburne/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
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
		# ok I got the image now I need to do my conversions
        # convert image to png.
        $this->convertImage($mugpath.$imagename.'.jpg');
        $imgpath = $mugpath.$imagename.'.png';
		$img = Image::factory($imgpath);
    	// crop it if needed, keep in mind mug_stamp function also crops the image
    	$img->crop(400, 480)->save();
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