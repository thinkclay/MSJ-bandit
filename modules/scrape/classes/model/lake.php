<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Lake
 *
 * @package Scrape
 * @author Winter King
 * @url https://www.lcso.org/
 * @notes I think this is a Dreamweaver site... 
 * 
 */
class Model_Lake extends Model_Scrape
{
	private $scrape 	= 'lake';
	private $state		= 'florida';
    private $cookies 	= '/tmp/lake_cookies.txt';
	
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
	* @return $info - passes to the controller for reporting
	*/
    function scrape() 
    {
		$index  = $this->curl_index();
		# build detail_links array
		$detail_links = array();
		// mugshot_booking_detail.php?bookingnumber=11004589&birthdate=08/17/1990&bookdate=05/21/2011 12:07:00 AM&ardate=05/20/2011 08:55:00 PM&reldate='>
		$check = preg_match_all('/mugshot_booking_detail.php([^\']*)\'/Uis', $index, $matches);
		if ($check)
		{
			foreach($matches[1] as $key => $value)
			{
				$detail_links[] = 'https://www.lcso.org/inmatepublic/mugshot_booking_detail.php' . $value; 	
			}
			# now loop through $detail_links and curl each detail page
			foreach($detail_links as $link)
			{
				
				$details = $this->curl_details($link);
				$extraction = $this->extraction($details);
                if ($extraction == 100) { $this->report->successful = ($this->report->successful + 1); $this->report->update(); }
                if ($extraction == 101) { $this->report->other = ($this->report->other + 1); $this->report->update(); }
                if ($extraction == 102) { $this->report->bad_images = ($this->report->bad_images + 1); $this->report->update(); }
                if ($extraction == 103) { $this->report->exists = ($this->report->exists + 1); $this->report->update(); }
                if ($extraction == 104) { $this->report->new_charges = ($this->report->new_charges + 1); $this->report->update(); }
                $this->report->total = ($this->report->total + 1); $this->report->update();
			}
			$this->report->failed = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
	        $this->report->finished = 1;
	        $this->report->stop_time = time();
	        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
	        $this->report->update();
	        return true; 
		} else { return false; } // no rows found 
	}
	
	/**
	* curl_index - gets the index of current population
	* 
	*@url https://www.lcso.org/inmatepublic/
	*  
	*  
	*/
	function curl_index()
	{
		$url = 'https://www.lcso.org/inmatepublic/';
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
	* curl_details
	*
	* @link   https://www.lcso.org/inmatepublic/mugshot_booking_detail.php?bookingnumber=11004611&birthdate=06/17/1972&bookdate=05/21/2011%2007:16:11%20PM&ardate=05/21/2011%2006:40:00%20PM&reldate=
	* @notes  this is to get the offender details page 
	* @params string $link - link for the details page
	* @return string $details - details page in as a string
	*/
	function curl_details($link)
	{
		$url = preg_replace('/\s/', '%20', $link);
		$ch = curl_init(); 
		$headers = array(
			"Host: www.lcso.org",
			"User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:2.0.1) Gecko/20100101 Firefox/4.0.1",
			"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
			"Accept-Language: en-us,en;q=0.5",
			"Accept-Encoding: gzip, deflate",
			"Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7",
			"Keep-Alive: 115",
			"Connection: keep-alive",
			"Cache-Control: max-age=0",
		);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        $details = curl_exec($ch);
        curl_close($ch);
		return $details;
	}
		
	/**
	* extraction - validates and extracts all data
	*
	* 
	* @params string $details  - offenders details page
	* @return array $ncharges - numerical array of new charges found
	* @return boolean false  	- on failed extraction
	* @return string 'success'	- on successful extraction
	* 
	*/
	function extraction($details)
	{
		$county = 'lake';
		# rip out Name/Tracking Information table
		$this->source = $details; 
        $this->anchor = 'Name/Tracking';
	    $this->anchorWithin = true;
		$this->headerRow = false;
		$this->stripTags = true;
        $ptable1 = $this->extractTable();
		$booking_id = @$ptable1[3][1];
		if(!empty($booking_id))
		{
			$booking_id = preg_replace("/\D/", "", $booking_id); 
			$booking_id = 'lake_' . $booking_id;
			# database validation 
			$offender = Mango::factory('offender', array(
				'booking_id' => $booking_id
			))->load();	
			# validate against the database
			if (empty($offender->booking_id)) 
			{
				$fullname = @$ptable1[2][1];
				if(!empty($fullname))
				{
					$fullname = preg_replace('/First\/Middle\/Last/', '', $fullname); 
					$explode = explode('&nbsp;', $fullname, 3);
					$firstname = strip_tags(trim(preg_replace('/\./', '', @$explode[0])));
					$firstname = preg_replace('/\&nbsp\;/', '', $firstname);
					$lastname = strip_tags(trim(preg_replace('/\./', '', @$explode[2])));
					$lastname = preg_replace('/\&nbsp\;/', '', $lastname);
					if (!empty($firstname) && !empty($lastname))
					{	
						# now get the physical description table 
						$this->source = $details; 
				        $this->anchor = 'Physical Description';
					    $this->anchorWithin = true;
						$this->headerRow = false;
						$this->stripTags = true;
						//$this->startRow = 2;
						//$this->maxCols  = 2;
				        $ptable2 = $this->extractTable();
						# get extra fields 
						$extra_fields = array();
						$dob = strtotime(preg_replace('/DateofBirth\:/', '', @$ptable2[2][1]));
						if (isset($dob) ) { $extra_fields['dob'] = $dob; }
						$race = preg_replace('/Race\:/', '', @$ptable2[2][2]);
						if (isset($race) ) { $extra_fields['race'] = $this->race_mapper($race); }
						$gender = preg_replace('/Sex\:/', '', @$ptable2[2][4]);
						if ($gender == 'M') {$gender = 'MALE';} else if ($gender == 'F') { $gender = 'FEMALE'; }
						if (isset($gender) ) { $extra_fields['gender'] = $gender; }
						$eye_color = preg_replace('/EyeColor\:/', '', @$ptable2[2][5]);
						if (isset($eye_color) ) { $extra_fields['eye_color'] = $eye_color; }
						$hair_color = preg_replace('/HairColor\:/', '', @$ptable2[2][6]);
						if (isset($hair_color) ) { $extra_fields['hair_color'] = $hair_color; }
						$height = preg_replace('/\D/', '', @$ptable2[2][7]);
						if (isset($height) ) { $extra_fields['height'] = $height; }
						$weight = preg_replace('/\D/', '', @$ptable2[2][8]);
						if (isset($weight) ) { $extra_fields['weight'] = $weight; }
						# get the booking date 
						$this->source = $details; 
				        $this->anchor = 'Arrest Information';
					    $this->anchorWithin = true;
						$this->headerRow = false;
						$this->stripTags = true;
						//$this->startRow = 2;
						//$this->maxCols  = 2;
				        $ptable3 = $this->extractTable();
						$booking_date = strtotime(preg_replace('/BookedDate\/Time\:/', '', @$ptable3[3][1]));
						if (!empty($booking_date))
						{
							$check = preg_match('/Agency CFS\<\/font\>\<\/td\>.*\<\/td>.*\<\/td>.*\<b\>.*\>(.*)\<\/font/Uis', $details, $match);
							if(!empty($match[1]))
							{
								$charges = preg_replace('/&nbsp\;/', '', $match[1]);
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
										$fcharges = trim(strtoupper($charges));	
										# make it unique and reset keys
										//$fcharges = array_unique($fcharges);
										//$fcharges = array_merge($fcharges);
										$dbcharges = $fcharges;
										# begin image extraction
										//https://www.lcso.org/inmatepublic/mugshot/imagepage3.php?imgnum=388284
										$check = preg_match('/FRONT.*<img\ssrc\=\"mugshot\/imagepage3\.php\?imgnum\=([^"]*)\"/Uis', $details, $match);
										if ($check)
										{
											$image_link = 'https://www.lcso.org/inmatepublic/mugshot/imagepage3.php?imgnum=' . $match[1];
											# set image name
											$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
											# set image path
									        $imagepath = '/mugs/florida/lake/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
									        # create mugpath
									        $mugpath = $this->set_mugpath($imagepath);
											//@todo find a way to identify extension before setting ->imageSource
											$this->imageSource    = $image_link;
									        $this->save_to        = $imagepath.$imagename;
									        $this->set_extension  = true;
											$this->cookie			= $this->cookies;
									        $this->download('curl');
											if (file_exists($this->save_to . '.jpg')) //validate the image was downloaded
											{
												#@TODO make validation for a placeholder here probably
												# ok I got the image now I need to do my conversions
										        # convert image to png.
										        $this->convertImage($mugpath.$imagename.'.jpg');
										        $imgpath = $mugpath.$imagename.'.png';
												$img = Image::factory($imgpath);
        										$img->resize(400, 480, Image::NONE)->save();
							                	$img->crop(400, 480)->save();
										        $imgpath = $mugpath.$imagename.'.png';
												if(strlen($dbcharges) >= 15)
												{
													$charges = $this->charge_cropper($dbcharges, 400, 15);
													if(empty($charges))
													{
														//return 101;
													}
													else
													{
														//$charges = $this->charges_abbreviator($list, $charges[0], $charges[1]); 
	                                                	$this->mugStamp_test1($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
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
												# now run through charge logic
												$chargeCount = count($fcharges);
												# run through charge logic	
												$mcharges 	= array(); // reset the array
										        if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
										        {
										            $mcharges 	= $this->charges_prioritizer($list, $fcharges);
													if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in Lexington scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-");  } // debugging
										            $mcharges 	= array_merge($mcharges);   
										            $charge1 	= $mcharges[0];
										            $charge2 	= $mcharges[1];    
										            $charges 	= $this->charges_abbreviator($list, $charge1, $charge2); 
										            $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
										        }
										        else if ( $chargeCount == 2 )
										        {
										            $fcharges 	= array_merge($fcharges);
										            $charge1 	= $fcharges[0];
										            $charge2 	= $fcharges[1];   
										           	$charges 	= $this->charges_abbreviator($list, $charge1, $charge2);
										            $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);           
										        }
										        else 
										        {
										            //$fcharges 	= array_merge($fcharges);
										            $charge1 	= $fcharges;    
										            $charges 	= $this->charges_abbreviator($list, $charge1);       
										            $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0]);   
										        }
												
												// Abbreviate FULL charge list
												//$dbcharges = $this->charges_abbreviator_db($list, $dbcharges);
												//$dbcharges = array_unique($dbcharges);
												# BOILERPLATE DATABASE INSERTS
												$offender = Mango::factory('offender', 
									                array(
									                	'scrape'		=> $this->scrape,
									                	'state'			=> $this->state,
									                	'county'		=> $county,
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
										} else { return 102; } // image link not found
									
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
													$charge->new 	= (int)0;
													$charge->update();
												}	
											}
										}
							            return 104;
									} // ncharges validation
								} else { return 101; } // no charges found
							} else { return 101; } // empty charges table
						} else { return 101; } // empty booking_date
					} else { return 101; } // no firstname or lastname
				} else { return 101; } // no fullname match in the table
			} else { return 101; } // no booking_id
		} else { return 103; } // database validation failed
	} // end extraction
} // class end