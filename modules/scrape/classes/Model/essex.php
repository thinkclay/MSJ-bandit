<?php defined('SYSPATH') or die('No direct script access.');
 
 
 
/**
 * Model_Essex
 *
 * @package Scrape
 * @author Winter King
 * @url 
 */
class Model_Essex extends Model_Scrape
{
    private $scrape     = 'essex';
    private $state      = 'new jersey';
    private $cookies    = '/tmp/essex_cookies.txt';
    
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
		$county = 'essex';
	  	$home_url = 'http://www.eccorrections.org/inmatelookup';
		foreach ($this->alphabet as $letter)
		{
			$thepage = $this->curl_to_url($home_url, $letter);
			$check = preg_match_all("/\<td\sbordercolor\=\"\#CCCCCC\"\>\<a\sclass\=\"search\"\shref\=\"javascript\:submitInmate\((.*)\)\"\>\<\/a\>\<\/td\>.*\<\/tr\>/Uis", $thepage, $matches);
			foreach($matches[1] as $value)
			{
				$check = preg_match("/^.*\)/", $value, $match);
				$match = preg_replace('/\'/', '', $match[0]);
				$clean_match = preg_replace('/\)/', '', $match);
				$the_nums = explode(',', $clean_match);
				$ind_post_vars = "flow_action=details&inmateID=" .$the_nums[0]. "&imageID=" .$the_nums[1];
				$the_details = $this->curl_to_url2($home_url, $ind_post_vars);
				$extractions = $this->extraction($the_details, $county);
			}
		}
	}//end scrape function
	
    function curl_to_url($url, $letter)
    {
    	$post = 'flow_action=search&quantity=1000&PIN=&SID=&PCP=&searchtype=n&optionalClause=b&firstname=&lastname='.$letter.'&identifierbox=PIN&identifier=';
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        $index = curl_exec($ch);
        curl_close($ch);
        return $index;
    } 
	function curl_to_url4($url)
    {
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, 
			"flow_action=cont_search&QUANTITY=10&START=0"
		);
        $index = curl_exec($ch);
        curl_close($ch);
        return $index;
    }
    
	
	 function curl_to_url3($url)
    {
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "flow_action=cont_search&QUANTITY=10&START=10");
        $index = curl_exec($ch);
        curl_close($ch);
        return $index;
    }
	
    function curl_to_url2($url,$post_vars)
    {
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vars);
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
    function extraction($details_page, $county)
    {
    	$check = preg_match('/Photo\sID\:\<\/td\>.*\<td\sbgcolor\=\"\#FFFFFF\"\>(.*)\</Uis', $details_page, $booking_id);
		if(!isset($booking_id[1]))
		{
			return 101;
		}
		$booking_id = $county .'_'. $this->clean_string_utf8($booking_id[1]);
		if(isset($booking_id))
		{
			$offender = Mango::factory('offender', array(
            	'booking_id' => $booking_id
        	))->load(); 
			if(!$offender->loaded())
			{
				$check = preg_match('/Name\:\s\<\/font\>\<\/div\>\<div\sclass\=\"body\"\>(.*)\</Uis', $details_page, $fullname); 
				if($check)
				{
					$fullname = strtoupper(trim($fullname[1]));
					$name = explode(' ', $fullname);
					$firstname = strtoupper(trim($name[0]));
					$lastname = strtoupper(trim($name[2]));
					$check = preg_match_all('/\<tr\sbgcolor\=\"\#FFFFFF\"\sclass\=\"bodysmall\"\>.*\<td\swidth\=\"77\"\>\<\/td\>.*\<td\scolspan\=\"2\"\sbordercolor\=\"\#CCCCCC\"\>(.*)\<\/td\>/Uis', $details_page, $charges);
					if($check)
					{
						$charges = $this->clean_string_utf8($charges[1][0]);
						$check = preg_match('/Comp\sDate\<\/td\>.*\<td\sbgcolor\=\"\#FFFFFF\"\>(.*)\<\/td\>/Uis', $details_page, $booking_date);
						if($check)
						{
							$booking_date = strtotime(trim($this->clean_string_utf8($booking_date[1])));
							$check = preg_match('/\<img\sid\=\"largephoto\"\ssrc\=\"imageservlet\?imgsysid\=(.*)\"/Uis', $details_page, $image_id);
							if($check)
							{
								$image_id = $image_id[1];
								$image_link = "http://www.eccorrections.org/imageservlet?imgsysid=" . $image_id;
								$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
								$imagepath = '/mugs/new jersey/'.$county.'/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
								$county_directory = '/mugs/new jersey';
								$this->make_county_directory($county_directory);
								$extra_fields = array();
								$check = preg_match('/Sex\:\<\/td\>.*\<td\swidth\=\"38\%\"\sclass\=\"bodysmall\"\>(.*)\</Uis', $details_page, $match);
								if($check)
								{
									$extra_fields['gender'] = $this->gender_mapper($this->clean_string_utf8($match[1]));
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
										//foreach($charges as $charge)
										//{
                                        	$fcharges[] = trim(strtoupper($charges));
										//} 
                                        # make it unique and reset keys
                                        $fcharges = array_unique($fcharges);
                                        $fcharges = array_merge($fcharges);
                                        $dbcharges = $fcharges;
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
				} else { return 101; } // no firstname or lastname found
			} else { return 103; } //offender already in DB
		} else { return 101; } //no booking id
	}
}

