<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Utah
 *
 * @package Scrape
 * @author Winter King
 * @description Takes a data and extracts all offenders found in that date
 * @url http://www.utahcountyonline.org/Dept/Sheriff/Corrections/InmateSearch.asp
 */
class Model_Utah extends Model 
{
	private $county 	= 'utah';
	private $state		= 'utah';
	private $user_agent = "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.13) Gecko/20101203 Firefox/3.6.13";
    private $cookies    = '/var/www/public/cookiejar/utah_cookies.txt';
	private $kw_order   = '/mugs/utah/utah/lists/utah_kw_order.csv';
	
	public function __construct()
	{
		set_time_limit(86400); //make it go forever	
		if ( file_exists($this->cookies) ) { unlink($this->cookies); } //delete cookie file if it exists      	
	}
	function print_r2($val)
	{
        echo '<pre>';
        print_r($val);
        echo  '</pre>';
	} 
    function scrape($date) 
    {
    	$info['Scrape'] = 'utah';
    	$info['Total'] = 0;
		$info['Successful'] = 0;
		$info['New_Charges'] = array();
        $info['Failed_New_Charges'] = 0;
        $info['Exists'] = 0;
        $info['Bad_Images'] = 0;
        $info['Other'] = 0;
    	$scrape = new Model_Scrape();
    	# first curl the home page to set the cookie
    	$home = $this->curl_home();
		//print_r($home);
    	# now curl the index page with a date
    	$index = $this->curl_index($date);
		# need to sleep for a few seconds here because it takes awhile to build the index
		sleep(5);  
		# rip out the table and tidy it for parsing
		//echo $index;
		//exit;
		$check = preg_match('/\<table\swidth\=\"100%\"\sborder\=\"0\"\scellspacing\=\"2\"\scellpadding\=\"0\">.*\<\/table\>/isU', $index, $match);
		if ($check)
		{
			$table = $match[0];
			$tidy = new tidy();
			$tidy->parseString($table);
			$tidy->cleanRepair();
			$scrape->source = $tidy;
	        $scrape->anchor = '<td class="bold">Name</td>';
		    $scrape->anchorWithin = true;
			$scrape->headerRow = true;
			$scrape->stripTags = true;
	        $index_table = $scrape->extractTable();
			# build booking_id array
			$booking_ids = array();
			foreach ($index_table as $row)
			{
				foreach($row as $key => $value)
				{
					$value = trim($value);
					if (($key == 'Booking#') && (is_numeric($value)))
					{
						$booking_ids[] = $value;
					}	
				}
			}
			# validate against empty results
			if (!empty($booking_ids))
			{
				$info['Total'] = count($booking_ids);
				foreach ($booking_ids as $booking_id)
				{
					$details = $this->curl_details($booking_id);
					# begin extraction
					$extraction = $this->extraction($details, $booking_id);
					if ($extraction == 102) { $info['Bad_Images'] += 1; }
	                if ($extraction == 103) { $info['Exists'] += 1; }
	                if ($extraction == 101) { $info['Other'] += 1; }
					if ($extraction == 'success') { $info['Successful'] += 1; }
					if (is_array($extraction)) // this means that the extraction failed because new charges
					{
					    $info['Failed_New_Charges'] += 1;
						# loop through the new charges and add them to the $info['New_Charges'] array
						foreach ($extraction as $charge)
						{
							$info['New_Charges'][] = $charge;		
						}
					}					
				}	
			} else { return false; } // empty result set for given booking date
		}
		return $info;	
	} 
	function curl_home()
	{
		$url = 'http://www.utahcountyonline.org/Dept/Sheriff/Corrections/InmateSearch.asp';
		$ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, false);		
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        $home = curl_exec($ch);
        curl_close($ch);
		return $home;
	}
	
	function curl_index($date)
	{
		$month = date('m', $date);
		$day	= (date('d', $date));
		$year	= date('Y', $date);
		$url = 'http://www.utahcountyonline.org/Dept/Sheriff/Corrections/BookingDateSearchResults.asp?date='.$month.'%2F'.$day.'%2F'.$year.'&Submit2=Submit';
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, false);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		//curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        $index = curl_exec($ch);
        curl_close($ch);
		return $index;
	}
	
	function curl_details($booking_id)
	{
		$url = 'http://www.utahcountyonline.org/Dept/Sheriff/Corrections/InmateDetail.asp?id='.$booking_id;
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        $details = curl_exec($ch);
        //$info = curl_getinfo($ch);
        curl_close($ch); 
        return $details;	
		
	}
	
	private function odd($var)
	{
	    // returns whether the input integer is odd
	    return($var & 1 );
	}
	function extraction($details, $booking_id)
	{
		$scrape = new Model_Scrape;
		$booking_id = 'utah_' . $booking_id;  // this is the var for the database booking_id insert and also for the name
		# validate against existing booking_id
		# database validation
		$offender = Mango::factory('offender', array(
			'booking_id' => $booking_id
		))->load();	
		if (empty($offender->booking_id)) 
		{
			#extract table of charges
			$scrape->startRow =7;
			//$scrape->maxRows = 2;
			$scrape->startCol = 1;
			$scrape->maxCols = 1;
			//$extraCols[] = array('column'=4, 'names'=>array('URL', 'Link Title'), 'regex'=>'/!REGEX!/');
			//$scrape->extraCols = $extraCols;
			$extraCols[] = array('column'=>1, 'names'=>'fullname', 'regex'=>'/\<a\shref\=[^>]*\>([^<]*)\</');
			//$scrape->extraCols = $extraCols; 
			$scrape->source = $details;
	        $scrape->anchor = '<div align="right">Name :</div>';
		    $scrape->anchorWithin = true;
			$scrape->headerRow = false;
			$scrape->stripTags = false;
	        $charges_table = $scrape->extractTable();
			# rip out just the charges			
			if (!empty($charges_table))
			{	
				$array = array();
				foreach ($charges_table as $key => $value)
				{
					foreach ($value as $v)
					{
						$v = trim($v);
						if ($v != '&nbsp;')
						{
							$array[] = $v;		
						}
					}
				}
				$charges = array();
				foreach ($array as $key => $value)
				{
					if ($this->odd($key))
					{
						$value = preg_replace('/\&nbsp\;/', ' ', $value);
						$charges[] = $value;
					}
				}

				if (!empty($charges)) // validate for existing charges
				{
					$dbcharges = array(); //make sure this is reset	
					$dbcharges = $charges; //set database charges array
					#loop throught charges and build new formated charges array
					foreach($charges as $charge)
					{	
						$fcharges[] = strtoupper(trim(preg_replace('/\s/', '', preg_replace('/^[0-9\-.]+/is', '', $charge))));
					}
					$fcharges = array_unique($fcharges); //make unique
					$fcharges = array_merge($fcharges); //resets keys
					# check for new charges
					# this creates a charges object for all charges that are not new for this county
					$charges_object = Mango::factory('charge', array('county' => $this->county, 'new' => 0))->load(false)->as_array(false);
					# I can loop through and pull out individual arrays as needed:
					$list = array();
					foreach($charges_object as $row)
					{
						$list[$row['charge']] = $row['abbr'];
					}
					# this gives me a list array with key == fullname, value == abbreviated
					$ncharges = array();
					# Run my full_charges_array through the charges check
					//$ncharges = $scrape->charges_check($charges, $list);
					# validate 
					// @ skip charge validation because this uses a keyword list
					// @todo figure out a more efficient way to do this =
					//       a refined KEYPHRASE prioritizer and abbreviator will work best
					//if (empty($ncharges)) // skip the offender if a new charge was found
					//{
						//Name :</div></td>
			            //    <td width="217" valign="top"><a href="InmateSearchResults.asp?name=PEREZ, MELECIO ROSAS">PEREZ, MELECIO ROSAS</a></td>
						# get the fullname
						$check = preg_match('/Name\s\:\<\/div\>\<\/td\>[^<]*\<td[^>]*\>\<a[^>]*\>([^<]*)\</', $details, $fullname);	
						if ($check)
						{
							$fullname = $fullname[1]; //drill down			
							#remove dot and trim so it doesn't mess up the image filename
				            $fullname = preg_replace('/\./', '', $fullname);
							# check for comma
							$pos = strpos($fullname, ',');
							if ($pos !== false)
							{
					            # Explode and trim fullname
					            # Set first and lastname
					            $explode      = explode(',', trim($fullname));
					            $lastname     = trim($explode[0]);
					            $explode      = explode(' ', trim($explode[1]));
					            $firstname    = trim($explode[0]);	
								# get booking_date
								//<td height="24" align="left">Booked: </td>
			                	//<td align="left">3/3/2011 2:46:30 AM</td>
								$check = preg_match('/Booked\:\s\<\/td\>[^<]*\<td[^>]*\>([^<]*)\</', $details, $booking_date);
								if ($check)
								{
									$explode = explode(' ', $booking_date[1]);
									$booking_date = trim($explode[0]);
									$booking_date = strtotime($booking_date);
									/* Ok now I have:
									 * $firstname
									 * $lastname
									 * $booking_id
									 * $booking_date
									 * $image
									 * $charges[]
									 * 
									 */ 
									 
									# get extra fields
									$extra_fields = array();
									$check = preg_match('/Height\s\:\<\/div\>\<\/td\>[^<]*\<td[^>]*\>([^<]*)\</', $details, $height);
									if ($check) { $height = $height[1]; } // set height 
									if (isset($height)) 
									{
										$extra_fields['height'] = $scrape->height_conversion($height); 
									}
									
									$check = preg_match('/Weight\s\:\<\/div\>\<\/td\>[^<]*\<td[^>]*\>([^<]*)\</', $details, $weight);
									if ($check) { $weight = $weight[1]; } // set weight 
									if (isset($weight)) 
									{
										$extra_fields['weight'] = preg_replace('/[^0-9]/', '', $weight);  
									}
										
									$check = preg_match('/Eyes\s\:\<\/div\>\<\/td\>[^<]*\<td[^>]*\>([^<]*)\</', $details, $eye_color);
									if ($check) { $eye_color = $eye_color[1]; } // set eye color 
									if (isset($eye_color)) { $extra_fields['eye_color'] = $eye_color; }
									
									$check = preg_match('/Hair\s\:\<\/div\>\<\/td\>[^<]*\<td[^>]*\>([^<]*)\</', $details, $hair_color);
									if ($check) { $hair_color = $hair_color[1]; } // set hair color
									if (isset($hair_color)) { $extra_fields['hair_color'] = $hair_color; }
									
									$check = preg_match('/Sex\s\:\<\/div\>\<\/td\>[^<]*\<td[^>]*\>([^<]*)\</', $details, $gender);
									if ($check) { $gender = strtoupper(trim($gender[1])); if ($gender == 'M') {$gender = 'MALE';} else if ($gender == 'F') { $gender = 'FEMALE'; }  }
									if (isset($gender)) { $extra_fields['gender'] = $gender; }
									
									$check = preg_match('/Address\s\:\<\/div\>\<\/td\>[^<]*\<td[^>]*\>([^<]*)\</', $details, $address);
									if ($check) { $address = $address[1]; }  
									if (isset($address)) { $extra_fields['address'] = $address; }
									
									$check = preg_match('/S\/M\/T\s\:\<\/div\>\<\/td\>[^<]*\<td[^>]*\>([^<]*)\</', $details, $misc);
									if ($check) { $misc = $misc[1]; } 
									if (isset($misc)) { $extra_fields['misc'] = $misc; }
									
									### BEGIN IMAGE EXTRACTION ###
									# Get image link'
									//<img src="http://www.utahcountyonline.com/jphtoz/257/257949.jpg" alt="Inmate Photo" width="104" height="78">
									$check = preg_match('/\/jlthm\/.*\.jpg/Uis', $details, $imagelink);
									if ($check)
									{
										
										$imgLnk = 'http://www.utahcountyonline.com'.$imagelink[0];
										$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
										# set image path
								        $imagepath = '/mugs/utah/utah/'.date('Y', $booking_date).'/week_'.$scrape->find_week($booking_date).'/';
								        # create mugpath
								        $mugpath = $scrape->set_mugpath($imagepath);
										//@todo find a way to identify extension before setting ->imageSource
										try
										{
											$scrape->imageSource    = $imgLnk;
									        $scrape->save_to        = $imagepath.$imagename;
									        $scrape->set_extension  = true;
									        $get = $scrape->download('gd');											
										} catch (ErrorException $e)
										{
											return 102;
										}

										# validate against broken image
										if ($get) 
										{
											# ok I got the image now I need to do my conversions
									        # convert image to png.
									        $scrape->convertImage($mugpath.$imagename.'.jpg');
									        $imgpath = $mugpath.$imagename.'.png';
											# crop 520x390 original
											$img = Image::factory($imgpath);
	                                        $img->crop(360, 390)->save();
											
											# now run through charge logic
											$chargeCount = count($fcharges);
											# run through charge logic
									        if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
									        {
									        	$fcharges 	= array_merge($fcharges);
									            $mcharges 	= $scrape->keyword_prioritizer($this->kw_order, $dbcharges);
												if ($mcharges == false) 
												{
													// didn't match anything, so just take the first two original charges
													$charge1 = $dbcharges[0];
													$charge2 = $dbcharges[1];
												} 
												else 
												{
													if (count($mcharges) > 1)
													{
														$charge1 = $mcharges[0];
														$charge2 = $mcharges[1];
													}	
													else if (count($mcharges) == 1)
													{
														// make $charge1 the first one returned
														$charge1 = $mcharges[0];
														//now run down the $fcharges and take the first one that doesn't match
														foreach ($fcharges as $key => $value)
														{
															if ($value != $charge1)
															{
																$charge2 = $value;
															}
														}
													}
												} 
									            $scrape->mugStamp($imgpath, $firstname . ' ' . $lastname, $charge1, $charge2);
									        }
									        else if ( $chargeCount == 2 )
									        {
									            $fcharges 	= array_merge($fcharges);
									            $charge1 	= $fcharges[0];
									            $charge2 	= $fcharges[1];   
									            $scrape->mugStamp($imgpath, $firstname . ' ' . $lastname, $charge1, $charge2);           
									        }
									        else if ( $chargeCount == 1)
									        {
									            $fcharges 	= array_merge($fcharges);
									            $charge1 	= $fcharges[0];    
									            $scrape->mugStamp($imgpath, $firstname . ' ' . $lastname, $charge1);   
									        }
											# now create the text file with the charges in it
											$txt_filename = $imagepath . $imagename . '.txt';
											$fh = fopen($txt_filename, 'w') or die ('cant open file');
											foreach($dbcharges as $key => $value)
											{
												$string = $value . "\r\n";
												fwrite($fh, $string);	
											}
											fclose($fh);
											
											
											# BOILERPLATE DATABASE INSERTS
											$offender = Mango::factory('offender', 
								                array(
								                	'scrape'		=> $this->county,
								                	'state'			=> strtolower($this->state),
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
											$county = Mango::factory('county', array('name' => $this->county, 'state' => $this->state))->load();
											if (!$county->loaded())
											{
												$county = Mango::factory('county', array('name' => $this->county, 'state' => $this->state))->create();
											}
											$county->booking_ids[] = $booking_id;
											$county->update();
											# END DATABASE INSERTS
											
											return 'success';
											### END EXTRACTION ###
										} else { return 102; } // image download validation
									} else { return 101; } // image link validation check
								} else { return 101; } // booking date check validation
							} else { return 101; } // fullname doesn't have a comma
						} else { return 101; } // fullname validation check	
		/*			} else {
						 # add new charges to the report
			            $ncharges = preg_replace('/\s/', '', $ncharges);
			            $week_report = Mango::factory('report', array(
			                'scrape' => $this->county,
			                'year'   => date('Y'),
			                'week'   => $scrape->find_week(time())
			            ))->load();
						if (!$week_report->loaded())
						{
							$week_report = Mango::factory('report', array(
				                'scrape' => $this->county,
				                'year'   => date('Y'),
				                'week'   => $scrape->find_week(time())
			            	))->create();
						}
			            $db_new_charges = $week_report->new_charges->as_array();
			            if (is_array($db_new_charges))
			            {
			                $merged = array_merge($db_new_charges, $ncharges);
			                $merged = array_unique($merged);
			                $merged = array_merge($merged);
			                sort($merged);
			                $week_report->new_charges = $merged;    
			            }
			            else 
			            {
			                sort($ncharges); 
			                $week_report->new_charges = $ncharges;   
			            }
			            $week_report->update(); 
						 
			            $info['New_Charges'] = array_unique($ncharges); 
			            return $ncharges;	
					} // new charges validation
 				*/
				} else { return 101; } // charges validation
			} else { return 101; } // charges table validation
		} else { return 103; } // database validation		
	} // end extraction	
} // class end
