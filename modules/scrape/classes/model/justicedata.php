<?php defined('SYSPATH') or die('No direct script access.');
 
/** 
 * Model_justicedata
 *
 * @package Scrape
 * @author 	
 * @url 	http://www.example.com
 */
class Model_Justicedata extends Model_Scrape
{
    private $scrape     = 'justicedata'; // Name of scrape goes here
	private $county 	= 'fairfield'; // If it is a single county, put it here, otherwise remove this property
    private $state      = 'ohio'; // State goes here
    private $cookies    = '/tmp/justicedata_cookies.txt'; // Replace with <scrape name>_cookies.txt
    private $raw_folder = '/raw/ohio/justicedata/ohio/fairfield/';
	private	$ext_folder = '/raw/ohio/justicedata/ohio/fairfield/extract/';
	private $old_folder = '/raw/ohio/justicedata/ohio/fairfield/old/';
	
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
	
	/**
	 * Utility function used to clear the contents of a directory
	 * 
	 */
	private function empty_dir($dir) 
	{
	    $mydir = opendir($dir);
	    while(false !== ($file = readdir($mydir))) 
	    {
	        if($file != "." && $file != "..") 
	        {
	            chmod($dir.$file, 0777);
	            if(is_dir($dir.$file)) 
	            {
	                chdir('.');
	                $this->empty_dir($dir.$file.'/');
	                rmdir($dir.$file) or DIE("couldn't delete $dir$file<br />");
	            }
	            else
				{
					unlink($dir.$file) or DIE("couldn't delete $dir$file<br />");
				}
	        }
	    }
	    closedir($mydir);
	}
	
	function print_r2($val)
    {
        echo '<pre>';
        print_r($val);
        echo  '</pre>';
    } 
   
    /**
    * scrape - main scrape function makes the curl calls and sends details to the extraction function
    *
    * @return true - on completed scrape
    * @return false - on failed scrape
    */
    function scrape() 
    {
		$this->empty_dir($this->ext_folder);
		function custom_filter($var)
		{
			$raw_dir = '/raw/ohio/justicedata/ohio/fairfield/';	
			if ($var == '.' OR $var == '..')
			{
				return false;
			} 
			else if (is_dir($raw_dir.$var) === true)
			{
				return false;
			}
			else { return true; }
		}
		$this->files = scandir($this->raw_folder);
		$this->files = array_filter($this->files, 'custom_filter');
		
		foreach ($this->files as $file)
		{
			$file = $this->raw_folder . $file;
			if (is_file($file))
			{
				if (strpos($file, 'MUGS') !== false)
				{
					if (@$prev)
					{
						if (filemtime($file) > $prev)
						{
							
							$prev = filemtime($file);
							$mugs_file = $file;
						}
					}
					else
					{
						$prev = filemtime($file);
						$mugs_file = $file;
					}
				}
			}
		}
		//echo $mugs_file;
		$mugs_file_date = substr(preg_replace('/[^0-9]/Uis', "", $mugs_file),0,-4);
		$prev = null;
		foreach ($this->files as $file)
		{
			if (strpos($file, 'DATA') !== false)
			{
				if (strpos($file, $mugs_file_date) !== false)
				{
					$data_file = $this->raw_folder . $file;
				}
			}
		}
		$zip = new ZipArchive;
		$res = $zip->open($data_file);
		if ($res !== true)
		{
			return false;	
		}
		$check = $zip->extractTo($this->ext_folder);
		if ($check !== true)
		{
			return false;;
		}
		$zip->close();
		$zip = new ZipArchive;
		$res = $zip->open($mugs_file);
		if ($res !== true)
		{
			return false;	
		}
		$check = $zip->extractTo($this->ext_folder);
		if ($check !== true)
		{
			return false;;
		}
		$zip->close();
		foreach (scandir($this->ext_folder) as $file)
		{
			if (strpos($file, '.xml') !== false)
			{
				$xml = $file;
				break;
			}
		}
		$xml_string = file_get_contents($this->ext_folder . $xml);
		$arrests = new SimpleXMLElement($xml_string);
		$offenders = array();
		foreach ($arrests->Inmate as $arrest)
		{
			// Set booking date from mugs_file_date 
			$arrest->booking_date = mktime(0,0,0,substr($mugs_file_date,4,2),substr($mugs_file_date,6,2),substr($mugs_file_date,0,4));
			$offenders[] = $arrest;
		}
		foreach ($offenders as $inmate)
		{
			$extraction = $this->extraction($inmate);
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
    	$booking_id = $this->scrape . '_' . $details->InmateID; // set the booking_id to <scrapename>_<booking_id>
        // attempt to load the offender by booking_id
        $offender = Mango::factory('offender', array(
            'booking_id' => $booking_id
        ))->load(); 
        // if they are not loaded then continue with extraction, otherwise skip this offender
        if ( ! $offender->loaded() ) 
        {
			$firstname = trim($details->FirstName);
			$lastname = trim($details->LastName);
			$booking_date = (int)$details->booking_date;
			$charges = array();
			foreach ($details->Charge as $charge)
			{
				$charges[] = $this->clean_string_utf8(htmlspecialchars($charge->ChargeDescr));
			}
			if (empty($charges))
			{
				return 101;
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
			# validate 			
			if (empty($ncharges)) // skip the offender if ANY new charges were found
			{
				
				// make unique and reset keys
				$fcharges = array();
				// trim, uppercase and scrub htmlspecialcharacters
				foreach($charges as $charge)
				{
					$fcharges[] = htmlspecialchars_decode(strtoupper(trim($charge)), ENT_QUOTES);
				}	
				
				$fcharges = array_unique($fcharges);
				$fcharges = array_merge($fcharges);
				$dbcharges = $fcharges;
				
				
				// now clear an $extra_fields variable and start setting all extra fields
				$extra_fields = array();
				$extra_fields['dob'] = strtotime((string)$details->InmateDateOfBirth);
				$extra_fields['age'] = date('Y') - (int)$details->Age;
				$extra_fields['gender'] = $this->gender_mapper($details->Gender[0]);
				$check = $this->race_mapper((string)$details->Race[0]);
				if ($check)
				{
					$extra_fields['race'] = $check;
				}
				$extra_fields['weight'] = (int)$details->Weight;
				$height = str_split((string)$details->Height, 1);
				$inches = '';
				foreach ($height as $key => $value)
				{
					if ($key != 0)
					{
						$inches = (string)$inches + (string)$value;
					}
				}
				$extra_fields['height'] = ($height[0] * 12) + (int)$inches;
				$jpg = '';
				foreach($details->Charge as $charge)
				{
					if (@$charge->ArrestPhoto && (@$charge->ArrestPhoto != 'NO IMAGE'))
					{
						$jpg = $charge->ArrestPhoto;
					}
				}
				if (empty($jpg))
				{
					if (@$details->ImageFile)
					{
						$jpg = $details->ImageFile;
					}
				}
				if (empty($jpg))
				{
					return 102;
				}
				$img_file = $this->ext_folder . $jpg;
				$image_link = $img_file;
				# set image name
				
				$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
				# set image path
				$imagepath = '/mugs/ohio/fairfield/'. date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
		        // $imagepath = '/mugs/'.$this->state.'/'.$this->county'/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
		        # create mugpath
				$mugpath = $this->set_mugpath($imagepath);
				$new_file = $mugpath . $imagename . '.jpg';
				if ( ! file_exists($image_link))
				{
					return 102;
				}
				if ($image_link == '')
				{
					return 102;
				}
				try
				{
					$check = copy($image_link, $new_file);
				} 
				catch (Exception $e)
				{
					return 102;
				}
				if ( ! file_exists($new_file)) //validate the image was downloaded
				{
					return 102;
				}
				# ok I got the image now I need to do my conversions
		        # convert image to png.
		        $this->convertImage($mugpath.$imagename.'.jpg');
		        $imgpath = $mugpath.$imagename.'.png';
				$img = Image::factory($imgpath);
            	// crop it if needed, keep in mind mug_stamp function also crops the image
            	$img->crop(350, 480)->save();
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
			}	
        } else { return 103; } // database validation failed
    } // end extraction
} // class end