<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Model_Arrest
 *
 * @package Scrape
 * @author 	
 * @url 	http://www.example.com
 */
class Model_Arrest extends Model_Scrape
{
    private $scrape = 'arrest'; //name of scrape goes here
    private $county = 'arrest'; // if it is a single county, put it here, otherwise remove this property
    private $state = 'arrest'; // state goes here
    private $cookies = '/tmp/arrest_cookies.txt'; // replace with <scrape name>_cookies.txt
    
    private $remove = null;
    
    public function __construct()
    {
        set_time_limit(86400); //make it go forever 
        if (file_exists($this->cookies)) {
            unlink($this->cookies);
        } //delete cookie file if it exists        
        # create mscrape model if one doesn't already exist
        $mscrape = Mango::factory('mscrape', array(
            'name' => $this->scrape,
            'state' => $this->state
        ))->load();
        if (!$mscrape->loaded()) {
            $mscrape = Mango::factory('mscrape', array(
                'name' => $this->scrape,
                'state' => $this->state
            ))->create();
        }
        # create report
        $this->report = Mango::factory('report', array(
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
        ))->create();
    }
    
    function print_r2($val)
    {
        echo '<pre>';
        print_r($val);
        echo '</pre>';
    }
    
    /**
     * scrape - main scrape function makes the curl calls and sends details to the extraction function
     *
     * @return true - on completed scrape
     * @return false - on failed scrape
     */
    function scrape()
    {
        $homepage = $this->curl_index();
        //http://arre.st/Mugshots/WestVirginia/CRJ/Melissa++Neal/1001050456/mugshot.html
        //(http:\/\/.*\.html)
        // /\bhttp\S*?html\b/Uis
        
        
        // Specify configuration
        $config = array(
            'doctype' => 'html5',
            'indent' => true,
            'output-xhtml' => true,
            'wrap' => 200
            
        );
        
        // Tidy
        $tidy = new tidy;
        $tidy->parseString($homepage, $config, 'utf8');
        $tidy->cleanRepair();
        
        preg_match_all('/http:\/\/arre.st.*.html/i', $tidy, $home_links);
        
        $home_links = $home_links[0];
        $success    = 0;
        
        $this->print_r2($home_links);
        
        for ($i = 0; $i < count($home_links); $i++) {
            //$url = 'http://arre.st/Mugshots/Alabama/Etowah/Jessie++Marrie/111207677/mugshot.html';
            $url        = $home_links[$i];
            $search     = $this->curl_search($url);
            $explode    = explode('/', $url);
            $state      = strtolower($explode[4]);
            $county     = preg_replace('/\./', '', preg_replace('/\s/', '_', urldecode(strtolower($explode[5]))));
            
            echo "<strong>{$state}</strong>: <em>{$county}</em><br />";
            
            $extraction = $this->extraction($search, $state, $county);
            
            if ($extraction == 100) {
                $this->report->successful = ($this->report->successful + 1);
                $this->report->update();
                $success++;
                echo "successful";
            }
            if ($extraction == 101) {
                $this->report->other = ($this->report->other + 1);
                $this->report->update();
            }
            if ($extraction == 102) {
                $this->report->bad_images = ($this->report->bad_images + 1);
                $this->report->update();
            }
            if ($extraction == 103) {
                $this->report->exists = ($this->report->exists + 1);
                $this->report->update();
            }
            if ($extraction == 104) {
                $this->report->new_charges = ($this->report->new_charges + 1);
                $this->report->update();
            }
            $this->report->total = ($this->report->total + 1);
            $this->report->update();
        }
        
        var_dump($this->report);
        
        echo "processed {$success} links successfully";
        //exit;
        /*
        foreach ($offenders as $key => $offender)
        {
        $extraction = $this->extraction();
        if ($extraction == 100) { $this->report->successful = ($this->report->successful + 1); $this->report->update(); }
        if ($extraction == 101) { $this->report->other = ($this->report->other + 1); $this->report->update(); }
        if ($extraction == 102) { $this->report->bad_images = ($this->report->bad_images + 1); $this->report->update(); }
        if ($extraction == 103) { $this->report->exists = ($this->report->exists + 1); $this->report->update(); }
        if ($extraction == 104) { $this->report->new_charges = ($this->report->new_charges + 1); $this->report->update(); }
        $this->report->total = ($this->report->total + 1); $this->report->update();
        
        }
        * */
        $this->report->failed     = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
        $this->report->finished   = 1;
        $this->report->stop_time  = time();
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
        $url = 'http://arre.st/'; // this will be the url to the index page
        $ch  = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_REFERER, 'http://arre.st/'); // Referer value
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
    function curl_search($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_REFERER, 'http://arre.st/'); // Referer value
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
    function extraction($search, $state, $county)
    {
        //Finds the first td that has name and image
        $check = preg_match_all("/\<td\salign\=center\>(.*)\<\/td\>/Uis", $search, $matches);
        if (!$check) {
            return 101;
        }
        //Split each item and build array by the center tag
        $center_match = preg_split("/\<\/center\>/", $matches[1][0]);
        //grab full name, first match
        $fullname     = strip_tags(trim($center_match[0]));
        
        
        //Find the image file from second match of center
        $check = preg_match_all("/src\=\"(.*?)\"/", $center_match[1], $imagematch);
        if (!$check) {
            return 101;
        }
        $imagefile = $imagematch[1][0];
        
        //Finds the second td that has all the other info
        $check = preg_match_all("/\<td\svalign\=top\>(.*)\<\/td\>/Uis", $search, $matches2);
        if (!$check) {
            return 101;
        }
        //Split each item and build array by the b tag
        $check = preg_match_all("/\<\/b\>(.*)\<b\>/Uis", $matches2[1][0], $moreinfo);
        if (!$check) {
            return 101;
        }
        
        //Get Age and Booking Date
        $age          = strip_tags(trim($moreinfo[0][2]));
        $booking_date = strip_tags(trim($moreinfo[0][3]));
        $booking_date = preg_replace('/-/', '/', $booking_date);
        $booking_date = strtotime($booking_date);
        
        
        //Just extract the number for Booking ID
        $check = preg_match_all('/\d+/', strip_tags(trim($moreinfo[0][4])), $booking_id);
        if (!$check) {
            return 101;
        }
        $booking_id = $booking_id[0][0];
        
        // attempt to load the offender by booking_id
        $offender = Mango::factory('offender', array(
            'booking_id' => $booking_id
        ))->load();
        // if they are not loaded then continue with extraction, otherwise skip this offender
        if ($offender->loaded()) {
            return 103; // database validation failed
        }
        
        // get first and lastnames
        $explode   = explode(' ', $fullname);
        $lastname  = $this->clean_string_utf8($explode[2]);
        $firstname = $this->clean_string_utf8($explode[0]);
        // Check if its in the future which would be an error
        if ($booking_date > strtotime('midnight', strtotime("+1 day"))) {
            return 101;
        }
        
        // set the charges variable
        $charges = array();
        //To find the charge we are looking for the i tag.
        $check   = preg_match_all('/\<i\>(.*)\<\/i\>/Uis', $matches2[0][0], $charges_matches);
        if (!$check) {
            return 101;
        }
        //str_replace("<br>", " ", $charge[1]);
        for ($i = 0; $i < count($charges_matches[1]); $i++) {
            $charges[$i] = strip_tags(trim($charges_matches[1][$i]));
        }
        
        // the next lines between the ### are boilerplate used to check for new charges
        ###
        # this creates a charges object for all charges that are not new for this county
        $charges_object = Mango::factory('charge', array(
            'county' => $this->scrape,
            'new' => 0
        ))->load(false)->as_array(false);
        # I can loop through and pull out individual arrays as needed:
        $list           = array();
        foreach ($charges_object as $row) {
            $list[$row['charge']] = $row['abbr'];
        }
        # this gives me a list array with key == fullname, value == abbreviated
        $ncharges = array();
        # Run my full_charges_array through the charges check
        $ncharges = $this->charges_check($charges, $list);
        ###
        
        # validate 
        if (!empty($ncharges)) // skip the offender if ANY new charges were found for that offender
            {
            # add new charges to the charges collection
            foreach ($ncharges as $key => $value) {
                #check if the new charge already exists FOR THIS COUNTY
                $check_charge = Mango::factory('charge', array(
                    'county' => $this->scrape,
                    'charge' => $value
                ))->load();
                if (!$check_charge->loaded()) {
                    if (!empty($value)) {
                        $charge         = Mango::factory('charge')->create();
                        $charge->charge = $value;
                        $charge->abbr   = $value;
                        $charge->order  = (int) 0;
                        $charge->county = $this->scrape;
                        $charge->scrape = $this->scrape;
                        $charge->new    = (int) 0;
                        $charge->update();
                    }
                }
            }
            return 104;
        }
        // make unique and reset keys
        $charges  = array_unique($charges);
        $charges  = array_merge($charges);
        $fcharges = array();
        // trim, uppercase and scrub htmlspecialcharacters
        foreach ($charges as $charge) {
            $fcharges[] = htmlspecialchars_decode(strtoupper(trim($charge)), ENT_QUOTES);
        }
        $dbcharges = $fcharges;
        
        // now clear an $extra_fields variable and start setting all extra fields
        $extra_fields = array();
        $arr          = str_split($lastname);
        
        # set image name
        $imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
        # set image path
        // normally this will be set to our specific directory structure
        // but I don't want testing images to pollute our production folders
        $imagepath = "/mugs/$state/$county/" . date('Y', $booking_date) . '/week_' . $this->find_week($booking_date) . '/';
        // $imagepath = '/mugs/'.$this->state.'/'.$this->county'/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
        
        # create mugpath
        $mugpath             = $this->set_mugpath($imagepath);
        $this->imageSource   = $imagefile;
        $this->save_to       = $imagepath . $imagename;
        $this->set_extension = true;
        $get                 = $this->download('gd');
        if ($get) {
            echo $mugpath.$imagename.'.jpg';
            
            # convert image to png.
            if ( file_exists($mugpath.$imagename.'.jpg') ) 
            {
                $this->convertImage($mugpath.$imagename.'.jpg');
                $imgpath = $mugpath.$imagename.'.png';
            } 
            else if ( file_exists($mugpath.$imagename.'.png') )
            {
                $imgpath = $mugpath.$imagename.'.png';
            }
            else
            {
                return;
            }
            
            /*
            $img = Image::factory($imgpath);
            // crop it if needed, keep in mind mug_stamp function also crops the image
            $img->crop(400, 430, 0, 0)->save();
            */
            // get a count
            $chargeCount = count($fcharges);
            // run through charge logic	
            // this is all boilerplate
            $mcharges    = array(); // reset the array
            if ($chargeCount > 2) //if more then 2, run through charges prioritizer
                {
                $mcharges = $this->charges_prioritizer($list, $fcharges);
                if ($mcharges == false) {
                    mail('winterpk@bychosen.com', 'Your prioritizer failed in marion scrape', "******Debug Me****** \n-=" . $fullname . "=-" . "\n-=" . $booking_id . "=-");
                    exit;
                } // debugging
                $mcharges = array_merge($mcharges);
                $charge1  = $mcharges[0];
                $charge2  = $mcharges[1];
                $charges  = $this->charges_abbreviator($list, $charge1, $charge2);
                $check    = $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
                if ($check === false) {
                    unlink($imgpath);
                    return 101;
                }
            } else if ($chargeCount == 2) {
                $fcharges = array_merge($fcharges);
                $charge1  = $fcharges[0];
                $charge2  = $fcharges[1];
                $charges  = $this->charges_abbreviator($list, $charge1, $charge2);
                $check    = $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
                if ($check === false) {
                    unlink($imgpath);
                    return 101;
                }
            } else {
                $fcharges = array_merge($fcharges);
                $charge1  = $fcharges[0];
                $charges  = $this->charges_abbreviator($list, $charge1);
                $check    = $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0]);
                if ($check === false) {
                    unlink($imgpath);
                    return 101;
                }
            }
            // Abbreviate FULL charge list
            $dbcharges = $this->charges_abbreviator_db($list, $dbcharges);
            $dbcharges = array_unique($dbcharges);
            # BOILERPLATE DATABASE INSERTS
            $offender  = Mango::factory('offender', array(
                'scrape' => $this->scrape,
                'state' => $this->state,
                'county' => strtolower($this->county), // this may differ on sites with multiple counties
                'firstname' => $firstname,
                'lastname' => $lastname,
                'booking_id' => $booking_id,
                'booking_date' => $booking_date,
                'scrape_time' => time(),
                'image' => $imgpath,
                'charges' => $dbcharges
            ))->create();
            #add extra fields
            foreach ($extra_fields as $field => $value) {
                $offender->$field = $value;
            }
            $offender->update();
            
            # now check for the county and create it if it doesnt exist 
            $mscrape = Mango::factory('mscrape', array(
                'name' => $this->scrape,
                'state' => $this->state
            ))->load();
            if (!$mscrape->loaded()) {
                $mscrape = Mango::factory('mscrape', array(
                    'name' => $this->scrape,
                    'state' => $this->state
                ))->create();
            }
            $mscrape->booking_ids[] = $booking_id;
            $mscrape->update();
            # END DATABASE INSERTS
            return 100;
            ### END EXTRACTION ###
        } else {
            return 102;
        }
    } // end extraction
} // class end