<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Dakota
 *
 * @package Scrape
 * @author 	
 * @url 	http://www.co.dakota.mn.us/LawJustice/Jail/Pages/inmate-search.aspx
 */
class Model_State_Minnesota_Dakota extends Model_Scrape
{
    private $scrape     = 'dakota'; // Name of scrape goes here
	private $county 	= 'dakota'; // if it is a single county, put it here, otherwise remove this property
    private $state      = 'minnesota'; // state goes here
    private $cookies    = '/tmp/template_cookies.txt'; // replace with <scrape name>_cookies.txt
    
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
		$index = $this->curl_index();
		$check = preg_match('/VIEWSTATE.*value\=\"(.*)\"/Uis', $index, $match);
		if ( ! $check)
			return false;
		$this->vs = $match[1];
		$check = preg_match('/EVENTVALIDATION.*value\=\"(.*)\"/Uis', $index, $match);
		if ( ! $check)
			return false;
		$this->ev = $match[1];
		$check = preg_match('/REQUESTDIGEST.*value\=\"(.*)\"/Uis', $index, $match);
		if ( ! $check)
			return false;
		$this->rd = $match[1];
		$search = $this->curl_search();
		//sleep(5);
		$check = preg_match_all('/\<a\sid\=\"(.*)\"/Uis', $search, $matches);
		
		if ($check) {
			$booking_links = $matches[1];
			$count = 2;
			foreach ($booking_links as $booking_link) {
				$details = $this->curl_details($count);
				$extraction = $this->extraction($details);
                if ($extraction == 100) { $this->report->successful = ($this->report->successful + 1); $this->report->update(); }
                if ($extraction == 101) { $this->report->other = ($this->report->other + 1); $this->report->update(); }
                if ($extraction == 102) { $this->report->bad_images = ($this->report->bad_images + 1); $this->report->update(); }
                if ($extraction == 103) { $this->report->exists = ($this->report->exists + 1); $this->report->update(); }
                if ($extraction == 104) { $this->report->new_charges = ($this->report->new_charges + 1); $this->report->update(); }
                $this->report->total = ($this->report->total + 1); $this->report->update();
				$this->curl_back();
				if ($count > 30)
				{
					break;
				}
				$count++;
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
    * curl_index
    * 
    * @url http://services.co.dakota.mn.us/InmateSearch/
    * 
    */
    function curl_index()
    {
        $url = 'http://www.co.dakota.mn.us/LawJustice/Jail/Pages/inmate-search.aspx';  // this will be the url to the index page
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
      
	function curl_search()
	{
		$post = 'ctl00%24ScriptManager=ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24updpnlMain%7Cctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24btnShrowAll&MSOWebPartPage_PostbackSource=&MSOTlPn_SelectedWpId=&MSOTlPn_View=0&MSOTlPn_ShowSettings=False&MSOGallery_SelectedLibrary=&MSOGallery_FilterString=&MSOTlPn_Button=none&__EVENTTARGET=ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24grdInmates&__EVENTARGUMENT=Sort%24NewBookingDate&__REQUESTDIGEST='.rawurlencode($this->rd).'&MSOSPWebPartManager_DisplayModeName=Browse&MSOSPWebPartManager_ExitingDesignMode=false&MSOWebPartPage_Shared=&MSOLayout_LayoutChanges=&MSOLayout_InDesignMode=&_wpSelected=&_wzSelected=&MSOSPWebPartManager_OldDisplayModeName=Browse&MSOSPWebPartManager_StartWebPartEditingName=false&MSOSPWebPartManager_EndWebPartEditing=false&__VIEWSTATE='.rawurlencode($this->vs).'&__EVENTVALIDATION='.rawurlencode($this->ev).'&InputKeywords=Search...&ctl00%24PlaceHolderSearchArea%24ctl00%24ctl03=0&ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24txtLastName=&ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24txtFirstName=&ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24calFromDate%24calFromDateDate=&ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24calToDate%24calToDateDate=&ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24calCourtDate%24calCourtDateDate=&__spDummyText1=&__spDummyText2=&_wpcmWpid=&wpcmVal=&__ASYNCPOST=true&ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24btnShowAll=Show%20All%20In%20Custody';
		$url = 'http://www.co.dakota.mn.us/LawJustice/Jail/Pages/inmate-search.aspx';  // this will be the url to the index page
        $ch = curl_init();   
		$headers = array(
			'Accept text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Encoding gzip, deflate',
			'Accept-Language en-US,en;q=0.5',
			'Cache-Control no-cache',
			'Connection keep-alive',
			'Content-Length 5366',
			'Content-Type application/x-www-form-urlencoded; charset=utf-8',
			'Host www.co.dakota.mn.us',
			//'Referer http://www.co.dakota.mn.us/LawJustice/Jail/Pages/inmate-search.aspx',
			//'User-Agent Mozilla/5.0 (Windows NT 6.1; WOW64; rv:16.0) Gecko/20100101 Firefox/16.0',
			'X-MicrosoftAjax Delta=true',
		);
        curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:16.0) Gecko/20100101 Firefox/16.0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		//curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post); // add post fields
        $index = curl_exec($ch);
        curl_close($ch);
		 $check = preg_match('/VIEWSTATE\|(.*)\|/Uis', $index, $match);
		if ( ! $check)
			return false;
		$this->vs = $match[1];
		$check = preg_match('/EVENTVALIDATION\|(.*)\|/Uis', $index, $match);
		if ( ! $check)
			return false;
		$this->ev = $match[1];
		$post = 'ctl00%24ScriptManager=ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24updpnlMain%7Cctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24grdInmates&MSOWebPartPage_PostbackSource=&MSOTlPn_SelectedWpId=&MSOTlPn_View=0&MSOTlPn_ShowSettings=False&MSOGallery_SelectedLibrary=&MSOGallery_FilterString=&MSOTlPn_Button=none&__REQUESTDIGEST='.rawurlencode($this->rd).'&MSOSPWebPartManager_DisplayModeName=Browse&MSOSPWebPartManager_ExitingDesignMode=false&MSOWebPartPage_Shared=&MSOLayout_LayoutChanges=&MSOLayout_InDesignMode=&MSOSPWebPartManager_OldDisplayModeName=Browse&MSOSPWebPartManager_StartWebPartEditingName=false&MSOSPWebPartManager_EndWebPartEditing=false&InputKeywords=Search...&ctl00%24PlaceHolderSearchArea%24ctl00%24ctl03=0&ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24txtLastName=&ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24txtFirstName=&ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24calFromDate%24calFromDateDate=&ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24calToDate%24calToDateDate=&ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24calCourtDate%24calCourtDateDate=&__spDummyText1=&__spDummyText2=&_wpcmWpid=&wpcmVal=&__EVENTTARGET=ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24grdInmates&__EVENTARGUMENT=Sort%24NewBookingDate&__VIEWSTATE='.rawurlencode($this->vs).'&__EVENTVALIDATION='.rawurlencode($this->ev).'&_wpSelected=&_wzSelected=&__ASYNCPOST=true&';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:16.0) Gecko/20100101 Firefox/16.0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		//curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post); // add post fields
        $index = curl_exec($ch);
        curl_close($ch);
		$check = preg_match('/VIEWSTATE\|(.*)\|/Uis', $index, $match);
		if ( ! $check)
			return false;
		$this->vs = $match[1];
		$check = preg_match('/EVENTVALIDATION\|(.*)\|/Uis', $index, $match);
		if ( ! $check)
			return false;
		$this->ev = $match[1];
        return $index;
	}
	  
	/**
	 * curl_details
     * 
     * @url 
     *   
     */
	function curl_details($count)
    {
    	if ($count < 10)
		{
			$count = (string)('0' . $count);
		}
        $url = 'http://www.co.dakota.mn.us/LawJustice/Jail/Pages/inmate-search.aspx';  
        $post = 'ctl00%24ScriptManager=ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24updpnlMain%7Cctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24grdInmates%24ctl'.$count.'%24DetailsLink&MSOWebPartPage_PostbackSource=&MSOTlPn_SelectedWpId=&MSOTlPn_View=0&MSOTlPn_ShowSettings=False&MSOGallery_SelectedLibrary=&MSOGallery_FilterString=&MSOTlPn_Button=none&__REQUESTDIGEST='.rawurlencode($this->rd).'&MSOSPWebPartManager_DisplayModeName=Browse&MSOSPWebPartManager_ExitingDesignMode=false&MSOWebPartPage_Shared=&MSOLayout_LayoutChanges=&MSOLayout_InDesignMode=&MSOSPWebPartManager_OldDisplayModeName=Browse&MSOSPWebPartManager_StartWebPartEditingName=false&MSOSPWebPartManager_EndWebPartEditing=false&InputKeywords=Search...&ctl00%24PlaceHolderSearchArea%24ctl00%24ctl03=0&ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24txtLastName=&ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24txtFirstName=&ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24calFromDate%24calFromDateDate=&ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24calToDate%24calToDateDate=&ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24calCourtDate%24calCourtDateDate=&__spDummyText1=&__spDummyText2=&_wpcmWpid=&wpcmVal=&__EVENTTARGET=ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24grdInmates%24ctl'.$count.'%24DetailsLink&__EVENTARGUMENT=&__VIEWSTATE='.rawurlencode($this->vs).'&__EVENTVALIDATION='.rawurlencode($this->ev).'&_wpSelected=&_wzSelected=&__ASYNCPOST=true&';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:16.0) Gecko/20100101 Firefox/16.0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		//curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post); // add post fields
        $details = curl_exec($ch);
        curl_close($ch);
		$check = preg_match('/VIEWSTATE\|(.*)\|/Uis', $details, $match);
		if ( ! $check)
			return false;
		$this->vs = $match[1];
		$check = preg_match('/EVENTVALIDATION\|(.*)\|/Uis', $details, $match);
		if ( ! $check)
			return false;
		$this->ev = $match[1];
        return $details;
    }

	/**
    * curl_index
    * 
    * @url http://services.co.dakota.mn.us/InmateSearch/
    * 
    */
    function curl_back()
    {
        $url = 'http://www.co.dakota.mn.us/LawJustice/Jail/Pages/inmate-search.aspx';  // this will be the url to the index page
        $post = 'ctl00%24ScriptManager=ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24updpnlMain%7Cctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24lnkToggleDetails&MSOWebPartPage_PostbackSource=&MSOTlPn_SelectedWpId=&MSOTlPn_View=0&MSOTlPn_ShowSettings=False&MSOGallery_SelectedLibrary=&MSOGallery_FilterString=&MSOTlPn_Button=none&__REQUESTDIGEST='.rawurlencode($this->rd).'&MSOSPWebPartManager_DisplayModeName=Browse&MSOSPWebPartManager_ExitingDesignMode=false&MSOWebPartPage_Shared=&MSOLayout_LayoutChanges=&MSOLayout_InDesignMode=&MSOSPWebPartManager_OldDisplayModeName=Browse&MSOSPWebPartManager_StartWebPartEditingName=false&MSOSPWebPartManager_EndWebPartEditing=false&InputKeywords=Search...&ctl00%24PlaceHolderSearchArea%24ctl00%24ctl03=0&__spDummyText1=&__spDummyText2=&_wpcmWpid=&wpcmVal=&__EVENTTARGET=ctl00%24SPWebPartManager1%24g_d98fa490_cff5_4019_b4b4_ed55faf4ac97%24lnkToggleDetails&__EVENTARGUMENT=&__VIEWSTATE='.rawurlencode($this->vs).'&__EVENTVALIDATION='.rawurlencode($this->ev).'&_wpSelected=&_wzSelected=&__ASYNCPOST=true&';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:16.0) Gecko/20100101 Firefox/16.0');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post); // add post fields
        $result = curl_exec($ch);
        curl_close($ch);
		$check = preg_match('/VIEWSTATE\|(.*)\|/Uis', $result, $match);
		if ( ! $check)
			return false;
		$this->vs = $match[1];
		$check = preg_match('/EVENTVALIDATION\|(.*)\|/Uis', $result, $match);
		if ( ! $check)
			return false;
		$this->ev = $match[1];
        return $result;
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
		$check = preg_match('/BookingNumber\"\>(.*)\</Uis', $details, $match);
		if ( ! $check) {
			echo 'Error: Bad Booking number <br />';
			return 101;
		}
		$booking_id = $this->scrape . '_' . $match[1]; // set the booking_id to <scrapename>_<booking_id>
		$check = preg_match('/Name\"\>(.*)\</Uis', $details, $match);
		if ( ! $check) {
			echo 'Error: Bad name <br />';
			return 101;
		}
		$fullname = $match[1];
		$explode = explode(',', $fullname);
		$lastname = strtoupper(trim($explode[0]));
		$explode = explode(' ', trim($explode[1]));
		$firstname = strtoupper($explode[0]);
        // attempt to load the offender by booking_id
        $offender = Mango::factory('offender', array(
            'booking_id' => $booking_id
        ))->load();
        // if they are not loaded then continue with extraction, otherwise skip this offender
        if ( $offender->loaded() ) {
			echo 'Already in the database. Skipping... <br />';
        	return 103; // database validation failed
    	}
		// get booking date
		$check = preg_match('/BookingDate\"\>(.*)\</Uis', $details, $match);
		if ( ! $check) {
			echo 'Error: Bad booking date <br />';
			return 101;
		}
		// Make sure to strtotime the booking date to get a unix timestamp
		$booking_date = strtotime($match[1]);
		// Check if its in the future which would be an error
		if ($booking_date > strtotime('midnight', strtotime("+1 day"))) {
			echo 'Error: Booking Date in the future <br />';
			return 101;
		}
		// get all the charges with preg_match_all funciton
		$check = preg_match_all('/Charge\:.*text\"\>(.*)\</Uis', $details, $matches);
		if ( ! $check) {
			echo 'Error: Charge not found <br />';
			return 101;
		}
		// set the charges variable
		$charges = array();
		foreach ($matches[1] as $charge)
		{
			// Run this function to cut down charges that are too long
			$charge = $this->charge_trim($charge);
			$charges[] = $this->clean_string_utf8(htmlspecialchars_decode(str_replace('&nbsp;', ' ', trim($charge)), ENT_QUOTES));
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
			echo 'New charge found for this offender. Skipping...';
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
		$check = preg_match('/Dob\"\>(.*)\</Uis', $details, $match);
		if ($check)
		{
			$extra_fields['dob'] = strtotime($match[1]);	
		}
		if ($extra_fields['dob'])
		{
			$extra_fields['age'] = floor(($booking_date - $extra_fields['dob']) / 31536000);	
		}
		$check = preg_match('/lblSex\"\>(.*)\</Uis', $details, $match);
		if ($check)
		{
			$gender = strtoupper(trim($match[1]));
			if ($gender == 'M')
			{
				$extra_fields['gender'] = 'MALE';	
			}
			else if ($gender == 'F')
			{
				$extra_fields['gender'] = 'FEMALE';
			}
		}
		$check = preg_match('/lblRace\"\>(.*)\</Uis', $details, $match);
		if ($check)
		{
			// this will map race names to our standard format for races
			// ie. African American becomes Black, 
			$race = $this->race_mapper($match[1]);
			if (trim($race))
			{
				$extra_fields['race'] = $race;
			}	
		} 
		// Now get the image link and download it
		
		$check = preg_match('/ashx\?PhotoId\=(.*)\"/Uis', $details, $match);
		
		if ( ! $check)
		{
			echo 'Error: Image not found. Line 402 - name: ' . $fullname . ' <br />';
			return 102; // Image link not found
		}
	    $image_link = 'http://www.co.dakota.mn.us/_layouts/CustomHandlers/PCI_Image.ashx?PhotoId='.$match[1];
		# set image name
		$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
		# set image path
		// normally this will be set to our specific directory structure
		// but I don't want testing images to pollute our production folders
		$imagepath = '/mugs/minnesota/dakota/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
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
			echo 'Error: Image not found. Line 414 <br />';
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
				echo 'Error: Image stamp could not be set. Line 441 <br />';
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
				echo 'Error: Image stamp could not be set. Line 455 <br />';
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
				echo 'Error: Image stamp could not be set. Line 470 <br />';
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
		// Add extra fields
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