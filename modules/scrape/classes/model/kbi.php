<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_kbi
 *
 * @package Scrape
 * @author Winter King
 * @url http://www.accesskansas.org/kbi/offender_registry/
 */
class Model_Kbi extends Model_Scrape
{
    private $scrape     = 'kbi';
    private $state      = 'kansas';
    private $cookies    = '/tmp/kbi_cookies.txt';
    
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
        $az = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
        foreach ($az as $ln)
        {
            $page = 1;
            $pages = null;
            $index   = $this->curl_index($ln);
            # set the $pages variable
            $check = preg_match('/<h4>Page\s[0-9]*\sof\s([0-9]*)<\/h4>/Uis', $index, $match);
            if ($check) { $pages = $match[1]; }
            else { $pages = 1; }
            # start pagination loop
            for ($i = 1; $i <= $pages; $i++)
            {
                if ($page > 1)
                {
                    $index = $this->curl_page($page);
                }
                # build booking_ids and extraction
                $check = preg_match_all('/oid\:\"(.*)\"/Uis', $index, $match);
                if ($check)
                {
                    $booking_ids = $match[1];
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
                $page++;
            } // end for loop
        } // end a-z loop
        $this->report->failed = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
        $this->report->finished = 1;
        $this->report->stop_time = time();
        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
        $this->report->update();
        return true;   
    }
    
    
    /**
    * curl_page - gets the index of current day
    * 
    * @url http://www.accesskansas.org/kbi/offender_registry/?page=results&pageID=2
    * 
    */
    function curl_page($page)
    {
        $url = 'http://www.accesskansas.org/kbi/offender_registry/?page=results&pageID='.$page;
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
    * curl_home - gets the search page (possibly unnecessary)
    * 
    * @url http://www.accesskansas.org/kbi/offender_registry/
    * 
    */
    function curl_home()
    {
        $url = 'http://www.accesskansas.org/kbi/offender_registry/';
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
    * @url http://www.accesskansas.org/kbi/offender_registry/
    *   
    */
    function curl_index($ln)
    { 
        $url = 'http://www.accesskansas.org/kbi/offender_registry/';
        $fields = 'fname=&lname='.$ln.'&address=&city=&zip=&county=&radio1=3&violent=1&drug=1&sex=1&submit=Submit&page=results&script=true';
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
    function curl_details($booking_id)
    {
        $url = 'http://www.accesskansas.org/kbi/offender_registry/?id='.$booking_id;
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
        $booking_id = 'kbi_' . $booking_id;
        # database validation 
        $offender = Mango::factory('offender', array(
            'booking_id' => $booking_id
        ))->load(); 
        # validate against the database
        if (empty($offender->booking_id)) 
        {
            # extract profile details
            # required fields
            $check = preg_match('/<!--\soffender\stab\s-->.*<h3>(.*)<\/h3>/Uis', $details, $match);
            if ($check)
            {
                $fullname = $match[1];
                $explode = explode('&nbsp;', $fullname);
                # remove empty values
                foreach ($explode as $key => $value)
                {
                    if(empty($value))
                    {
                        unset($explode[$key]);
                    }
                }
                $explode = array_merge($explode); // reset keys
                $firstname = trim(strtoupper(@$explode[0]));
                $lastname  = trim(strtoupper($explode[count($explode) - 1] ));
                if (!empty($firstname) && !empty($lastname))
                {
                    # get address table
                    $this->source = $details; 
                    $this->anchor = 'patable';
                    $this->anchorWithin = true;
                    $this->headerRow = false;
                    $this->stripTags = false;
                    $this->startRow = 2;
                    $addy_table = $this->extractTable();
                    if (!empty($addy_table[1][4]))
                    {
                        //<abbrtitle="KANSAS">KS</abbr>
                        $check = preg_match('/title=\"(.*)\"/is', $addy_table[1][4], $match);
                        if ($check)
                        {
                        	
                            $county = strtolower($match[1]);
                            # get charges table and booking_date
                            $check = preg_match('/<div\sid\=\"offensetable\">\s.*<\/table>/Uis', $details, $match);
							$charges = array();
							if ($check)
							{
								$check = preg_match('/<tbody.*<\/tbody>/Uis', $match[0], $match);
								if ($check)
								{
									$check = preg_match_all('/<tr.*<\/tr>/Uis', $match[0], $matches);
									if ($check)
									{
										foreach($matches as $match)
										{
											$check = preg_match('/<td.*<\/td>/Uis', $match[0], $match);
											$charges[] = strip_tags($match[0]); 	
										}
									} else { return 101; }
								} else { return 101; }
							} else { return 101; }
							$smashed_charges = array();
							foreach($charges as $charge)
							{
								// smash it
								$smashed_charges[] = preg_replace('/\s/', '', $charge);
							}
							if (!empty($charges))
                            {
	                            $this->source = $details; 
	                            $this->anchor = 'otable';
	                            $this->anchorWithin = true;
	                            $this->headerRow = false;
	                            $this->stripTags = true;
	                            $this->startRow = 2;
	                            $charges_table = $this->extractTable();
	                            $booking_date = strtotime(preg_replace('/\-/', '/', @$charges_table[1][5]));
	                            if (!empty($booking_date))
	                            {
	                                # get extra fields
	                                $extra_fields = array();
	                                # get charges table and booking_date
	                                $this->source = $details; 
	                                $this->anchor = 'pdtable';
	                                $this->anchorWithin = true;
	                                $this->headerRow = false;
	                                $this->stripTags = true;
	                                //$this->startRow = 2;
	                                $profile_table = $this->extractTable();
	                                if (!empty($profile_table[1][1]))
	                                {
	                                    $extra_fields['height'] = $this->height_conversion($profile_table[1][1]);
	                                }
	                                if (!empty($profile_table[1][2]))
	                                {
	                                    $extra_fields['weight'] = $profile_table[1][2];
	                                }
	                                if (!empty($profile_table[1][3]))
	                                {
	                                    $extra_fields['eye_color'] = $profile_table[1][3];
	                                }
	                                if (!empty($profile_table[1][4]))
	                                {
	                                    $extra_fields['hair_color'] = $profile_table[1][4];
	                                }
	                                if (!empty($profile_table[1][5]))
	                                {
	                                    $extra_fields['race'] = $this->race_mapper($profile_table[1][5]);
	                                }
	                                if (!empty($profile_table[1][6]))
	                                {
	                                    $extra_fields['gender'] = strtoupper($profile_table[1][6]);
	                                }
	                                if (!empty($addy_table[1][1]))
	                                {
	                                    $extra_fields['gender'] = strtoupper($profile_table[1][6]);
	                                }
	                                $check = preg_match('/DOB.*:(.*)<br/Uis', $details, $match);
	                                if ($check)
	                                {
	                                    $extra_fields['dob'] = preg_replace('/\-/', '/', $match[1]); 
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
                                    $ncharges2 = $this->charges_check($smashed_charges, $list);
									if (!empty($ncharges)) // this means it found new charges (unsmashed)
									{
									    if (empty($ncharges2)) // this means our smashed charges were found in the db
									    {
									        $ncharges = $ncharges2;
									    }
									}
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
                                        $check = preg_match('/oimgs\/(.*).jpg/Uis', $details, $match);
                                        if ($check)
                                        {
                                            
                                            $image_link = 'http://www.accesskansas.org/kbi/offender_registry/oimgs/'.$match[1].'.jpg';
                                            # set image name
                                            $imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
                                            # set image path
                                            $imagepath = '/mugs/kansas/kbi/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
                                            # create mugpath
                                            $mugpath = $this->set_mugpath($imagepath);
                                            //@todo find a way to identify extension before setting ->imageSource
                                            $this->imageSource    = $image_link;
                                            $this->save_to        = $imagepath.$imagename;
                                            $this->set_extension  = true;
                                            $this->cookie         = $this->cookies;
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
                                                    if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in kbi scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
                                                    $mcharges   = array_merge($mcharges);   
                                                    $charge1    = $mcharges[0];
                                                    $charge2    = $mcharges[1];    
                                                    $charges    = $this->charges_abbreviator($list, $charge1, $charge2); 
                                                    $check = $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
													if ($check === false)
													{
													    unlink($imgpath);
													    return 101;
													}
                                                }
                                                else if ( $chargeCount == 2 )
                                                {
                                                    $fcharges   = array_merge($fcharges);
                                                    $charge1    = $fcharges[0];
                                                    $charge2    = $fcharges[1];   
                                                    $charges    = $this->charges_abbreviator($list, $charge1, $charge2);
                                                    $check = $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
													if ($check === false)
													{
													    unlink($imgpath);
													    return 101;
													}           
                                                }
                                                else 
                                                {
                                                    $fcharges   = array_merge($fcharges);
                                                    $charge1    = $fcharges[0];    
                                                    $charges    = $this->charges_abbreviator($list, $charge1);       
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
                                                        'scrape'        => $this->scrape,
                                                        'state'         => strtolower($this->state),
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
													$charge->abbr = $value;
                                                    $charge->order = (int)0;
                                                    $charge->county = $this->scrape;
													$charge->scrape = $this->scrape;
                                                    $charge->new    = (int)0;
                                                    $charge->update();
                                                }   
                                            }
                                        }
                                        return 104; 
                                    } // ncharges validation   
                                } else { return 101; } // no first or lastname found
                            } else { return 101; } // no charges found at all 
                        } else { return 101; } // no booking date found
                    } else { return 101; } // no county found
                } else { return 101; } // no county found
            } else { return 101; } // preg match failed     
        } else { return 103; } // database validation failed
    } // end extraction
} // class end