<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Bourbon
 *
 * @package Scrape
 * @author Winter King
 * @params takes a date range
 * @description this one is powered by JailTracker which is .NET (.aspx) which means viewstate and eventvalidation variables
 * @url http://www.bourboncountydetention.com/bourbon_inmatelist.html
 */
class Model_Bourbon extends Model_Scrape
{
	private $scrape 	= 'bourbon';
	private $state 		= 'ohio';
    private $cookies 	= '/tmp/bourbon_cookies.txt';
	
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
    function scrape($sdate, $edate) 
    {
    	# set flag and start loop for paging
    	$flag  = false;
		$num_pages  = false;
		$count = 0;
		$sort = 'dgInmates$ctl02$ctl03'; // this is the sort js postback	
		$index = $this->curl_index();
		#get __VIEWSTATE
		$check = preg_match('/id\=\"\_\_VIEWSTATE\"\svalue\=\"([^"]*)"/', $index,  $vs);
		if ($check)
		{
			$vs = $vs[1];	
			#get __EVENTVALIDATION
			$check = preg_match('/id\=\"\_\_EVENTVALIDATION\"\svalue\=\"([^"]*)"/', $index,  $ev);
			$ev = $ev[1];
			
			while ($flag == false)
			{
				//$page = urlencode('dgInmates$ctl24$ctl01');
				if ($num_pages) // check for paging
				{
					$post_backs = array();
					for($i = 0; $i < $num_pages; $i++) // loop the number of pages and build js postback var array ()
					{
						$post_backs[] = 'dgInmates$ctl24$ctl0' . ($i + 1);
						
					}
					if ($count == 7) { break; }
					if (!isset($post_backs[$count - 1])) { break; }
					$page = $post_backs[($count - 1)];
					$index = $this->handler($vs, $ev, $sdate, $edate, $post_backs[($count - 1)]);
					$check = preg_match('/id\=\"\_\_VIEWSTATE\"\svalue\=\"([^"]*)"/', $index,  $vs);
					$vs = $vs[1];
					#get __EVENTVALIDATION
					$check = preg_match('/id\=\"\_\_EVENTVALIDATION\"\svalue\=\"([^"]*)"/', $index,  $ev);
					$ev = $ev[1];
				}
				else // if num_pages isn't set then that means its the first loop so sort
				{
					$index = $this->handler($vs, $ev, $sdate, $edate, $sort);
				}
				#get __VIEWSTATE
				$check = preg_match('/id\=\"\_\_VIEWSTATE\"\svalue\=\"([^"]*)"/', $index,  $vs);
				$vs = $vs[1];
				#get __EVENTVALIDATION
				$check = preg_match('/id\=\"\_\_EVENTVALIDATION\"\svalue\=\"([^"]*)"/', $index,  $ev);
				$ev = $ev[1];
				$check = preg_match_all('/ID\=([^\']*)\'/', $index, $links);
				if ($check)
				{
					$links = array_unique($links[1]);
					$links = array_merge($links);
					foreach($links as $key => $booking_id)
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
				# check for paging
				$check = preg_match_all('/dgInmates\$ctl24\$ctl0[0-9]/', $index, $pages);
				if ($check)
				{
					// ok this means that we found paging links.  but how many?
					$num_pages = count($pages[0]);	
				}
				else 
				{
					$flag = true;
				}
				$count++;
			}
			$this->report->failed = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
			$this->report->finished = 1;
	        $this->report->stop_time = time();
	        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
	        $this->report->update();
	        return true;   
		} else { $this->report->info = 'ERROR: No viewstate found'; return false; } // no viewstate found
	}
	//__EVENTTARGET=dgInmates%24ctl02%24ctl03&__EVENTARGUMENT=&__VIEWSTATE=%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%2BMTAzPC9iPiBpbm1hdGVzIGluIHRoaXMgZmFjaWxpdHkuZGQCEQ9kFgICAQ8PFgIeCEltYWdlVXJsZGRkGAEFHl9fQ29udHJvbHNSZXF1aXJlUG9zdEJhY2tLZXlfXxYBBQ5jaGtQYXN0SW5tYXRlc%2Bkj8QPuHJGbpuFxlDJWxUfq1c2U&__EVENTVALIDATION=%2FwEWDgLwxo%2FdBgKdlcWpAgK5h62pDwLwtbnZDAKAzfnNDgLChrRGAqWf8%2B4KAs3T19YCAt%2FgvIsJAt7gvIsJAt3gvIsJAtzgvIsJAujbjfANAufbjfANxKr1MrOF%2FtNKfw0F0y8HbSQfKg8%3D&txtLastName=&txtFirstName=&chkPastInmates=on&txtBeginDate=03%2F9%2F2011&txtEndDate=03%2F16%2F2011
	
	/**
	* date_sort
	*
	* @notes  
	* @params 
	*/
	function date_sort($vs, $ev, $sdate, $edate)
	{
		$url = 'http://www.bourboncountydetention.com/InmateList/InmateList.aspx';
		$fields = '__EVENTTARGET=dgInmates%24ctl02%24ctl03&__EVENTARGUMENT=&__VIEWSTATE='.urlencode($vs).'&__EVENTVALIDATION='.urlencode($ev).'&txtLastName=&txtFirstName=&chkPastInmates=on&txtBeginDate='.urlencode($sdate).'&txtEndDate='.urlencode($edate);
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        $date_sort = curl_exec($ch);
        curl_close($ch);
		return $date_sort;
	}
	
	
	/**
	* handler
	*
	* @notes  
	* @params 
	*/
	function handler($vs, $ev, $sdate, $edate, $page = null, $sort = null)
	{
		$url = 'http://www.bourboncountydetention.com/InmateList/InmateList.aspx';
		if ($sort)
		{
			$fields = '__EVENTTARGET='.urlencode($sort).'&__EVENTARGUMENT=&__VIEWSTATE='.urlencode($vs).'&__EVENTVALIDATION='.urlencode($ev).'&txtLastName=&txtFirstName=&chkPastInmates=on&txtBeginDate='.urlencode($sdate).'&txtEndDate='.urlencode($edate);		
		}
		if ($page)
		{
			$fields = '__EVENTTARGET='.urlencode($page).'&__EVENTARGUMENT=&__VIEWSTATE='.urlencode($vs).'&__EVENTVALIDATION='.urlencode($ev).'&txtLastName=&txtFirstName=&chkPastInmates=on&txtBeginDate='.urlencode($sdate).'&txtEndDate='.urlencode($edate);		
		}
		else 
		{
			return false;
		}
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        $date_sort = curl_exec($ch);
        curl_close($ch);
		return $date_sort;
	}
	
	/**
	* date_sort
	*
	* @notes  
	* @params $page - javascript postback variable ( dgInmates$ctl24$ctl01 )
	*/
	function paging($vs, $ev, $sdate, $edate, $page)
	{
		$url = 'http://www.bourboncountydetention.com/InmateList/InmateList.aspx';
		$fields = '__EVENTTARGET='.$page.'&__EVENTARGUMENT=&__VIEWSTATE='.urlencode($vs).'&__EVENTVALIDATION='.urlencode($ev).'&txtLastName=&txtFirstName=&chkPastInmates=on&txtBeginDate='.urlencode($sdate).'&txtEndDate='.urlencode($edate);
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        $paged_index = curl_exec($ch);
        curl_close($ch);
		return $paged_index;
	}
	

	/**
	* curl_index 
	*
	* @notes  this is just to get the ev and vs
	* @return json data
	*/
	function curl_index()
	{
		$url = 'http://www.bourboncountydetention.com/InmateList/InmateList.aspx';
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        $index = curl_exec($ch);
        curl_close($ch);
		return $index;
	}
	
	
	/**
	* curl_handler - 
	*
	* 
	* @url - http://www.bourboncountydetention.com/InmateList/InmateList.aspx
	* @return 
	*/
	function curl_handler($vs, $ev, $date)
	{
		
		$url = 'http://www.bourboncountydetention.com/InmateList/InmateList.aspx';
		$fields = '__EVENTTARGET=&__EVENTARGUMENT=&__VIEWSTATE='.urlencode($vs).'&__EVENTVALIDATION='.urlencode($ev).'&txtLastName=&txtFirstName=&chkPastInmates=on&txtBeginDate='.urlencode($date).'&txtEndDate='.urlencode(date('m/d/Y')).'&btnSearch=Search';
		//echo $fields . '<hr>';
		//echo '__EVENTTARGET=&__EVENTARGUMENT=&__VIEWSTATE=%2FwEPDwUKLTc5NzIyMTMwNw8WBB4Jc29ydEZpZWxkBQhsYXN0bmFtZR4Nc29ydERpcmVjdGlvbgUDQVNDFgICAQ9kFggCBQ9kFgICAw8PFgIeBFRleHQFbihDaGVjayB0aGlzIGlmIHlvdSB3b3VsZCBsaWtlIHRvIHNlYXJjaCBieSBib29raW5nIGRhdGUgb3Igc2VhcmNoIGZvciBwYXN0IGlubWF0ZXMgd2l0aCB1bmtub3duIGJvb2tpbmcgZGF0ZS4pZGQCDQ88KwALAQAPFgoeCVBhZ2VDb3VudAIGHghEYXRhS2V5cxYUAt%2BKgr0HAoGWgr0HAo%2BVgr0HAs2Wgr0HAteWgr0HAvaWgr0HAtuWgr0HAsOVgr0HAqaWgr0HArCVgr0HAtOVgr0HAuOVgr0HAvyVgr0HAvuVgr0HApKWgr0HAu2Kgr0HAsSQgr0HAs%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%2BMTA1PC9iPiBpbm1hdGVzIGluIHRoaXMgZmFjaWxpdHkuZGQCEQ9kFgICAQ8PFgIeCEltYWdlVXJsZGRkGAEFHl9fQ29udHJvbHNSZXF1aXJlUG9zdEJhY2tLZXlfXxYBBQ5jaGtQYXN0SW5tYXRlc8%2BZKT%2FDkee%2Fo5L8fgI7eaSb%2FvV1&__EVENTVALIDATION=%2FwEWEQKg9OQxAp2VxakCArmHrakPAvC1udkMAoDN%2Bc0OAsKGtEYCpZ%2Fz7goCzdPX1gIC3%2BC8iwkC3uC8iwkC3eC8iwkC3OC8iwkC6NuN8A0C59uN8A0C5tuN8A0C7duN8A0C7NuN8A1dCbyrC9%2Bh1u5xSsg%2BbHktoaaHhA%3D%3D&txtLastName=&txtFirstName=&chkPastInmates=on&txtBeginDate=03%2F14%2F2011&txtEndDate=&btnSearch=Search';
		//exit;
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_POST, true);
        $index = curl_exec($ch);
        curl_close($ch);
		return $index;
	}
	
	
	/**
	* curl_details
	*
	* 
	* @url //http://www.bourboncountydetention.com/InmateList/InmateView.aspx?ID=2007008607
	* 
	* @return $details - html
	*/
	function curl_details($booking_id)
	{
		$url = 'http://www.bourboncountydetention.com/InmateList/InmateView.aspx?ID='.$booking_id;
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
	* @params $details  - offenders details page
	* @return $ncharges - numerical array of new charges found
	* @return false  	- on failed extraction
	* @return true 		- on successful extraction
	* 
	*/
	function extraction($details, $booking_id)
	{
		$booking_id = 'bourbon_' . $booking_id;
		$fcharges 	= array();
		$county = 'bourbon';
		# extract profile details
		//lblInmateName"><b><font face="Verdana" size="2">JAKE T ANDERS</font>
		# get fullname
 		$check = preg_match('/lblInmateName\"\>\<b\>\<[^>]*\>([^<]*)\</', $details, $fullname);
		if ($check) 
		{
			# replace the ', Jr.' if exists
			$fullname = preg_replace('/\,\sJr\./', '', trim($fullname[1]));
			
			# make sure there is no comma
			//we're only taking fullnames if there is no comma in this one
			if(strpos($fullname, ',') === false )
			{
				# extract firstname, lastname and booking_id
				$fullname  = str_replace('.', '', $fullname); //remove dot
				$fullname  = preg_replace('/\s\s+/', ' ', $fullname); //remove excess spaces
				$explode   = explode(' ', $fullname);
				$firstname = trim($explode[0]);
				$lastname  = trim($explode[count($explode) - 1]); 
				# database validation 
				$offender = Mango::factory('offender', array(
					'booking_id' => $booking_id
				))->load();	
				if ($firstname AND $lastname)
				{
					# validate against the database
					if (empty($offender->booking_id)) 
					{
						#get booking date
						//lblBookDate"><font face="Verdana" size="2">3/9/2011</font>
						$check = preg_match('/lblBookDate\"\>\<[^>]*\>([^<]*)\</', $details, $booking_date);
						if ($check)
						{
							$booking_date = trim($booking_date[1]);
							$booking_date = strtotime($booking_date);
							# get charges
							//_Description">OPER MTR VEH U/INFLU ALC/DRUGS/ETC. .08 4TH OR ></span
							$check = preg_match_all('/\_Description\"\>([^<]*)\<\/span/', $details, $matches, PREG_SET_ORDER);
							//$this->print_r2($charges) . '<hr>';
							# validate charges 
							if ($check)
							{
											
								$charges = array();
								foreach($matches as $key => $charge)
								{
									$charges[] = $charge[1];
								}
								$smashed_charges = array();
								foreach($charges as $charge)
								{
								 // smash it
								 $smashed_charges[] = preg_replace('/\s/', '', $charge);
								}
								
								$dbcharges = array();
								$dbcharges = $charges;
								# make sure to always reset arrays!
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
									
									// continue
									$fcharges = array();
									foreach ($charges as $key => $value)
									{
										$fcharges[] = trim(strtoupper($value));	
									}
									# make it unique and reset keys
									$fcharges = array_unique($fcharges);
									$fcharges = array_merge($fcharges);
									# get extra fields
									$extra_fields = array();
									
									$check = preg_match('/lblAge\"\>\<[^>]*\>([^<]*)\</', $details, $age);
									if ($check) { $age = trim($age[1]); $age = (int)$age; }
									if (isset($age)) 
									{
										$extra_fields['age'] = $age; 
									}
									
									$check = preg_match('/lblSex\"\>\<[^>]*\>([^<]*)\</', $details, $gender);
									if ($check) { $gender = strtoupper(trim($gender[1])); if ($gender == 'M') {$gender = 'MALE';} else if ($gender == 'F') { $gender = 'FEMALE'; }  }								
									if (isset($gender)) { $extra_fields['gender'] = $gender; }
									
								 	$check = preg_match('/lblHeight\"\>\<[^>]*\>([^<]*)\</', $details, $height);
									if ($check) { $height = trim($height[1]); $height = $height; }
									if (isset($height)) 
									{
										# height conversion
										$extra_fields['height'] = $this->height_conversion($height);
									}
									
								 	$check = preg_match('/lblWeight\"\>\<[^>]*\>([^<]*)\</', $details, $weight);
									if ($check) { $weight = trim($weight[1]); $weight = $weight; }
									if (isset($weight)) 
									{
										$extra_fields['weight'] = preg_replace('/[^0-9]/', '', $weight); // rip out ALL but numbers
									}
									
								 	$check = preg_match('/lblRace\"\>\<[^>]*\>([^<]*)\</', $details, $race);
									if ($check) { $race = trim($race[1]); $race = $race; }
									if (isset($race)) 
									{
										$race = $this->race_mapper($race);
										if ($race)
										{
									 		$extra_fields['race'] = $race;
										}
									}
									
								 	$check = preg_match('/lblHairColor\"\>\<[^>]*\>([^<]*)\</', $details, $hair_color);
									if ($check) { $hair_color = trim($hair_color[1]);  }
									if (isset($hair_color)) { $extra_fields['hair_color'] = $hair_color; }
									
								 	$check = preg_match('/lblEyeColor\"\>\<[^>]*\>([^<]*)\</', $details, $eye_color);
									if ($check) { $eye_color = trim($eye_color[1]); }
									if (isset($eye_color)) { $extra_fields['eye_color'] = $eye_color; }
									
									### BEGIN IMAGE EXTRACTION ###
									# Get image link
									$check = preg_match('/Image1\"[^"]*\"([^"]*)\"/', $details, $image);
									if ($check)
									{
										$check = preg_match('/nia\.gif/', $details, $nia);
										if (!$check)
										{
											$imgLnk =  $image[1];
											# set image name
											$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
											# set image path
									        $imagepath = '/mugs/ohio/bourbon/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
									        # create mugpath
									        $mugpath = $this->set_mugpath($imagepath);
											//@todo find a way to identify extension before setting ->imageSource
											$this->imageSource    = $imgLnk;
									        $this->save_to        = $imagepath.$imagename;
									        $this->set_extension  = true;
									        $get = $this->download('gd');
											# validate against broken image
											if ($get)
											{
												# check for placeholder
												if (filesize($this->save_to.'.jpg') > 10240) // make sure the filesize is at least 10kb
												{
													# use my custom face cropper here 
													//$check = $this->face_crop($this->save_to . '.jpg');
													//if ($check) {
														# ok I got the image now I need to do my conversions
												        # convert image to png.
												        $this->convertImage($mugpath.$imagename.'.jpg');
												        $imgpath = $mugpath.$imagename.'.png';
														$img = Image::factory($imgpath);
														//704px × 480
			                                        	$img->crop(500, 650)->save();
												        $imgpath = $mugpath.$imagename.'.png';
														# now run through charge logic
														$chargeCount = count($fcharges);
														# run through charge logic
														$mcharges 	= array(); // reset the array
												        if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
												        {
												            $mcharges 	= $this->charges_prioritizer($list, $fcharges);
															if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in bourbon scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
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
														$dbcharges = $this->charges_abbreviator_db($list, $dbcharges);
														$dbcharges = array_unique($dbcharges);
		
														# DATABASE BOILERPLATE INSERTS
														$offender = Mango::factory('offender', 
											                array(
											                	'scrape'		=> $this->scrape,
											                	'state'			=> strtolower($this->state),
											                    'firstname'     => $firstname,
											                    'lastname'      => $lastname,
											                    'booking_id'    => $booking_id,
											                    'booking_date'  => $booking_date,
											                    'scrape_time'	=> time(),
											                    'image'         => $imgpath,
											                    'charges'		=> $dbcharges,
											                    'county'		=> $county,                                      
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
														# END DATABASE INSERTS
														
														return 100;
														### END EXTRACTION ###	
													//} else { unlink($this->save_to . '.jpg'); return false; } // face not detected
												} else { unlink($this->save_to . '.jpg'); return 102; } // placeholder validation
											} else { return 102; } //img download validation
										} else { return 102; } //img placeholder
									} else { return 102; } // image link validation
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
							} else { return 101; } // empty charges table
						} else { return 101; } // booking_date validation
					} else { return 103; } // database validation
				} else { return 101; } 			
			} else { return 101; } // fullname comma validation failed
		} else { return 101; } // fullname validation failed
	} // end extraction					
} // class end