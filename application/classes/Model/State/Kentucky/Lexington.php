<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Model_Lexington
 *
 * @package Scrape
 * @author Winter King
 * @url http://jail.lfucg.com/
 */

class Model_State_Kentucky_Lexington extends Model_Scrape
{
    private $scrape  = 'lexington';
    private $state  = 'kentucky';
    private $cookies  = '/tmp/lexington_cookies.txt';

    public function __construct()
    {
        set_time_limit(3600); //make it go forever

        if ( file_exists($this->cookies) )
        {
            //delete cookie file if it exists
            unlink($this->cookies);
        }

        // create mscrape model if one doesn't already exist
        $mscrape = Mango::factory('mscrape', array('name' => $this->scrape, 'state' => $this->state))->load();

        if ( ! $mscrape->loaded() )
        {
            $mscrape = Mango::factory('mscrape', array('name' => $this->scrape, 'state' => $this->state))->create();
        }

        // create report
        $this->report = Mango::factory(
            'report', 
            array(
                'scrape' => $this->scrape, 
                'successful' => 0, 
                'failed' => 0, 
                'new_charges' => 0, 
                'total' => 0, 
                'bad_images' => 0, 
                'exists' => 0, 
                'other' => 0, 
                'start_time' => $this->getTime(), 
                'stop_time' => null, 
                'time_taken' => null, 
                'week' => $this->find_week(time()), 
                'year' => date('Y'), 
                'finished' => 0
            )
        )->create();
    }


    /**
     * scrape - main scrape function calls the curls and handles paging
     *
     * @params $date - timestamp of begin date
     * @return $info - passes to the controller for reporting
     */
     
    function print_r2($val)
    {
        echo '<pre>';
        print_r($val);
        echo  '</pre>';
    }
	
    function scrape()
    {
        $login_page = $this->curl_login_page();
        $check = preg_match('/.*name\=\"__RequestVerificationToken\".*value\=\"(.*)\"/Uis', $login_page, $match);
		
        if ( ! $check )
        {
            echo 'no token found<hr />';
            echo $login_page;
            exit;
        }
        
        $token = $match[1];
        $login = $this->curl_login($token);
        $search = $this->curl_search();
        // $check = preg_match_all('/\/Site\/InmateProfile\/(.*)\"/Uis', $search, $matches); // OLD check
        $check = preg_match_all('/(\/Jail\/(\d)+\/Inmate\?InmateGUID=[\w-]+)(?=\"\>View Arrest)/Uis', $search, $matches);

        // Got to this point preg_match_all failing because Site InmateProfile changed
        // Matt's Edits 12/9/2013 - The site has changed significantly and old link structure is non-existent.
        // Insertered new RegEx to capture the links to each inmate followin ght format /Jail/##/Inmate?InmateGUID=[hash-tag-number-thing]
		
        if ( ! $check )
        {
            echo 'no booking links<hr />';
            echo $search;
            exit;
        }

        $booking_links = $matches[1];
        $booking_links = array_unique($booking_links);
        $booking_links = array_merge($booking_links);
        
        $data = array();
        
        foreach ( $booking_links as $key => $booking_link )
        {
            $details = $this->curl_details($booking_link);            
            $extraction = $this->extraction($details, $booking_link);
            
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
        $this->print_r2($this->report->as_array());
        return true;
    }


    /**
     * curl_home - set the EV and VS
     *
     *@url http://jail.lfucg.com/
     *
     *
     */
    function curl_home()
    {
        $url = 'http://jail.lfucg.com/';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $home = curl_exec($ch);
        curl_close($ch);
        
        return $home;
    }


    /**
     * curl_search - gets the index of current population
     *
     *@url http://jail.lfucg.com/Query.aspx?OQ=f1c6dec3-959a-45d7-8619-f6d21a79d1fb
     *
     *
     */
    function curl_search()
    {
        // NameLast=&NameFirst=&NameMiddle=&MinAge=&MaxAge=&MinWeight=&MaxWeight=&Race=&Sex=&Facility=&SearchMode=48
        $post = 'AccountSearch.NameLast=&AccountSearch.NameFirst=&AccountSearch.NameMiddle=&AccountSearch.MinAge=&AccountSearch.MaxAge=&AccountSearch.MinWeight=&AccountSearch.MaxWeight=&AccountSearch.Race=&AccountSearch.Sex=&AccountSearch.Facility=&AccountSearch.SearchMode=48';
        $url = 'https://www.jailwebsite.com/Site/Search';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        $search = curl_exec($ch);
        curl_close($ch);
        
        return $search;
    }


    /**
     * curl_login - logs in to their website
     *
     *@url http://jail.lfucg.com/Query.aspx?OQ=f1c6dec3-959a-45d7-8619-f6d21a79d1fb
     *@notes Using login info:  msj777@hushmail.com / mugs
     *   sidenote, this is a HIGHLY insecure way to handle a username/password login
     *
     */
    function curl_login_page()
    {
        $url = 'https://www.jailwebsite.com/Site/Login';
        $headers = array(
            'Host: www.jailwebsite.com',
            'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:19.0) Gecko/20100101 Firefox/19.0',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: gzip, deflate',
            'Connection: keep-alive',
            'Pragma: no-cache',
            'Cache-Control: no-cache',
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        //curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        //curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $login = curl_exec($ch);
        curl_close($ch);
        return $login;
    }


    function curl_login($token = null)
    {

        // Nlq8HPRMk2RASItdK-DeVWOx7C4eoCEtGYszlM3Tpg1a3rvOQdiC9Mjhn1nhYjHuXSoL52DvOWAXOz6ykK56xFkKEL5l_BW5qRNBI4JwbUU1
        // Nlq8HPRMk2RASItdK-DeVWOx7C4eoCEtGYszlM3Tpg1a3rvOQdiC9Mjhn1nhYjHuXSoL52DvOWAXOz6ykK56xFkKEL5l_BW5qRNBI4JwbUU1

        $url = 'https://www.jailwebsite.com/Site/Login';
        $headers = array(
            'GET /Account/Login HTTP/1.1',
            'Host: www.jailwebsite.com',
            'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:19.0) Gecko/20100101 Firefox/19.0',
            'Referer: https://www.jailwebsite.com/Site/Login',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: gzip, deflate',
            'Connection: keep-alive',
            'Pragma: no-cache',
            'Cache-Control: no-cache',
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $fields = '__RequestVerificationToken='.$token.'&ReturnURL=%7E%2FSite%2FSearch&EmailAddress=mugs111%40hushmail.com&Password=mugs111&RememberMe=false';
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        $login = curl_exec($ch);
        curl_close($ch);
        return $login;
    }


    /**
     * curl_past - gets the index of past 48 hours
     *
     *@url http://jail.lfucg.com/Query.aspx?OQ=f1c6dec3-939b-45d7-8619-f6d21a79d1fb
     *
     *
     */
    function curl_past()
    {
        $url = 'http://jail.lfucg.com/Query.aspx?OQ=f1c6dec3-939b-45d7-8619-f6d21a79d1fb';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $past = curl_exec($ch);
        curl_close($ch);
        return $past;
    }


    /**
     * curl_index - gets the index of current population
     *
     *@url http://jail.lfucg.com/Query.aspx?OQ=f1c6dec3-959a-45d7-8619-f6d21a79d1fb
     *
     *
     */
    function curl_index($vs, $ev, $fn, $ln)
    {
        $url = 'http://jail.lfucg.com/Query.aspx?OQ=f1c6dec3-939b-45d7-8619-f6d21a79d1fb';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $index = curl_exec($ch);
        curl_close($ch);
        
        return $index;
    }


    /**
     * curl_details
     *
     * @notes  this is just to get the offender details page
     * @params string $row
     * @return string $details - details page in as a string
     */
    function curl_details($booking_link)
    {
        $url = 'https://www.jailwebsite.com/' . $booking_link;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        $details = curl_exec($ch);
        curl_close($ch);
        
        return $details;
    }


    //https://jailtracker.com/JTClientWeb/(S(cnwrnhb4uf1v2h55rgy15s20))/JailTracker/GetImage/

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
    function extraction($details, $booking_id)
    {
        // set the booking_id to <scrapename>_<booking_id>
        $booking_id = $this->scrape.'_'.$booking_id; 
        
        // attempt to load the offender by booking_id
        $offender = Mango::factory('offender', array('booking_id' => $booking_id))->load();
        
        // if they are not loaded then continue with extraction, otherwise skip this offender
        if ( $offender->loaded() )
            return 103;
        
        // get first and lastnames
        if ( ! preg_match('/(\<a\sClass\=\"inmate\-name\".*\"\>.*)(?=\<\/a\>)/Uis', $details, $match) )
            return 101;
        
		// Format for weird characters
        $fullname = $this->clean_string_utf8(htmlspecialchars_decode(trim($match[1]), ENT_QUOTES));
		$fullname = strip_tags($fullname); // added 12/9/2013 to take care of new preg_match_all capture
        $explode = explode(',', $fullname);
        $lastname = htmlspecialchars_decode(trim($explode[0], ENT_QUOTES));
        $firstname = trim($explode[1]);
        
        if ( ! preg_match('/(?<=(<td>))\d+\/\d+\/\d{4}/Uis', $details, $match) )
            return 101;

        // $booking_date = strtotime(str_replace('Arrested: ', '', $match[1])); // OLD matching
        $booking_date = strtotime($match[0]);

        // Check if its in the future which would be an error
        if ( $booking_date > strtotime('midnight', strtotime("+1 day")))
            return 101;
            
        // Get the table that the charges reside in
        if (! preg_match('/(?<=(<table class=\"inmate-offenses\" style=\"width: 100%; \">)).*(?=<\/table>)/ism', $details, $charges_table) ) {
			echo 'Error: Charge table not found for ' . $fullname . '<br />';
			return 101;
		}
        // get all the charges with preg_match_all funciton
        if ( ! preg_match_all('/(?<=<td>)(.*)(?=<\/td>(\s\n?)+<\/tr>)/i', $charges_table[0], $matches) ) {
            echo 'Error: No charges found for ' . $fullname . '<br />';
            return 101;
		}
            
        $charges = array();
        
        foreach ( $matches[0] as $charge )
        {
        	if ($charge == 'Offense') { // first row has the word 'Offense' so disregard
        		continue;
        	}
			
            // Run this function to cut down charges that are too long
            $charge = $this->charge_trim($charge);
            $charges[] = $this->clean_string_utf8(htmlspecialchars_decode(str_replace('&nbsp;', ' ', trim($charge)), ENT_QUOTES));
        }

        $charges = array_unique($charges);
        $charges = array_merge($charges);
        
        // the next lines between the ### are boilerplate used to check for new charges
        if ( ! $charges )
            return 101;
        
        // this creates a charges object for all charges that are not new for this county
        $charges_object = Mango::factory('charge', array('county' => $this->scrape, 'new' => 0))->load(false)->as_array(false);
        
        // I can loop through and pull out individual arrays as needed:
        $list = array();
        
        foreach ( $charges_object as $row )
        {
            $list[$row['charge']] = $row['abbr'];
        }
        
        // this gives me a list array with key == fullname, value == abbreviated
        $ncharges = array();
        
        // Run my full_charges_array through the charges check
        $ncharges = $this->charges_check($charges, $list);

        // validate
        // skip the offender if ANY new charges were found
        if ( ! empty($ncharges) ) 
        {
            // add new charges to the charges collection
            foreach ( $ncharges as $key => $value )
            {
                //check if the new charge already exists FOR THIS COUNTY
                $check_charge = Mango::factory('charge', array('county' => $this->scrape, 'charge' => $value))->load();
                
                if ( ! $check_charge->loaded() )
                {
                    if ( ! empty($value) )
                    {
                        $charge = Mango::factory('charge')->create();
                        $charge->charge = $value;
                        $charge->abbr = $value;
                        $charge->order = (int)0;
                        $charge->county = $this->scrape;
                        $charge->scrape = $this->scrape;
                        $charge->new  = (int)0;
                        $charge->update();
                    }
                }
            }
            return 104;
        }
        
        // make unique and reset keys
        $fcharges = array();
        
        // trim, uppercase and scrub htmlspecialcharacters
        foreach ( $charges as $charge )
        {
            $fcharges[] = htmlspecialchars_decode(strtoupper(trim($charge)), ENT_QUOTES);
        }
        
        $dbcharges = $fcharges;
		
		// get the table with the detention informaiton in it
		preg_match('/(?<=(<div class=\"container-header-555555\"><span class=\"Text\">Detention Information<\/span><\/div>))(.*?)(?=<\/table>)/ism', $details, $detention_table);
		
        if ( ! preg_match('/(?<=<td>)(?=(.*)\sCounty\,)/Uis', $detention_table[0], $match) )
            return 101;
            
        $county = $this->clean_string_utf8(htmlspecialchars_decode(str_replace('&nbsp;', ' ', trim($match[1])), ENT_QUOTES));

        // now clear an $extra_fields variable and start setting all extra fields
        $extra_fields = array();
        
        if ( preg_match('/(?<=<td class=\"ip-info\" style=\"\">)\d+\/\d+\/\d{4}/Uis', $details, $match) )
        {
        	var_dump($match); echo $fullname;
            $extra_fields['dob'] = strtotime($match[0]);
			
			$extra_fields['age'] = floor(((int)$booking_date - (int)$extra_fields['dob']) / 31536000);
        }
        
        

        if ( preg_match('/(?<=<td class=\"ip-info\">)(\w)(?=<)/Uis', $details, $match) )
        {
            $gender = $match[1];

            if ($gender == 'M')
            {
                $gender = 'MALE';
            } 
            else if ($gender == 'F')
            {
                $gender = 'FEMALE';
            }
            
            $extra_fields['gender'] = $gender;
        }
        
        if ( preg_match('/(?<=<td class=\"ip-info\">)([A-Z]{2,})(?=<)/Uis', $details, $match) ) // NOT WORKING - FIX LATER
        {
            // this will map race names to our standard format for races
            // ie. African American becomes Black,
            $extra_fields['race'] = $this->race_mapper($match[1]);
        }

        // now get the image link and download it
        if ( ! preg_match('/Inmates\/(.*)\"/Uis', $details, $match) ) {
            echo 'Error: Image link not found for ' . $fullname;
            return 102; // Image link not found
		}

        $image_link = 'https://www.jailwebsite.com/Content/Images/Inmates/' . $match[1];

        // https://www.jailwebsite.com/Content/Images/Inmates/60a06452-a79d-4d68-bf71-cf59fd6fe350.jpg
        // set image name
        $imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
		$imagename = str_replace('/', '', $imagename);
        // set image path
        // normally this will be set to our specific directory structure
        // but I don't want testing images to pollute our production folders
       $imagepath = '/mugs/kentucky/lexington/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
echo '<br />';
        // $imagepath = '/mugs/'.$this->state.'/'.$this->county'/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
        // create mugpath
        $mugpath = $this->set_mugpath($imagepath);

        //@todo find a way to identify extension before setting ->imageSource
        $this->imageSource    = $image_link;
        echo $this->save_to        = $imagepath.$imagename;
        $this->set_extension  = true;
        $this->cookie   = $this->cookies;
        $this->download('curl');
        //validate the image was downloaded
        if ( ! file_exists($imagepath.$imagename.'.jpg') ) {
            echo 'file not downloaded';
            return 102;
		}
        // ok I got the image now I need to do my conversions convert image to png.
        try
        {
            $this->convertImage($mugpath.$imagename.'.jpg');
        }
        catch(ErrorException $e)
        {
            unlink($imagepath.$imagename.'.jpg');
            return 102;
        }
        
        $imgpath = $mugpath.$imagename.'.png';
        $img = Image::factory($imgpath);
        // crop it if needed, keep in mind mug_stamp function also crops the image
        $img->crop(200, 258)->save();
        // get a count
        $chargeCount = count($fcharges);
        // run through charge logic
        // this is all boilerplate
        $mcharges  = array(); // reset the array
        if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
            {
            $mcharges  = $this->charges_prioritizer($list, $fcharges);
            if ($mcharges == false)
                { mail('bustedreport@gmail.com', 'Your prioritizer failed in marion scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
            $mcharges  = array_merge($mcharges);
            $charge1  = $mcharges[0];
            $charge2  = $mcharges[1];
            $charges  = $this->charges_abbreviator($list, $charge1, $charge2);
            $check = $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
            if ($check === false)
            {
                unlink($imgpath);
                return 101;
            }
        }
        else if ( $chargeCount == 2 )
            {
                $fcharges  = array_merge($fcharges);
                $charge1  = $fcharges[0];
                $charge2  = $fcharges[1];
                $charges  = $this->charges_abbreviator($list, $charge1, $charge2);
                $check = $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
                if ($check === false)
                {
                    unlink($imgpath);
                    return 101;
                }
            }
        else
        {
            $fcharges  = array_merge($fcharges);
            $charge1  = $fcharges[0];
            $charges  = $this->charges_abbreviator($list, $charge1);
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
        
        // BOILERPLATE DATABASE INSERTS
        $offender = Mango::factory('offender', array(
            'scrape'  => $this->scrape,
            'state'   => $this->state,
            'county'  => strtolower($county), // this may differ on sites with multiple counties
            'firstname'     => $firstname,
            'lastname'      => $lastname,
            'booking_id'    => $booking_id,
            'booking_date'  => $booking_date,
            'scrape_time' => time(),
            'image'         => $imgpath,
            'charges'  => $dbcharges,
        ))->create();

        foreach ($extra_fields as $field => $value)
        {
            $offender->$field = $value;
        }

        $offender->update();

        // now check for the county and create it if it doesnt exist
        $mscrape = Mango::factory('mscrape', array('name' => $this->scrape, 'state' => $this->state))->load();

        if ( ! $mscrape->loaded() )
        {
            $mscrape = Mango::factory('mscrape', array('name' => $this->scrape, 'state' => $this->state))->create();
        }

        $mscrape->booking_ids[] = $booking_id;
        $mscrape->update();

        return 100;
    } // end extraction
} // class end