<?php defined('SYSPATH') or die('No direct script access.');


/**
 * Model_Muskegon
 *
 * @package Scrape
 * @TODO 	fix a bug where it fails where two rows have the same name (ajax index will often swap the order)
 * @NOTES 	Often times the offender image will be replaced by a different offender image of the same person
 * 			Not sure why this happens so often, but the initial image will be scaped and not replaced
 * 			
 * @author  Winter King
 * @url     http://www.mcd911.net/p2c/jailinmates.aspx
 * @notes booking_date will fail on curtis holden 
 */
class Model_NewMuskegon extends Model_Scrape
{
	private $scrape 	= 'muskegon';
	private $state		= 'michigan';
	private $cookies 	= '/tmp/muskegon_cookies.txt';
	private $user_agent = "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6";
	
	
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
	
    function scrape() 
    {
		$new = 0; // this is a counter for new offenders scraped
        # curl 'http://www.mcd911.net/p2c/jqHandler.ashx?op=s' to get total rows
        $index = $this->curl_index();
        echo $index;
        $rows = $index['records'];

        # loop x time where x = $rows 
        # $i can be changed to represent the starting row... 
  		$error_codes = array();
        for($i = 0; $i < $rows; $i++)
		{
        	unset($fcharges);
            unset($charge1);
            unset($charge2);
            unset($charges);
			unset($mcharges);
			unset($dbcharges);
			unset($table);
            
			# initial post to get viewstate and eventvalidation variables
	        $home = $this->curl_handler();
	        
	        # get __VIEWSTATE 
	        //<input type="hidden" name="__VIEWSTATE" id="__VIEWSTATE" value="/wEPDwUKMTYwNTM2Nzk4OQ9kFgICAQ9kFgYCAw9kFgICAQ9kFgICAw8PFgoeBFRleHQFJFN1bW1pdCBDb3VudHkgSmFpbCBNYW5hZ2VtZW50IFN5c3RlbR4JRm9yZUNvbG9yCqQBHglGb250X1NpemUoKiJTeXN0ZW0uV2ViLlVJLldlYkNvbnRyb2xzLkZvbnRVbml0BVNtYWxsHgpGb250X05hbWVzFQEFQXJpYWweBF8hU0IChAxkZAIFD2QWBAIBD2QWBAIBD2QWCAIFDw9kFgIeCW9ua2V5ZG93bgVwaWYgKChldmVudC53aGljaCA9PSAxMykgfHwgKGV2ZW50LmtleUNvZGUgPT0gMTMpKSB7ZG9jdW1lbnQuZ2V0RWxlbWVudEJ5SWQoJ2J0blN1Ym1pdCcpLmNsaWNrKCk7IHJldHVybiBmYWxzZTt9IGQCCQ8PZBYCHwUFcGlmICgoZXZlbnQud2hpY2ggPT0gMTMpIHx8IChldmVudC5rZXlDb2RlID09IDEzKSkge2RvY3VtZW50LmdldEVsZW1lbnRCeUlkKCdidG5TdWJtaXQnKS5jbGljaygpOyByZXR1cm4gZmFsc2U7fSBkAg0PD2QWAh8FBXBpZiAoKGV2ZW50LndoaWNoID09IDEzKSB8fCAoZXZlbnQua2V5Q29kZSA9PSAxMykpIHtkb2N1bWVudC5nZXRFbGVtZW50QnlJZCgnYnRuU3VibWl0JykuY2xpY2soKTsgcmV0dXJuIGZhbHNlO30gZAIRDxAPFgIeC18hRGF0YUJvdW5kZ2QQFQMADUdsZW53b29kIEphaWwSU3VtbWl0IENvdW50eSBKYWlsFQMCLTEBNAEzFCsDA2dnZ2RkAgMPZBYCAgMPDxYCHgdWaXNpYmxlaGRkAgUPZBYGAgEPD2QWAh4Hb25DbGljawUpd2luZG93Lm9wZW4oJ2h0dHA6Ly93d3cuZW1lcmFsZHN5cy5jb20nKTtkAgMPDxYGHwAFPEluZm9ybWF0aW9uIGluIHRoaXMgU3lzdGVtIGlzIHRoZSBQcm9wZXJ0eSBvZiBTdW1taXQgQ291bnR5Lh8BCiMfBAIEZGQCBQ8PFgIeB1Rvb2xUaXAFHFdlZG5lc2RheSwgRmVicnVhcnkgMDIsIDIwMTFkZAIGDxYSHghTdHJhdGVneQspjwFTdHJlbmd0aENvbnRyb2xzLlNjcm9sbGluZy5TY3JvbGxTdHJhdGVneSwgU3RyZW5ndGhDb250cm9scy5TY3JvbGxpbmcsIFZlcnNpb249MS4yLjEzMzEuMTU5NzYsIEN1bHR1cmU9bmV1dHJhbCwgUHVibGljS2V5VG9rZW49YjQwNzBmNTJiZDA5NDgyNQEeCU1haW50YWluWGgeCE1haW50YWluZx4NTGFzdFBvc3RiYWNrWQUBMB4NTGFzdFBvc3RiYWNrWAUBMB4LVXNlT25zY3JvbGxnHglNYWludGFpbllnHgxUYXJnZXRPYmplY3RlHglVc2VPbmxvYWRnZGQND51Wu+8/4HzOy2H8iI5kPxCOEQ==" />     
	        preg_match_all('/id\=\"\_\_VIEWSTATE\"\svalue\=\"([^"]*)"/', $home,  $matches,  PREG_PATTERN_ORDER);       
	        @$vs = $matches[1][0];
	        
	        # get __EVENTVALIDATION
	        //<input type="hidden" name="__EVENTVALIDATION" id="__EVENTVALIDATION" value="/wEWCALUu+aNBAK7zu7HAgK7zqYkArvOspsPAvqxlIIPAvWx2IEPAvSx2IEPAsKL2t4DO1kp3Q2GcOzI/TmQVfQHevcD8gk=" />
	        preg_match_all('/id\=\"\_\_EVENTVALIDATION\"\svalue\=\"([^"]*)"/', $home, $matches, PREG_PATTERN_ORDER);
	        @$ev = $matches[1][0];
	        
	        # check for failure 
	        if (!empty($ev) && !empty($vs)) 
			{
				# get the first details page
	            $post = '__EVENTTARGET=&__EVENTARGUMENT=&__LASTFOCUS=&__VIEWSTATE='.urlencode($vs).'&__EVENTVALIDATION='.urlencode($ev).'&ctl00%24ctl00%24DDLSiteMap1%24ddlQuickLinks=0&ctl00%24ctl00%24mainContent%24CenterColumnContent%24hfRecordIndex='.$i.'&ctl00%24ctl00%24mainContent%24CenterColumnContent%24btnInmateDetail=';
	            $details = $this->curl_handler($post); 
	            
	            # ok first thing check the index again and immediatly check to make sure the
	            # $index['rows'][$row]['book_id'] corresponds to the proper firstname, lastname and booking_date
	            
	            # ok need to regex fullname 
	            # looking for <span id="ctl00_ctl00_mainContent_CenterColumnContent_lblName" class="ShadowBoxFont" style="font-weight:bold;">ADAMS, SUSAN LOUISE</span>
	            //$check = preg_match_all('/Full Name :\<\/span\>\<\/TD\>[^\<]*\<TD[^\>]*\>[^\<]*\<span[^\>]*\>([^\<]*)/', $result, $fullname);
	            //ctl00_ctl00_mainContent_CenterColumnContent_lblName
	            $errors = array();
	          	preg_match('/\_mainContent\_CenterColumnContent\_lblName[^\>]*\>([^\<]*)/', $result, $matches);
			    if (!empty($matches[1])) //check to make these is a match for fullname
			    {
					$fullname = $matches[1];
		            #remove dot and trim so it doesn't mess up the image filename
		            $fullname = preg_replace('/\./', '', $fullname);
					$fullname = trim($fullname);
		            # Explode and trim fullname
		            # Set first and lastname
		            $explode      = explode(',', trim($fullname));
		            $lastname     = trim($explode[0]);
		            $explode      = explode(' ', trim($explode[1]));
		            $firstname    = trim($explode[0]);				
					# get age
					preg_match('/\_mainContent\_CenterColumnContent\_lblAge[^\>]*\>([^\<]*)/', $result, $matches);
		            if (!empty($matches[1])) //check for match on age { $errors[] = 'Age not found on pass: ' . $i . "\n" ; }	
					{
			            $age = intval(trim($matches[1]));
			 			# get booking date
			 			preg_match('/\_mainContent\_CenterColumnContent\_lblArrestDate[^\>]*\>([^\<]*)/', $result, $matches);		
						if (!empty($matches[1])) //check for match on booking date { $errors[] = 'Booking Date not found on pass: ' . $i . "\n" ; }
						{			           
						    $booking_date = strtotime($matches[1]);
				            # do my checkes now 
				            # THIS IS VERY CRITICAL           
				            $index = $this->curl_index();
							# this is a little sloppy but sometimes the offender will have
							# two first names and I only grab the first one
							# so on the check I need to only look at the first firstname as well
							# explode on space and grab the first one
							$indexfn = explode(' ', $index['rows'][$i]['firstname']);
				            if (($indexfn[0] == $firstname)) //check firstname with index to ensure correct data
				            {
				            	
								if ($index['rows'][$i]['lastname']  == $lastname) //check lastname with index to ensure correct data
								{
									
									if (strtotime($index['rows'][$i]['disp_arrest_date'])  == $booking_date) //check booking_date with index 
									{
													
										if ($index['rows'][$i]['age']  == $age)  //check age with index  { $errors[] =  'Age mismatch on line: ' . $i . "\n"; }
										{
											//if ($i == 25) { exit; }
											# check passed so set booking_id
					            			$booking_id = 'muskegon_' . $index['rows'][$i]['book_id'];	
											//echo 'precheck: ' . $booking_id . "\n";
											
											# now check for an existing offender
											$offender = Mango::factory('offender', array(
												'booking_id' => $booking_id
											))->load();	
										
											# run the extraction if they are new
											if (empty($offender->booking_id)) 
											{
												######BEGIN EXTRACTION######
												# get charges
									           	$this->stripTags = false;
									            $this->anchorWithin = true;
									            $this->source = $result; 
									            $this->headerRow = true;
									            $this->anchor = "ctl00_ctl00_mainContent_CenterColumnContent_dgMainResults";
									            $table = $this->extractTable();
									            foreach ($table as $key => $value)
									            {							            	
									                $charges[] = $value['Charge'];      
									            }
												$dbcharges = $charges; // this is setting an array for database insert
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
												# validate for new charge
												if (empty($ncharges)) // skip the offender if a new charge was found
												{
													# set image name
							           	 			$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
							            			# set image path
										            $imagepath = '/mugs/michigan/muskegon/'.date('Y', $booking_date).'/week '.$this->find_week($booking_date).'/';
										            # create mugpath
										            $mugpath = $this->set_mugpath($imagepath);     
										            # curl the details page and leave it open
												    $url = 'http://www.mcd911.net/p2c/jailinmates.aspx';
										            $fields = '__EVENTTARGET=&__EVENTARGUMENT=&__LASTFOCUS=&__VIEWSTATE='.urlencode($vs).'&__EVENTVALIDATION='.urlencode($ev).'&ctl00%24ctl00%24DDLSiteMap1%24ddlQuickLinks=0&ctl00%24ctl00%24mainContent%24CenterColumnContent%24hfRecordIndex='.$i.'&ctl00%24ctl00%24mainContent%24CenterColumnContent%24btnInmateDetail=';
										            $ch = curl_init();
										            curl_setopt($ch, CURLOPT_URL, $url);
													curl_setopt($ch, CURLOPT_TIMEOUT, 0);
										            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
										            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6"); 
										            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
										            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
										            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
										            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
										            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
										            curl_setopt($ch, CURLOPT_REFERER, 'http://www.mcd911.net/p2c/jailinmates.aspx'); 
										              
										            $result = curl_exec($ch);    
										            //$info   = curl_getinfo($ch);
													//sleep(1);
												    # get the image
										            # this is the second curl request for the details curl request 
										            $this->imageSource    = 'http://www.mcd911.net/p2c/Mug.aspx';
										            $this->save_to        = $imagepath.$imagename;
										            $this->set_extension  = true;
										            $this->cookie         = $this->cookies;
										            $this->handle         = $ch;
										            $this->download('curl');
													# check for image size -- 15.3 KB is a placeholder
													// 15667 is the byte size of a placeholder
													if (filesize($this->save_to . '.jpg') > 16000) 
													{
														# ok I got the image now I need to do my conversions
											            # convert image to png.
											            $this->convertImage($mugpath.$imagename.'.jpg');
											            $imgpath = $mugpath.$imagename.'.png';
											            
											            # now run through charge logic
											            # trim and uppercase the charges
											            foreach ($charges as $value)
											            {
											                $fcharges[] = strtoupper(trim($value));
											            }
														# remove duplicates
											            $fcharges = array_unique($fcharges);  
											            $chargeCount = count($fcharges); //set charge count   
											            #set list paths
											            // this is the config file with the order of charges and charge abbreviations for the mugstamper
											           	# run through charge logic
								   						
														if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
											            {
											                $mcharges 	= $this->charges_prioritizer($list, $fcharges);
															if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in Muskegon scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
											                $mcharges = array_merge($mcharges);   
											                $charge1 = $mcharges[0];
											                $charge2 = $mcharges[1];    
											                $charges 	= $this->charges_abbreviator($list, $charge1, $charge2); 
											                $this->mugStamp($imgpath, $fullname, $charges[0], $charges[1]);
											            }
											            else if ( $chargeCount == 2 )
											            {
											                $fcharges = array_merge($fcharges);
											                $charge1 = $fcharges[0];
											                $charge2 = $fcharges[1];   
											                $charges 	= $this->charges_abbreviator($list, $charge1, $charge2);
											                $this->mugStamp($imgpath, $fullname, $charges[0], $charges[1]);           
											            }
											            else 
											            {
											                $fcharges = array_merge($fcharges);
											                $charge1 = $fcharges[0];    
											                $charges 	= $this->charges_abbreviator($list, $charge1);       
											                $this->mugStamp($imgpath, $fullname, $charges[0]);   
											            }
											            
														//now get extra fields
														//<span id="ctl00_ctl00_mainContent_CenterColumnContent_lblAge" class="ShadowBoxFont" style="font-weight:bold;">21 YEARS OLD</span></p>
														$extra_fields = array();
														
														$check = preg_match('/lblAge\"[^>]*\>([^<]*)\</', $result, $age);
														if ($check) { $age = preg_replace("/\D/", "", $age[1]); $age = trim($age); $age = (int)$age; } // rip out numbers, trim and make it an int
														if (isset($age)) { $extra_fields['age'] = $age;}
													
														$check = preg_match('/lblRace\"[^>]*\>([^<]*)\</', $result, $race);
														if ($check) { $race = trim($race[1]); }
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
														
														$check = preg_match('/lblSex\"[^>]*\>([^<]*)\</', $result, $gender);
														if ($check) { $gender = trim(strtoupper($gender[1])); }
														if (isset($gender)) { $extra_fields['gender'] = $gender; }
														
											            // Abbreviate FULL charge list
													    $dbcharges = $this->charges_abbreviator_db($list, $dbcharges); 
													    $dbcharges = array_unique($dbcharges);
											            # BOILERPLATE DATABASE INSERTS
													    $offender = Mango::factory('offender', 
											                array(
											                	'scrape'		=> $this->scrape,
											                	'state'			=> $this->state,
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
														$county = Mango::factory('county', array('name' => $this->scrape, 'state' => $this->state))->load();
														if (!$county->loaded())
														{
															$county = Mango::factory('county', array('name' => $this->scrape, 'state' => $this->state))->create();
														}
														$county->booking_ids[] = $booking_id;
														$county->update();
														# END DATABASE INSERTS				
														####### END EXTRACTION ####### 	
														
														$new++;
														# update report
														$week_report = Mango::factory('report', array(
															'scrape' => 'muskegon',
															'year'	 => date('Y'),
															'week'	 => $this->find_week(time())
														))->load();
														if (!$week_report->loaded())
														{
															$week_report = Mango::factory('report', array(
												                'scrape' => $this->scrape,
												                'year'   => date('Y'),
												                'week'   => $this->find_week(time())
											            	))->create();
														}
														$week_report->successful = ($week_report->successful + $new);	
														$week_report->update();
														// updates successful
														 		
													} else {  unlink($this->save_to . '.jpg'); $error_codes[] = 102; } // placeholder validation failed
												} else {// new charges validation failed
													# add new charges to the charges collection
													foreach ($ncharges as $key => $value)
													{
														$value = preg_replace('/\s/', '', $value);
														#check if the new charge already exists FOR THIS COUNTY
														$check_charge = Mango::factory('charge', array('county' => $this->scrape, 'charge' => $value, 'new' => 1))->load();
														if (!$check_charge->loaded())
														{
															if (!empty($value))
															{
																$charge = Mango::factory('charge')->create();	
																$charge->charge = $value;
																$charge->order = (int)0;
																$charge->county = $this->scrape;
																$charge->new 	= (int)1;
																$charge->update();
															}	
														}
													}	
													$info['New_Charges'] = array_unique($ncharges);
													//exit;
												}
												
											} else { $error_codes[] = 103; } //end database check
																												
										} else { $error_codes[] = 101; } //end age check
										
									}  else { $error_codes[] = 101; } //booking_date check
									
								} else { $error_codes[] = 101; } //lastname check 	
									
							} else { $error_codes[] = 101; } //firstname check
							
						} else { $error_codes[] = 101; } //booking date match check
						
					} else { $error_codes[] = 101; } //age match check   
					
				} else { $error_codes[] = 101; } //fullname match check	
			
			} else { $error_codes[] = 101; }// vs and ev validation
            		
		} //end loop
		$info['error_codes'] = $error_codes;
		$info['Total'] = $rows;
    	$info['Successful'] = $new; 
		return $info;
	}
	
	
	
	# initial post to get viewstate and eventvalidation variables
	function curl_handler($post = null)
	{
        #set curl variables
        $url = 'http://www.mcd911.net/p2c/jailinmates.aspx';
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent); 
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_REFERER, $url); 
        if ($post)
		{
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post); 	
		}
        $home = curl_exec($ch);
        curl_close($ch);
		return $home;
	}
	
    # This function will curl the index with some values
    function curl_index()
    {
        $url = 'http://www.mcd911.net/p2c/jqHandler.ashx?op=s';
        # post fields = 't=ii&_search=false&nd=1297713697870&rows=10&page=1&sidx=disp_name&sord=asc';
        $fields = 't=ii&_search=false&nd=&rows=10000&page=1&sidx=disp_name&sord=asc';
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->user_agent); 
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_REFERER, 'http://www.mcd911.net/p2c/jailinmates.aspx'); 
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        $index = curl_exec($ch);
        //$info = curl_getinfo($ch);
        curl_close($ch);
        $index = json_decode($index, true);        
        return $index;
    }

	function extraction($details, $booking_id)
	{
		
	}

}