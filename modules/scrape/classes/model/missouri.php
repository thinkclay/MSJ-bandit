<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Misouri
 *
 * @package Scrape
 * @author Winter King
 * @url http://www.mshp.dps.mo.gov/CJ38/searchRegistry.jsp
 */
class Model_Missouri extends Model_Scrape
{
    private $scrape     = 'missouri';
    private $state      = 'missouri';
    private $cookies    = '/tmp/missouri_cookies.txt';
    
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
        $home = $this->curl_home(); // just to set a cookie
        //<option value="Adair">Adair</option>
        
        $check = preg_match_all('/<select\sname\=\"searchCounty\"[^>].*\<\/select/Uis', $home, $matches);
        if ($check)
        {
            $select = $matches[0][0];
            //<option value="Adair">Adair</option>
            
            $check = preg_match_all('/value\=\"(.*)\"/Uis', $select, $matches);
            $counties = array();
            foreach($matches[1] as $key => $value)
            {
                $value = trim($value);
                if (!empty($value))
                {
                    $counties[] = $value;
                }
            }
            foreach ($counties as $county)
            {
            	$county = strtolower($county);
                $index = $this->curl_index($county);
                //<table id="resultTable"
                //<a href="/CJ38/Photo?
                $check = preg_match_all('/\<a\shref\=\"(\/[a-z1-9]*\/Photo\?.*)\"/Uis', $index, $match);
                if ($check)
                {
                    $detail_links = array();
                    foreach($match[1] as $value)
                    {
                        $detail_link = preg_replace('/photo/Uis', 'OffenderDetails', $value);
                        $detail_link = 'www.mshp.dps.mo.gov' . $detail_link;
                        $detail_links[] = $detail_link;
                    }
                    
                }
                foreach($detail_links as $detail_link)
                {
                    //echo $detail_link;
                    $check = preg_match('/id\=([0-9]*)\&/Uis', $detail_link, $match);
                    if ($check)
                    {
                        $booking_id = trim($match[1]);
						$firstname = preg_match('/firstName\=(.*)\&/Uis',$detail_link,$match);
                        $firstname = trim(strtoupper($match[1]));
                        $lastname = preg_match('/lastName\=(.*)\&/Uis',$detail_link,$match);
						$lastname = trim(strtoupper($match[1]));
						$middlename = preg_match('/middleName\=(.*)$/Uis',$detail_link,$match);
						$middlename = trim(strtoupper($match[1]));
                        $details = $this->curl_details($detail_link);
                        $extraction = $this->extraction($details, $detail_link, $booking_id, $firstname, $lastname, $middlename, $county);
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
        } 
    }
    
    
    /**
    * curl_offenses - gets the offenses page
    * 
    * 
    * 
    */
    function curl_offenses($url)
    {
     	$url = preg_replace('/offenderdetails/Uis','Offense',$url);
		$url = preg_replace("/\&amp\;/", '&', $url);
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		sleep(5);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $offenses = curl_exec($ch);
        curl_close($ch);
        return $offenses;
    }
    
    /**
    * curl_home - gets the search page (possibly unnecessary)
    * 
    * @url http://www.accesskansas.org/kbi/offender_registry/
    * 
    */
    function curl_home()
    {
        $url = 'http://www.mshp.dps.mo.gov/CJ38/searchRegistry.jsp';
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
    * curl_index - gets the index based on a search parameter
    * 
    * @url http://www.mshp.dps.mo.gov/CJ38/Search
    *   
    */
    function curl_index($county)
    { 
        $url = 'http://www.mshp.dps.mo.gov/CJ38/Search';
        $fields = 'searchLast=&searchFirst=&searchMonth=0&searchDay=0&searchYear=Year&searchAddressType=+&searchStreet=&searchCity=&searchCounty='.$county.'&searchZip=';
        
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $index = curl_exec($ch);
        curl_close($ch);
        return $index;
    } 
      
    /**
    * curl_details - gets the details page based on a booking_id
    * 
    * @url http://www.accesskansas.org/kbi/offender_registry/?id=
    *   
    */
    function curl_details($detail_link)
    {
        $url = $detail_link;
        $url = preg_replace("/amp;/", '&', $url);
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
    function extraction($details, $detail_link, $booking_id, $firstname, $lastname, $middlename, $county)
    {
        $booking_id = 'missouri_' . $booking_id;
        # database validation 
        $offender = Mango::factory('offender', array(
            'booking_id' => $booking_id
        ))->load(); 
        # validate against the database
        if (!$offender->loaded()) 
        {
			
			$offensepage = $this->curl_offenses($detail_link);
			//echo $offensepage;									
			$check = preg_match_all('/\<span\sid\=\"offensetable\:offenseoutput\"\sclass\=\"outputtext\"\>.*\>(.*)\</Uis', $offensepage, $matches);
			$charges = array();
			foreach ($matches[1] as $value)
			{
				$charges[] = trim($value);
			}
			if (!empty($charges))
			{

				$check = preg_match('/date\:.*\<\/td\>.*\<td.*\>(.*)\&/Uis', $details, $match);
				if ($check)
				{
					$booking_date = trim(strtotime($match[1]));

		       		# extract profile details
		        	# required fields
		            # get extra fields
		            $extra_fields = array();
		            # get charges table and booking_date
					$check = preg_match('/solid\;\"\>.*height\:.*\<td.*\>(.*)\&/Uis', $details, $match);
					if ($check)
					{
						$extra_fields['height'] = $this->height_conversion($match[1]);	
					}
					$check = preg_match('/solid\;\"\>.*\sweight\:.*\<\/td\>.*\<td.*\>(.*)lbs/Uis', $details, $match);
					if ($check)
					{
						$extra_fields['weight'] = trim($match[1]);	
					}
					$check = preg_match('/eye\scolor\:\<\/td\>.*\>(.*)\&/Uis', $details, $match);
					if ($check)
					{
						$extra_fields['eye_color'] = trim(strtoupper($match[1]));
					}
					$check = preg_match('/hair\scolor\:\<\/td\>.*\>(.*)\&/Uis', $details, $match);
					if ($check)
					{
						$extra_fields['hair_color'] = trim(strtoupper($match[1]));
					}
					$check = preg_match('/race\:.*\<\/td\>.*\>(.*)\&/Uis', $details, $match);
					if ($check)
					{
						$extra_fields['race'] = trim($this->race_mapper($match[1]));
					}
					
					$check = preg_match('/gender\:.*\<\/td\>.*\>(.*)\</Uis', $details, $match);
					if ($check)
					{
						$extra_fields['gender'] = trim($this->gender_mapper($match[1]));
					}
					$check = preg_match('/solid\;\"\>.*street\:.*\<\/td\>.*<.*\>(.*)\&/Uis', $details, $match);
					if($check)
					{
						$street = $match[1];
						$check = preg_match('/solid\;\"\>.*city\:.*\<\/td\>.*<.*\>(.*)\&/Uis', $details, $match);
						if($check)
						{
							$city = $match[1];
							$extra_fields['address'] = trim(strtoupper($street) .', ' . strtoupper($city));
						}
					}
		            if (!empty($charges))
		            {
		            	$check = Mango::factory('charge', array('county' => $this->scrape))->load();
						if(!$check->loaded())
						{
							$new_charge = Mango::factory('charge')->create();
							$new_charge->charge = 'test';
							$new_charge->abbr = 'test';
							$new_charge->county = $this->scrape;
							$new_charge->new = 0;
							$new_charge->order = 0;
							$new_charge->update();
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
		                    # begin image extraction
		                    //http://www.accesskansas.org/kbi/offender_registry/oimgs/4cb984820ab386dafb0e4a7ac98b7e790dd308fd.jpg    
		                    $check = preg_match('/sorphotos\/(.*).jpg/Uis', $details, $match);
		                    if ($check)
		                    {
		                        $image_link = 'http://www.mshp.dps.mo.gov/MSHPWeb/sorphotos/'.$match[1].'.jpg';
		                        # set image name
		                        $imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
		                        # set image path
		                        
		                        $county_path = $this->set_county_path($this->state, $county);
		                        
		                        $imagepath = '/mugs/missouri/' .$county. '/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
		                        # create mugpath
		                        $mugpath = $this->set_mugpath_test($imagepath);
		                        //@todo find a way to identify extension before setting ->imageSource
		                        $this->imageSource    = $image_link;
		                        $this->save_to        = $imagepath.$imagename;
		                        $this->set_extension  = true;
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
		                            
                            		if(strlen($fcharges[0]) >= 15)
									{
										$charges = $this->charge_cropper($fcharges[0], 400, 15);
										if(!$charges)
										{
											$fcharges = $charges;
										}
										else
										{
											//$charges = $this->charges_abbreviator($list, $charges[0], $charges[1]); 
                                        	$this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
											$offender = Mango::factory('offender', 
                                            array(
                                                'scrape'        => $this->scrape,
                                                'state'         => strtolower($this->state),
                                                'county'        => $county,
                                                'firstname'     => $firstname,
                                                'lastname'      => $lastname,
                                                'booking_id'    => $booking_id,
                                                'booking_date'  => $booking_date,
                                                'scrape_time'   => time(),
                                                'image'         => $imgpath,
                                                'charges'       => $charges,                                      
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
											
										}
									} 
		                            $chargeCount = count($fcharges);
		                            # run through charge logic  
		                            $mcharges   = array(); // reset the array
		                            if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
		                            {
		                                $mcharges   = $this->charges_prioritizer($list, $fcharges);
		                                if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in kbi scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
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
		                                    'state'         => $this->state,
		                                    'county'        => $county,
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
		                                
		                        } else { return 102; } // get failed 
		                    } else { return 102; } // no image link found
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
										$charge->abbr   = $value;
		                                $charge->order  = (int)0;
		                                $charge->county = $this->scrape;
		                                $charge->scrape = $this->scrape;
		                                $charge->new    = (int)0;
		                                $charge->update();
		                            }   
		                        }
		                    }
		                    return 104; 
		                } // ncharges validation   */
		            } else { return 101; } // no charges found at all  
	            } else { return 101; } //no charges found   
	        } else { return 103; } // database validation failed
        } else { return 101; } //no charge date found
    } // end extraction
} // class end