<?php defined('SYSPATH') or die('No direct script access.'); 
 
/**
 * Model_Mansfield
 *
 * @notes ok this one is like muskegon but trickier because the paging elipsis (...)
 *  	  can't filter because it does exact fn or ln match
 *   	  need to determine how many pages there are in the index
 *   	  to do this I loook for the ... in the paging <TD> cell
 *   	  if one is found, then I simulate the click and check again
 *   	  if none found, then I get the last pagination number which will be the total pages
 * 
 * @package Scrape
 * @author Winter King
 * @URL: http://p2c.mansfield-tx.gov/jailinmates.aspx
 */
class Model_mansfield extends Model_Scrape
{
	private $scrape		= 'mansfield';
	private $state		= 'texas';
    private $cookies 	= '/tmp/mansfield_cookies.txt';
	
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
	
	/*
	* SUDO to handle paging
	* 1. curl initial index page
	* 2. Set page number
	* 3. loop through detail pages and extact offender
	* 4. flip page
	*    a. increment page number
	* 	 b. check array to determine which event target needs passed next
	* 		(this requires a compare on page number to array and then grab corresponding event target)
	*    c. curl main for next page
	* 5. Repeat steps 3 - 4 until $page is that last one in $matches array
	* scrape - main scrape function calls the curls and handles paging
 	* 
	* @$race - B(Black), A(Asian),  H(Hispanic), N(Non-Hispanic), W(White)
	* @$sex  - M(Male), F(Female)
	* @params $date - timestamp of begin date
	* @return $info - passes to the controller for reporting
	*/
    function scrape() 
	{
    	$index = $this->curl_main();	
		# set page number for paging
		$page = (int)1; // starts on page 1
		$flag = false;
		while ($flag == false) // begin paging loop
		{
			# get __VIEWSTATE 
	        preg_match_all('/id\=\"\_\_VIEWSTATE\"\svalue\=\"([^"]*)"/Uis', $index,  $matches,  PREG_PATTERN_ORDER);       
	        # get __EVENTVALIDATION
	        preg_match_all('/id\=\"\_\_EVENTVALIDATION\"\svalue\=\"([^"]*)"/Uis', $index, $matches2, PREG_PATTERN_ORDER);
	       	if ($matches[1][0] && $matches2[1][0])
			{
				$vs = $matches[1][0];	
				$ev = $matches2[1][0];
				# match all  
				$check = preg_match_all('/\<a\shref\=\"javascript\:\_\_doPostBack\(\'(.*)\'\,\'\'\)\"\>(.*)\<\/a\>/Uis', $index, $matches);
				if ($check)
				{
					
					# build details link array
					$detail_links = array();
					$check = preg_match_all('/Linkbutton1\".*\(\'(.*)\'\,/Uis', $index, $detail_links);
					if ($check)
					{
						#loop through detail pages
						foreach($detail_links[1] as $key => $et)
						{
							$post = '__EVENTTARGET='.urlencode($et).'&__EVENTARGUMENT=&__LASTFOCUS=&__VIEWSTATE='.urlencode($vs).'&__EVENTVALIDATION='.urlencode($ev).'&ctl00%24ctl00%24DDLSiteMap1%24ddlQuickLinks=0&ctl00%24ctl00%24mainContent%24CenterColumnContent%24txtLastName=&ctl00%24ctl00%24mainContent%24CenterColumnContent%24txtFirstName=';
							$details = $this->curl_main($post);
							# begin extraction
							$extraction = $this->extraction($details);
							if ($extraction == 100) { $this->report->successful = ($this->report->successful + 1); $this->report->update(); }
	                        if ($extraction == 101) { $this->report->other = ($this->report->other + 1); $this->report->update(); }
	                        if ($extraction == 102) { $this->report->bad_images = ($this->report->bad_images + 1); $this->report->update(); }
	                        if ($extraction == 103) { $this->report->exists = ($this->report->exists + 1); $this->report->update(); }
	                        if ($extraction == 104) { $this->report->new_charges = ($this->report->new_charges + 1); $this->report->update(); }
	                        $this->report->total = ($this->report->total + 1); $this->report->update();
						}
					} else { $this->report->info = 'ERROR: detail links not matched'; $this->report->update(); return false; } // detail links not matched
					$page++;
					# check for the next page in level 2 $matches array 
					if (in_array($page, $matches[2])) // next page found in paging array so continue with pageflip
					{
						foreach ($matches[2] as $key => $value)
						{
							if ((int)$value == $page) 
							{
								$link_key = $key; 
								break;
							}
						}
						// phew ok this gives me the next event target variable in order
						// unless of course the next link is elipsis
						$et   = $matches[1][$link_key];
						$post = '__EVENTTARGET='.urlencode($et).'&__EVENTARGUMENT=&__LASTFOCUS=&__VIEWSTATE='.urlencode($vs).'&__EVENTVALIDATION='.urlencode($ev).'&ctl00%24ctl00%24DDLSiteMap1%24ddlQuickLinks=0&ctl00%24ctl00%24mainContent%24CenterColumnContent%24txtLastName=&ctl00%24ctl00%24mainContent%24CenterColumnContent%24txtFirstName=';
						$index = $this->curl_main($post);
					}
					else if (in_array('...', $matches[2])) // Next page was not found but an elipsis was. That means this is the last page in the list before elipsis
					{									   
						# click elipsis
						$index = $this->curl_main($post);
						foreach ($matches[2] as $key => $value)
						{
							if (trim($value) == '...') 
							{
								$link_key = $key; 
								break;
							}
						}
						$et   = $matches[1][$link_key];
						$post = '__EVENTTARGET='.urlencode($et).'&__EVENTARGUMENT=&__LASTFOCUS=&__VIEWSTATE='.urlencode($vs).'&__EVENTVALIDATION='.urlencode($ev).'&ctl00%24ctl00%24DDLSiteMap1%24ddlQuickLinks=0&ctl00%24ctl00%24mainContent%24CenterColumnContent%24txtLastName=&ctl00%24ctl00%24mainContent%24CenterColumnContent%24txtFirstName=';
						$index = $this->curl_main($post);
					}
					else // end of pages so break while loop
					{
						$flag = true;
					} 
					# set link for paging	
				} else {  return false; } // was unable to find paging links
			} else { mail('winterpk@bychosen.com', 'Error in ' . $this->scrape, 'viewstate and eventvalidation failed to match'); return false; } // ev and vs validation failed
		} // end paging loop
		$this->report->failed = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
        $this->report->finished = 1;
        $this->report->stop_time = time();
        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
        $this->report->update();
        return true;
	}
	
	/**
	* curl_main - get the search page 
	*
	*/
	function curl_main($post = null)
	{
        $url = 'http://p2c.mansfield-tx.gov/jailinmates.aspx';
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		if ($post)
		{
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}
        curl_setopt($ch, CURLOPT_REFERER, $url); 
        $main = curl_exec($ch);
		curl_close($ch);	
		return $main;
	}


	/**
	* extraction - validates and extracts all data
	*
	* 
	* @params $details  - offenders details page
	* @return $ncharges - numerical array of new charges found
	* @return false  	- on failed extraction
	* @return true 		- on successful extraction
	* 
	*/
	function extraction($details)
	{
		$county = 'mansfield';
		# extract profile details
		# required fields
		// _lblName" class="ShadowBoxFont" style="font-weight:bold;">ADKISON, HUNTER CHEYENNE</span>
		$check = preg_match('/\_lblName[^>]*>(.*)<\/span/Uis', $details, $match);
		if ($check)
		{
			$firstname = null;
			$lastname = null;
			$fullname  = strip_tags($match[1]);
			$fullname = preg_replace('/\./', '', $fullname);
			$fullname = trim($fullname);
            # Explode and trim fullname
            # Set first and lastname
            $explode      = explode(',', trim($fullname));
            $lastname     = trim($explode[0]);
            $explode      = explode(' ', trim($explode[1]));
            $firstname    = trim($explode[0]);
            if ($firstname && $lastname)
			{
				// _lblArrestDate" class="ShadowBoxFont" style="font-weight:bold;">3/30/2011</span>
				$check = preg_match('/_lblArrestDate[^>]*>(.*)<\/span/Uis', $details, $match);
				if ($check)
				{ 
					$booking_date = strtotime(strip_tags(trim($match[1])));
					# get charges
			       	$this->stripTags 		= false;
			        $this->anchorWithin 	= true;
			        $this->source 		= $details; 
			        $this->headerRow 		= false;
					$this->startRow 		= 2;
			      	$this->anchor 		= "Charge";
			        $charges_table 			= $this->extractTable();
					//echo $details;
					if (!empty($charges_table))
					{
						$check = preg_match('/ctl00\_ctl00\_mainContent\_CenterColumnContent\_dgMainResults.*<\/table/Uis', $details, $match);
						if ($check)
						{
							$check = preg_match_all('/<td>(.*)<\/td>/Uis', $match[0], $matches);
							if ($check)
							{
								array_shift($matches[1]);
								array_shift($matches[1]);
								array_shift($matches[1]);
								array_shift($matches[1]);
								$charges = array();
								foreach($matches[1] as $key => $match)
								{
									if ($key == 0 || ($key %3) == 0)
									{
										$charges[] = strip_tags(preg_replace('/\(.*\)/Uis', '', $match));
									}
								}	
							} else { return 101; }
						} else { exit; return 101; }
						$smashed_charges = array();
						foreach($charges as $charge)
						{
							// smash it
							$smashed_charges[] = preg_replace('/\s/', '', $charge);
						}
						if (!empty($charges))
						{
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
							 	$fcharges = array_unique($charges);
								$fcharges = array_merge($fcharges);
								$dbcharges = $fcharges;
								#get booking_date
								# extra fields
								$extra_fields = array();
								$age = null; // _lblAge
								$check = preg_match('/_lblAge[^>]*>(.*)<\/span/Uis', $details, $age);
								if ($check) 
								{
									$age = preg_replace("/\D/", "", $age); 
									$age = trim((int)$age[1]);
									$extra_fields['age'] = $age;
									#build booking_id based on $fullname, $age, $booking_date 
									$booking_id = preg_replace('#\W#', '', $fullname) . $age . $booking_date;
									$booking_id = $this->scrape . '_' . md5($booking_id); // hash it with md5
									# database validation 
									$offender = Mango::factory('offender', array(
										'booking_id' => $booking_id
									))->load();
									# validate against the database
									if (empty($offender->booking_id)) 
									{
										$race = null; // _lblRace
										$check = preg_match('/_lblRace[^>]*>(.*)<\/span/Uis', $details, $race);
										if ($check) 
										{
											$race = strip_tags(trim($race[1])); 
											# run it though the race mapper
											$mrace = $this->race_mapper($race);
											if ($mrace) { $extra_fields['race'] = $mrace; }
										}
										$gender = null; // _lblSex
										$check = preg_match('/_lblSex[^>]*>(.*)<\/span/Uis', $details, $gender);
										if ($check) 
										{
											$gender = strip_tags(trim($gender[1])); 
											$extra_fields['gender'] = $gender;
										}
										$dob = null; // _lblDOB
										$check = preg_match('/_lblDOB[^>]*>(.*)<\/span/Uis', $details, $dob);
										if ($check) 
										{
											$dob = strtotime(strip_tags(trim($dob[1])));
											$extra_fields['dob'] = $dob;
										}
										// http://p2c.mansfield-tx.gov/Mug.aspx
										# set image name
										$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
										# set image path
								        $imagepath = '/mugs/texas/mansfield/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
								        # create mugpath
								        $mugpath = $this->set_mugpath($imagepath);

										#get image
										//http://p2c.mansfield-tx.gov/Mug.aspx
										$this->imageSource    = 'http://p2c.mansfield-tx.gov/Mug.aspx';
							            $this->save_to        = $imagepath.$imagename;
							            $this->set_extension  = true;
							            $this->cookie         = $this->cookies;
							            $this->download('curl');
										if (file_exists($this->save_to . '.jpg')) //validate the image was downloaded
										{
											if (filesize($this->save_to . '.jpg') > 16000) 
											{
												# ok I got the image now I need to do my conversions
										        # convert image to png.
										        $this->convertImage($mugpath.$imagename.'.jpg');
										        $imgpath = $mugpath.$imagename.'.png';
												
												# now run through charge logic
												$chargeCount = count($fcharges);
												# run through charge logic
												$mcharges 	= array(); // reset the array
										        if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
										        {
										            $mcharges 	= $this->charges_prioritizer($list, $fcharges);
													if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in mansfield scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
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
												/*
												# BOILERPLATE DATABASE INSERTS
												$offender = Mango::factory('offender', 
									                array(
									                	'scrape'		=> $this->scrape,
									                	'state'			=> strtolower($this->state),
												 		'county' 		=> $county,
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
												 * 
												 */
                                                return 100;
                                                    ### END EXTRACTION ###
											 
											} else {  unlink($this->save_to . '.jpg'); } // placeholder validation failed 
										} else { return false; } // image file does not exist
									} else { return false; } // database validation failed
								} else { return false; } // age validation failed
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
											$charge->new 	= (int)0;
											$charge->update();
										}	
									}
								}
					           	return 104;
							} // ncharges validation
						} else { return false; } // charges not found
					} else { return false; } // charges not found
				} else { return false; } // booking_date mach failed
			} else { return false; } // firstname and lastname validation failed
		} else { return false; } // name match failed
	} // end extraction
} // class end