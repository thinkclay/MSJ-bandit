<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Platte
 *
 * @package Scrape
 * @author 	Winter King
 * 
 */
class Model_Platte extends Model_Scrape
{
    private $scrape     = 'platte'; //name of scrape goes here
	private $county 	= 'platte'; // if it is a single county, put it here, otherwise remove this property
    private $state      = 'missouri'; // state goes here
    private $cookies    = '/tmp/platte_cookies.txt'; // replace with <scrape name>_cookies.txt
    private $zip_dir	= '/raw/missouri/platte/upload/';
	private $unzip_dir	= '/raw/missouri/platte/extract/';
    private $data		= array();
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
		// Empty out the unzip directory
		$files = scandir($this->unzip_dir);
		foreach ($files as $file)
		{
			if ($file == '.' || $file == '..')
				continue;
			unlink($this->unzip_dir . $file);
		}
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
    	$files = scandir($this->zip_dir);
		$target = null;
		// Find the newest zip file
		foreach ($files as $file)
		{
			if (strpos($file, '.zip') === false) // Make sure we're dealing with zip files
				continue;
			if ($target) // Check if target has been set
			{
				if (filemtime($target) < filemtime($this->zip_dir . $file)) // Check if newer
				{
					$target = $this->zip_dir . $file; // Set new target if so
				}
			}
			else // If target has not been set yet
			{
				$target = $this->zip_dir . $file;	
			}
		}
		// Extract it to our unzip directory
		$zip = new ZipArchive;
		$res = $zip->open($target);
		if ($res === true)
		{
			$zip->extractTo($this->unzip_dir);
			$zip->close();
		}
		// Look in the unzip folder for the .csv file
		$files = scandir($this->unzip_dir);
		$csv = null;
		foreach ($files as $file)
		{
			if (strpos($file, '.csv') === false)
				continue;
			$csv = $this->unzip_dir . $file;
		}
		$handler = fopen($csv, 'r+');
		$count = 0;
		if ($handler)
		{
			while (($fh = fgetcsv($handler, 0, ",")) !== false) 
			{
				$count++;
				if ($count === 1)
					continue;
				if ($count === 2)
				{
					$headings = explode('^', $fh[0]);
				}
				else 
				{
					$offender_arr = explode('^', $fh[0]);
					foreach ($offender_arr as $key => $row)
					{
						$this->data[($count-2)][$headings[$key]] = $row; 
					}	
				}
				
			}
		}
		if ($this->data)
		{
			foreach ($this->data as $offender)
			{
				$extraction = $this->extraction($offender);
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
		} else {
			return false;
		}
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
    	$booking_id = $this->scrape . '_' . $details['Booking_NameNo']; // set the booking_id to <scrapename>_<booking_id>
    	
        // attempt to load the offender by booking_id
        $offender = Mango::factory('offender', array(
            'booking_id' => $booking_id
        ))->load(); 
        // if they are not loaded then continue with extraction, otherwise skip this offender
        if ( $offender->loaded() ) 
        {
        	return 103; // database validation failed
		} 
    	// get first and lastnames
    	

		try
		{
			$firstname = str_replace('"', '', $details['"Booking_FIRSTNAME"']);
			$lastname  = str_replace('"', '', $details['"Booking_LASTNAME"']);
			$middlename = str_replace('"', '', $details['"Booking_MIDNAME"']);
			// get booking date
			$booking_date = str_replace('"', '', $details['"Booking_BookDT"']);
			$explode = explode(' ', $booking_date);
			$explode = explode('-', $explode[0]);
			$booking_date = mktime(0, 0, 0, $explode[1], $explode[2], $explode[0]);	
		}
		catch(ErrorException $e)
		{
			return 101;
		}
		
		// set the charges variable
		$charges = array();
		$charges[] = $this->clean_string_utf8(htmlspecialchars_decode(str_replace('&nbsp;', ' ', trim(str_replace('"', '', $details['"OffClass_DescriptionText"']))), ENT_QUOTES));
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
		if ( ! empty($ncharges)) // skip the offender if ANY new charges were found
		{
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
		
		// now clear an $extra_fields variable and start setting all extra fields
		$extra_fields = array();
		
		$extra_fields['age'] = str_replace(' YRS', '', str_replace('"', '', $details['"Booking_PerAge"']));	
		$image_file = null;
    	if ($details['"Mugshots_PhotoId1"'] == "\"\"")
		{
			if ($details['"Mugshots_PhotoId2"'] == "\"\"")
			{
				return 102;
			}
			else
			{
				$image_file = $this->unzip_dir . str_replace('"', '', $details['"Mugshots_PhotoId2"']); 
			}
		}
		else
		{
			$image_file = $this->unzip_dir . str_replace('"', '', $details['"Mugshots_PhotoId1"']);	
		}
		# set image name
		$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
		# set image path
		// normally this will be set to our specific directory structure
		// but I don't want testing images to pollute our production folders
		$imagepath = '/mugs/missouri/platte/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
        // $imagepath = '/mugs/'.$this->state.'/'.$this->county'/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
        # create mugpath
        $mugpath = $this->set_mugpath($imagepath);
		//@todo find a way to identify extension before setting ->imageSource
		# ok I got the image now I need to do my conversions
        # convert image to png.
        // move image file to the imagepath
        try
        {
        	$check = copy($image_file, $mugpath.$imagename.'.jpg');
        } catch (ErrorException $e)
		{
			return 102;
		}
      
		if ($check === false)
		{
			return 102;
		}
        $this->convertImage($mugpath.$imagename.'.jpg');
		
        $imgpath = $mugpath.$imagename.'.png';
		$img = Image::factory($imgpath);
    	// crop it if needed, keep in mind mug_stamp function also crops the image
    	$img->crop(500, 468)->save();
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
    } // end extraction
} // class end