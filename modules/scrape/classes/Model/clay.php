<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Clay
 *
 * @package Scrape
 * @author	Winter King
 * @url 	http://www.claycountymo.gov/files/
 */
class Model_Clay extends Model_Scrape
{
    private $scrape     = 'clay'; //name of scrape goes here
	private $county 	= 'clay'; // if it is a single county, put it here, otherwise remove this property
    private $state      = 'missouri'; // state goes here
    private $cookies    = '/tmp/clay_cookies.txt'; // replace with <scrape name>_cookies.txt
    private $temp_folder = '/raw/missouri/clay/';
    
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
    * scrape - main scrape function makes the curl calls and sends details to the extraction function
    *
    * @return true - on completed scrape
    * @return false - on failed scrape
    */
    function scrape() 
    {
    	$temp = '/raw/missouri/clay/';
    	// Download the pdf from our server email
    	$hostname = '{imap.gmail.com:993/imap/ssl}INBOX';
		$username = 'sys@mugshotjunkie.com';
		$password = 'Lollip0p!'; 
		$inbox = imap_open($hostname, $username, $password) or die('Cannot connect to Gmail: ' . imap_last_error());
		$emails = imap_search($inbox, 'ALL');
		if ( ! $emails)
		{
			return false;		
		}
		
		rsort($emails);
		$urls = array();
		/*
		foreach ($emails as $email_num) 
		{
			$overview = imap_fetch_overview($inbox, $email_num, 0);
			$edate = strtotime('midnight', strtotime($overview[0]->date));
			$today = strtotime('midnight');
			$body = imap_fetchbody($inbox,$email_num,2);
			$check = preg_match_all('/href\=\"(.*)\"/Uis', $body, $matches);
			if ( ! $check) // It didn't find the urls that were supposed to be there.
				continue; // So continue.
			$this->print_r2($matches);
			exit;
			foreach ($matches[0] as $match)
			{
				$chars = str_split($match . '====');
				foreach ($chars as $key => $char)
				{
					if (in_array(ord($char), array(61, 13, 10)))
					{
						unset($chars[$key]);
					}
				}
				$chars = array_merge($chars);
				$url = '';
				foreach ($chars as $char)
				{
					$url .= $char;
				}
				$urls[] = $url;
			}
			break;
		} 
		 *
		
		 
		$urls = array(
			'http://www.claycountymo.gov/files/bp1001-1014p1.pdf',
			'http://www.claycountymo.gov/files/bp1001-1014p2.pdf',
			'http://www.claycountymo.gov/files/bp1001-1014p3.pdf',
			'http://www.claycountymo.gov/files/bp1001-1014p4.pdf',
			'http://www.claycountymo.gov/files/bp1001-1014p5.pdf',
			'http://www.claycountymo.gov/files/bp1001-1014p6.pdf',
		);
		
		// Ok I got the urls, now I need to download the PDFs
		$files = array();
		foreach ($urls as $url)
		{
			$pdf = $this->curl_pdf($url);
			$check = preg_match('/files\/(.*\.pdf)/', $url, $match);
			if ( ! $check )
			{
				return false;
			}
			$full_path = $temp . $match[1];
			$files[] = $full_path;
			if (file_exists($full_path))
			{
				unlink($full_path);
			}
			$fp = fopen($full_path, 'x');
			fwrite($fp, $pdf);
			fclose($fp);
		}  
		 * *  
		 */
		 
		$files = scandir($temp);
		$txt_files = array();
		// Now I have the files array and I can read one
		foreach ($files as $file)
		{
			if ($file == '.' || $file == '..' || strpos($file, '.pdf') === false)
				continue;
			$file = $temp . $file;
			$temp_images_folder = preg_replace('/\.pdf/Uis', '_images/', $file);
			if ( ! is_dir($temp_images_folder) )
			{
				$check = mkdir($temp_images_folder);
				if ( ! $check )
				{
					return false;
				}	
			}
			
			$text_pdf = preg_replace('/\.pdf/Uis', '.txt', $file);
			$temp_file = $text_pdf;
			/*
			if (file_exists($temp_file))
			{
				unlink($temp_file);
			}
			$check = shell_exec('pdftotext ' . $file . ' ' . $temp_file);
			if ( ! $check)
			{
				return false;
			}
			$check = shell_exec('pdfimages -j ' . $temp . $file . ' ' . $temp . $temp_images_folder);
			if ( ! $check)
			{
				return false;
			} 
			 */ 
			$fh = fopen($temp_file, 'r');
			$text = fread($fh, filesize($temp_file));
			
			fclose($fh);
			//unlink($temp_file);
			$txt_files[] = array('data' => $text, 'pdf' => $file);
		}
		
		// Loop through each file and break out individual detail pages
		$details_array = array();
		$count = 0;
		foreach ($txt_files as $key => $file)
		{
			// $offenders_layout = explode(' Clay County Sheriff', trim($file['layout']));
			$offenders_no_layout = explode('Clay County Sheriff', trim($file['data']));
			unset($offenders_no_layout[0]);
			$offenders_no_layout = array_merge($offenders_no_layout);
			foreach ($offenders_no_layout as $offender_no_layout)
			{
				$details_array[$count]['data'] = $offender_no_layout;
				$details_array[$count]['images_folder'] = str_replace('.pdf', '', $file['pdf']) . '_images/';
				$count++;
			}
		}
		// Now I need to calculate this offenders image
		$count = 0;
		foreach ($details_array as $key => $details)
		{
			$image_files = scandir($details['images_folder']);
			unset($image_files[0]);
			unset($image_files[1]);
			$image_files = array_merge($image_files);
			if ($count > (count($image_files) - 1))
			{
				$count = 0;
			}
			
			$offender_image_file = $details['images_folder'] . $image_files[$count];
			$extraction = $this->extraction($details['data'], $offender_image_file);
            if ($extraction == 100) { $this->report->successful = ($this->report->successful + 1); $this->report->update(); }
            if ($extraction == 101) { $this->report->other = ($this->report->other + 1); $this->report->update(); }
            if ($extraction == 102) { $this->report->bad_images = ($this->report->bad_images + 1); $this->report->update(); }
            if ($extraction == 103) { $this->report->exists = ($this->report->exists + 1); $this->report->update(); }
            if ($extraction == 104) { $this->report->new_charges = ($this->report->new_charges + 1); $this->report->update(); }
            $this->report->total = ($this->report->total + 1); $this->report->update();
			$count ++;
        }
        $this->report->failed = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
        $this->report->finished = 1;
        $this->report->stop_time = time();
        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
        $this->report->update();
        return true; 
    }

	/**
    * curl_index
    * 
    * @url
    * 
    */
    function curl_pdf($url)
    {
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        $index = curl_exec($ch);
        curl_close($ch);
        return $index;
    } 
      
    /**
    * curl_details
    * 
    * @url 
    *   
    */
    function curl_details($booking_id)
    {
        $url = 'http://example.com/handler?'.$booking_id;
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
    * @return false     - on failed extraction
    * @return true      - on successful extraction
    * 
    */
    function extraction($details, $image_file)
    {
    	$details = nl2br($details);
		$check = preg_match('/Age\<br\s\/\>\s<br\s\/\>\s(\d+)\<br/Uis', $details, $match);
        if ( ! $check )
		{
			return 101;
		}
		$booking_id = $this->scrape . '_' . $match[1];
        // attempt to load the offender by booking_id
        $offender = Mango::factory('offender', array(
            'booking_id' => $booking_id
        ))->load(); 
        // if they are not loaded then continue with extraction, otherwise skip this offender
        if ( $offender->loaded() ) 
        {
        	return 103; // database validation failed
		}
		// Get name
		$check = preg_match("/<br\s\/>\s<br\s\/\>\s([\s,A-Z]*)<br\s\/>\s<br\s\/>\sResidence\sCity/Uis", $details, $match);
        if ( ! $check )
		{
			return 101;
		}
		$fullname = $match[1];
		$explode = explode(', ', $fullname);
		if ( ! @$explode[0] || ! @$explode[1] )
		{
			return 101;
		}
		$firstname = $explode[1];
		$lastname = $explode[0];
		$explode = explode(' ', $firstname);
		$firstname = $explode[0];
		$check = preg_match('/Residence\sCity\<br\s\/\>\s<br\s\/\>\s([\/0-9]+)\<br/Uis', $details, $match);
        if ( ! $check )
		{
			return 101;
		}
		$booking_date = strtotime($match[1]);
		$check = preg_match('/Release\sConditions\<br\s\/\>\s<br\s\/\>\s(.+)\<br/Uis', $details, $match);
		if ( ! $check)
		{
			return 101;	
		} 
		$charges = array();
		$charges[0] = $this->clean_string_utf8($this->charge_trim(preg_replace('/\d*\.\d*\s/Uis', '', $match[1]))); 
		
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
		$check = preg_match('/LOCATION\sOF\sARREST\<br\s\/\>\s<br\s\/\>\s(\d+)\<br/Uis', $details, $match);
		if ($check)
		{
			$extra_fields['age'] = $match[1];	
		}
		# set image name
		$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
		# set image path
		// normally this will be set to our specific directory structure
		// but I don't want testing images to pollute our production folders
		$imagepath = '/mugs/missouri/clay/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
        # create mugpath
        // Move the image file over to the proper path and rename it
        $mugpath = $this->set_mugpath($imagepath);
		$check = copy($image_file, $imagepath . $imagename . '.jpg');	
		if ( ! ($check) )
		{
			return 102;
		}
		# ok I got the image now I need to do my conversions
        # convert image to png.
        $this->convertImage($mugpath.$imagename.'.jpg');
        $imgpath = $mugpath.$imagename.'.png';
		$img = Image::factory($imgpath);
    	// crop it if needed, keep in mind mug_stamp function also crops the image
    	$img->crop(480, 600, 0, 0)->save();
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