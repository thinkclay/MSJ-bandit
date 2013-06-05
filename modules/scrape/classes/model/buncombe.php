<?php defined('SYSPATH') or die('No direct script access.');
 
 
 
/**
 * Model_Buncombe
 *
 * @package Scrape
 * @author Winter King and Bryan Galli
 * @url http://bcsd.p2c.buncombecounty.org/p2c/
 */
class Model_Buncombe extends Model_Scrape
{
    private $scrape     = 'buncombe';
    private $state      = 'north carolina';
    private $cookies    = '/tmp/buncombe_cookies.txt';
    
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
    	$county = 'buncombe';
    	$home_url = 'http://bcsd.p2c.buncombecounty.org/p2c/Arrests.aspx';
        $home = $this->curl_to_url($home_url); // just to set a cookie
        
        $check = preg_match_all('/typeno\=\"4\"\>(.*)\<div/Uis', $home, $matches);
	
		if($check)
		{
			$check = preg_match_all('/imageid\=\"(.*)\"/Uis', $matches[1][0], $matches);
			if($check)
			{
				foreach($matches[1] as $match)
				{
					$details_page_url = 'http://bcsd.p2c.buncombecounty.org/p2c/Arrests.aspx?ImageID=' . $match;
					$details_page = $this->curl_to_url($details_page_url);
					$the_id = $match;
					$extraction = $this->extraction($details_page, $county, $the_id);
				}
			}
		}
	}//end scrape function
		
	
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
    function extraction($details_page, $county, $the_id)
    {
		$the_id = $this->clean_string_utf8(trim($the_id));
		$booking_id = $county .'_'. $the_id;
		if(isset($booking_id))
		{
			$offender = Mango::factory('offender', array(
            	'booking_id' => $booking_id
        	))->load(); 
			if(!$offender->loaded())
			{
				$check = preg_match('/lblName\"\sclass\=\"ShadowBoxFont\"\>(.*)\</Uis', $details_page, $name); 
				if($check)
				{
					$fullname = $this->clean_string_utf8($name[1]);
					$name = explode(' ', $fullname);
					$firstname = strtoupper(trim($name[0]));
					$lastname = strtoupper(trim($name[1]));
					$check = preg_match('/charge\:.*titlefont\>(.*)\</Uis', $details_page, $charge);
					if($check)
					{
						$charges = trim(preg_replace('/\&nbsp\;/Uis', '', $charge[1]));

						$check = preg_match('/time\:.*titlefont\>(.*)\sat/Uis', $details_page, $booking_date);
						if($check)
						{
							$patterns = array('/\&nbsp\;/Uis','/On\s/');
							$booking_date = strtotime(trim(preg_replace($patterns, '', $booking_date[1])));
							$check = preg_match('/src\=\"Mug\.aspx\?Type\=4\&amp\;ImageID\=(.*)\"/Uis', $details_page, $image_id);
							if($check)
							{
								$image_id = $this->clean_string_utf8($image_id[1]);
								
								
								$image_link = 'http://bcsd.p2c.buncombecounty.org/p2c/Mug.aspx?Type=4&ImageID=' . $image_id;
								$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
								$imagepath = '/mugs/northcarolina/'.$county.'/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
								$county_directory = '/mugs/northcarolina';
								$this->make_county_directory($county_directory);
								$extra_fields = array();
								$this->source = $details_page; 
					  			$this->anchor = 'Mug.aspx?Type=4&amp;ImageID=';
								$this->headerRow = false;
								$this->stripTags = true;
								$this->cleanHTML = true;
					    		$extras_table = $this->extractTable();
								if(!empty($extras_table[1][2])) 
								{
									$extra_fields['race'] = strtoupper(trim($extras_table[1][2]));
								}
								if(!empty($extras_table[2][2])) 
								{
									$extra_fields['gender'] = $this->gender_mapper($extras_table[2][2]);
								}
								if(!empty($extras_table[3][2])) 
								{
									$good_age = preg_replace('/[a-zA-Z]/', '', $extras_table[3][2]);
									$extra_fields['age'] = strtoupper(trim($good_age));
								}
								if(!empty($extras_table[4][1])) 
								{
									$description = explode('"', $extras_table[4][1]);
									$extra_fields['height'] = $this->height_conversion(strtoupper(trim($description[0])));
									$extra_fields['weight'] = strtoupper(trim(preg_replace('/[a-zA-Z]/', '', $description[1])));
								}
								if(!empty($charges))
								{
									$charges_object = Mango::factory('charge', array('county' => $this->scrape, 'new' => 0))->load(false)->as_array(false);
									
									$list = array();
                                    foreach($charges_object as $row)
                                    {
                                        $list[$row['charge']] = $row['abbr'];

                                    }
                                    $ncharges = array();
                                    $ncharges = $this->charges_check($charges, $list);
									if(empty($ncharges))
									{
										$fcharges   = array();
                                        $fcharges[0] = trim(strtoupper($charges));
										$chargesholder = $fcharges; 
                                        # make it unique and reset keys
                                        //$fcharges = array_unique($fcharges);
                                        //$fcharges = array_merge($fcharges);
                                        $dbcharges[0] = $fcharges[0];
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
											if(strlen($fcharges[0]) >= 15)
											{
												$charges = $this->charge_cropper($fcharges[0], 400, 15);
												if(!$charges)
												{
													$fcharges = $chargesholder;
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
											$fcharges = $fcharges;	
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
                                            	if(is_array($fcharges))
												{
                                                	$fcharges   = array_merge($fcharges);
												}
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
                                        
									///this is where I was///                          

									}
									else//add new charges to DB
									{
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
													$charge->abbr 	= $value;
                                                    $charge->order 	= (int)0;
                                                    $charge->county = $this->scrape;
                                                    $charge->scrape = $this->scrape;
                                                    $charge->new    = (int)0;
                                                    $charge->update();
                                                }   
                                           	}
                                     	}
                                        return 104; 
                                    }
								} else { return 101; } //no charge found
							} else { return 101; } //no mugshot found
						} else { return 101; } //no booking date found 
					} else { return 101; } //no charge found 
				} else { return 101; } // no first or lastname found
			} else { return 103; } //offender already in DB
		} else { return 101; } //no booking id
	}
}

