<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Saltlake
 *
 * @package 
 * @author Winter King
 * @params 
 * @TODO figure out why we're getting duplicates for saltlake
 * @TODO Some images seem to break gd.. need to look into this and how to check/bypass
 * @description 
 * @url http://iml.slsheriff.org/IML
 */
class Model_Saltlake extends Model_Scrape 
{
	private $scrape	 	= 'saltlake';
	private $state		= 'utah';
    private $cookies    = '/tmp/saltlake_cookies.txt';
	
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
    function  scrape($lastname, $firstname = null) 
    {
    	$post = 'flow_action=searchbyname&quantity=10&systemUser_identifiervalue=&searchtype=PIN&systemUser_includereleasedinmate=N&systemUser_includereleasedinmate2=N&systemUser_firstName=&systemUser_lastName='.$lastname.'&releasedA=checkbox&identifierbox=PIN&identifier=&releasedB=checkbox';
    	$index = $this->curl_main($post);
		$count = 0;
		$flag = false;
		while ($flag == false)
		{
			unset($linkIDs); // reset this to get an accurate list of new linkIDs
			# build an array for the javascript links
			$check = preg_match_all('/\"javascript\:submitInmate\(\'([^\']*)\'\,\'([^\']*)\'\)\"\>\</', $index, $js_links);
			# check for empty rows
			if ($check > 0)
			{
				# make a $key=>$value array with sysIDs and imgSysIDs
				$linkIDs = array_combine($js_links[1], $js_links[2]);
				//$this->print_r2($linkIDs);
				$rows 		= count($linkIDs);
				foreach($linkIDs as $sysID => $imgSysID)
				{
					# curl to get details page
					$post = 'flow_action=edit&sysID='.$sysID.'&imgSysID='.$imgSysID;
					$details = $this->curl_main($post);
					//sleep(15);
					# extraction
					$extraction = $this->extraction($details, $sysID, $imgSysID);
					if ($extraction == 100) { $this->report->successful = ($this->report->successful + 1); $this->report->update(); }
                    if ($extraction == 101) { $this->report->other = ($this->report->other + 1); $this->report->update(); }
                    if ($extraction == 102) { $this->report->bad_images = ($this->report->bad_images + 1); $this->report->update(); }
                    if ($extraction == 103) { $this->report->exists = ($this->report->exists + 1); $this->report->update(); }
                    if ($extraction == 104) { $this->report->new_charges = ($this->report->new_charges + 1); $this->report->update(); }
                    $this->report->total = ($this->report->total + 1); $this->report->update();
					$count++;					
				}
				
				# go back to index
				$post = 'flow_action=inmatedb_srchresults&QUANTITY=null&START=null';
				$index = $this->curl_main($post);	
				# check for next at end of recid loop and if one is found, flip pages and start loop again
				//<a class="generalnav" href="javascript:submitMe('nextSearch')">Next&gt;</a>
            	$next_check = preg_match('/javascript\:submitMe\(\'nextSearch\'\)/', $index, $match); 
				if ($next_check == 1)
				{
					$post = 'flow_action=next&currentStart='.($count);
					$index = $this->curl_main($post);	
				} else { $flag = true; } // no new pages so break loop	
			} else { $flag = true; } // row validation failed break loop
		}
		$this->report->failed = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
        $this->report->finished = 1;
        $this->report->stop_time = time();
        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
        $this->report->update();
        return true;
	} 
	function curl_main($post)
	{
		$url = 'http://iml.slsheriff.org/IML';
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        $index = curl_exec($ch);
        curl_close($ch);
		return $index;
	}
	
	
	function extraction($details, $sysID, $imgSysID)
	{
		$county = 'saltlake';
		$ncharges 	= array();
		$charges 	= array();
		$fcharges 	= array();
		$mcharges 	= array();	
		$dbcharges 	= array();
		# extract profile details
 		$check = preg_match('/Name\:\s\<\/font\>\s\<font\sclass\=\"bodywhite\"\>([^\<]*)\</', $details, $fullname);
		if ($check > 0) // check for fullname
		{
			$fullname = htmlspecialchars_decode(trim($fullname[1]), ENT_QUOTES);	
			$firstname = preg_replace('/\W.*/', '',  $fullname);
			
			preg_match('/[^ ]*$/', $fullname, $lastname);
			$lastname = $lastname[0];
			
													
			# get booking_id
			//<td width="130" bgcolor="#D7D7D7" class="bodysmallbold">Booking #:</td>
            //               <td width="170" bgcolor="#FFFFFF">10064376    </td>
			
			$check = preg_match('/Booking\s\#\:\<\/td\>[^>]*\>([^\<]*)\</', $details, $booking_id);
			if ($check > 0) // check for booking_id
			{
				# set booking_id
				$booking_id = trim($booking_id[1]);	
				$booking_id = 'saltlake_' . $booking_id;
				if ($booking_id == 'saltlake_13011286')
				{
					$firstname = 'KELLI';	
				} 
				# get booking_date
				//Booked Date:</td>
    			//<td bordercolor="#CCCCCC" bgcolor="#FFFFFF">12/14/2010</td>
    			$check = preg_match('/Booked\sDate\:\<\/td\>[^>]*\>([^<]*)\</', $details, $booking_date);
				$booking_date = strtotime($booking_date[1]);
				if ($check > 0)
				{
					//<td>76.6.302</td>
	                //          <td colspan="2">Aggravated Robbery</td>
	                # get charges and run through charge validation and logic
					$check = preg_match_all('/\<td\>[0-9]*\.[^<]*\<\/td\>[^>]*\>([^<]*)\</', $details, $charges);
					if ($check > 0) // check for charges
					{
						$charges = $charges[1];
						$smashed_charges = array();
						foreach($charges as $charge)
						{
							// smash it
							$smashed_charges[] = preg_replace('/\s/', '', $charge);
						}
						$dbcharges = $charges;	
						foreach($charges as $charge)
						{
							$fcharges[] = strtoupper(trim(preg_replace('/\s/', '', $charge)));
						}
						$fcharges = array_unique($fcharges);
						$fcharges = array_merge($fcharges); //resets keys
						# check for new charges
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
						//exit;
						# validate
						$ncharges2 = $this->charges_check($smashed_charges, $list);
						if (!empty($ncharges)) // this means it found new charges (unsmashed)
						{
						    if (empty($ncharges2)) // this means our smashed charges were found in the db
						    {
						        $ncharges = $ncharges2;
						    }
						} 
						if (empty($ncharges)) // skip the offender if a new charge was found
						{
							# database validation
					
							$offender = Mango::factory('offender', array(
								'booking_id' => $booking_id
							))->load();	

							if (empty($offender->booking_id)) 
							{
								if (!empty($firstname)) 
								{	
									if (!empty($lastname))
									{
										if (!empty($booking_id))
										{
											if (!empty($booking_date))
											{
												if (!empty($fcharges))
												{


													/* Ok now I have:
													 * $firstname
													 * $lastname
													 * $booking_id
													 * $booking_date
													 * $image
													 * $fcharges[]
													 * 
													 */ 
													 
												 	# get extra fields
												 	$extra_fields = array();
												 	$check = preg_match('/Sex\:\<\/td\>[^<]*\<[^>]*\>([^<]*)\</', $details, $gender);
													if ($check) { $gender = strtoupper(trim($gender[1])); 
													if ($gender == 'M') {$gender = 'MALE';} else if ($gender == 'F') { $gender = 'FEMALE'; }  }
												 	if (isset($gender)) { $extra_fields['gender'] = $gender;}
																												
												 	$check = preg_match('/Age\:\<\/td\>[^<]*\<[^>]*\>([^<]*)\</', $details, $age);
													if ($check) { $age = trim($age[1]); $age = (int)$age; }
													if (isset($age)) { $extra_fields['age'] = $age;}
													
													if (isset($age))
													{
														$seconds = (31557600 * $age);
														$birth_year = date('Y', $booking_date - $seconds);
														$extra_fields['birth_year'] = $birth_year;	
													}
												 	$check = preg_match('/Height\:\<\/td\>[^<]*\<[^>]*\>([^<]*)\</', $details, $height);
													if ($check) { $height = trim($height[1]); $height = (int)$height; }
													if (isset($height)) 
													{
														$extra_fields['height'] = $this->height_conversion($height);
													}
																							
												 	$check = preg_match('/Weight\:\<\/td\>[^<]*\<[^>]*\>([^<]*)\</', $details, $weight);
													if ($check) { $weight = trim($weight[1]); $weight = (int)$weight; }
													if (isset($weight)) 
													{
														$extra_fields['weight'] = preg_replace('/[^0-9]/', '', $weight); 
													}
													
												 	$check = preg_match('/Race\:\<\/td\>[^<]*\<[^>]*\>([^<]*)\</', $details, $race);
													if ($check) { $race = trim($race[1]);}
													if (isset($race)) 
													{
														if (isset($race)) 
														{
															 $race = $this->race_mapper($race);
															 if ($race)
															 {
														 	 	$extra_fields['race'] = $race;
															 }
														}
													}
													
												 	$check = preg_match('/Hair\sColor\:\<\/td\>[^<]*\<[^>]*\>([^<]*)\</', $details, $hair_color);
													if ($check) { $hair_color = trim($hair_color[1]); }
													if (isset($hair_color)) { $extra_fields['hair_color'] = $hair_color;}
													
												 	$check = preg_match('/Eye\sColor\:\<\/td\>[^<]*\<[^>]*\>([^<]*)\</', $details, $eye_color);
													if ($check) { $eye_color = trim($eye_color[1]); }
													if (isset($eye_color)) { $extra_fields['eye_color'] = $eye_color;}
														### BEGIN IMAGE EXTRACTION ###
													# Get image link
													$imgLnk =  'http://iml.slsheriff.org/imageservlet?sysid='.$sysID.'&imgsysid='.$imgSysID;
													# set image name
													$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
													# set image path
											        $imagepath = '/mugs/utah/saltlake/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
											        # create mugpat
		

											       	$mugpath = $this->set_mugpath($imagepath);
													$flag = false;
													if (isset($gender) && isset($race))
													{
														$criteria = array(
															'age' => (int)$age,
															'scrape' => 'saltlake',
															'firstname' => strtoupper($firstname),
															'lastname' => strtoupper($lastname),
															'gender' => strtoupper($gender),
															'race' => strtoupper($race),
															'birth_year' => array('$gte' => (string)($birth_year - 1), '$lte' => (string)($birth_year + 1)),
															'eye_color' => strtoupper($eye_color),
															'hair_color' => strtoupper($hair_color)
														);
														$exists = Mango::factory('offender')->load(1, null, null, array(), $criteria);

														if ($exists->loaded())
														{
															$flag = true;		
														}
													}	
													
													$imgpath = $mugpath.$imagename.'.png';
													if ($flag == false)
													{
														//@todo find a way to identify extension before setting ->imageSource
														$this->imageSource    = $imgLnk;
												        $this->save_to        = $imagepath.$imagename;
												        $this->set_extension  = true;
												        $get = $this->download('gd');
														# validate against broken image
														if ( ! $get) { return 102; }		
														$this->convertImage($mugpath.$imagename.'.jpg');
													}
													else
													{
														$old_img = $exists->image;
														$img = Image::factory($old_img);
														$img->crop(400, 480, 0, 0);
														$img->save($imgpath);
													}
											        
													# now run through charge logic
													$chargeCount = count($fcharges);
													# run through charge logic
											        if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
											        {
											            $mcharges 	= $this->charges_prioritizer($list, $fcharges);
														if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in Saltlake scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
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
													$dbcharges = array_unique($dbcharges); // make unique
													$dbcharges = array_merge($dbcharges); // reset keys
													
													# BOILERPLATE DATABASE INSERTS
													
													$offender = Mango::factory('offender', 
										                array(
										                	'scrape'		=> $this->scrape,
										                	'state'			=> strtolower($this->state),
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
													//$img = 'http://xw.textdata.com:81/photo/seorj/0109731.jpg';
													
												} else { return 101; }
											} else { return 101; }
										} else { return 101; }
									} else { return 101; }
								} else { return 101; }
							} else { return 103; } // db validation									
						} else {
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
										$charge->new 	= (int)0;
										$charge->update();
									}	
								}
							} 
				            return 104; 
						} // ncharges validation
					} else { return 101; } // charges match validation failed
				} else { return 101; } // booking_date validation check failed 
			} else { return 101; } // booking_id validation check fail
		} else { return 101; } // fullname validation check failed	
	} // end extraction 
} // class end