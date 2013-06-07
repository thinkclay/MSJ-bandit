<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Dallas
 *
 * @package Scrape
 * @author 	Winter 
 * @url 	http://www.dallascounty.org/jaillookup/search.jsp
 */
class Model_Dallas extends Model_Scrape
{
    private $scrape     = 'dallas'; //name of scrape goes here must be unique to all other scrape names
	private $county 	= 'dallas'; // if it is a single county, put it here, otherwise remove this property
    private $state      = 'texas'; // state goes here
    private $cookies    = '/tmp/dallas_cookies.txt'; // replace with <scrape name>_cookies.txt
    
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
	* @$race - B(Black), A(Asian),  H(Hispanic), N(Non-Hispanic), W(White)
	* @$sex  - M(Male), F(Female)
	* @params $date - timestamp of begin date
	* @return $info - passes to the controller for reporting
	*/
    function scrape() 
    {
		//$az = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z');
		$races  = array('A', 'B', 'H', 'N', 'W');
		$sexes = array('M', 'F');
		$home = $this->curl_home();
		foreach ($sexes as $sex)
		{
			foreach ($races as $race)
			{
				$index = $this->curl_index('_', '_', $race, $sex);
				// <img src="/jaillookup/show_image.do?url=https://www.ais.dallascounty.org/ais_dx/ReadPhoto.aspx?width=75&amp;personid=C7007E9F-37CE-0EB8-5B43-2CBDE6498D86&amp;key=F600BE95-AAFA-42C5-EF3F-2ACEE946A4BA" width="90" height="90" alt="ANDERSON" />		
				$check = preg_match_all('/defendant\_detail\.do\?recno\=(.*)\"/Uis', $index, $matches);
				if ($check)
				{
					$details_links = $matches[1];
					foreach ($details_links as $details_link)
					{
						$details = $this->curl_details(htmlspecialchars_decode($details_link, ENT_QUOTES));
						$extraction = $this->extraction($details, $details_link);
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
			}
		}
        return true; 
    }

    /**
    * curl_home
    * 
    * @url http://www.dallascounty.org/jaillookup/search.jsp
    * 
    */
    function curl_home()
    {
        $url = 'http://www.dallascounty.org/jaillookup/search.jsp';  // this will be the url to the index page
        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:9.0.1) Gecko/20100101 Firefox/9.0.1');
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $index = curl_exec($ch);
        curl_close($ch);
        return $index;
    } 
	
	/**
    * curl_index
    * 
    * @url 
    * 
    */
    function curl_index($ln, $fn, $race, $sex)
    {
    	$post = 'lastName='.$ln.'&firstName='.$fn.'&dobMonth=&dobDay=&dobYear=&race='.$race.'&sex='.$sex.'&searchType=Search+By+Prisoner+Info&bookinNumber=&caseNumber='; // build out the post string here if needed
        $url = 'http://www.dallascounty.org/jaillookup/search.do';  // this will be the url to the index page
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:9.0.1) Gecko/20100101 Firefox/9.0.1');
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post); // add post fields
        $index = curl_exec($ch);
        curl_close($ch);
		sleep(600);
        return $index;
		
    } 
      
    /**
    * curl_details
    * 
    * @url http://www.dallascounty.org/jaillookup/defendant_detail.do?recno=C7007E9F-37CE-0EB8-5B43-2CBDE6498D86&bookinNumber=12006554&bookinDate=1327687740000&dob=1987-04-22&lastName=ANDERSON&firstName=ADRIAN&sex=Male&race=Black
    *   
    */
    function curl_details($details_link)
    {				
        $url = 'http://www.dallascounty.org/jaillookup/defendant_detail.do?recno='.$details_link;
		$headers = array(
			'Host: www.dallascounty.org',
			'Referer: http://www.dallascounty.org/jaillookup/search.do',
		);
		
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:9.0.1) Gecko/20100101 Firefox/9.0.1');
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $details = curl_exec($ch);
        curl_close($ch);
		sleep(3);
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
    function extraction($details, $details_link)
    {
    	$check = preg_match('/Bookin\sNumber.*\<TD.*\<DIV.*\>(.*)\<\/DIV/Uis', $details, $matches);
		if ($check)
		{
	        $booking_id = $this->scrape . '_' . htmlspecialchars_decode(trim($matches[1]), ENT_QUOTES); // set the booking_id to <scrapename>_<booking_id>
	        // attempt to load the offender by booking_id
	        $offender = Mango::factory('offender', array(
	            'booking_id' => $booking_id
	        ))->load(); 
	        // if they are not loaded then continue with extraction, otherwise skip this offender
	        if ( ! $offender->loaded() ) 
	        {
	        	// get first and lastnames
				$check = preg_match('/<DIV\salign\=\"right\"\sclass\=\"style5\".*\>.*Name.*<\/DIV.*\<TD.*\<DIV.*\>(.*)\<\/DIV/Uis', $details, $match);
				if ($check)
				{
					// usually you'll need to explode to set firstname and lastname
					$fullname = str_replace('&nbsp;', '', trim($match[1]) );
					$explode = explode(',', $fullname);
					$lastname = trim($explode[0]);
					$explode = explode(' ', trim($explode[1]));
					$firstname = trim($explode[0]);
					// get booking date
					$check = preg_match('/<DIV\salign\=\"right\"\sclass\=\"style5\".*\>.*Bookin\sDate.*<\/DIV.*\<TD.*\<DIV.*\>(.*)\<\/DIV/Uis', $details, $match);
					if ($check)
					{
						$booking_date = strtotime(str_replace('&nbsp;', '', trim($match[1])));
						// make sure to strtotime the booking date to get a unix timestamp
						$bookingdate = strtotime($match[1]);
						// get all the charges with preg_match_all funciton
						$check = preg_match_all('/\<STRONG\>.*Charge.*<\/DIV.*\<TD.*\<DIV.*\>(.*)\<\/DIV/Uis', $details, $matches);
						if ($check)
						{
							$charges = array();
							foreach ($matches[1] as $charge)
							{
								$charges[] = $this->clean_string_utf8(str_replace('&nbsp;', '', trim($charge)));
							}
							
							// the next lines between the ### are boilerplate used to check for new charges
							
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
								// make unique and reset keys
								$charges = array_unique($charges);
								$charges = array_merge($charges);
								$fcharges = array();
								// trim and uppercase all charges to a new array
								foreach($charges as $charge)
								{
									$fcharges[] = strtoupper(trim($charge));
								}	
								$dbcharges = $fcharges;
								
								// now clear an $extra_fields variable and start setting all extra fields
								$extra_fields = array();
								$check = preg_match('/<DIV\salign\=\"right\"\sclass\=\"style5\".*\>.*Address.*<\/DIV.*\<TD.*\<DIV.*\>(.*)\<\/DIV/Uis', $details, $match);
								if ($check)
								{
									$extra_fields['address'] = str_replace('&nbsp;', ' ', htmlspecialchars_decode(trim($match[1]),  ENT_QUOTES));	
								}
								$check = preg_match('/<DIV\salign\=\"right\"\sclass\=\"style5\".*\>.*DOB.*<\/DIV.*\<TD.*\<DIV.*\>(.*)\<\/DIV/Uis', $details, $match);
								if ($check)
								{
									$extra_fields['dob'] = strtotime($match[1]);	
								}
								$extra_fields['age'] = floor(($booking_date - $extra_fields['dob']) / 31556926);
								$check = preg_match('/<DIV\salign\=\"right\"\sclass\=\"style5\".*\>.*Sex.*<\/DIV.*\<TD.*\<DIV.*\>(.*)\<\/DIV/Uis', $details, $match);
								if ($check)
								{
									$extra_fields['gender'] = strtoupper(trim($match[1]));
								}	
								$check = preg_match('/<DIV\salign\=\"right\"\sclass\=\"style5\".*\>.*Race.*<\/DIV.*\<TD.*\<DIV.*\>(.*)\<\/DIV/Uis', $details, $match);
								if ($check)
								{
									// this will map race names to our standard format for races
									// ie. African American becomes Black, 
									$extra_fields['race'] = $this->race_mapper($match[1]);	
								}
								// now get the image link and download it
								//http://www.dallascounty.org/jaillookup/show_image.do?url=https://www.ais.dallascounty.org/ais_dx/ReadPhoto.aspx?width=200&personid=C7007E9F-37CE-0EB8-5B43-2CBDE6498D86&key=F600BE95-AAFA-42C5-EF3F-2ACEE946A4BA
								$check = preg_match('/ReadPhoto\.aspx\?(.*)\"/Uis', $details, $match);
								if ($check)
								{
									
									$image_link = 'http://www.dallascounty.org/jaillookup/show_image.do?url=https://www.ais.dallascounty.org/ais_dx/ReadPhoto.aspx?'.str_replace('&amp;', '&', $match[1]);
									# set image name
									$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
									# set image path
									$imagepath = '/mugs/'.$this->state.'/'.$this->scrape.'/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
							        // $imagepath = '/mugs/'.$this->state.'/'.$this->county'/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
							        # create mugpath
							        
							        $mugpath = $this->set_mugpath($imagepath);
									$headers = array(
										'Host: www.dallascounty.org',
										'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:9.0.1) Gecko/20100101 Firefox/9.0.1',
										'Accept: image/png,image/*;q=0.8,*/*;q=0.5',
										'Accept-Language: en-us,en;q=0.5',
										'Accept-Encoding: gzip, deflate',
										'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
										'Connection: keep-alive',
										//'Referer: http://www.dallascounty.org/jaillookup/defendant_detail.do?recno=58006D9B-2986-6794-EBD5-C4DCBC4AADBA&bookinNumber=12007930&bookinDate=1328141100000&dob=1978-12-31&lastName=NGUYEN&firstName=QUANG&sex=Male&race=Asian',
										//'Referer: http://www.dallascounty.org/jaillookup/defendant_detail.do?recno=58006D9B-2986-6794-EBD5-C4DCBC4AADBA&bookinNumber=12007930&bookinDate=1328141100000&dob=1978-12-31&lastName=NGUYEN&firstName=QUANG&sex=Male&race=Asian',
										'Referer: http://www.dallascounty.org/jaillookup/defendant_detail.do?recno='.str_replace('&amp;', '&', $details_link), 
										'Pragma: no-cache',
										'Cache-Control: no-cache',
									);
									$ch = curl_init($image_link);
									$fp = fopen($imagepath.$imagename.'.jpg', 'wb');
									curl_setopt($ch, CURLOPT_FILE, $fp);
									curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
									curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
									curl_exec($ch);
									curl_close($ch);
									fclose($fp);
									try
									{
										$im = new Imagick();
										$im->readImage( $imagepath.$imagename.'.jpg' );
										$im->setImageFormat( "png" );
										$im->writeImage( $imagepath.$imagename. '.png');	
									}
									catch(ImagickException $e)
									{
										unlink($imagepath.$imagename.'.jpg');	
										return 101;
									}
									unlink($imagepath.$imagename.'.jpg');
									if (file_exists($imagepath.$imagename.'.png')) //validate the image was downloaded
									{
										# ok I got the image now I need to do my conversions
								        $imgpath = $mugpath.$imagename.'.png';
										$img = Image::factory($imgpath);
					                	// crop it if needed, keep in mind mug_stamp function also crops the image
					                	$img->crop(200, 240)->save();
								        $imgpath = $mugpath.$imagename.'.png';
										// get a count
										$chargeCount = count($fcharges);
										// run through charge logic	
										// this is all boilerplate
										$mcharges 	= array(); // reset the array
								        if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
								        {
								        	$mcharges 	= $this->charges_prioritizer($list, $fcharges);
											if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in marion scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
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
										# BOILERPLATE DATABASE INSERTS
										$offender = Mango::factory('offender', 
							                array(
							                	'scrape'		=> $this->scrape,
							                	'state'			=> $this->state,
							                   	'county'		=> strtolower($this->county), // this may differ on sites with multiple counties
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
										if ( ! $mscrape->loaded() )
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
					} else { return 101; }
				} else { return 101; }
	        } else { return 103; } // database validation failed
		} else { return 101; }
    } // end extraction
} // class end