<?php defined('SYSPATH') or die('No direct script access.');
 
 
 
/**
 * Model_Txdps
 *
 * @package Scrape
 * @author Winter King and Bryan Galli
 * @url https://records.txdps.state.tx.us/DpsWebsite/index.aspx
 */
class Model_Txdps extends Model_Scrape
{
    private $scrape     = 'txdps';
    private $state      = 'texas';
    private $cookies    = '/tmp/txdps_cookies.txt';
    
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
    	$home_url = 'https://records.txdps.state.tx.us/DpsWebsite/index.aspx';
        $home = $this->curl_to_url($home_url); // just to set a cookie
        $caveats = $this->curl_caveats();
        $search_page = $this->curl_search();
		$counties = array();
		
		$check = preg_match_all('/<option\svalue\=\"(.*)\"\>(.*)\</Uis', $search_page, $matches);
		if($check)
		{
			$counties = $matches[1];
			$counties_names = $matches[2];
			unset($counties[0]);
			unset($counties_names[0]);
			array_merge($counties);
			array_merge($counties_names);
			foreach($counties as $county)
			{
				$county_index = $county;
				$county_name = $counties_names[intval($county_index)];
				$post_vars = array();
				$check = preg_match('/\_templateticket\"\svalue\=\"(.*)\"/Uis', $search_page, $templateticket);
				if($check)
				{
					$post_vars['_TemplateTicket'] = $templateticket[1];
					
					$check = preg_match('/\_eventtarget\"\svalue\=\"(.*)\"/Uis', $search_page, $eventtarget);
					if($check !== false)
					{
						if(empty($eventtarget))
						{
							$post_vars['__EVENTTARGET'] = '';
						}
						else
						{
							$post_vars['__EVENTTARGET'] = $eventtarget;
						}
						$check = preg_match('/\_eventargument\"\svalue\=\"(.*)\"/Uis', $search_page, $eventargument);
						if($check !== false)
						{
							if(empty($eventargument))
							{
								$post_vars['__EVENTARGUMENT'] = '';
							}
							else
							{
							$post_vars['__EVENTARGUMENT'] = $eventargument;	
							}
							$check = preg_match('/\_viewstate\"\svalue\=\"(.*)\"/Uis', $search_page, $viewstate);
							if($check)
							{
								$post_vars['__VIEWSTATE'] = $viewstate[1];
								$check = preg_match('/\_eventvalidation\"\svalue\=\"(.*)\"/Uis', $search_page, $eventvalidation);
								if($check)
								{
									$post_vars['__EVENTVALIDATION'] = $eventvalidation[1];
									$post_vars['CurrentDPStemplateBase$ctl06$ctl00$ddlCounty'] = $county;
									$post_vars['CurrentDPStemplateBase$ctl06$ctl00$rblOutput'] = 'TXT';
									$post_vars['CurrentDPStemplateBase$ctl06$btnSearch'] = 'Search';
								}
							}
						}
					}
				}
				$index = $this->curl_index($post_vars);
				$check = preg_match_all('/IND_IDN\=(.*)\&/Uis', $index, $matches);
				if($check)
				{
					$detail_links = array();
					foreach($matches[1] as $value)
					{
						$detail_links[] = 'https://records.txdps.state.tx.us/DPS_WEB/SorNew/PublicSite/index.aspx?PageIndex=Individual&IND_IDN=' .$value. '&SearchType=County';	
					}
				}
				foreach($detail_links as $detail_link)
				{
					$detail_page = $this->curl_details($detail_link);
					$extraction = $this->extraction($detail_page, $county, $county_name);
				}
			}
		}
	}//end scrape function
		
	
    /**
    * curl_page - gets the index of current day
    * 
    * @url 
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
    * @url https://records.txdps.state.tx.us/DPS_WEB/SorNew/PublicSite/Search/index.aspx?PageIndex=Search&SearchType=County
    * 
    */
    function curl_to_url($url)
    {
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		//curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $index = curl_exec($ch);
        curl_close($ch);
        return $index;
    } 
	
	function curl_caveats()
	{
		$ch = curl_init();  
		$url = 'https://records.txdps.state.tx.us/DPS_WEB/SorNew/PublicSite/index.aspx?SearchType=County'; 
        curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		//curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $caveats = curl_exec($ch);
        curl_close($ch);
        return $caveats;
	}

    function curl_search()
    {
        $ch = curl_init();  
        $url = 'https://records.txdps.state.tx.us/DPS_WEB/SorNew/PublicSite/Search/index.aspx?PageIndex=Search&SearchType=County'; 
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $caveats = curl_exec($ch);
        curl_close($ch);
        return $caveats;
    }
    
    
    /**
    * curl_index - gets the index based on a search parameter
    * 
    * @url 
    *   
    */
    function curl_index($post_vars)
    {
        $url = 'https://records.txdps.state.tx.us/DPS_WEB/SorNew/PublicSite/Search/index.aspx?PageIndex=Search&SearchType=County';
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		//curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Host: records.txdps.state.tx.us',
            'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.18) Gecko/20110614 Firefox/3.6.18',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-us,en;q=0.5',
            'Accept-Encoding: gzip,deflate',
            'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
            'Keep-Alive: 115',
            'Connection: keep-alive',
            'Referer: https://records.txdps.state.tx.us/DPS_WEB/SorNew/PublicSite/Search/index.aspx?PageIndex=Search&SearchType=County',
		));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vars);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $index = curl_exec($ch);
        curl_close($ch);
        return $index;
    } 
      
    /**
    * curl_details - gets the details page based on a booking_id
    * 
    * @url 
    *   
    */
    function curl_details($detail_url)
    {
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $detail_url);
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
    function extraction($detail_page, $county, $county_name)
    {
    	$check = preg_match('/sid\<\/th\>\<td\sclass\=\"text\"\>(.*)\</Uis', $detail_page, $theid);
		if($check)
		{
			$theid = $this->clean_string_utf8(trim($theid[1]));
			$booking_id = 'txdps_' . $theid;
        	$offender = Mango::factory('offender', array(
            	'booking_id' => $booking_id
        	))->load(); 
        	# validate against the database
        	if (!$offender->loaded())
        	{
		    	$check = preg_match('/user\:\<\/div\>\<h1\>(.*)\</Uis', $detail_page, $name);
				if($check)
				{
					$fullname = $this->clean_string_utf8($name[1]);
					$name = explode(',', $fullname);
					$lastname = strtoupper(trim($name[0]));
					$name = explode(' ', trim($name[1]));
					$firstname = strtoupper(trim($name[0]));
					if(!empty($name[1]))
					{
						$middlename = strtoupper(trim($name[1]));
					}	
					$check = preg_match_all('/tx\:[0-9]*(.*)\</Uis', $detail_page, $matches);
					if($check)
					{
						$charges = array();
						foreach($matches[1] as $match)
						{
							$formatedmatch = strtoupper(trim(preg_replace('/[0-9]/', '', $match)));
							$charges[] = $formatedmatch;
						}
						$charges = $this->clean_string_utf8($charges);
						$smashed_charges = array();
						foreach($charges as $charge)
						{
							// smash it
							$smashed_charges[] = preg_replace('/\s/', '', $charge);
						}
						$this->source = $detail_page; 
					    $this->anchor = 'TX:';
						$this->headerRow = false;
						$this->stripTags = true;
						$this->cleanHTML = true;
					    $charges_table = $this->extractTable();
					    $booking_date = $this->clean_string_utf8(strtotime(substr($charges_table[2][5], 0, -2)));
						if($booking_date)
						{
							$check = preg_match('/current\srecord\"\ssrc.*photoid\=(.*)\"/Uis', $detail_page, $match);
							if($check)
							{
								$image_link = '/DPS_WEB/SorNew/SorPhoto.ashx?Height=150&amp;Width=150&amp;PhotoId=' . $match[1];
								$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
								$imagepath = '/mugs/texas/'.$county_name.'/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
								
								$extra_fields = array();
								$this->source = $detail_page; 
					  			$this->anchor = 'Description';
								$this->headerRow = false;
								$this->stripTags = true;
								$this->cleanHTML = true;
					    		$extras_table = $this->extractTable();
								if(!empty($extras_table[5][2])) 
								{
									$extra_fields['sex'] = strtoupper(trim($extras_table[5][2]));
								}
								if(!empty($extras_table[6][2]))
								{
									$extra_fields['race'] = strtoupper(trim($extras_table[6][2]));
								}
								if(!empty($extras_table[8][2]))
								{
									$extra_fields['height'] = $this->height_conversion(trim($extras_table[8][2]));
								}
								if(!empty($extras_table[9][2]))
								{
									$check = preg_replace('/[a-zA-z]/', '' ,$extras_table[9][2]);
									if($check)
									{
									$extra_fields['weight'] = trim($check);
									}
								}
								if(!empty($extras_table[10][2]))
								{
									$extra_fields['haircolor'] = strtoupper(trim($extras_table[10][2]));
								}
								if(!empty($extras_table[11][2]))
								{
									$extra_fields['eyecolor'] = strtoupper(trim($extras_table[11][2]));
								}
								if(!empty($middlename))
								{
									$extra_fields['middlename'] = strtoupper(trim($middlename));
								}
								if(!empty($charges))
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
									
                                    $charges_object = Mango::factory('charge', array('county' => $this->scrape, 'new' => 0))->load(false)->as_array(false);
                                    $list = array();
                                    foreach($charges_object as $row)
                                    {
                                        $list[$row['charge']] = $row['abbr'];

                                    }
                                    $ncharges = array();
                                    $ncharges = $this->charges_check($charges, $list);
									$ncharges2 = $this->charges_check($smashed_charges, $list);
									if (!empty($ncharges)) // this means it found new charges (unsmashed)
									{
									    if (empty($ncharges2)) // this means our smashed charges were found in the db
									    {
									        $ncharges = $ncharges2;
									    }
									}
									if(empty($ncharges))
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
										$this->make_county_directory($imagepath);
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
                                                    'county'        => $county_name,
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
									} else {//if new charges exist then add them to the DB
                                    # add new charges to the charges collection
                                    foreach ($ncharges as $key => $value)
                                    {
                                        //$value = preg_replace('/\s/', '', $value);
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
                                    }
								} else { return 101;} //charges field was empty
							} else { return 101; } // no image
						} else { return 101; } // no booking date
					} else { return 101; } // no charges found at all
				} else { return 101; } // no first or lastname found
			} else { return 103; } //person is already in DB 
		} else { return 101; } //no booking id found 
	}//end extraction function
}//end txpds class