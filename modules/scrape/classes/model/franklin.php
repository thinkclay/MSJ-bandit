<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Johnson
 *
 * @TODO fix this to support bottom table extraction
 * @package Scrape
 * @author Winter King
 * @url http://www.jocosheriff.org/br/
 */
class Model_Franklin extends Model_Scrape 
{
    private $scrape     = 'franklin';
    private $state      = 'ohio';
    private $cookies    = '/tmp/franklin_cookies.txt';
    
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
        $this->raw_dir = '/raw/ohio/franklin/upload/';
        $this->extract_dir = '/raw/tmp/franklin';
		$file = null;
        if ($handle = opendir($this->raw_dir)) 
        {
            /* This is the correct way to loop over the directory. */
            $flag = false;
            $file = false;
            while (false !== ($entry = readdir($handle))) 
            {
            	if ($entry == '.' OR $entry == '..')
					continue;
				if ($file == null)
				{
					$file = $this->raw_dir . $entry;
				}
				else
				{
					if (filemtime($file) < filemtime($this->raw_dir . $entry))
					{
						$file = $this->raw_dir . $entry;
					}
				}
			}
            closedir($handle);
            // unzip the file
            $zip = new ZipArchive;
            $res = $zip->open($file);
            if ($res === true)
            {
                $zip->extractTo($this->extract_dir);
                $zip->close();
            }
            else { return false; }
            
            if ($handle2 = opendir($this->extract_dir . '/')) {
                $count = 0;
                while (false !== ($entry = readdir($handle2))) 
                {
                    if (strpos($entry, '.xml') !== false)
                    {
                        $fh = fopen($this->extract_dir . '/' . $entry, 'r');
                        $xml_string = fread($fh, filesize($this->extract_dir . '/' . $entry));
                        $extraction = $this->extraction($xml_string);                
                    }
                }
            } else { return false; }
        } else { return false; }
        closedir($handle2);
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
    function extraction($details)
    {
        $details = new SimpleXMLElement($details);
        $county = 'franklin';
        $this->county = 'franklin';
        $booking_id = $county . '_' . (int)$details->arrest->arrestNum;
        # database validation 
        $offender = Mango::factory('offender', array(
            'booking_id' => $booking_id
        ))->load();
        # validate against the database
        if (empty($offender->booking_id)) 
        {
            $booking_date = strtotime($details->arrest->arrestDate);   
            $lastname = trim(strtoupper($details->arrest->person->nameLast));
            if (!empty($lastname))
            {
                $firstname = trim(strtoupper($details->arrest->person->nameFirst)); 
                if (!empty($firstname))
                {
                    #get extra fields
                    $extra_fields = array();
                    if (isset($details->arrest->person->middleName))
                    {
                        if (!empty($details->arrest->person->middleName))
                        {
                            $extra_fields['middlename'] = $details->arrest->person->middleName;    
                        }
                    }
                    if (isset($details->arrest->person->gender))
                    {
                        if (!empty($details->arrest->person->gender))
                        {
                            $extra_fields['gender'] = strtoupper(trim($details->arrest->person->gender));
                        }
                    }
                    if (isset($details->arrest->person->race))
                    {
                        if (!empty($details->arrest->person->race))
                        {
                            $race = $this->race_mapper($details->arrest->person->race);
                            if ($race)
                            {
                                $extra_fields['race'] = $race;
                            }
                        }
                    }
                    if (isset($details->arrest->location))
                    {
                        if (!empty($details->arrest->location->streetAddress) AND !empty($details->arrest->location->city) AND !empty($details->arrest->location->state) AND !empty($details->arrest->location->zipCode))
                        {
                            $extra_fields['address'] = $details->arrest->location->streetAddress . ', ' . $details->arrest->location->city . ', ' . $details->arrest->location->state . ' ' . $details->arrest->location->zipCode;
                     
                        }
                    }
                    // now get charges
                    $charges = array();
                    if (isset($details->arrest->charges))
                    {
                        if (is_array($details->arrest->charges->charge))
                        {
                            foreach($details->arrest->charges->charge as $charge)
                            {
                                $charges[] = $this->clean_string_utf8(htmlspecialchars_decode(str_replace('&nbsp;', ' ', trim($charge->chargeText)), ENT_QUOTES));         
                            }
                        }        
                        else
                        {
                            $charges[] = $this->clean_string_utf8(htmlspecialchars_decode(str_replace('&nbsp;', ' ', trim($details->arrest->charges->charge->chargeText)), ENT_QUOTES));
                        }
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
                                // make unique and reset keys
                                $charges = array_unique($charges);
                                $charges = array_merge($charges);
                                $fcharges = array();
                                // trim, uppercase and scrub htmlspecialcharacters
                                foreach($charges as $charge)
                                {
                                    $fcharges[] = htmlspecialchars_decode(strtoupper(trim($charge)), ENT_QUOTES);
                                }    
                                $dbcharges = $fcharges;
                                // get the image
                                $imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id . '.jpg';
                                $imagepath = '/mugs/ohio/franklin/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
                                $mugpath = $this->set_mugpath($imagepath);
                                if (file_exists($this->extract_dir . '/' . $details->arrest->arrestImage))
                                {
                                    // move it to the image path
                                    copy($this->extract_dir . '/' . $details->arrest->arrestImage, $imagepath . $imagename);
                                    if (file_exists($imagepath . $imagename))
                                    {
                                        $this->convertImage($mugpath.$imagename);
										
                                        $imgpath = str_replace('.jpg', '.png', $mugpath.$imagename);
                                        $img = Image::factory($imgpath);
										//704px × 480
										if ($img->width > 400)
										{
											$img->crop(315, 420, null, true)->save();
										}
                        
                                        $chargeCount = count($fcharges);
                                        $mcharges = array();
                                        if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
                                        {
                                            $mcharges   = $this->charges_prioritizer($list, $fcharges);
                                            if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in marion scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
                                            $mcharges   = array_merge($mcharges);   
                                            $charge1    = $mcharges[0];
                                            $charge2    = $mcharges[1];    
                                            $charges    = $this->charges_abbreviator($list, $charge1, $charge2);
                                            $check = $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
                                            if ($check === false)
                                            {
                                                unlink($imgpath);
                                                return 101;
                                            }
                                        }
                                        else if ( $chargeCount == 2 )
                                        {
                                            $fcharges   = array_merge($fcharges);
                                            $charge1    = $fcharges[0];
                                            $charge2    = $fcharges[1];   
                                            $charges    = $this->charges_abbreviator($list, $charge1, $charge2);
                                            $check = $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
                                            if ($check === false)
                                            {
                                                unlink($imgpath);
                                                return 101;
                                            }           
                                        }
                                        else 
                                        {
                                            $fcharges   = array_merge($fcharges);
                                            $charge1    = $fcharges[0];    
                                            $charges    = $this->charges_abbreviator($list, $charge1);       
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
                                                'scrape'        => $this->scrape,
                                                'state'         => $this->state,
                                                'county'        => strtolower($this->county), // this may differ on sites with multiple counties
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
                                        if ( ! $mscrape->loaded() )
                                        {
                                            $mscrape = Mango::factory('mscrape', array('name' => $this->scrape, 'state' => $this->state))->create();
                                        }
                                        $mscrape->booking_ids[] = $booking_id;
                                        $mscrape->update();  
                                        # END DATABASE INSERTS
                                        return 100;
                                    } else { return 101; }
                                } else { return 101; }
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
                                            $charge->new    = (int)0;
                                            $charge->update();
                                        }   
                                    }
                                }
                                return 104;
                            } // ncharges validation
                        } else { return 101; }
                    } else { return 101; }
                } else { return 101; }
            } else { return 101; }
        } else { return 101; } 
    } // end extraction
} // class end