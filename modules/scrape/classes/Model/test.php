<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Kalamazoo
 *
 * @package Scrape
 * @author 	
 * @url 	http://www.example.com
 */
class Model_Test extends Model_Scrape
{
    private $scrape     = 'test'; //name of scrape goes here
	private $county 	= 'test'; // if it is a single county, put it here, otherwise remove this property
    private $state      = 'test'; // state goes here
    private $cookies    = '/tmp/test_cookies.txt'; // replace with <scrape name>_cookies.txt
    private $path 		= '/raw/test/';
    
	private $remove		= null;
	
    public function __construct()
    {
        // Right now the claim is we have more photos than offenders meaning blank images, 
        //I thought I got all the blank offenders but keeps saying that so I decided to skip another offender 
        //with no info on them to see if evening it out works. 
        
    	// Add missing offenders here
    	$this->remove = array(
    	    'JOHNSON, NICOLE CHERIE',
    	    'PETERSON, MARCUSE J-ADARYL',
			'SANS, JOSEPH JACOB',
			'SMITH, ERICA LEATRICE',
			'BENSON, TERRI ELLEN'
		);
        set_time_limit(86400); //make it go forever 
        if ( file_exists($this->cookies) ) { unlink($this->cookies); } //delete cookie file if it exists        
        # create mscrape model if one doesn't already exist
        $mscrape = Mango::factory('mscrape', array('name' => $this->scrape, 'state' => $this->state))->load();
        if (!$mscrape->loaded())
        {
            $mscrape = Mango::factory('mscrape', array('name' => $this->scrape, 'state' => $this->state))->create();
        }
		$files = scandir($this->path);
		foreach ($files as $file)
		{
			if ($file == '.' || $file == '..')
				continue;
			else 
				unlink($this->path.$file);
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
		$search = $this->curl_search();
		$file = $this->path . "test_report.pdf";
		$fh = fopen($file, 'w') or die("can't open file");
		fwrite($fh, $search);
		fclose($fh);
    	$command = 'pdftohtml ' . $file . ' ' . $this->path.'kalamazoo_report.html';
		shell_exec($command);
		$files = scandir($this->path);
		$image_files = array();
		foreach ($files as $file)
		{
			$check = preg_match('/kalamazoo\_report\-([0-9]+)\_([0-9]+)\./Uis', $file, $match);
			if ( ! $check)
			{
				continue;
			}
			$image_files[$match[1]][$match[2]] = $file;
		}
		ksort($image_files);
		$img_files = array();
		foreach ($image_files as $image_set)
		{
			foreach ($image_set as $image_file)
			{
				$img_files[] = $image_file;
			}
		}
		array_shift($img_files);
		$html_file = $this->path . 'test_reports.html';
		$html_string = file_get_contents($html_file);
		//echo $html_string;
		$arr = preg_split('/[0-9]+\/[0-9]+\/[0-9]+\s[0-9]+\:[0-9]+/Uis', $html_string);
		$offenders = array();
		foreach ($arr as $off)
		{
			if (strpos($off, 'Inmate Name') !== false)
			{
				continue;
			}
			if (strpos($off, 'Page') !== false)
			{
				continue;
			}
			$offenders[] = $off;
		}
		$offenders2 = array();
		foreach ($offenders as $off)
		{
			$explode = explode('<br>', $off);
			$fullname = $explode[1];
			$fullname = str_replace(' ', '\s', $fullname);
			$check = preg_match('/([0-9]+\/[0-9]+\/[0-9]+\s[0-9]+\:[0-9]+\<br\>'.$fullname.'.*)[0-9]+\/[0-9]+\/[0-9]+\s[0-9]+\:[0-9]+/Uis', $html_string, $match);
			$offenders2[] = $match[1];
		}
		$offenders = $offenders2;
		foreach ($offenders as $key => $off)
		{
			$criminal = explode('<br>', $off);
			$fullname = $criminal[1];
			foreach ($this->remove as $key2 => $remove)
			{
				if (trim($fullname) == trim($remove))
				{
					unset($offenders[$key]);
					unset($this->remove[$key2]);
					break;
				}
			}
		}
		$offenders = array_merge($offenders);
		//		ALWAYS TESTING BEFORE RUNNING ON SERVER!
echo count($offenders) . ' ';
		echo count($img_files);
		exit;
		if (count($offenders) !== count($img_files))
		{
			echo 'Missing image!';
			exit;
		}
		foreach ($offenders as $key => $offender)
		{
 			$extraction = $this->extraction($offender, $img_files[$key]);
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
    * curl_index
    * 
    * @url
    * 
    */
    function curl_index()
    {
    	//$post = 'firstname=a&lastname=a'; // build out the post string here if needed
        $url = 'http://jail.kalcounty.com/Default.aspx';  // this will be the url to the index page
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		//curl_setopt($ch, CURLOPT_POST, true);
		//curl_setopt($ch, CURLOPT_POSTFIELDS, $post); // add post fields
        $index = curl_exec($ch);
        curl_close($ch);
        return $index;
    } 

   /**
    * curl_search
    * 
    * @url
    * 
    */
    function curl_search()
    {
    	$from = date('m/d/Y', strtotime('-6 days')); // 1/20/2013
		$to = date('m/d/Y', strtotime('-1 day'));
	    $url = 'http://jail.kalcounty.com/ArchonixXJailPublic/Reports/ReportViewer.aspx?ref=BOOKINGLOG,'.$from.','.$to.',0%20,_blank';  // this will be the url to the index page
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:18.0) Gecko/20100101 Firefox/18.0');
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
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
    function extraction($criminal, $img_file)
    {
    	$criminal = explode('<br>', $criminal);
		$booking_date = strtotime($criminal[0]);
		$fullname = $criminal[1];
		$booking_id = md5($fullname.$booking_date);
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
		$explode = explode(',', $fullname);
		$lastname = $this->clean_string_utf8($explode[0]);
		$explode1 = explode(' ', trim($explode[1]));
		$firstname = $this->clean_string_utf8($explode1[0]);
		// Check if its in the future which would be an error
		if ($booking_date > strtotime('midnight', strtotime("+1 day")))
		{
			return 101;
		} 
		// set the charges variable
		$charges = array();
		$chrg = $this->clean_string_utf8(htmlspecialchars_decode(str_replace('&nbsp;', ' ', trim($criminal[2])), ENT_QUOTES));
		if (empty($chrg))
		{
			return 101;	
			
		}
		$charges[] = $chrg;	
		
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
		$arr = str_split($lastname);
		# set image name
		$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
		# set image path
		// normally this will be set to our specific directory structure
		// but I don't want testing images to pollute our production folders
		$imagepath = '/mugs/michigan/kalamazoo/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
        // $imagepath = '/mugs/'.$this->state.'/'.$this->county'/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
        # create mugpath
        $mugpath = $this->set_mugpath($imagepath);
		$raw_image_path = $this->path . $img_file;
		copy($raw_image_path, $imagepath . $imagename.'.jpg');
		# ok I got the image now I need to do my conversions
        # convert image to png.
        $this->convertImage($mugpath.$imagename.'.jpg');
        $imgpath = $mugpath.$imagename.'.png';
		//$img = Image::factory($imgpath);
    	// crop it if needed, keep in mind mug_stamp function also crops the image
    	//$img->crop(400, 480)->save();
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