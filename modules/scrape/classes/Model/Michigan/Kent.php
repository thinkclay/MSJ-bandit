<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Model_Kent
 *
 * @TODO this one has a bug in it.  Getting the wrong info sometime.
 *       So far found ROBERT ROUSE and SAMUEL GONZALES
 * @package
 * @author Winter King
 * @params $booking_date datetime
 * @description This one takes a booking date in unix time format
 *              and scrape all new offenders
 * @url https://www.accesskent.com/InmateLookup/searchPrev.do
 */
class Model_Michigan_Kent extends Model_Scrape
{
    private $scrape = 'kent';
    private $state  = 'michigan';
    private $cookies = '/tmp/kent_cookie.txt';

    public function __construct()
    {
        $ncharges = $days_array = [];

        // lets loop through the past 7 days
        $date = date('m/d/Y');
        $time = strtotime($date);
        
        for ( $i = 0; $i < 5; $i++)
        {
            if ( $i == 0 ) 
            { 
                $days_array[] = $time; 
            }
            else
            {
                $time = $time - 86400;
                $days_array[] = $time;
            }
        }

        foreach ($days_array as $day)
        {
            $this->scrape($day);
        }
        // check for any duplicate booking_ids and delete them
        $this->bid_dupe_check($scrape);
        // check for duplicate firstname, lastname, and booking_id and flag them
        $this->profile_dupe_check($scrape);
        
    
        set_time_limit(86400); // make it go forever
        if ( file_exists($this->cookies) )
        {
            unlink($this->cookies);
        } // delete cookie file if it exists

        // create mscrape model if one doesn't already exist
        $mscrape = Mango::factory('mscrape', array('name' => $this->scrape, 'state' => $this->state))->load();
        if ( ! $mscrape->loaded() )
        {
            $mscrape = Mango::factory('mscrape', array('name' => $this->scrape, 'state' => $this->state))->create();
        }

        // create report
        $this->report = Mango::factory(
            'report',
            [
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
            ]
        )->create();
    }

    function scrape($booking_date = NULL)
    {
        # if there is no booking date passed, just use the current day
        if ( $booking_date == NULL )
        {
            $booking_date = time();
        }

        $month  = date('m', $booking_date);
        $day = date('d', $booking_date);
        $year = date('Y', $booking_date);

        // set cookie path
        $this->curl_homepage(); // this should set the cookie from the lookup page

        // now just do the search based on booking date
        $url = 'https://www.accesskent.com/InmateLookup/search.do?&lastName=&firstName=&dobMonth=&dobDay=&dobYear=&startMonth='
            .$month.'&startDay='.$day.'&startYear='.$year.'&Submit=Search&limit=100000';
        $referer = $url;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);

        // this gives me an index of offenders for a given booking date
        $index = curl_exec($ch);
        curl_close($ch);

        // Check for total amount of results
        preg_match('/Results[^o]*of[^0-9]*([0-9]*)/', $index, $rows);
        $rows = (int)trim($rows['1']);
        # get number of pages as 10 results per page.
        $pages = ceil($rows / 10);

        // alright go into a loop here for each page
        for ($i = 0; $i < $pages; $i++ )
        {
            if ( $i > 0 )
            {
                $index = $this->click_next();
            }

            // build my booking_number array
            //<INPUT type="submit" class="submitLink" value="1100031" name="bookNo" onclick="document.SearchResult.action='./showDetail.do'">
            //preg_match_all('/submitLink\"\svalue\=\"([^\"]*)\"\sname\=\"bookNo\"/', $index, $booking_ids);
            preg_match_all('/\<a\shref=.*bookNo=(\d+)"\>/Uis', $index, $booking_ids);
            $booking_ids = $booking_ids[1]; //rip out just the booking_id numbers
            # validate for booking_ids
            if (!empty($booking_ids))
            {
                //print_r($booking_ids);
                # loop through booking_number array and curl each one individually
                foreach ($booking_ids as $key => $booking_id)
                {
                    $details = $this->curl_details($booking_id);
                    $extraction = $this->extraction($details, $booking_id);
                } // end rows loop
            } // empty results validation
        } // end paging loop
        
        return TRUE;
    }

    function curl_homepage()
    {
        set_time_limit(86400);
        $url = 'https://www.accesskent.com/InmateLookup/';
        # set some vars
        #set curl variables
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
    function curl_details($booking_id)
    {
        set_time_limit(86400);
        $url = 'https://www.accesskent.com/InmateLookup/showDetail.do?bookNo='.$booking_id;
        //$url = 'https://www.accesskent.com/InmateLookup/showDetail.do?bookNo=';
        # curl inmate details page
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_REFERER, $url);
        curl_setopt($ch, CURLOPT_POST, false);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        # this gives me an index of offenders for a given booking date
        $details = curl_exec($ch);
        //$info = curl_getinfo($ch);
        curl_close($ch);
        return $details;
    }
    function curl_charges($booking_id)
    {
        set_time_limit(86400);
        $ref = 'https://www.accesskent.com/InmateLookup/showDetail.do?bookNo='.$booking_id;
        $url = 'https://www.accesskent.com/InmateLookup/showCharge.do';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_REFERER, $ref);
        // curl_setopt($ch, CURLOPT_POST, true);
        //curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        # this gives me an index of offenders for a given booking date
        $curl_charges = curl_exec($ch);
        //$info = curl_getinfo($ch);
        curl_close($ch);

        # rip out all the charges
        $check = preg_match_all('/Charge\s+Description:\<\/strong\>.*\;(.*)\</', $curl_charges, $matches);
        if(!$check)
        {
            $check = preg_match_all('/Hold\s+Description:\s+\<\/strong\>\<\/td\>\s+\<td\>\s+(.*)\s+\</Uis', $curl_charges, $matches);

        }
        if ($check)
        {
            $matches = $matches[1]; // drill down a level
            $charges = array();
            # loop and trim
            foreach ($matches as $charge)
            {
                $charges[] = trim($charge);
            }
            return $charges;
        }
        else
        {
            $charges = array();
            return $charges;
        }
    }

    function extraction($details, $booking_id)
    {
        $orig_booking_id = $booking_id;
        $county = 'kent'; // manually set county
        $booking_id = 'kent_' . $booking_id;
        $fullname = $this->get_fullname($details);

        if ( ! empty($fullname) )
        {
            # remove dot and trim so it doesn't mess up the image filename
            $fullname = preg_replace('/\./', '', $fullname);
            $fullname = trim($fullname);
            # remove all EXTRA whitespace in the name
            $fullname = preg_replace('/\s\s+/', ' ', $fullname);
            preg_match('/[^\s]*\s/', $fullname, $match);
            $firstname = trim($match[0]); //set as a string
            $lastname = preg_split('/\s+/', trim($fullname));
            $lastname = $lastname[count($lastname)-1];
            $lastname = trim($lastname);

            if ( preg_match('/Booking\sDate:\<\/strong\>.*\;(.*)\</', $details, $match) )
            {
                $booking_date = strtotime($match[1]);
                $extra_fields = [];

                if ( preg_match('/DOB:\<\/strong\>.*\;(.*)\</', $details, $dob) )
                {
                    $dob = trim($dob[1]); $dob = strtotime($dob);

                    $extra_fields['dob'] = $dob;
                    // calculate age at time of arrest
                    $extra_fields['age'] = floor(($booking_date - $dob) / 31556926);
                }

                if ( preg_match('/Sex:\<\/strong\>.*\;(.*)\</', $details, $gender) )
                {
                    $gender = trim($gender[1]); $gender = strtoupper($gender);
                    $extra_fields['gender'] = $gender;
                }

                if ( preg_match('/Race:\<\/strong\>.*\;(.*)\</', $details, $race) )
                {
                    if ( $race = $this->race_mapper(trim($race[1])) )
                    {
                        $race = strtoupper($race);
                        $extra_fields['race'] = $race;
                    }
                }

                if ( preg_match('/Eye\sColor\:\<\/strong\>.*\;(.*)\</', $details, $eye_color) )
                {
                    $eye_color = trim($eye_color[1]); $eye_color = strtoupper($eye_color);
                    $extra_fields['eye_color'] = $eye_color;
                }

                if ( preg_match('/Hair\sColor\:\<\/strong\>.*\;(.*)\</', $details, $hair_color) )
                {
                    $hair_color = trim($hair_color[1]); $hair_color = strtoupper($hair_color);
                    $extra_fields['hair_color'] = $hair_color;
                }

                if ( preg_match('/Height\:\<\/strong\>.*\;(.*)\</', $details, $height) )
                {
                    $height = trim($height[1]); $height = strtoupper($height);
                    $extra_fields['height'] = $this->height_conversion($height);
                }

                if ( preg_match('/Weight\:\<\/strong\>.*\;(.*)\</', $details, $weight) )
                {
                    $weight = trim($weight[1]); $weight = strtoupper($weight);
                    $extra_fields['weight'] = preg_replace('/[^0-9]/', '', $weight);
                }

                if ( $charges = $this->curl_charges($orig_booking_id) )
                {
                    $smashed_charges = $list = $ncharges = [];

                    foreach ( $charges as $charge )
                    {
                        // smash it
                        $smashed_charges[] = preg_replace('/\s/', '', $charge);
                    }

                    $dbcharges = $charges;

                    // check for new charges
                    // this creates a charges object for all charges that are not new for this county
                    $charges_object = Mango::factory('charge', array('county' => $this->scrape, 'new' => 0))->load(false)->as_array(false);

                    // I can loop through and pull out individual arrays as needed:
                    foreach ( $charges_object as $row )
                    {
                        $list[$row['charge']] = $row['abbr'];
                    }

                    // this gives me a list array with key == fullname, value == abbreviated
                    // Run my full_charges_array through the charges check
                    // this means it found new charges (unsmashed)
                    if ( $ncharges = $this->charges_check($charges, $list) )
                    {
                        if ( $ncharges2 = $this->charges_check($smashed_charges, $list) ) // this means our smashed charges were found in the db
                            $ncharges = $ncharges2;
                    }
                    if (empty($ncharges)) // skip the offender if a new charge was found
                    {
                        $offender = Mango::factory('offender', array(
                            'booking_id' => $booking_id
                        ))->load();

                        if ( empty($offender->booking_id) )
                        {
                            // make sure we didn't get the placeholder image.  ()
                            if ( ! preg_match('/(noimage\.jpg)/', $details, $match) )
                            {
                                # validation passed
                                ### BEGIN IMAGE EXTRACTION ###
                                # Now run through all the image logic
                                # set image name
                                $imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
                                # set image path
                                $imagepath = '/mugs/michigan/kent/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
                                # create mugpath
                                $mugpath = $this->set_mugpath($imagepath);

                                $this->imageSource    = 'https://www.accesskent.com/appImages/MugShots/'.preg_replace('/kent\_/Uis', '', $booking_id).'.jpg';
                                $this->save_to        = $imagepath.$imagename;
                                $this->set_extension  = true;
                                //$this->cookie       = $cookies;
                                $this->download('curl');
                                if (file_exists($this->save_to . '.jpg')) //validate the image was downloaded
                                {
                                    # ok I got the image now I need to do my conversions
                                    # convert image to png.
                                    $this->convertImage($mugpath.$imagename.'.jpg');
                                    $imgpath = $mugpath.$imagename.'.png';
                                    # now run through charge logic
                                    # trim and uppercase the charges
                                    $fcharges = array();
                                    foreach ($charges as $value)
                                    {
                                        $fcharges[] = strtoupper(trim($value));
                                    }
                                    # remove duplicates
                                    $fcharges = array_unique($fcharges);
                                    $chargeCount = count($fcharges); //set charge count
                                    # run through charge logic

                                    // if more then 2, run through charges prioritizer
                                    if ( $chargeCount > 2 )
                                    {
                                        $mcharges = $this->charges_prioritizer($list, $fcharges);
                                        if ( $mcharges == false )
                                        {
                                            error_log("unable to prioritize charges for: $booking_id");
                                            mail(
                                                'thinkclay@gmail.com',
                                                'Your prioritizer failed in Kent scrape',
                                                "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"
                                            );
                                        }
                                        $mcharges = array_merge($mcharges);
                                        $charge1 = $mcharges[0];
                                        $charge2 = $mcharges[1];
                                        $charges    = $this->charges_abbreviator($list, $charge1, $charge2);
                                        $check = $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);

                                        if ( $check === false )
                                        {
                                            error_log("error 101: failed to stamp mug for: $booking_id");
                                            unlink($imgpath);
                                            return 101;
                                        }
                                    }
                                    else if ( $chargeCount == 2 )
                                    {
                                        $fcharges = array_merge($fcharges);
                                        $charge1 = $fcharges[0];
                                        $charge2 = $fcharges[1];
                                        $charges    = $this->charges_abbreviator($list, $charge1, $charge2);
                                        $check = $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);

                                        if ( $check === false )
                                        {
                                            error_log("error 101: failed to stamp mug for: $booking_id");
                                            unlink($imgpath);
                                            return 101;
                                        }

                                    }
                                    else
                                    {
                                        $fcharges = array_merge($fcharges);
                                        $charge1 = $fcharges[0];
                                        $charges    = $this->charges_abbreviator($list, $charge1);
                                        $check = $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0]);

                                        if ( $check === false )
                                        {
                                            error_log("error 101: failed to stamp mug for: $booking_id");
                                            unlink($imgpath);
                                            return 101;
                                        }

                                    }
                                    // Abbreviate FULL charge list
                                    $dbcharges = $this->charges_abbreviator_db($list, $dbcharges);
                                    $dbcharges = array_unique($dbcharges);
                                    # BOILERPLATE DATABASE INSERTS
                                    $offender = Mango::factory(
                                        'offender',
                                        [
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
                                        ]
                                    )->create();

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
                                    error_log("success 100: new record for: $booking_id");
                                    return 100;
                                }
                                else
                                {
                                    error_log("error 102: failed to save mug for: $booking_id");
                                    unlink($this->save_to . '.jpg');
                                    return 102;
                                } // get failed
                            }
                            else
                            {
                                error_log("error 102: failed to stamp mug for: $booking_id");
                                return 102;
                            }
                        }
                        else
                        {
                            error_log("error 103: failed to save to database for: $booking_id");
                            return 103;
                        } // database validation failed
                    }
                    # validation failed on charge check. So return the new_charges array
                    # also add the new charges to the database
                    else
                    {
                        # add new charges to the charges collection
                        foreach ( $ncharges as $key => $value )
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
                        error_log("error 104: charges check failed for: $booking_id");
                        return 104;
                    }
                }
                else
                {
                    error_log("error 101: failed to validate charges for: $booking_id");
                    return 101;
                } // charge validation failed
            }
            else
            {
                error_log("error 101: failed to validate booking date for: $booking_id");
                return 101;
            } // booking_date validation failed
        }
        else
        {
            error_log("error 101: failed to validate full name for: $booking_id");
            return 101;
        } // fullname validation failed
    }

    function get_fullname($details)
    {
        set_time_limit(86400);
        //preg_match('/Name:[^<]*\<[^<]*\<[^>]*\>([^<]*)\</', $details, $match);
        preg_match('/Name:\<\/strong\>.*\;(.*)\</', $details, $match);
        @$fullname = $match[1];
        return $fullname;
    }

    #this will click next for paging
    function click_next()
    {
        //https://www.accesskent.com/InmateLookup/searchNext.do
        set_time_limit(86400);
        $url = 'https://www.accesskent.com/InmateLookup/searchNext.do';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        $next = curl_exec($ch);
        curl_close($ch);
        return $next;
    }
}