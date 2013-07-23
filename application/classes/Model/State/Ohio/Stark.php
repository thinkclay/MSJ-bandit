<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Stark County, Ohio Scrape
 *
 * @package
 * @description
 * @url http://xw.textdata.com:81/cgi/progcgi.exe?program=gbdate?recid=1272743?mfrom=02?dfrom=19?yfrom=2011?mto=02?dto=24?yto=2011?fid=seorj?admin=no?searchby=bookdate
 */
class Model_Ohio_Stark extends Model_Bandit
{
    private $scrape   = 'stark';
    private $state    = 'ohio';
    private $cookies  = '/tmp/stark_cookie.txt';

    public function __construct()
    {
        if ( file_exists($this->cookies) )
            unlink($this->cookies);

        // create mscrape model if one doesn't already exist
        $mscrape = Mango::factory('mscrape', array('name' => $this->scrape, 'state' => $this->state))->load();
        if ( ! $mscrape->loaded() )
        {
            $mscrape = Mango::factory('mscrape', array('name' => $this->scrape, 'state' => $this->state))->create();
        }

        // create report
        $a = explode (' ',microtime());
        $getTime = (double) $a[0] + $a[1];

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
                'start_time' => $getTime,
                'stop_time' => null,
                'time_taken' => null,
                'week' => $this->find_week(time()),
                'year' => date('Y'),
                'finished' => 0
            ]
        )->create();
    }

    public function scrape()
    {
        $from = strtotime('-7 days');
        $to = time();

        $index = $this->curl_index($from, $to);

        echo ($index);
        exit;

        // start a 100 count loop
        $flag = FALSE;
        while ( $flag == FALSE )
        {
            // build array of recid's
            preg_match_all('/program\=master\?fid\=seorj\?recid\=([^\>]*)\>/', $index, $recIds);
            $recIds = $recIds[1]; //drill down to just ids

            // validate for Ids
            if ( ! empty($recIds) )
            {
                $count = 0;

                foreach ( $recIds as $recId )
                {
                    $details = $this->curl_details($recId);

                    // check for valid image
                    // http://xw.textdata.com:81/photo/seorj/0275970.jpg
                    $extraction = $this->extraction($details);

                    if ($extraction == 100) { $this->report->successful = ($this->report->successful + 1); $this->report->update(); }
                    if ($extraction == 101) { $this->report->other = ($this->report->other + 1); $this->report->update(); }
                    if ($extraction == 102) { $this->report->bad_images = ($this->report->bad_images + 1); $this->report->update(); }
                    if ($extraction == 103) { $this->report->exists = ($this->report->exists + 1); $this->report->update(); }
                    if ($extraction == 104) { $this->report->new_charges = ($this->report->new_charges + 1); $this->report->update(); }
                    $this->report->total = ($this->report->total + 1); $this->report->update();
                }
            }
            else
            {
                $flag = TRUE;
            }

            // <A HREF="/cgi/progcgi.exe?program=gbdate?recid=315756?mfrom=08?dfrom=20?yfrom=2000?mto=09?dto=21?yto=2000?fid=seorj?admin=no?searchby=bookdate">MORE RECORDS...</A>
            # check for next at end of recid loop and if one is found, click next and start loop again
            $next_check = preg_match('/\<A\sHREF\=\"([^"]*)\"\>MORE\sRECORDS/', $index, $match);
            if ( $next_check == 1 )
            {
                $url = 'http://xw.textdata.com:81' . $match[1];
                $index = $this->curl_next($url);
            }
            else
            {
                $flag = TRUE;
            }
        }

        $this->report->failed = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
        $this->report->finished = 1;
        $this->report->stop_time = time();
        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
        $this->report->update();

        // check for any duplicate booking_ids and delete them
        // $this->bid_dupe_check('stark');

        // // check for duplicate firstname, lastname, and booking_id and flag them
        // $this->profile_dupe_check('stark');

        return TRUE;
    }

    function curl_index($from, $to)
    {
        $mfrom = date('m', $from);
        $dfrom = date('d', $from);
        $yfrom = date('Y', $from);
        $mto = date('m', $to);
        $dto = date('d', $to);
        $yto = date('Y', $to);
        $url = 'http://xw.textdata.com:81/cgi/progcgi.exe?mfrom='.$mfrom.'&dfrom='.$dfrom.'&yfrom='.$yfrom.'&mto='.$mto.'&dto='.$dto.'&yto='.$yto.'&searchby=bookdate&program=gbdate&fid=seorj';


echo $url; exit;
        $list = $this->load_url([
            'target'    => $this->urls['main'],
            'referrer'  => $this->urls['main'],
            'method'    => 'POST',
            'cookie'    => $this->cookie,
            'data'      => $post
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_PUT, TRUE);
        $index = curl_exec($ch);
        curl_close($ch);

        return $index;
    }

    function curl_details($recId)
    {
        //http://xw.textdata.com:81/cgi/progcgi.exe?program=master?fid=seorj?recid=1272748
        $url = 'http://xw.textdata.com:81/cgi/progcgi.exe?program=master?fid=seorj?recid='.$recId;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        $details = curl_exec($ch);
        curl_close($ch);

        return $details;
    }

    function curl_next($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        $next = curl_exec($ch);
        curl_close($ch);

        return $next;
    }

    function extraction($details)
    {
        $county = 'stark';
        $charges = array();
        unset($fcharges);
        unset($mcharges);
        unset($dbcharges);

        // extract profile details
        $this->source = $details;
        $this->anchor = 'Book ID';
        $this->anchorWithin = TRUE;
        $this->headerRow = TRUE;
        $this->stripTags = TRUE;

        try
        {
            $profile = $this->extractTable();
        }
        catch (ErrorException $e)
        {
            return 101;
        }

        // drill down a level
        $profile = $profile[1];

        // get profile details
        $booking_id = 'stark_'.$profile['BookID'];
        $lastname = $profile['LastName'];
        $firstname = $profile['FirstName'];

        // get booking_date
        preg_match('/BOOKDATE[^\>]*\>([^\<]*)\</', $details, $booking_date);
        $booking_date = strtotime($booking_date[1]);

        // extract charges
        $this->source = $details;
        $this->anchor = 'Offense Description';
        $this->anchorWithin = true;
        $this->headerRow = true;
        $this->cleanHtml = true;
        $this->stripTags = true;
        $charges_table = $this->extractTable();

        foreach ( $charges_table as $key => $value )
        {
            foreach ( $value as $header => $value2 )
            {
                if ( $header == 'OffenseDescription' )
                {
                    // format the charge (trim, strip spaces, uppercase)
                    $charge = strtoupper(trim(preg_replace('/\s/', '', $value2)));
                    $charges[] = $value2;
                }
            }
        }

        if ( ! empty($charges) )
        {
            // remove duplicates
            $charges = array_unique($charges);
            $smashed_charges = [];

            foreach ( $charges as $charge )
            {
                // smash it
                $smashed_charges[] = preg_replace('/\s/', '', $charge);
            }

            $dbcharges = $charges;

            // check for new charges
            // this creates a charges object for all charges that are not new for this county
            $charges_object = Mango::factory('charge', array('county' => $this->scrape, 'new' => 0))->load(false)->as_array(false);

            # I can loop through and pull out individual arrays as needed:
            $list = [];
            foreach ( $charges_object as $row )
            {
                $list[$row['charge']] = $row['abbr'];
            }

            // this gives me a list array with key == fullname, value == abbreviated
            $ncharges = array();

            // Run my full_charges_array through the charges check
            $ncharges = $this->charges_check($charges, $list);
            $ncharges2 = $this->charges_check($smashed_charges, $list);

            if ( ! empty($ncharges) ) // this means it found new charges (unsmashed)
            {
                if ( empty($ncharges2) ) // this means our smashed charges were found in the db
                {
                    $ncharges = $ncharges2;
                }
            }

            // validate against everything
            if ( empty($ncharges) ) // skip the offender if a new charge was found
            {
                $offender = Mango::factory('offender', array('booking_id' => $booking_id))->load();

                if ( empty($offender->booking_id) )
                {
                    if ( ! empty($firstname) )
                    {
                        if ( ! empty($lastname) )
                        {
                            if ( ! empty($booking_id) )
                            {
                                if ( ! empty($booking_date) )
                                {
                                    if ( ! empty($charges) )
                                    {
                                        /**
                                         * Ok now I have:
                                         * $firstname
                                         * $lastname
                                         * $booking_id
                                         * $booking_date
                                         * $image
                                         * $charges[]
                                         */
                                        // now get extra fields
                                        $extra_fields = array();
                                        $check = preg_match('/DOB\:[^>]*\>([^<]*)\</', $details, $dob);
                                        if ($check) { $dob = strtotime(trim($dob[1])); }
                                        if (isset($dob)) { $extra_fields['dob'] = $dob; }

                                        $check = preg_match('/Sex\:[^>]*\>([^<]*)\</', $details, $gender);
                                        if ($check)
                                        {
                                            $gender = strtoupper(trim($gender[1]));
                                            if ( $gender == 'M' )
                                            {
                                                $gender = 'MALE';
                                            }
                                            else if ( $gender == 'F' )
                                            {
                                                $gender = 'FEMALE';
                                            }
                                        }

                                        if ( isset($gender) )
                                        {
                                            $extra_fields['gender'] = $gender;
                                        }

                                        $check = preg_match('/Hair\:[^>]*\>([^<]*)\</', $details, $hair_color);
                                        if ( $check )
                                        {
                                            $hair_color = trim($hair_color[1]);
                                        }

                                        if ( isset($hair_color) )
                                        {
                                            $extra_fields['hair_color'] = $hair_color;
                                        }

                                        if ( preg_match('/Eyes\:[^>]*\>([^<]*)\</', $details, $eye_color) )
                                        {
                                            $eye_color = trim($eye_color[1]);
                                        }

                                        if ( isset($eye_color) )
                                        {
                                            $extra_fields['eye_color'] = $eye_color;
                                        }

                                        ### BEGIN IMAGE EXTRACTION ###
                                        # Get image link
                                        if ( ! preg_match('/(\/photo\/seorj\/[0-9]*\.jpg)/', $details, $img) )
                                            return 102;

                                        $img = 'http://xw.textdata.com:81'.$img[1];
                                        // $img = 'http://xw.textdata.com:81/photo/seorj/0109731.jpg';

                                        # Run through image logic
                                        # set image name
                                        $imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
                                        # set image path
                                        $imagepath = '/mugs/ohio/stark/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
                                        # create mugpath
                                        $mugpath = $this->set_mugpath($imagepath);

                                        $this->imageSource    = $img;
                                        $this->save_to        = $imagepath.$imagename;
                                        $this->set_extension  = true;
                                        $get = $this->download('gd');

                                        # validate against broken image
                    if ( $get )
                    {
                      # ok I got the image now I need to do my conversions
                          # convert image to png.
                          $this->convertImage($mugpath.$imagename.'.jpg');
                          $imgpath = $mugpath.$imagename.'.png';
                                          # crop it
                                          $img = Image::factory($imgpath);
                                          $img->crop(140, 150)->save();
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
                          if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
                          {
                              $mcharges   = $this->charges_prioritizer($list, $fcharges);
                        if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in Stark scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
                              $mcharges = array_merge($mcharges);
                              $charge1 = $mcharges[0];
                              $charge2 = $mcharges[1];
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
                              $fcharges = array_merge($fcharges);
                              $charge1 = $fcharges[0];
                              $charge2 = $fcharges[1];
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
                              $fcharges = array_merge($fcharges);
                              $charge1 = $fcharges[0];
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
                      # BOILERPLATE DATABASE INSERTS
                      $offender = Mango::factory('offender',
                                array(
                                  'scrape'    => $this->scrape,
                                  'state'     => strtolower($this->state),
                                  'county'    => $county,
                                    'firstname'     => $firstname,
                                    'lastname'      => $lastname,
                                    'booking_id'    => $booking_id,
                                    'booking_date'  => $booking_date,
                                    'scrape_time' => time(),
                                    'image'         => $imgpath,
                                    'charges'   => $dbcharges,
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
                    } else { return 102; } // image validation
                  } else { return 101; } // charges validation
                } else { return 101; } // booking_date validation
              } else { return 101; } // booking_id validation
            } else { return 101; } // lastname validation
          } else { return 101; } // firstname validation
        } else { return 103; } // database validation
      } else {
        # add new charges to the charges collection
        foreach ($ncharges as $key => $value)
        {
          //$value = preg_replace('/\s/', '', $value);
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
              $charge->new  = (int)0;
              $charge->update();
            }
          }
        }
              return 104;
          }
    } else { return 101; } // empty charges array
  } // end extraction
}