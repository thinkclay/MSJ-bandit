<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_weber
 *
 * @package Scrape
 * @author Winter King
 * @todo get all charges instead of the main
 * @params 
 * @description Cold fusion site uses json data for index with paging
 * @url http://www.standard.net/jail-mugs
 */
class Model_Weber extends Model_Scrape
{
	private $scrape = 'weber';
	private $state = 'utah';
    private $cookies = '/tmp/weber_cookies.txt';
	
	public function __construct()
    {
        set_time_limit(0); //make it go forever 
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
	* @params $page = page number
	* @return $info - passes to the controller for reporting
	*/
    function scrape() 
    {
    	# set flag and start loop for paging
    	$flag = false;
		$page = 0;
		$count = 0;
		while ($flag == false)
		{
			#check paging logic
			if ($page != 0)
			{
				#get index with page number
				$index = $this->curl_index($page);		
			}
			else 
			{
				#get index
				$index = $this->curl_index();
				
			}
			# Build detail link array
			$check = preg_match_all('/views\-field\sviews\-field\-field\-mugshot\-fid\"\>[^<]*\<a\shref\=\"\/jail\-mugs\/([^"]*)\"/', $index, $links);
			if ($check)
			{
				
				foreach ($links[1] as $key => $link)
				{
					$details = $this->curl_details($link);
					echo $details;
					exit;
					$extraction = $this->extraction($details);
                    if ($extraction == 100) { $this->report->successful = ($this->report->successful + 1); $this->report->update(); }
                    if ($extraction == 101) { $this->report->other = ($this->report->other + 1); $this->report->update(); }
                    if ($extraction == 102) { $this->report->bad_images = ($this->report->bad_images + 1); $this->report->update(); }
                    if ($extraction == 103) { $this->report->exists = ($this->report->exists + 1); $this->report->update(); }
                    if ($extraction == 104) { $this->report->new_charges = ($this->report->new_charges + 1); $this->report->update(); }
                    $this->report->total = ($this->report->total + 1); $this->report->update();
				}
			}
			# check for paging
			$check = preg_match('/Go\sto\snext\spage/', $index, $next);
			if ($check)
			{
				# set page num
				$page = $page + 1;
			}
			else 
			{
				$flag = true;
			}
			if ($page == 200) { $flag = true; }
		}
		$this->report->failed = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
        $this->report->finished = 1;
        $this->report->stop_time = time();
        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
        $this->report->update();
        return true;	
	}


	/**
	* curl_index - this will return a json index of passed page number
	*
	* 
	* @params $page = page number
	* @return json data
	*/
	function curl_index($page = false)
	{
		// paging link:
		if ($page) 
		{
			$url = 'http://www.standard.net/jail-mugs?page=' . $page;		
		}
		else
		{
			$url = 'http://www.standard.net/jail-mugs';	
		}
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
	* curl_details - 
	*
	* 
	* 
	* @return 
	*/
	function curl_details($link)
	{
		$url = 'http://www.standard.net/jail-mugs/'.$link;
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
	function extraction($details)
	{
		$county = 'weber';
		$ncharges 	= array();
		$fcharges 	= array();
		$mcharges 	= array();	
		$dbcharges 	= array();
		# extract profile details
		# get fullname
 		$check = preg_match('/\<h1\>([^<]*)\</', $details, $fullname);
		if ($check) 
		{
			# drill down and set fullname
			$fullname = trim($fullname[1]);
			if(strpos($fullname, ',') !== false )
			{
				# this fullname also has the booking_id in it
				# extract firstname, lastname and booking_id
				$fullname = str_replace('.', '', $fullname);
				$explode  = explode(',', $fullname);
				$lastname  = trim($explode[0]);
				$firstname = trim($explode[1]);
				$firstname = explode(' ', $firstname);
				$firstname = trim($firstname[0]);
				# check for the comma.  Must be a comma to ensure first/last names
				$explode = explode(' ', $fullname);
				$booking_id = trim($explode[(count($explode) - 1)]);
				$booking_id = 'weber_' . $booking_id;
				
				# get booking_id
				if ($check)
				{
					# database validation
					$offender = Mango::factory('offender', array(
						'booking_id' => $booking_id
					))->load();	
					# validate against the database
					if (empty($offender->booking_id)) 
					{
						#get booking date
						$check = preg_match('/Booking\sdate\:[^<]*\<[^<]*\<[^<]*\<[^>]*\>([^<]*)\</', $details, $booking_date);
						if ($check)
						{
							$booking_date = trim($booking_date[1]);
							$booking_date = explode(',', $booking_date);
							$booking_date = trim(strtotime($booking_date[1]));
							# get charge
							//Description:</span><span class="booking-record-charge-info">POSS METH W / IN 1000FT W / INTEST</span></div>
							$check = preg_match('/Description\:\<\/span\>\<[^>]*\>([^<]*)\</', $details, $charge);
							# validate charges table
							if ($check)
							{
								$charge = trim($charge[1]);
								$charge = preg_replace('/\(.*\)/Uis', '', $charge);
								$smashed_charges = array();
								$smashed_charges[] = preg_replace('/\s/', '', $charge);

								$dbcharges = array($charge);
								$fcharge = strtoupper($charge);
								$fcharge = preg_replace('/\s/', '', $fcharge);
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
								$ncharges = $this->charges_check($charge, $list);
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
									# get extra fields								
									$extra_fields = array();
									$check = preg_match('/Height\:[^<]*\<[^<]*\<[^>]*\>([^<]*)\</', $details, $height);
									if ($check) { $height = trim($height[1]); }
									if (isset($height)) 
									{
										$extra_fields['height'] = $this->height_conversion($height); 
									}
									
									$check = preg_match('/Weight\:[^<]*\<[^<]*\<[^>]*\>([^<]*)\</', $details, $weight);
									if ($check) { $weight = trim($weight[1]); }
									if (isset($weight)) 
									{
										$extra_fields['weight'] = preg_replace('/[^0-9]/', '', $weight);  
									}
									
									$check = preg_match('/Eye\scolor\:[^<]*\<[^<]*\<[^>]*\>([^<]*)\</', $details, $eye_color);
									if ($check) { $eye_color = trim($eye_color[1]); }
									if (isset($eye_color)) { $extra_fields['eye_color'] = $eye_color; }
									
									$check = preg_match('/Hair\scolor\:[^<]*\<[^<]*\<[^>]*\>([^<]*)\</', $details, $hair_color);
									if ($check) { $hair_color = trim($hair_color[1]); }
									if (isset($hair_color)) { $extra_fields['hair_color'] = $hair_color; }
									
									$check = preg_match('/Birth\sdate\:[^<]*\<[^<]*\<[^>]*\>[^>]*\>([^<]*)\</', $details, $dob);
									if ($check) { $dob = strtotime(trim($dob[1])); }
									if (isset($dob)) 
									{
										$extra_fields['dob'] = $dob; 
										$extra_fields['age'] = floor(($booking_date - $dob) / 31556926);
									}
									
									# begin image extraction
									$check = preg_match('/views\-field\-field\-mugshot\-fid\"\>[^<]*\<[^<]*\<img[^"]*\"([^"]*)\"/', $details, $image_link); 								
									if ($check)
									{
										$image_link = $image_link[1]; 
										# set image name
										$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
										# set image path
								        $imagepath = '/mugs/utah/weber/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
								        # create mugpath
								        $mugpath = $this->set_mugpath($imagepath);
										//@todo find a way to identify extension before setting ->imageSource
										$this->imageSource    = $image_link;
								        $this->save_to        = $imagepath.$imagename;
								        $this->set_extension  = true;
								        $get = $this->download('gd');
										# validate against broken image
										if ($get) 
										{ 
											
											# ok I got the image now I need to do my conversions
									        # convert image to png.
									        $this->convertImage($mugpath.$imagename.'.jpg');
									        $imgpath = $mugpath.$imagename.'.png';
											# now run through charge abbreviator
								        	$charges 	= array();
								            $charge 	= $this->charges_abbreviator($list, $charge);       
											$check = $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charge[0]);
											if ($check === false)
											{
											    unlink($imgpath);
											    return 101;
											}
											// Abbreviate FULL charge list
											$dbcharges = $this->charges_abbreviator_db($list, $dbcharges);
											$dbcharges = array_unique($dbcharges);
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
											
										} else { return 102; } //image download failed
									} else { return 102; } //regex validation failed - no image link found
								} 
								else 
								{
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
							} else { return 101; } // empty charges table
						} else { return 101; } // booking_date validation
					} else { return 103; } // database validation
				} else { return 101; } // booking_id check			
			} else { return 101; } // fullname comma validation failed
		} else { return 101; } // fullname validation failed
	} // end extraction					
} // class end