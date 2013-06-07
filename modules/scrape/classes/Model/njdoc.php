<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_njdoc - Scrape model for njdoc county new jersey
 *
 * @TODO figure out why I need to manually set the headers
 * @package Scrape
 * @author Winter King
 * @url https://www6.state.nj.us/DOC_Inmate/inmatesearch
 */
class Model_Njdoc extends Model_Scrape
{
    private $scrape     = 'njdoc';
    private $state      = 'new jersey';
    private $cookies    = '/tmp/njdoc_cookies.txt';
	private $user_agent = '	Mozilla/5.0 (Windows NT 6.1; WOW64; rv:2.0.1) Gecko/20100101 Firefox/4.0.1';
    
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
    * index_crawl 
    *
    * @params $index - timestamp of begin date
    * @return false - on fail
    */
    function index_crawl($index)
	{
		$check = preg_match_all('/details\?x\=(.*)\&amp\;n\=(.*)\"/Uis', $index, $matches);
		if ($check)
		{
			$rids = $matches[1];
			$rn   = $matches[2];
			$row_count = count($rids);
			for($i = 0; $i < $row_count; $i++)
			{
				$details = $this->curl_details($rids[$i], $rn[$i]);
				$extraction = $this->extraction($details);
				if ($extraction == 100) { $this->report->successful = ($this->report->successful + 1); $this->report->update(); }
                if ($extraction == 101) { $this->report->other = ($this->report->other + 1); $this->report->update(); }
                if ($extraction == 102) { $this->report->bad_images = ($this->report->bad_images + 1); $this->report->update(); }
                if ($extraction == 103) { $this->report->exists = ($this->report->exists + 1); $this->report->update(); }
                if ($extraction == 104) { $this->report->new_charges = ($this->report->new_charges + 1); $this->report->update(); }
                $this->report->total = ($this->report->total + 1); $this->report->update();
			}
		} else { return false; }
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
    	# jump through the hoops
    	$home = $this->curl_home();
    	$disclaimer = $this->curl_disclaimer();
		$search = $this->curl_search();
		$counties = array('ATLANTIC', 'BERGEN', 'BURLINGTON', 'CAMDEN', 'CAPE MAY', 'CUMBERLAND', 'ESSEX', 'GLOUCESTER', 'HUDSON', 'HUNTERDON', 'MERCER', 'MIDDLESEX', 'MONMOUTH', 'MORRIS', 'OCEAN', 'PASSAIC', 'SALEM', 'SOMERSET', 'SUSSEX', 'UNION', 'WARREN');
		$az = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
		$count = 0;
		foreach($az as $fn)
		{
			foreach($az as $ln)
			{
				$index = $this->curl_index($fn, $ln, 'ALL', 'ALL');
				$check = preg_match('/<title>Attention<\/title>/Uis', $index, $match);
				if ($check) // this means we got too many results
				{
					# loop through counties and redo the search
					foreach ($counties as $county)
					{
						$index = $this->curl_index($fn, $ln, 'ALL', $county);
						$this->index_crawl($index);
					}
				}
				else 
				{
					$this->index_crawl($index);
				}
				
				if ($count > 5) { exit; }
				$count++;			
			}
		}
		$this->report->failed = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
        $this->report->finished = 1;
        $this->report->stop_time = time();
        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
        $this->report->update();
    }

    //http://www.state.nj.us/corrections/pages/index.shtml
    
    /**
    * curl_home - gets the home page to set the cookie
    * 
    * @url http://www.state.nj.us/corrections/pages/index.shtml
	* 
    */
    function curl_home()
    {
        $url = 'http://www.state.nj.us/corrections/pages/index.shtml';
		$headers = array(
			'Host: www.state.nj.us', 
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: en-us,en;q=0.5',
			'Accept-Encoding: gzip, deflate',
			'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
			'Keep-Alive: 115',
			'Connection: keep-alive',
			'Cache-Control: max-age=0'
		);
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $home = curl_exec($ch);
        curl_close($ch);
        return $home;
    	
	}
    
    /**
    * curl_disclaimer - gets the search page
    * 
    * @url https://www6.state.nj.us/DOC_Inmate/inmatefinder?i=I
	* 
    */
    function curl_disclaimer()
    {
        $url = 'https://www6.state.nj.us/DOC_Inmate/inmatefinder?i=I';
        $ch = curl_init();   
		$headers = array(
			'Host: www.state.nj.us', 
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: en-us,en;q=0.5',
			'Accept-Encoding: gzip, deflate',
			'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
			'Keep-Alive: 115',
			'Connection: keep-alive',
			'Cache-Control: max-age=0'
		);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
		curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
        $disclaimer = curl_exec($ch);
        curl_close($ch);
        return $disclaimer;
    }
	
	
	/**
    * curl_search - gets the search page
    * 
    * @url https://www6.state.nj.us/DOC_Inmate/inmatesearch
	* $post accept=T&inmatesearch=Accept
    */
    function curl_search()
    {
        $url = 'https://www6.state.nj.us/DOC_Inmate/inmatesearch';
		$post = 'accept=T&inmatesearch=Accept';
        $ch = curl_init();   
		$headers = array(
			'Host: www.state.nj.us', 
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			'Accept-Language: en-us,en;q=0.5',
			'Accept-Encoding: gzip, deflate',
			'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
			'Keep-Alive: 115',
			'Connection: keep-alive',
			'Cache-Control: max-age=0'
		);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
        curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
        $search = curl_exec($ch);
        curl_close($ch);
        return $search;
    }
	
    /**
    * curl_index - gets the index based on a date
    * 
    * @url https://www6.state.nj.us/DOC_Inmate/results
	* 
    */
    function curl_index($fn, $ln, $sex = 'ALL', $county = 'ALL')
    {
    	//$county = 'ATLANTIC';
    	$cookie_file = file_get_contents('/tmp/njdoc_cookies.txt'); 
		$check = preg_match('/JSESSIONID(.*)www/Uis', $cookie_file, $match);
		if ($check)
		{
			$session_id = trim($match[1]);
			$post = 'SBI=&Last_Name='.$ln.'&First_Name='.$fn.'&Aliases=NO&Sex=ALL&Hair_Color=ALL&Eye_Color=ALL&Race=ALL&County='.$county.'&Location=ALL&bday_from_month=None&bday_from_day=None&bday_from_year=None&Age=&AgeTo=&bday_to_month=None&bday_to_day=None&bday_to_year=None&Increment=250&Submit=Submit';
	        $url = 'https://www6.state.nj.us/DOC_Inmate/results';
			$headers = array(
				'Host: www6.state.nj.us', 
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language: en-us,en;q=0.5',
				'Accept-Encoding: gzip, deflate',
				'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
				'Keep-Alive: 115',
				'Connection: keep-alive',
				'Cache-Control: max-age=0',
				'Cookie: JSESSIONID='.$session_id.'; JROUTE=WxVv',
			);
	        $ch = curl_init();   
	        curl_setopt($ch, CURLOPT_URL, $url);
	        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
	        //curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
	        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
	        $index = curl_exec($ch);
	        curl_close($ch);
	        return $index;	
		} else { return false; }
    } 
    
	  
    /**
    * curl_details - gets the details page based on a booking_id
    * 
	* 
    * @url https://www6.state.nj.us/DOC_Inmate/details?x=1017535&n=0
    *   
    */
    function curl_details($rid, $rn)
    {
    	$cookie_file=file_get_contents('/tmp/njdoc_cookies.txt'); 
		$check = preg_match('/JSESSIONID(.*)www/Uis', $cookie_file, $match);
		if ($check)
		{
			$session_id = trim($match[1]);
	        $url = 'https://www6.state.nj.us/DOC_Inmate/details?x='.$rid.'&n='.$rn;
			//$post = 'x=1060156&n=0';
	        $ch = curl_init();   
			$headers = array(
				'Host: www6.state.nj.us', 
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language: en-us,en;q=0.5',
				'Accept-Encoding: gzip, deflate',
				'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
				'Keep-Alive: 115',
				'Connection: keep-alive',
				'Cache-Control: max-age=0',
				'Referer: https://www6.state.nj.us/DOC_Inmate/results',
				'Cookie: JSESSIONID='.$session_id.'; JROUTE=WxVv',
			);
	        curl_setopt($ch, CURLOPT_URL, $url);
	        //curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
	        //curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
	        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			//curl_setopt($ch, CURLOPT_POST, true);
			//curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
	        curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
	        $details = curl_exec($ch);
	        curl_close($ch);
	        return $details;
		} else { return false; }
    }

	/**
    * curl_photo - gets the inmate photo
    * 
	* 
    * @url https://www6.state.nj.us/DOC_Inmate/photo?id=$booking_id
    *   
    */
    function curl_photo($booking_id)
    {
    	$cookie_file=file_get_contents('/tmp/njdoc_cookies.txt'); 
		$check = preg_match('/JSESSIONID(.*)www/Uis', $cookie_file, $match);
		if ($check)
		{
			$session_id = trim($match[1]);
	        $url = 'https://www6.state.nj.us/DOC_Inmate/photo?id='.$booking_id;
	        $ch = curl_init();   
			$headers = array(
				'Host: www6.state.nj.us', 
				'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				'Accept-Language: en-us,en;q=0.5',
				'Accept-Encoding: gzip, deflate',
				'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
				'Keep-Alive: 115',
				'Connection: keep-alive',
				'Cache-Control: max-age=0',
				'Referer: https://www6.state.nj.us/DOC_Inmate/results',
				'Cookie: JSESSIONID='.$session_id.'; JROUTE=WxVv',
			);
	        curl_setopt($ch, CURLOPT_URL, $url);
	        //curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
	        //curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
	        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			//curl_setopt($ch, CURLOPT_POST, true);
			//curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
	        curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent);
	        $photo = curl_exec($ch);
	        curl_close($ch);
	        return $photo;
		} else { return false; }
    }
    
    
    /**
    * extraction - validates and extracts all data
    *
    * 
    * @params $details  - offenders details page
    * @return false     - on failed extraction
    * @return true      - on successful extraction
    * 
    */
    function extraction($details)
    {
    	//echo $details;
		
    	/*
		 * SBI Number:</strong></a> </td>

        <td width="29%" bgcolor="#FFF2C1" class="standard_font">000461711D</td>
      </tr>
		 * 
		 * 
		 * 
		 */
		$check = preg_match('/SBI\sNumber.*<td[^>]*>(.*)<\/td>/Uis', $details, $match);
    	if ($check)
		{
			$booking_id = 'njdoc_' . trim($match[1]);
			# database validation 
	        $offender = Mango::factory('offender', array(
	            'booking_id' => $booking_id
	        ))->load(); 
	        # validate against the database
	        if (empty($offender->booking_id)) 
	        {
				/*
				 * 
				 * Sentenced as:</strong></a> </td>
	        <td  class="standard_font">Abdullah, Abdul&nbsp;Wal</td>
				 * 
				 * 
				 * 
				 */ 
				$check = preg_match('/Sentenced\sas.*<td[^>]*>(.*)<\/td>/Uis', $details, $match);
				if ($check)
				{
					$fullname = trim($match[1]);
					$explode = explode(',', $fullname);
					$lastname = strtoupper($explode[0]);
					$explode2 = explode('&nbsp;', trim($explode[1]));
					$firstname = strtoupper($explode2[0]);
					#get booking_date
					$check = preg_match('/Admission\sDate.*<td[^>]*>(.*)<\/td>/Uis', $details, $match);
					if ($check)
					{
						$booking_date = strtotime($match[1]);
						# get charges
						//SENTENCE
						# get charges table
	                    $this->source = $details;
	                    $this->anchor = 'Offense';
	                    $this->anchorWithin = true;
	                    $this->headerRow = false;
	                    $this->stripTags = true;
	                    $this->startRow = 3;
	                    $charges_table = $this->extractTable();
						if (!empty($charges_table))
						{
							$charges = array();
							foreach($charges_table as $row)
							{
								$chg = preg_replace('/.*\&NBSP\;\:/Uis', '', $row[1]);
								
								$charges[] = preg_replace('/\/[0-9]/is', '', $chg);
								$county = strtoupper(trim($row[4]));
							}
							if (!empty($charges))
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
	                            	$fcharges   = array();
	                                foreach($charges as $key => $value)
	                                {
	                                    $fcharges[] = trim(strtoupper($value)); 
	                                }
	                                # make it unique and reset keys
	                                $fcharges = array_unique($fcharges);
	                                $fcharges = array_merge($fcharges);
	                                $dbcharges = $fcharges;
									#get extra fields
									$extra_fields = array();	
									$check = preg_match('/Race.*<td[^>]*>(.*)<\/td>/Uis', $details, $match);
									if ($check)
									{
										$extra_fields['race'] = $this->race_mapper(trim($match[1]));
									}
									$check = preg_match('/Sex.*<td[^>]*>(.*)<\/td>/Uis', $details, $match);
									if ($check)
									{
										$extra_fields['gender'] = $this->gender_mapper(trim($match[1]));
									}
									$check = preg_match('/Hair\sColor.*<td[^>]*>(.*)<\/td>/Uis', $details, $match);
									if ($check)
									{
										$extra_fields['hair_color'] = trim(strtoupper($match[1]));
									}
									$check = preg_match('/Eye\sColor.*<td[^>]*>(.*)<\/td>/Uis', $details, $match);
									if ($check)
									{
										$extra_fields['eye_color'] = trim(strtoupper($match[1]));
									}
									$check = preg_match('/Height\:\<\/.*<td[^>]*>(.*)<\/td>/Uis', $details, $match);
									if ($check)
									{
										$extra_fields['height'] = $this->height_conversion(trim(strtoupper($match[1])));
									}
									$check = preg_match('/Weight.*<td[^>]*>(.*)<\/td>/Uis', $details, $match);
									if ($check)
									{
										$extra_fields['weight'] = preg_replace('/[\D]/', '', $match[1]);
									} 
									$check = preg_match('/Birth\sDate.*<td[^>]*>(.*)<\/td>/Uis', $details, $match);
									if ($check)
									{
										$extra_fields['dob'] = strtotime($match[1]);
									}
									
									# begin image extraction
									// https://www6.state.nj.us/DOC_Inmate/photo?id=1060156
									$check = preg_match('/photo\?id\=(.*)\"/Uis', $details, $match);
									if ($check)
									{
										$image_id = $match[1];
										$image_link = 'https://www6.state.nj.us/DOC_Inmate/photo?id='.$image_id;
		                                # set image name
		                                $imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
		                                # set image path
		                                $imagepath = '/mugs/new jersey/njdoc/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
		                                # create mugpath
		                                $mugpath = $this->set_mugpath($imagepath);
		                                //@todo find a way to identify extension before setting ->imageSource
		                                $this->imageSource    = $image_link;
		                                $this->save_to        = $imagepath.$imagename;
		                                $this->set_extension  = true;
		                                //$this->cookie         = $this->cookies;
		                                $this->download('curl');
										if (file_exists($this->save_to . '.jpg')) //validate the image was downloaded
                                        {
                                            #@TODO make validation for a placeholder here probably
                                            # ok I got the image now I need to do my conversions
                                            # convert image to png.
                                            $this->convertImage($mugpath.$imagename.'.jpg');
                                            $imgpath = $mugpath.$imagename.'.png';
                                            $img = Image::factory($imgpath);
                                            $imgpath = $mugpath.$imagename.'.png';
                                            # now run through charge logic
                                            $chargeCount = count($fcharges);
                                            # run through charge logic  
                                            $mcharges   = array(); // reset the array
                                            if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
                                            {
                                                $mcharges   = $this->charges_prioritizer($list, $fcharges);
                                                if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in njdoc scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
                                                $mcharges   = array_merge($mcharges);   
                                                $charge1    = $mcharges[0];
                                                $charge2    = $mcharges[1];    
                                                $charges    = $this->charges_abbreviator($list, $charge1, $charge2); 
                                                $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
                                            }
                                            else if ( $chargeCount == 2 )
                                            {
                                                $fcharges   = array_merge($fcharges);
                                                $charge1    = $fcharges[0];
                                                $charge2    = $fcharges[1];   
                                                $charges    = $this->charges_abbreviator($list, $charge1, $charge2);
                                                $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);           
                                            }
                                            else 
                                            {
                                                $fcharges   = array_merge($fcharges);
                                                $charge1    = $fcharges[0];    
                                                $charges    = $this->charges_abbreviator($list, $charge1);       
                                                $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0]);   
                                            }
                                            
                                            // Abbreviate FULL charge list
                                            $dbcharges = $this->charges_abbreviator_db($list, $dbcharges);
                                            $dbcharges = array_unique($dbcharges);
                                            # BOILERPLATE DATABASE INSERTS
                                            $offender = Mango::factory('offender', 
                                                array(
                                                    'scrape'        => $this->scrape,
                                                    'state'         => strtolower($this->state),
                                                    'county'        => strtolower($county),
                                                    'firstname'     => $firstname,
                                                    'lastname'      => $lastname,
                                                    'booking_id'    => $booking_id,
                                                    'booking_date'  => $booking_date,
                                                    'scrape_time'   => time(),
                                                    'image'         => $imgpath,
                                                    'charges'       => $dbcharges,                                      
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
									} else { return 102; }
								} else {
									# add new charges to the charges collection
	                                foreach ($ncharges as $key => $value)
	                                {
	                                    $value = preg_replace('/\s/', '', $value);
	                                        #check if the new charge already exists FOR THIS COUNTY
	                                    $check_charge = Mango::factory('charge', array('county' => $this->scrape, 'charge' => $value))->load();
	                                    if (!$check_charge->loaded())
	                                    {
	                                        if (!empty($value))
	                                        {
	                                            $charge = Mango::factory('charge')->create();   
	                                            $charge->charge = $value;
	                                            $charge->order = (int)0;
	                                            $charge->county = $this->scrape;
												$charge->scrape = $this->scrape;
	                                            $charge->new    = (int)1;
	                                            $charge->update();
	                                        }   
	                                    }
	                                }
	                                return 104;
								}
							} else { return 101; } // no charges
						} else { return 101; } // empty charges_table
					} else { return 101; } // no admissions date 
				} else { return 101; } // no name 
			} else { return 103; } // database validation failed
		} else { return 101; } // no booking id found
    } // end extraction
} // class end