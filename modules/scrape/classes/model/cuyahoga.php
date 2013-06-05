<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Model_cuyahoga
 * @TODO THIS IS BROKEN FIX IT
 * @TODO fix this to support bottom table extraction
 * @package Scrape
 * @author Winter King
 * @url ftp://ftp.cuyahogacounty.us/
 */
class Model_Cuyahoga extends Model_Scrape 
{
    private $scrape     = 'cuyahoga';
    private $state      = 'ohio';
    private $cookies    = '/tmp/cuyahoga_cookies.txt';
	private $zip_folder = '/raw/ohio/cuyahoga/zips/';
	
    public function __construct()
    {
        set_time_limit(1200); //make it go forever 
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
    	$scrape = new Model_Scrape;
        function ftp_isdir($conn_id, $dir)
        {
            if(ftp_chdir($conn_id, $dir))
            {
                @ftp_cdup($conn_id);
                return true;
            }
            else
            {
                return false;
            }
        } 
        # connect to the ftp server
        $ftp_server = 'ftp.cuyahogacounty.us';
        $conn_id = @ftp_connect($ftp_server) or die("Couldn't connect to server"); 
        if ($conn_id)
        { 
            # set un/pw and login 
            $ftp_user = 'sheriff.guest';
            $ftp_pass = 'sh3r1ff';
            if (@ftp_login($conn_id, $ftp_user, $ftp_pass)) 
            {
                #change directory to v-sheriffs/
                $dir = 'v-sheriffs/';
                if (ftp_isdir($conn_id, $dir))
                {
                    ftp_chdir($conn_id, 'v-sheriffs/');
					ftp_chdir($conn_id, 'booking/');
					$list = ftp_rawlist($conn_id, '');
					// yeah thats that directory list
                    for ($i = 1; $i <= 7; $i++)
					{ 
	                  	$yesterday = time() - (86400 * $i);
						$date_format = date('Ymd', $yesterday);
	                    $remote_file = $date_format . '-bkg.zip';
	                    $local_file = $this->zip_folder . $remote_file;
	                    $zip_folder = preg_replace('/\.zip/Uis', '', $local_file);
	                    $images_folder = $zip_folder . '/tmp/' . $date_format;
	                    //$local_file = '/tmp/test.txt';
	                    // open some file to write to
	                    $handle = fopen($local_file, 'w+');
	                    if (@ftp_fget($conn_id, $handle, $remote_file, FTP_BINARY, 0)) 
	                    {
	                        $zip = new ZipArchive; 
							// this lets me work with zip files
							// I can specify name 
	                        if ($zip->open($local_file) === TRUE)
	                        {
	                            $zip->extractTo('' . $zip_folder);
	                            $zip->close();
								
	                            # ok so we now have a folder extracted for yesterdays data.
	                            # lets build a multi-dimentional array for charges and profile data
	                            if ( ! file_exists($zip_folder . '/tmp/ccso-booking-' . $date_format . '.txt'))
									continue;
	                            $handle = fopen($zip_folder . '/tmp/ccso-booking-' . $date_format . '.txt', 'r');
	                            $contents = stream_get_contents($handle);
	                            $contents = str_replace('\r\n\t\o', '', $contents);
	                            $profiles_array = explode('|', $contents);
	                            $handle = fopen($zip_folder . '/tmp/ccso-charges-' . $date_format . '.txt', 'r');
	                            $contents = stream_get_contents($handle);
	                            $contents = str_replace('\r\n\t\o', '', $contents);
	                            $charges_explode = explode('|', $contents);
	                            $details_array = array();
	                            $count = 0;
	                            $count2 = 0;
	                            foreach($profiles_array as $key => $value)
	                            {
	                                $details_array[$count2][$count] = $value;
	                                $count++;
	                                if ($count == 8)
	                                {
	                                    $count2++;
	                                    $count = 0; 
	                                }    
	                            }
	                            $count = 0;
	                            $count2 = 0;
	                            $charges_array = array();                            
	                            foreach($charges_explode as $key => $value)
	                            {
	                                $charges_array[$count2][$count] = $value;
	                                $count++;
	                                if ($count == 5)
	                                {
	                                    $count = 0;
	                                    $count2++;
	                                }  
	                            }
	                            
	                            foreach($details_array as $pro_key => $pro_arr)
	                            {
	                                $profile_charges = array();
	                                foreach($charges_array as $key => $chg_arr)
	                                {
	                                    if ((@$chg_arr[1] == @$pro_arr[1]) && (!empty($chg_arr[1]) && !empty($pro_arr[1])))
	                                    {
	                                        $profile_charges[] = $chg_arr[4];
	                                    }   
	                                }
	                                $details_array[$pro_key][8] = $profile_charges;
	                            }
	                            # phew ok now I have my multidimentional $details_array with charges
	                            # now loop though and send each one to the extractor
	                            foreach($details_array as $key => $details)
	                            {
	                                $extraction = $this->extraction($details, $images_folder);
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
	                        } else { continue; }            
	                    } else { continue; }
                	} return true;
                } else { return false; }    
            } else { return false; }
        } else { return false; }
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
    function extraction($details, $images_folder)
    {
        $scrape = new Model_Scrape;
        $flag = false;
        foreach($details as $key => $value)
        {
            if (empty($value))
            {
                $flag = true;
            }
        }
        if ($flag == false)
        {
            $county = 'cuyahoga';
            $booking_id =  'cuyahoga_' . $details[1];
            # database validation 
            $offender = Mango::factory('offender', array(
                'booking_id' => $booking_id
            ))->load(); 
            # validate against the database
            if (empty($offender->booking_id)) 
            {
                $booking_date = strtotime($details[7]);
                $fullname = $details[2];
                $explode = explode(',', $fullname);
                $lastname = $explode[0];
                $explode2 = explode(' ', $explode[1]);
                $firstname = $explode2[1];
                # get extra fields
                $extra_fields = array();
                $extra_fields['dob'] = strtotime($details[3]);
                $extra_fields['height'] = $scrape->height_conversion($details[4] . $details[5]);
                $extra_fields['weight'] = trim($details[6]);
                # ok now get charges but first make sure no dupes exist
                $charges = array();
                foreach($details[8] as $charge)
                {
                    $charge = trim(strtoupper($charge));
                    $charges[] = $charge;
                }
                $charges = array_unique($charges);
                $charges = array_merge($charges);
				
                $dbcharges = $charges;
                $fcharges = $charges;
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
                    //$ncharges = array();
                    # Run my full_charges_array through the charges check
                    //$ncharges = $this->charges_check($charges, $list);
                    ###
                    # validate 
                    if (empty($ncharges)) // skip the offender if ANY new charges were found
                    {
                        # begin image extraction 
                        
                        if ($handle = opendir($images_folder)) 
                        {
                            /* This is the correct way to loop over the directory. */
                            while (false !== ($file = readdir($handle))) 
                            {
                                $bid = preg_replace('/cuyahoga\_/Uis', '', $booking_id);
                                $pos = strpos($file, $bid);
                                if ($pos !== false)
                                {
                                    $image_file = $file;
                                } 
                            } // end while images loop
                            closedir($handle);
                        }     
                        if (isset($image_file))
                        {
                        	// hmm ok I have an idea
                        	/// ok see this try/catch statement?
                        	// this is how php handles "EXCEPTIONS"
                        	// this will attempt to TRY all funcitons within the brackets
                        	try
                        	{
	                            $image_file = $images_folder . '/' . $image_file;
	                            # set image name
	                            $imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
	                            # set image path
	                            $imagepath = '/mugs/ohio/cuyahoga/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
	                            # create mugpath
	                            $mugpath = $this->set_mugpath($imagepath);
	                            $fullpath = $imagepath . $imagename;
	                            copy($image_file, $fullpath . '.jpg');
	                            # ok I got the image now I need to do my conversions
	                            # convert image to png.
	                            $this->convertImage($mugpath.$imagename.'.jpg');
	                            $imgpath = $mugpath.$imagename.'.png';
	                            $img = Image::factory($imgpath);
	                            $img->crop(500, 468)->save();
                            }
							catch(Exception $e)
							{
								return 101;
							}
                            $imgpath = $mugpath.$imagename.'.png';
                            # now run through charge logic
                            $chargeCount = count($fcharges);
                            # run through charge logic  
                            $mcharges   = array(); // reset the array
                            if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
                            {
                                $charge1    = $fcharges[0];
                                $charge2    = $fcharges[1]; 
                                $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
                            }
                            else if ( $chargeCount == 2 )
                            {
                                $fcharges   = array_merge($fcharges);
                                $charge1    = $fcharges[0];
                                $charge2    = $fcharges[1];   
                                $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);           
                            }
                            else 
                            {
                                $fcharges   = array_merge($fcharges);
                                $charge1    = $fcharges[0];    
                                $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0]);   
                            }
                            
                            // Abbreviate FULL charge list
                            //$dbcharges = $this->charges_abbreviator_db($list, $dbcharges);
                            //$dbcharges = array_unique($dbcharges);
                            # BOILERPLATE DATABASE INSERTS
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
                        } else { return 101; } 
                    } else {
                        # add new charges to the charges collection
                        foreach ($ncharges as $key => $value)
                        {
                            $value = preg_replace('/\s/', '', $value);
                            #check if the new charge already exists FOR THIS COUNTY
                            $check_charge = Mango::factory('charge', array('county' => $this->scrape, 'charge' => $value))->load();
                            if (!$check_charge->loaded())
                            {
                                if (!empty($value))
                                {
                                    $charge = Mango::factory('charge')->create();   
                                    $charge->charge = $value;
                                    $charge->order = (int)0;
                                    $charge->county = $this->scrape;
                                    $charge->scrape = $this->scrape;
                                    $charge->new    = (int)0;
                                    $charge->update();
                                }   
                            }
                        }
                    }
                    return 104;  
                } else { return 101; } // no charges
            } else { return 103; } // database validation    
        } else { return 101; }
    } // ncharges validation    
} // class end