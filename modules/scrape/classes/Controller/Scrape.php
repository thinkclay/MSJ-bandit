@<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Srape - Main module for webscraping mugshot images and data
 *
 * @TODO
 * @package default
 * @author  Winter King
 */
class Controller_Scrape extends Controller
{
    public function before()
    {
        set_time_limit(86400);

        // if (php_sapi_name() !== 'cli')
        // {
        //     //echo 'Unauthorized access';
        //     //exit;
        // }
    }

    public function action_index()
    {
        echo 'hello scraper';
    }

    private function print_r2($val)
    {
        echo '<pre>';
        print_r($val);
        echo  '</pre>';
    }

    // this is used to cleanup offenders without valid images
    public function action_cleanup($scrape)
    {
        $offenders = Mango::factory('offender', array('scrape' => $scrape))->load(false)->as_array();
        $count = 0;
        foreach($offenders as $offender)
        {
            if(!file_exists($offender->image))
            {
                $count++;
                echo $count . ' Offender(s) without image file <br />';
                echo $offender->firstname . ' ' . $offender->lastname;
                echo '<br />' . $offender->booking_id;
                echo 'Deleting...';
                echo '<hr>';
                $bad_one = Mango::factory('offender', array('booking_id' => $offender->booking_id))->load();
                $bad_one->delete();
            }
            foreach($offender->charges as $charge)
            {
                if ($charge == '&NBSP;')
                {
                    echo 'Offender ' . $offender->firstname . ' ' . $offender->lastname . ' has bad charges.';
                    echo '<br /> Deleting...';
                    echo '<br />';
                    $bad_one = Mango::factory('offender', array('booking_id' => $offender->booking_id))->load();
                    $bad_one->delete();
                }
            }
        }

        //print_r($offenders);
        exit;
    }

    public function action_maint()
    {
        $file = '/mugs/booking_list_2.pdf';
        $contents = shell_exec('/usr/bin/pdftotext '.$file . ' -');
        $check = preg_match_all('/NAME\:\s(.*)\sArrest\sCity/Uis', $contents, $m);
        $total = 0;
        $matches = 0;
        $match_names = array();
        $non_matches = 0;
        $non_match_names = array();
        foreach ($m[1] as $key => $value)
        {
            $explode = explode(',', trim($value));
            $lastname = $explode[0];
            $explode = explode(' ', trim($explode[1]));
            $firstname = trim($explode[0]);
            $offender = Mango::factory('offender')->load(1, null, null, array(), array('scrape'=>'anoka', 'firstname'=>$firstname, 'lastname'=>$lastname));
            if ($offender->loaded())
            {
                $matches++;
                $match_names[] = $firstname . ' ' . $lastname;
            } else {
                $non_matches++;
                $non_match_names[] = $firstname . ' ' . $lastname;
            }
            $total++;
        }
        $message = "Total Offenders Checked: " . $total . "<br />";
        $message .= "<hr />Found: ".$matches."<br /><hr />";
        foreach ($match_names as $name)
        {
            $message .= $name . "<br />";
        }
        $message .= "<hr />Not Found: ".$non_matches."<br /><hr />";
        foreach ($non_match_names as $name)
        {
            $message .= $name . "<br />";
        }
        echo $message;

        $headers = "FROM: admin@mugshotjunkie.com\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
        //mail('winterpk@arr.ae', 'report', $message, $headers);
        exit;
        //set_time_limit(0);
        if (is_file($pdf))
        {

        }
        exit;
        $offenders = Mango::factory('offender')->load(false, null, null, array(), array('scrape'=>'njdoc'));

        foreach ($offenders->as_array() as $offender)
        {
            $offender->county = strtolower($offender->county);
            $offender->update();
            //offender->booking_id = 'utah_' . $offender->booking_id;
            //$offender->update();
        }
        echo 'updated';
        exit;
        //$this->print_r2($offenders);
    }

    public function action_nightly_report()
    {
        $body_inc = 0;
        $counter = 0;
        $week_calculator = new model_scrape();
        $current_week = $week_calculator->find_week(time());
        $body = '<html><body><table width="1350" cellpadding="5" cellspacing="5" border="1" style="font-size:10pt;"><tr><td colspan="9"><p align="center" style="font-size:18pt;">Nighty Scrape Report</p></td></tr>';
        $mugs = scandir('/mugs');
        foreach ($mugs as $state_key => $state_name)
        {
            if(is_dir('/mugs/' . $state_name))
            {
                if( $state_name !== '.' AND $state_name !== '..' AND $state_name !== 'test')
                {
                    $counties = scandir('/mugs/' . $state_name);
                    foreach ($counties as $counties_key => $county_name)
                    {
                        if( $county_name !== '.' AND $county_name !== '..')
                        {
                            if(!file_exists('/mugs/' . $state_name . '/' . $county_name . '/' . date('Y') . '/week_' . $current_week))
                            {
                                if($body_inc == 0)
                                {
                                    $body .= '<tr>';
                                }
                                $body .= '<td height="100" width="110">State: ' . $state_name . '<br />County: ' . $county_name . '<br />No Folder<br /></td>';
                                If($body_inc == 8)
                                {
                                    $body .= '</tr>';
                                    $body_inc = -1;
                                }
                            }
                            else
                            {
                                if($body_inc == 0)
                                {
                                    $body .= '<tr>';
                                }
                                $images = scandir('/mugs/' . $state_name . '/' . $county_name . '/' . date('Y') . '/' . 'week_' . $current_week);
                                $count = 0;
                                foreach ($images as $image_key => $image)
                                {
                                    if(substr($image, 4, 2) == date('d'))
                                    {
                                        $count++;
                                    }
                                }
                                $body .= '<td height="100" width="110">State: ' . $state_name . '<br />County: ' . $county_name . '<br />Image Quantity: ' . $count . '<br /></td>';

                                If($body_inc == 8)
                                {
                                    $body .= '</tr>';
                                    $body_inc = -1;
                                }
                            }
                            $counter++;
                            $body_inc++;
                            if($counter == 9)
                            {
                                $counter = 0;
                            }
                        }
                    }
                }
            }
        }
        while ($counter < 9) {
            $body .= '<td height="100" width="110"><p align="center" style="font-size:18pt;">No Data</p></td>';
            $counter++;
        }
        $body .= '</table></body></html>';
        $base64contents = rtrim(chunk_split(base64_encode($body)));
        $headers   = "From: root@mugshotjunkie.com\r\n";
        //$headers .= "Reply-To: noahoc@arr.ae\r\n";
        //$headers .= "CC: noahoc@arr.ae\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
        $headers .= "Content-Transfer-Encoding: base64";
        //mail('noahoc@arr.ae', 'Nightly Scrape Report', $base64contents, $headers);
        mail('winterpk@arr.ae', 'Nightly Scrape Report', $base64contents, $headers);
        //mail('dev@arr.ae', 'Nightly Scrape Report', $base64contents, $headers);
        //mail('noahoc@arr.ae', 'Nightly Scrape Report', $base64contents, $headers);
        mail('design@mugshotjunkie.com', 'Nightly Scrape Report', $base64contents, $headers);
        mail('rc@mugshotjunkie.com', 'Nightly Scrape Report', $base64contents, $headers);
        mail('ja@mugshotjunkie.com', 'Nightly Scrape Report', $base64contents, $headers);
        //mail('bryangalli@arr.ae', 'Nightly Scrape Report', $base64contents, $headers);
        //mail('noahoc@arr.ae', 'Nightly Scrape Report', $base64contents, $headers);
        //mail('dev@arr.ae', 'Nightly Scrape Report', $body,'FROM: root@mugshotjunkie.com' . "\r\n" . 'X-Mailer: PHP/' . phpversion());
        echo "-=SUCCESS=-\r\n";
        exit;
    }

    public function action_db_populate($state, $county, $security = 'insecure')
    {
        exit;
        if ($security == 'secure')
        {
            $scrape = new Model_Scrape;
            $listpath = '/mugs/'.$state.'/'.$county.'/lists/'.$county.'.csv';
            $list     = $scrape->import_csv($listpath, true);
            $count = 1;
            foreach($list as $row)
            {
                $charge = Mango::factory('charge')->create();
                $charge->charge = $row['FULL'];
                $charge->abbr   = $row['ABBR'];
                $charge->order  = $count;
                $charge->county = $county;
                $charge->new    = (int)0;
                $charge->update();
                $count++;
            }
            echo $count . ' charges populated';
        }
        else { echo 'not secure!'; }
    }



    public function action_csv_merge($abbr = '/mugs/utah/saltlake/lists/saltlake_abbr.csv', $order = '/mugs/utah/saltlake/lists/saltlake_order.csv')
    {
        exit;
        $scrape = new Model_Scrape;
        if ($abbr)
        {
            $abbr_arr = $scrape->import_csv($abbr, true);
            $order_arr = file($order);
            $merged = array();
            foreach($order_arr as $okey => $ocharge)
            {
                foreach($abbr_arr as $akey => $acharge)
                {
                    // acharge is an array!
                    if (trim($acharge['FULL']) == trim($ocharge))
                    {
                        $merged[$okey]['FULL'] = $acharge['FULL'];
                        $merged[$okey]['ABBR'] = $acharge['ABBR'];
                        break;
                    }
                }
            }


            foreach ($merged as $key => $value)
            {
                echo $value['ABBR'] . '<br/>';
            }
            echo "<hr><hr>";
            foreach ($merged as $key => $value)
            {
                echo $value['FULL'] . '<br/>';
            }
            exit;
        }
    }


    public function action_mapnewtodb($scrape)
    {
        exit;
        $report = Mango::factory('report', array('scrape' => $scrape))->load(false)->as_array(false);
        $new_charges = array();
        foreach($report as $key => $value)
        {
            foreach($value['new_charges'] as $charge)
            {
                $new_charges[] = $charge;
            }
        }
        $new_charges = array_unique($new_charges);
        $new_charges = array_merge($new_charges);
        foreach($new_charges as $charge)
        {
            $charges = Mango::factory('charge', array('charge' => $charge, 'scrape' => $scrape, 'new' => 1))->create();
        }
        echo 'success!';
        exit;
    }

    public function action_maptodb($csv = '/mugs/florida/marionfl/lists/marionfl.csv')
    {

        $scrape = new Model_Scrape;
        $arr = $scrape->import_csv($csv, true);

        foreach ($arr as $key => $value)
        {
            $charges = Mango::factory('charge', array('charge' => $value['FULL'], 'abbr' => $value['ABBR'], 'county' => 'marionfl', 'order' => $key + 1, 'new' => 0))->create();
        }
        exit;
        //$charges = Mango::factory('charge', array('full' => $fn, 'abbr' => $abbr, 'scrape' => $scrape, 'order' => $order, 'new' => $new))->create();
    }


    public function action_mongo($secure = 'not')
    {
        exit;
        if ($secure == 'highly')
        {
            $csv_path = '/mugs/michigan/muskegon/';
            file();
            $charges = Mango::factory('charge', array('full' => $fn, 'abbr' => $abbr, 'scrape' => $scrape, 'order' => $order, 'new' => $new))->create();

        }
        else
        {
            echo 'non secure access';
        }

    }

    public function action_viewRaces($scrape)
    {
        exit;
        $test = Mango::factory('offender', array('scrape' => $scrape))->load(false, null, null, array('race', 'firstname', 'lastname'))->as_array(false);
        $races = array();
        foreach($test as $key => $value)
        {
                @$races[] = trim($value['race']);
        }
        $races = array_unique($races);
        $races = array_merge($races);
        //$this->print_r2($test);
        $this->print_r2($races);
        exit;
    }
    public function action_watch()
    {
        exit;
        $unique_offenders = Mango::factory('offender', array('scrape' => 'muskegon'))->load(false)->as_array(false);
        //$this->print_r2($unique_offenders);
        $dups = array();
        foreach($unique_offenders as $key1 => $value1)
        {
            foreach($unique_offenders as $key2 => $value2)
            {
                if ($value1['booking_id'] == $value2['booking_id'])
                {
                    $dups[] = $value1['booking_id'];
                }
            }
        }
        $dups2 = array_unique($dups);
        $dups2 = array_merge($dups);
        echo count($dups2) . "<br/>";
        echo count($dups) . "<br/>";
        exit;
    }
    public function action_test()
    {
        echo 'test';
        exit;
        $scrape = new Model_Scrape;
        echo $scrape->find_week(time());
        exit;
        $scrape->image_dupe_check('kentucky', 'lexington');
        exit;
        $bid_dupe_check = $scrape->bid_dupe_check('saltlake');
        echo $bid_dupe_check;

        exit;
        $host = 'api.decaptcher.com';
        $port = '24537';
        $login = 'winterpk';
        $password = 'Lollip0p!';

        $ccp = new ccproto();
        $ccp->init();
        $ccp->login($host, $port, $login, $password);

        $system_load = 0;
        if( $ccp->system_load( $system_load ) != ccERR_OK ) {
            print( "system_load() FAILED\n" );
            return;
        }
        print( "System load=".$system_load." perc\n" );
        echo '<br/>';
        $balance = 0;
        if( $ccp->balance( $balance ) != ccERR_OK ) {
            print( "balance() FAILED\n" );
            return;
        }
        print( "Balance=".$balance."\n" );


        $major_id   = 0;
        $minor_id   = 0;
        $pict = file_get_contents(DOCROOT.'public/images/captcha/captcha_image.jpg');
        $text = '';
        print( "sending a picture..." );
        $pict_to    = ptoDEFAULT;
        $pict_type  = ptUNSPECIFIED;
        $res = $ccp->picture2( $pict, $pict_to, $pict_type, $text, $major_id, $minor_id );
        echo '<br/>';
        switch( $res ) {
            // most common return codes
            case ccERR_OK:
                print( "got text for id=".$major_id."/".$minor_id.", type=".$pict_type.", to=".$pict_to.", text='".$text."'" );
                break;
            case ccERR_BALANCE:
                print( "not enough funds to process a picture, balance is depleted" );
                break;
            case ccERR_TIMEOUT:
                print( "picture has been timed out on server (payment not taken)" );
                break;
            case ccERR_OVERLOAD:
                print( "temporarily server-side error" );
                print( " server's overloaded, wait a little before sending a new picture" );
                break;

            // local errors
            case ccERR_STATUS:
                print( "local error." );
                print( " either ccproto_init() or ccproto_login() has not been successfully called prior to ccproto_picture()" );
                print( " need ccproto_init() and ccproto_login() to be called" );
                break;

            // network errors
            case ccERR_NET_ERROR:
                print( "network troubles, better to call ccproto_login() again" );
                break;

            // server-side errors
            case ccERR_TEXT_SIZE:
                print( "size of the text returned is too big" );
                break;
            case ccERR_GENERAL:
                print( "server-side error, better to call ccproto_login() again" );
                break;
            case ccERR_UNKNOWN:
                print( " unknown error, better to call ccproto_login() again" );
                break;

            default:
                // any other known errors?
                break;
        }
        echo '<br/>';
        //echo $captcha;
        $balance = 0;
        if( $ccp->balance( $balance ) != ccERR_OK ) {
            print( "balance() FAILED\n" );
            return;
        }
        print( "Balance=".$balance."\n" );
        $ccp->close();

        echo '<img src="/public/images/captcha/captcha_image.jpg" />';
        exit;

        # results page
        //https://scsojms.summitoh.net/MatrixJMS/PublicSearch/OffenderSearchResults.aspx

        $scrape = new Model_Scrape;
        $cookies = '/tmp/summit_cookies.txt';

        if (!$_POST)
        {
            $url =  'https://scsojms.summitoh.net/MatrixJMS/PublicSearch/MasterFileNameSearch.aspx';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_TIMEOUT, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
            $result = curl_exec($ch);
            curl_close($ch);
            $check = preg_match('/CaptchaImage.axd\?guid=[^"]*\"/Uis', $result, $match);
            $captcha_link = 'https://scsojms.summitoh.net/MatrixJMS/PublicSearch/' . preg_replace('/\"/', '', $match[0]);
            $scrape->imageSource    = $captcha_link;
            $scrape->save_to        = DOCROOT.'public/images/captcha/captcha_image';
            $scrape->set_extension  = true;
            $scrape->download('curl');
            # get __VIEWSTATE
            //<input type="hidden" name="__VIEWSTATE" id="__VIEWSTATE" value="/wEPDwUKMTYwNTM2Nzk4OQ9kFgICAQ9kFgYCAw9kFgICAQ9kFgICAw8PFgoeBFRleHQFJFN1bW1pdCBDb3VudHkgSmFpbCBNYW5hZ2VtZW50IFN5c3RlbR4JRm9yZUNvbG9yCqQBHglGb250X1NpemUoKiJTeXN0ZW0uV2ViLlVJLldlYkNvbnRyb2xzLkZvbnRVbml0BVNtYWxsHgpGb250X05hbWVzFQEFQXJpYWweBF8hU0IChAxkZAIFD2QWBAIBD2QWBAIBD2QWCAIFDw9kFgIeCW9ua2V5ZG93bgVwaWYgKChldmVudC53aGljaCA9PSAxMykgfHwgKGV2ZW50LmtleUNvZGUgPT0gMTMpKSB7ZG9jdW1lbnQuZ2V0RWxlbWVudEJ5SWQoJ2J0blN1Ym1pdCcpLmNsaWNrKCk7IHJldHVybiBmYWxzZTt9IGQCCQ8PZBYCHwUFcGlmICgoZXZlbnQud2hpY2ggPT0gMTMpIHx8IChldmVudC5rZXlDb2RlID09IDEzKSkge2RvY3VtZW50LmdldEVsZW1lbnRCeUlkKCdidG5TdWJtaXQnKS5jbGljaygpOyByZXR1cm4gZmFsc2U7fSBkAg0PD2QWAh8FBXBpZiAoKGV2ZW50LndoaWNoID09IDEzKSB8fCAoZXZlbnQua2V5Q29kZSA9PSAxMykpIHtkb2N1bWVudC5nZXRFbGVtZW50QnlJZCgnYnRuU3VibWl0JykuY2xpY2soKTsgcmV0dXJuIGZhbHNlO30gZAIRDxAPFgIeC18hRGF0YUJvdW5kZ2QQFQMADUdsZW53b29kIEphaWwSU3VtbWl0IENvdW50eSBKYWlsFQMCLTEBNAEzFCsDA2dnZ2RkAgMPZBYCAgMPDxYCHgdWaXNpYmxlaGRkAgUPZBYGAgEPD2QWAh4Hb25DbGljawUpd2luZG93Lm9wZW4oJ2h0dHA6Ly93d3cuZW1lcmFsZHN5cy5jb20nKTtkAgMPDxYGHwAFPEluZm9ybWF0aW9uIGluIHRoaXMgU3lzdGVtIGlzIHRoZSBQcm9wZXJ0eSBvZiBTdW1taXQgQ291bnR5Lh8BCiMfBAIEZGQCBQ8PFgIeB1Rvb2xUaXAFHFdlZG5lc2RheSwgRmVicnVhcnkgMDIsIDIwMTFkZAIGDxYSHghTdHJhdGVneQspjwFTdHJlbmd0aENvbnRyb2xzLlNjcm9sbGluZy5TY3JvbGxTdHJhdGVneSwgU3RyZW5ndGhDb250cm9scy5TY3JvbGxpbmcsIFZlcnNpb249MS4yLjEzMzEuMTU5NzYsIEN1bHR1cmU9bmV1dHJhbCwgUHVibGljS2V5VG9rZW49YjQwNzBmNTJiZDA5NDgyNQEeCU1haW50YWluWGgeCE1haW50YWluZx4NTGFzdFBvc3RiYWNrWQUBMB4NTGFzdFBvc3RiYWNrWAUBMB4LVXNlT25zY3JvbGxnHglNYWludGFpbllnHgxUYXJnZXRPYmplY3RlHglVc2VPbmxvYWRnZGQND51Wu+8/4HzOy2H8iI5kPxCOEQ==" />
            preg_match_all('/id\=\"\_\_VIEWSTATE\"\svalue\=\"([^"]*)"/', $result,  $matches,  PREG_PATTERN_ORDER);
            @$vs = $matches[1][0];

            # get __EVENTVALIDATION
            //<input type="hidden" name="__EVENTVALIDATION" id="__EVENTVALIDATION" value="/wEWCALUu+aNBAK7zu7HAgK7zqYkArvOspsPAvqxlIIPAvWx2IEPAvSx2IEPAsKL2t4DO1kp3Q2GcOzI/TmQVfQHevcD8gk=" />
            preg_match_all('/id\=\"\_\_EVENTVALIDATION\"\svalue\=\"([^"]*)"/', $result, $matches, PREG_PATTERN_ORDER);
            @$ev = $matches[1][0];

            echo '<img src="/public/images/captcha/captcha_image.jpg" />';
            echo '
                <form id="cap-crack" method="post" action="test">
                    <input type="text" name="code" />
                    <input type="submit" value="Code" />
                    <input type="hidden" value='.$vs.' name="viewstate" />
                    <input type="hidden" value='.$ev.' name="eventvalidation" />
                </form>
            ';
        }
        else
        {
            //header('Content-Type: image/jpeg');
            //$image = Image::factory($scrape->save_to.'.jpg');
            //echo $image;
            $vs = $_POST['viewstate'];
            $ev = $_POST['eventvalidation'];
            $code = $_POST['code'];
            $fn = '_';
            $ln = '_';
            $url    = 'https://scsojms.summitoh.net/MatrixJMS/PublicSearch/MasterFileNameSearch.aspx';
            $fields = '__VIEWSTATE='.urlencode($vs).'&__EVENTVALIDATION='.urlencode($ev).'&txtLName='.$ln.'&txtFName='.$fn.'&txtMName=&ddlFacilityList=-1&txtVerify='.$code.'&btnSubmit=Submit&SmartScroller1_ScrollX=0&SmartScroller1_ScrollY=0&limit=100000';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_TIMEOUT, 0);
            //curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            //curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
            curl_setopt($ch, CURLOPT_REFERER, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            $result = curl_exec($ch);
            echo $result;
            exit;
        }
        exit;
        //header('Content-Type: image/jpeg');
        $image = Image::factory($scrape->save_to.'.jpg');
        //echo $image;

        # get __VIEWSTATE
        //<input type="hidden" name="__VIEWSTATE" id="__VIEWSTATE" value="/wEPDwUKMTYwNTM2Nzk4OQ9kFgICAQ9kFgYCAw9kFgICAQ9kFgICAw8PFgoeBFRleHQFJFN1bW1pdCBDb3VudHkgSmFpbCBNYW5hZ2VtZW50IFN5c3RlbR4JRm9yZUNvbG9yCqQBHglGb250X1NpemUoKiJTeXN0ZW0uV2ViLlVJLldlYkNvbnRyb2xzLkZvbnRVbml0BVNtYWxsHgpGb250X05hbWVzFQEFQXJpYWweBF8hU0IChAxkZAIFD2QWBAIBD2QWBAIBD2QWCAIFDw9kFgIeCW9ua2V5ZG93bgVwaWYgKChldmVudC53aGljaCA9PSAxMykgfHwgKGV2ZW50LmtleUNvZGUgPT0gMTMpKSB7ZG9jdW1lbnQuZ2V0RWxlbWVudEJ5SWQoJ2J0blN1Ym1pdCcpLmNsaWNrKCk7IHJldHVybiBmYWxzZTt9IGQCCQ8PZBYCHwUFcGlmICgoZXZlbnQud2hpY2ggPT0gMTMpIHx8IChldmVudC5rZXlDb2RlID09IDEzKSkge2RvY3VtZW50LmdldEVsZW1lbnRCeUlkKCdidG5TdWJtaXQnKS5jbGljaygpOyByZXR1cm4gZmFsc2U7fSBkAg0PD2QWAh8FBXBpZiAoKGV2ZW50LndoaWNoID09IDEzKSB8fCAoZXZlbnQua2V5Q29kZSA9PSAxMykpIHtkb2N1bWVudC5nZXRFbGVtZW50QnlJZCgnYnRuU3VibWl0JykuY2xpY2soKTsgcmV0dXJuIGZhbHNlO30gZAIRDxAPFgIeC18hRGF0YUJvdW5kZ2QQFQMADUdsZW53b29kIEphaWwSU3VtbWl0IENvdW50eSBKYWlsFQMCLTEBNAEzFCsDA2dnZ2RkAgMPZBYCAgMPDxYCHgdWaXNpYmxlaGRkAgUPZBYGAgEPD2QWAh4Hb25DbGljawUpd2luZG93Lm9wZW4oJ2h0dHA6Ly93d3cuZW1lcmFsZHN5cy5jb20nKTtkAgMPDxYGHwAFPEluZm9ybWF0aW9uIGluIHRoaXMgU3lzdGVtIGlzIHRoZSBQcm9wZXJ0eSBvZiBTdW1taXQgQ291bnR5Lh8BCiMfBAIEZGQCBQ8PFgIeB1Rvb2xUaXAFHFdlZG5lc2RheSwgRmVicnVhcnkgMDIsIDIwMTFkZAIGDxYSHghTdHJhdGVneQspjwFTdHJlbmd0aENvbnRyb2xzLlNjcm9sbGluZy5TY3JvbGxTdHJhdGVneSwgU3RyZW5ndGhDb250cm9scy5TY3JvbGxpbmcsIFZlcnNpb249MS4yLjEzMzEuMTU5NzYsIEN1bHR1cmU9bmV1dHJhbCwgUHVibGljS2V5VG9rZW49YjQwNzBmNTJiZDA5NDgyNQEeCU1haW50YWluWGgeCE1haW50YWluZx4NTGFzdFBvc3RiYWNrWQUBMB4NTGFzdFBvc3RiYWNrWAUBMB4LVXNlT25zY3JvbGxnHglNYWludGFpbllnHgxUYXJnZXRPYmplY3RlHglVc2VPbmxvYWRnZGQND51Wu+8/4HzOy2H8iI5kPxCOEQ==" />
        //preg_match_all('/id\=\"\_\_VIEWSTATE\"\svalue\=\"([^"]*)"/', $result,  $matches,  PREG_PATTERN_ORDER);
        @$vs = $matches[1][0];

        # get __EVENTVALIDATION
        //<input type="hidden" name="__EVENTVALIDATION" id="__EVENTVALIDATION" value="/wEWCALUu+aNBAK7zu7HAgK7zqYkArvOspsPAvqxlIIPAvWx2IEPAvSx2IEPAsKL2t4DO1kp3Q2GcOzI/TmQVfQHevcD8gk=" />
        preg_match_all('/id\=\"\_\_EVENTVALIDATION\"\svalue\=\"([^"]*)"/', $result, $matches, PREG_PATTERN_ORDER);
        @$ev = $matches[1][0];

        $fn = 'a';
        $ln = 'a';
        $code = '1NR8NP4';
        $url    = 'https://scsojms.summitoh.net/MatrixJMS/PublicSearch/MasterFileNameSearch.aspx';
        $fields = '__VIEWSTATE='.urlencode($vs).'&__EVENTVALIDATION='.urlencode($ev).'&txtLName='.$ln.'&txtFName='.$fn.'&txtMName=&ddlFacilityList=-1&txtVerify='.$code.'&btnSubmit=Submit&SmartScroller1_ScrollX=0&SmartScroller1_ScrollY=0';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        //curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies);
        curl_setopt($ch, CURLOPT_REFERER, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        $result = curl_exec($ch);

        echo $result;
        exit;
        //header('Content-type: image/jpeg');
        //https://scsojms.summitoh.net/MatrixJMS/PublicSearch/CaptchaImage.axd?guid=2603cb55-126f-436b-961c-10c32f6121f6
        //CaptchaImage.axd?guid=b3246e29-ea63-4174-baa8-b6c559e819a9"
        $check = preg_match('/CaptchaImage.axd\?guid=[^"]*\"/Uis', $result, $match);
        if ($check)
        {
            $captcha_link = 'https://scsojms.summitoh.net/MatrixJMS/PublicSearch/' . preg_replace('/\"/', '', $match[0]);

            $scrape->imageSource    = $captcha_link;
            $scrape->save_to        = '/tmp/captcha_image';
            $scrape->set_extension  = true;
            $scrape->download('curl');
            header('Content-Type: image/jpeg');
            $image = Image::factory('/tmp/captcha_image.jpg')->render('jpg');

            echo $image;

            exit;
        } else { echo 'failed'; }
        exit;
        $this->print_r2($match);
        //echo $result;
        exit;
        function var_test($count)
        {
            echo '<hr/>pass #:' . $count . '<br/>';
            echo 'before set: ' . @$variable . '<br/>';
            $variable = $count;
            echo 'after set: ' . $variable . '<br/>';
            return $variable;
        }

        $test_array = array(1, 2, 3);
        foreach ($test_array as $number)
        {
            var_test($number);
        }

        exit;


        $offenders = Mango::factory( 'offender', array('state' => $state, 'scrape' => $county) )
            ->load(0, array( 'booking_date' => -1 ), $skip, array(), array('status' => array('$ne' => 'denied')))->as_array(false);


        $this->print_r2($offenders);
        exit;


        $scrape = new Model_Scrape();

        $report = Mango::factory('report')->create();
        $this->print_r2($report);
        exit;
        $this->print_r2($charges);
        exit;
        $testmodel->county = 'testcounty';
        $testmodel->scrapes = 4;
        $testmodel->week   = 25;
        $testmodel->year   = 2011;

        $testmodel->total  = 1000;
        $testmodel->new    = 250;
        $testmodel->failed = 250;
        $testmodel->charge_mismatch = 250;
        $testmodel->already_exists = 250;
        $testmodel->times = array(123, 555, 555, 556, 544, 543, 544, 543, 555, 626);

        echo Kohana::debug($testmodel->changed( $testmodel->loaded()));
        $testmodel->update();
        $testmodel->times[] = (int)235;
        echo Kohana::debug($testmodel->changed( $testmodel->loaded()));
        $testmodel->update();

        foreach($testmodel->times as $key => $value)
        {
            echo '<br/>key: ' . $key . '</br>';
            echo 'value: ' . $value . '</br>';
        }
        exit;
        $im = new Imagick();

        /*** the image file ***/
        $imgpath = '/mugs/oregon/marion/2011/week_13/(03-23-2011)_AIKMAN_JODY_marion_18280832.jpg';

        /*** read the image into the object ***/
        $im->readImage( $imgpath );

        /**** convert to png ***/
        $im->setImageFormat( "png" );

        /*** write image to disk ***/
        $im->writeImage( '/mugs/oregon/marion/2011/week_13/(03-23-2011)_AIKMAN_JODY_marion_18280832.png' );
        //$this->print_r2($im);
        exit;
        $imgpath = '/mugs/oregon/marion/2011/week_13/(03-23-2011)_AIKMAN_JODY_marion_18280832.jpg';
        $magicwand = NewMagickWand();
        $this->print_r2($magicwand);
        exit;
        $image_test = imagecreatefromjpeg($imgpath);
        exit;
        $url = 'http://www.whatsmyip.org';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_exec($ch);
        curl_close($ch);
        exit;

        $test1 = '/mugs/kentucky/lexington/test1.txt';
        $test2 = '/mugs/kentucky/lexington/test2.txt';
        $orig_hex_string = file_get_contents($test1);
        $orig_hex_string = unpack('H*', $orig_hex_string);
        $hex_array = str_split($orig_hex_string[1], 2);
        //$this->print_r2($hex_array);
        foreach($hex_array as $key => $hex)
        {
            if ($hex == 20)
            {
                $hex_array[$key] = 00;
            }
        }
        $new_hex_string = '';
        foreach($hex_array as $hex)
        {
            $new_hex_string .= $hex;
        }
        echo $orig_hex_string[1];
        echo '<br/>';
        echo $new_hex_string;

        //$this->print_r2($hex_array);
        exit;
        function writehex($hexcode)
        {

            foreach(str_split($hexcode,2) as $hex)
            {
                $tmp .= pack('C*', hexdec($hex));
            }
                fwrite($handle, $tmp);

        }
        //$string = bin2hex($string);
        print_r($string);



        exit;
        $scrape = new Model_Scrape;
        $list = '/mugs/ohio/kenton/lists/kenton.csv';
        $scrape->charges_prioritizer2($list);

        $unique_offenders = Mango::factory('offender', array('scrape' => 'muskegon'))->load(false)->as_array(false);
        //$this->print_r2($unique_offenders);
        $dups = array();
        foreach($unique_offenders as $key1 => $value1)
        {
            foreach($unique_offenders as $key2 => $value2)
            {
                if ($value1['booking_id'] == $value2['booking_id'])
                {
                    $dups[] = $value1['booking_id'];
                }
            }
        }
        $dups2 = array_unique($dups);
        $dups2 = array_merge($dups);
        echo count($dups2) . "<br/>";
        echo count($dups) . "<br/>";

        exit;

        # now check for the county and create it if it doesnt exist
        $county = Mango::factory('county', array('name' => 'testing'))->load();
        if (!$county->loaded())
        {
            $county = Mango::factory('county', array('name' => 'testing'))->create();
        }
        $county->booking_ids[] = 'and a new one ';
        $county->update();
        $county = Mango::factory('county', array('name' => 'testing'))->load()->as_array(true);
        $this->print_r2($county);
        exit;

        # now check for the county and create it if it doesnt exist
        $bid_array = array('123545', 'utah_1541958', 'test_2135987105', '*****!@$(*NMM<', 'checking!@#154', '\][\][pfsd[i,mn1246]]');
        $county = Mango::factory('county', array('name' => 'testing', 'state' => 'test_state'))->create();
        $county = Mango::factory('county', array('name' => 'testing'))->load();
        $county->booking_ids = $bid_array;
        $county->update();
        $county = Mango::factory('county', array('name' => 'testing'))->load()->as_array(true);
        $this->print_r2($county);
        exit;
        $array = array('charge1', 'charge2', 'charge3', 'chg', 'chg', 'chg', '12-49870239158lskjdfh');
        $offender = Mango::factory('offender',
        array(
            'scrape'        => 'davis',
            'state'         => 'utah',
            'firstname'     => 'test',
            'lastname'      => 'test',
            'booking_id'    => '0001',
            'booking_date'  => 100234956,
            'scrape_time'   => time(),
            'image'         => '$imgpath',
            'charges'       => $array,
        ))->create();
        exit;
        $offender = Mango::factory('offender', array('booking_id' => $booking_id))->load();
        //$this->print_r2($offender);
        $offender->rating = 5;
        $offender->update();
        $offender = Mango::factory('offender', array('booking_id' => $booking_id))->load()->as_array();
        $this->print_r2($offender);
        exit;
        $booking_ids = Mango::factory('county', array('name' => 'bourbon'))->load(false)->as_array(false);
        $this->print_r2($booking_ids);
        exit;
        $offenders = Mango::factory('offender')->load(false, null, null, array('height'))->as_array(false);
        $this->print_r2($offenders);
        exit;

        exit;
        $scrape = new Model_Scrape;
        //echo strtotime('03/22/2011');
        //echo date('m-d-Y', 1300773600);
        echo $scrape->find_week(1300773600);
        exit;

        exit;
        $week_report = Mango::factory('report', array(
                'scrape'        => 'weber',
                'year'          => date('Y'),
                'week'          => $scrape->find_week(time()),
                'total'         => 0,
                'successful'    => 0,

            ))->create();
        $week_report = Mango::factory('report', array(
                'scrape'        => 'kenton',
                'year'          => date('Y'),
                'week'          => $scrape->find_week(time()),
                'total'         => 0,
                'successful'    => 0,

            ))->create();
        exit;

        $report = Mango::factory('report', array('scrape' => 'weber'))->load()->as_array();
        print_r($report);
        exit;
        foreach ($report['new_charges'] as $key => $value)
        {
            echo $value . "\n";
        }
        exit;
        $scrape = new Model_Scrape;
        $keyword_list = '/mugs/utah/utah/lists/utah_kw_order.csv';
        $charges = array('molestation it', 'assault',  'MUrdER', 'Eludeing the police');
        $mcharges = $scrape->keyword_prioritizer($keyword_list, $charges);
        $this->print_r2($mcharges);
        exit;
        $value = 'this is a string with a /\\\\//\/\/\/\/\/\ in it';
        $value = preg_replace('/\//', '', $value);
        $value = preg_replace('/\\\/', '', $value);
        echo $value;
        //echo phpinfo();
        //exit;
        //$filename = '/mugs/michigan/muskegon/2011/week 10/(03-07-2011) ANIBLE, DOUGLAS.png';
        exit;
    }

    /**
    * csvReplace - this is for the weekly sheet that justin sends.  Might build this into the site.. not sure
    *
    * @return void
    * @author
    */
    public function action_csvReplace()
    {
        $scrape = new Model_Scrape;
        $list = DOCROOT.'public/includes/test/data.csv';
        $csv = $scrape->import_csv($list, true);
        foreach ($csv as $key => $value)
        {
            $value =  preg_replace('/\\\[^\\\]*\\\/', '', $value['FILE_PATH']). '<br/>';
            $value =  preg_replace('/\.001/', '.jpg', $value);
            echo $value;
            //echo $value['FILE_PATH'] . '<br/>';

        }
        exit;
        echo '<pre>';
        print_r($csv);
        echo '</pre>';
        exit;
    }


    function action_serialize_charges()
    {

        #weekfinder
        //http://www.frihost.com/forums/vt-49454.html
        //But you have another option: get the date for the start of the year, get the date you want, subtract, divide by a week in seconds (7 * 24 * 60 * 60), adjust result.



        $file = file(DOCROOT.'public/includes/abbreviated.txt');

        foreach( $file as $key => $value)
        {
            $abbreviations[] = strtoupper(trim($value));
        }
        //echo '<pre>';
        //print_r($abbreviations);
        //echo '</pre>';
        $file = file(DOCROOT.'public/includes/!abbreviated.txt');
        //$file = array_unique($file);

        foreach( $file as $key => $value)
        {
            $full[] = strtoupper(trim($value));
        }
        $count = 0;
        foreach ($full as $key => $value)
        {
            if ($count == 162) { break; }
            $abbs[$value] = $abbreviations[$count];

            $count ++;
        }

        //$full = array_unique($full);

        $abbs = serialize($abbs);

        $fh = fopen(DOCROOT.'public/includes/abbreviations.txt', 'x');
        fwrite($fh, $abbs);

        exit;
        $file = file_get_contents(DOCROOT.'public/includes/charges_order.txt');
        echo '<pre>';
        //print_r(unserialize($file));
        echo '</pre>';
        exit;
        $fh = fopen(DOCROOT.'/public/includes/Charges_abbr_mod2.csv', 'w+');

        $file = fgetcsv($fh);

        echo $file;
        exit;
        $replace = preg_replace('/\[[0-9]*\]\s\=\>/', ' ', $file);
        //$neat = trim($replace);
        $arr = explode("\n", $replace);
        foreach ($arr as $key => $value)
        {
            if ($key != 163)
            {
            $charges[] = trim($value);
            }
        }
        echo '<pre>';
        print_r($charges);
        echo '</pre>';
        $handle = fopen(DOCROOT.'public/includes/charges_order.txt', 'x');
        fwrite($handle, serialize($charges));
        fclose($handle);
        exit;

        $month  = date('m');
        $day    = date('d');
        $year   = date('Y');
        $Jan1   = gmmktime(0, 0, 0, 1, 1, $year);
        $mydate = gmmktime(0, 0, 0, $month, $day, $year);
        $delta  = (int)(($mydate - $Jan1) / (7 * 24 * 60 * 60)) + 1;
        echo $delta ; // and somehow this gives me the week number
        exit;

        //$csv = file($file);

        echo '<pre>';
        echo $check;
        echo '</pre>';
        fputcsv($file);

        $handle = fopen(DOCROOT.'public/includes/charges_test.php', "x");
        fwrite($handle, $check);
        fclose($handle);

        exit;
    }


    public function action_show_charges($scrape)
    {
        exit;
        $scrape = new Model_Scrape();
        $week_report = Mango::factory('report', array(
            'scrape'        => $scrape,
            'year'          => date('Y'),
            'week'          => $scrape->find_week(time())
        ))->load();
        $this->print_r2($week_report->new_charges);
    }


    /**
    * new_county used to prep the system for a new county
    *
    * @notes this is for development purposes only
    * @return void
    * @author Winter King
    */
    public function action_new_county($name = null, $state = null)
    {
        exit;
        if ($name && $state)
        {
            $scrape = new Model_Scrape();
            $week_report = Mango::factory('report', array(
                'scrape'        => $name,
                'year'          => date('Y'),
                'week'          => $scrape->find_week(time()),
                'total'         => 0,
                'successful'    => 0,

            ))->create();
            $county = Mango::factory('county')->create();
            $county->name  = $name;
            $county->state = $state;
            $county->update();
        }
    }


    /**
     * alachua - scrapes the alachua county directory for images and offender
     *
     * @url     www.circuit8.org
     * @author  Winter King
     *
     */
    public function action_alachua()
    {
        $scrape = 'alachua';
        $alachua = new Model_Alachua;
        $alachua->scrape();
        ## check for any duplicate booking_ids and delete them
        $alachua->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $alachua->profile_dupe_check($scrape);
    }

    /**
     * Anoka - scrapes the anoka county directory for images and offender
     *
     * @url     http://www.anokacountysheriff.us/v4_sheriff/inmate-locator.aspx#
     * @author  Winter King
     *
     */
    public function action_anoka()
    {
        $scrape = 'anoka';
        $model = new Model_Anoka;
        $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }

    /**
     * Arrest -
     *
     * @url http://arre.st
     * @author Jiran Dowlati
     *
     */
    public function action_arrest()
    {
        $scrape = 'arrest';
        $arrest = new Model_Arrest;
        $arrest->scrape();
        ## check for any duplicate booking_ids and delete them
        $arrest->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $arrest->profile_dupe_check($scrape);
    }

    public function action_benton()
    {
        $scrape = 'benton';
        $model = new Model_Benton;
        $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }

    public function action_bernco()
    {
        $scrape = 'bernco';
        $model = new Model_Bernco;
        $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }

    /**
    * bourbon - Scrape for bourbon county website.
    *
    * @url    - http://www.bourboncountydetention.com/bourbon_inmatelist.html
    * @notes
    * @return
    * @author   Winter King
    */
    public function action_bourbon()
    {
        $bourbon = new Model_Bourbon;
        $past = time() - (20 * 24 * 60 * 60);
        $sdate = date('m/d/Y', $past);
        $edate = date('m/d/Y');
        $report = $bourbon->scrape($sdate, $edate);
        $scrape = 'bourbon';
        $bourbon->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $bourbon->profile_dupe_check($scrape);
    }

    public function action_brevard()
    {
        $scrape = 'brevard';
        $model = new Model_Brevard;
        $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }

    public function action_buncombe()
    {
        $scrape = 'buncombe';
        $buncombe = new Model_Buncombe;
        $buncombe->scrape();
        ## check for any duplicate booking_ids and delete them
        $buncombe->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $buncombe->profile_dupe_check($scrape);
    }

    public function action_chatham()
    {
        $scrape = 'chatham';
        $buncombe = new Model_Chatham;
        $buncombe->scrape();
        ## check for any duplicate booking_ids and delete them
        $buncombe->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $buncombe->profile_dupe_check($scrape);
    }

    /**
     * citrus - scrapes the citrus county florida inmate list
     *
     * @url     http://www.sheriffcitrus.org/public/ArRptQuery.aspx
     * @author  Winter King
     *
     */
    public function action_citrus()
    {
        $citrus = new Model_Citrus;
        $citrus->scrape();
        $scrape = 'citrus';
        ## check for any duplicate booking_ids and delete them
        $citrus->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $citrus->profile_dupe_check($scrape);
    }


    /**
    * clackamus - Scrape for clackamus county website.
    *
    * @url http://web3.co.clackamas.or.us/sheriff/roster/default.asp
    * @notes
    * @return
    * @author   Winter King
    */
    public function action_clackamus()
    {
        $scrape = 'clackamus';
        $model = new Model_Clackamus;
        $report = $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }

    /**
    * Scrape for clay county website.
    *
    * @author   Winter King
    */
    public function action_clay()
    {
        $scrape = 'clay';
        $model = new Model_Clay;
        $report = $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }

    /**
    * Columbia
    *
    * @url http://web3.co.clackamas.or.us/sheriff/roster/default.asp
    * @notes
    * @return
    * @author   Winter King
    */
    public function action_columbia()
    {
        $scrape = 'columbia';
        $model = new Model_Columbia;
        $report = $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }

    /**
    * covington - Scrape for covington
    *
    * @url      http://www.jailtracker.com/kncdc/kenton_inmatelist.html
    * @notes
    * @return
    * @author   Winter King
    *
    * @params
    * @notes    ok this one is tricky.  First of all it uses a handler which gets passed a hash via URI (jailtracker.com/JTClientWeb/(S(5q031uqy2bumtxu0oxrqcem1))/JailTracker/).
    *           This hash is used to flag an error if the session expires.  So istead of being cookie session based it uses its own system.  I need to refresh the hash every so often
    *
    *
    * @return $result['Image']
    * @return $result['Profile'] = First Name, Last Name, Middle Name, Suffix, Alias, Current Age, Booking Date, Date Released, Height, Weight, Birth Date, Hair Color, Eye Color, Race, Sex, Arresting Officer, Arresting Agency, Arrest Date, Zip
    * @return $result['Cases']   = CaseID, CaseNo, Status, BondType, BondAmount, FineAmount, Sentence, CourtTime
    * @return $result['Charges'] = ChargeId, ArrestCode, ChargeDescription
    */
    function action_covington()
    {
        $url = 'https://jailtracker.com/jtclientweb/jailtracker/index/KENTON_COUNTY_KY';

        //
        // $ch = curl_init();
        // curl_setopt($ch, CURLOPT_URL, $url);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        // curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6");
        // curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        // curl_setopt($ch, CURLOPT_HEADER, false);
        // curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        // $test = curl_exec($ch);
        // $info = curl_getinfo($ch);
        // curl_close($ch);
        // echo '<pre>';
        // print_r($test);
        // echo '</pre>';
        //
        // //https://jailtracker.com/jtclientweb/(S(4dropnmpui0fir452izuiq2u))/jailtracker/index/KENTON_COUNTY_KY
        // //JTClientweb/(S([a-zA-Z0-9]))/JailTracker
        //
        // if ( preg_match("/(?<=\(S\().*(?=\)\))/", $test, $matches) )
        // {
        //  echo "Match was found <br />";
        //  echo '<pre>';
        //  print_r($matches);
        //  echo '</pre>';
        // }
        // else
        //  {
        //      echo 'no match';
        //  }
        // exit;
        // preg_match_all('/jtclientweb\/\(S\([a-A]\)\)/', $test, $test2);
        // //print_r($test2);
        // //exit;
        // print_r($test);
        //
        //
        // need to figure out how this hash is created ..
        // new 5q031uqy2bumtxu0oxrqcem1
        // old w000n345tsptiw451lduy02e
        $hash = 'w5xn3f2ig4crl33nodxyulzd';
        // https://jailtracker.com/JTClientWeb/(S(5q031uqy2bumtxu0oxrqcem1))/JailTracker/GetInmates?_dc=1295417974806&start=0&limit=28&sort=LastName&dir=ASC
        //$result = 'Result not set';
        //$ArrestNumbers = array();
        $info   = 'Info not set';
        $this->template->view = View::factory('scrape');
        $this->template->title   = 'Busted Paper | Covington Scrape';
        $this->template->h1      = 'Busted Paper | Covington Scrape';

        $handler = 'https://jailtracker.com/JTClientWeb/JailTracker/';
        # GetInmates returns a json object of all inmates
        $GetInmates = 'https://jailtracker.com/JTClientWeb/(S('.$hash.'))/JailTracker/GetInmates?_dc=1295373691017&dir=ASC&limit=10000&sort=LastName&start=0';
        $index = file_get_contents($GetInmates);
        $index = json_decode($index, true); // returns an assoc array of the offender index


        $results['Index'] = $index;
        foreach ( $results['Index']['data'] as $row  )
        {
            //echo $row['ArrestNo'] . '<br/>';
            $ArrestNumbers[] = $row['ArrestNo']; //this gives us an array with every Arrest Number
        }

        $count = 0;
        foreach ( $ArrestNumbers as $ArrestNo )
        {
            # GetImage returns an image of the offender
            $GetImage = 'https://jailtracker.com/JTClientWeb/(S('.$hash.'))/JailTracker/GetImage/';
            $fields     = array('arrestNo' => $ArrestNo);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $GetImage);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6");
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            $image = curl_exec($ch);
            $image = json_decode($image, TRUE); // this returns an assoc array of offenders image
            curl_close($ch);
            $image = '<img src="https://jailtracker.com/JTClientWeb/(S('.$hash.'))/JailTracker/StreamInmateImage/'.$image['Image'] .' " />';
            $result[]['Image'] = $image;

            # GetInmate returns a json object of offenders profile data
            $GetInmate = 'https://jailtracker.com/JTClientWeb/(S('.$hash.'))/JailTracker/GetInmate?_dc=1295374868697&arrestNo='.$ArrestNo;
            $profile = file_get_contents($GetInmate);
            $profile = json_decode($profile, TRUE);
            $result[]['Profile'] = $profile;

            # GetCases returns json data of offenders cases
            $GetCases = 'https://jailtracker.com/JTClientWeb/(S('.$hash.'))/JailTracker/GetCases?_dc=1295374868697&arrestNo='.$ArrestNo;
            $cases  = file_get_contents($GetCases);
            $cases = json_decode($cases, TRUE);
            $result[]['Cases'] = $cases;

            # GetCharges POST
            $GetCharges = 'https://jailtracker.com/JTClientWeb/(S('.$hash.'))/JailTracker/GetCharges';
            $fields     = array('arrestNo' => $ArrestNo);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $GetCharges);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6");
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            $charges = curl_exec($ch);
            $charges = json_decode($charges, TRUE); // this returns a json object of all charges
            curl_close($ch);
            $result[]['Charges'] = $charges;

            // https://jailtracker.com/JTClientWeb/(S(mxovk1i2mq5lwx55mdbi0l45))/JailTracker/GetInmates?_dc=1295374326313&start=0&limit=28&sort=LastName&dir=ASC
            // handler: https://jailtracker.com/JTClientWeb/(S(w000n345tsptiw451lduy02e))/JailTracker/
            // when click the handler is passed 2 GETs and 2 POSTS
            // GetInmate?_dc=1295372808454&arrestNo=787774
            // GetCases?_dc=1295372808613&arrestNo=787774
            // POSTS:
            // https://jailtracker.com/JTClientWeb/(S(w000n345tsptiw451lduy02e))/JailTracker/GetCharges
            // Params: arrestNo => 787774
            // https://jailtracker.com/JTClientWeb/(S(w000n345tsptiw451lduy02e))/JailTracker/GetImage/
            // Params: arrestNo => 787774
            if ( $count == 5 ) { break; }
            $count++;
        }


        $this->template->view->result   = $result;
        $this->template->view->info     = $info;
    }

    /**
    * cuyahoga - Scrape for clackamus county website.
    *
    * @url
    * @notes
    * @return
    * @author   Winter King
    */
    public function action_cuyahoga()
    {
        $scrape = 'cuyahoga';
        $model = new Model_Cuyahoga;
        $report = $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }

    /**
     * dakota   - scrape for dakota county MN website
     *
     * @url     - http://services.co.dakota.mn.us/InmateSearch/
     *
     * @author  Winter King
     */
    public function action_dakota()
    {
        $scrape = 'dakota';
        $model = new Model_Dakota;
        $report = $model->scrape();
        // Do this one twice in a row
        $report = $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }

    /**
    * dallas   - scrape for dallas website
    *
    * @url     - http://www.dallascounty.org/jaillookup/search.jsp
    * @notes   B(Black), A(Asian),  H(Hispanic), N(Non-Hispanic), W(White)
    * @return
    * @author  Winter King
    */
    public function action_dallas()
    {
        $scrape = 'dallas';
        $model = new Model_Dallas;
        $report = $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }


    /**
    * davis
    *
    * @url
    * @author   Winter King
    *
    * @params
    * @return
    */
    function action_davis()
    {
        $county = 'davis';
        $scrape = 'davis';
        $davis = new Model_Davis;
        $davis->scrape();
        ## check for any duplicate booking_ids and delete them
        $davis->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $davis->profile_dupe_check($scrape);
    }


    /**
    * denton
    *
    * @url
    * @author   Winter King
    *
    * @params
    * @return
    */
    function action_denton()
    {
        $scrape = new Model_Scrape;
        $denton = new Model_Denton;
        exit;
        # set the reporting vars
        $report['Scrape'] = 'denton';
        $report['Total'] = 0;
        $report['Successful'] = 0;
        $report['New_Charges'] = array();
        $start = $scrape->getTime();
        $report = $davis->scrape();
        echo "SUCCESS!!\n";
        echo $report['Scrape'] . "\n";
        echo 'TOTAL: ' . $report['Total'] . "\n";
        echo 'SUCCESSFUL: ' . $report['Successful'] . "\n";
        echo "NEW CHARGES: \n";
        echo "\n";
        $message = 'Denton List' . "\n";
        foreach ($report['New_Charges'] as $charge)
        {
            echo $charge . "\n";
        }

        $end = $scrape->getTime();
        echo "Time taken = ".number_format(($end - $start),2)." secs\n";
        # do database update for weekly report
        $week_report = Mango::factory('report', array(
                    'scrape' => 'denton',
                    'year'   => date('Y'),
                    'week'   => $scrape->find_week(time())
                ))->load();
        if (!$week_report->loaded())
        {
            $week_report = Mango::factory('report', array(
                'scrape'        => 'denton',
                'year'          => date('Y'),
                'week'          => $scrape->find_week(time()),
                'total'         => 0,
                'successful'    => 0,

            ))->create();
        }
        $week_report->total += $report['Total'];
        $week_report->successful += $report['Successful'];
        $week_report->update();
    }

    public function action_essex()
    {
        $scrape = 'essex';
        $essex = new Model_Essex;
        $essex->scrape();
        $essex->bid_dupe_check($scrape);
        $essex->profile_dupe_check($scrape);
    }

    /**
    * Franklin
    *
    * @url
    * @notes
    * @return
    * @author   Winter King
    */
    public function action_franklin()
    {
        $scrape = 'franklin';
        $model = new Model_Franklin;
        $model->scrape();
        ## check for any duplicate booking_ids and delete them
        //$model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        //$model->profile_dupe_check($scrape);
    }

    /**
    * hamilton - Scrape for hamilton county website.
    *
    * @url      http://www.hcso.org/publicservices/inmateinfo/inmateinfomain.aspx
    * @notes
    * @return
    * @author   Winter King
    */
    public function action_hamilton()
    {
        $scrape = 'hamilton';
        $model = new Model_Hamilton;
        $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }

    /**
     * indianriver
     *
     * @url      http://www.ircsheriff.org/community/online-tools/inmate-records-search
     * @author   Justin Bowers
     *
     * @params
     * @return
     */
    function action_indianriver()
    {
        $scrape = 'indianriver';
        $model = new Model_Indianriver;
        for ($i=0; $i < 5; $i++) {
            $date = date('m/d/Y', strtotime('-'.$i.' days'));
            $model->scrape($date);
        }
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }

    /**
     * jacksonville
     *
     * @url      http://inmatesearch.jaxsheriff.org/%28a1zi2255inwwm255r0cnl245%29/Default.aspx
     * @author  Justin Bowers
     *
     * @params
     * @return
     */
    function action_jacksonville()
    {
        $scrape = 'jacksonville';
        $model = new Model_Jacksonville;
        $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }

    /**
     * johnson - scrapes the johnson county directory for images and offender
     *
     * @url     www.circuit8.org
     * @author  Winter King
     *
     */
    public function action_johnson()
    {
        $scrape = 'johnson';
        $model = new Model_Johnson;
        $check = $model->scrape();
        if ( ! $check)
        {
            echo 'failed';
            exit;
        }
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }

    /**
     * justicedata
     *
     * @author  Winter King
     *
     */
    public function action_justicedata()
    {
        $scrape = 'justicedata';
        $model = new Model_Justicedata;
        $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }

    /**
     * Kalamazoo -
     *
     *
     */
    public function action_kalamazoo()
    {
        $kalamazoo = new Model_Kalamazoo;
        $scrape = 'kalamazoo';
        $kalamazoo->scrape();
        ## check for any duplicate booking_ids and delete them
        $kalamazoo->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $kalamazoo->profile_dupe_check($scrape);
    }

    /**
     * kbi - scrapes the kansas bureau of investigation directory for images and offender data
     *
     * @author  Winter King
     *
     */
    public function action_kbi()
    {
        $kbi = new Model_kbi;
        $scrape = 'kbi';
        $kbi->scrape();
        ## check for any duplicate booking_ids and delete them
        $kbi->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $kbi->profile_dupe_check($scrape);
    }


    /**
    * kenton - Scrape for kenton county website.
    *
    * @url
    * @notes
    * @return
    * @author   Winter King
    */
    public function action_kenton()
    {
        $scrape = 'kenton';
        $model = new Model_Kenton;
        $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
        echo "Success!\r\n";

    }


    public function action_kent()
    {
        $kent = new Model_Michigan_Kent();
        $scrape = 'kent';
        $ncharges = array();
        # lets loop through the past 5 days
        $date = date('m/d/Y');
        $time = strtotime($date);
        $days_array = array();
        for ( $i = 0; $i < 5; $i++)
        {
            if ($i == 0) { $days_array[] = $time; }
            else
            {
                $time = $time - 86400;
                $days_array[] = $time;
            }
        }

        foreach ($days_array as $day)
        {
            $kent->scrape($day);
        }
        ## check for any duplicate booking_ids and delete them
        $kent->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $kent->profile_dupe_check($scrape);
    }


    /**
     * lake - scrapes the Lake county florida inmate list
     *
     * @url
     * @author  Winter King
     *
     */
    public function action_lake()
    {
        $scrape = 'lake';
        $lake = new Model_Lake;
        $lake->scrape();
        ## check for any duplicate booking_ids and delete them
        $lake->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $lake->profile_dupe_check($scrape);
    }

    /**
     * lakeca - scrapes the Lake county california inmate list
     *
     * @url http://www.lakesheriff.com/Recent_Arrests.htm
     * @author  Winter King
     *
     */
    public function action_lakeca()
    {
        $scrape = 'lakeca';
        $lakeca = new Model_Lakeca;
        $lakeca->scrape();
        ## check for any duplicate booking_ids and delete them
        $lakeca->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $lakeca->profile_dupe_check($scrape);
    }

    /**
    * lexington - Scrape for lexington county website.
    *
    * @url
    * @notes
    * @return
    * @author   Winter King
    */
    public function action_lexington()
    {
        $scrape = 'lexington';
        $lexington = new Model_Lexington;
        $lexington->scrape();
        ## check for any duplicate booking_ids and delete them
        $lexington->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $lexington->profile_dupe_check($scrape);
    }

    /**
    * licking - Licking county import action
    *
    * @url
    * @notes
    * @return
    * @author   Winter King
    */
    public function action_licking()
    {
        $scrape = 'licking';
        $model = new Model_Licking;
        $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }

    /**
     * macomb
     *
     * @url      http://itasw0aepv01.macombcountymi.gov/jil/faces/InmateSearch.jsp
     * @author   Justin Bowers
     *
     * @params
     * @return
     */
    function action_macomb()
    {
        $scrape = 'macomb';
        $model = new Model_Macomb;
        $model->scrape();
        //$model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }

    /**
    * mansfield
    *
    * @return void
    * @author
    */
    function action_mansfield()
    {
        $scrape = 'mansfield';
        $mansfield  = new Model_Mansfield;
        $mansfield->scrape();
        ## check for any duplicate booking_ids and delete them
        $lexington->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $lexington->profile_dupe_check($scrape);
    }


    /**
    * marion
    *
    * @return void
    * @author
    */
    function action_marion()
    {
        $scrape = 'marion';
        $marion  = new Model_Marion;
        $marion->scrape();
        ## check for any duplicate booking_ids and delete them
        $marion->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $marion->profile_dupe_check($scrape);
    }


    /**
    * marionfl
    *
    * @return void
    * @author
    */
    function action_marionfl()
    {
        $scrape = 'marionfl';
        $marionfl  = new Model_Marionfl;
        $marionfl->scrape();
        ## check for any duplicate booking_ids and delete them
        $marionfl->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $marionfl->profile_dupe_check($scrape);
    }


    /**
    * Multnomah
    *
    * @return void
    * @author
    */
    function action_multnomah()
    {
        $scrape = 'multnomah';
        $multnomah  = new Model_Multnomah;
        $multnomah->scrape();
        ## check for any duplicate booking_ids and delete them
        $multnomah->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $multnomah->profile_dupe_check($scrape);
    }


    /**
    * muskegon - Scrape for muskegon county website.
    *
    * @url      http://www.mcd911.net/p2c/jailinmates.aspx
    * @notes    Cron will need to be as frequent as possible because records are deleted as soon as bail is posted
    * @return   A json object
    * @author   Winter King
    */
    public function action_muskegon()
    {
        //set_time_limit(0);
        $scrape = new Model_Scrape();
        $muskegon = new Model_Muskegon();
        $start = $scrape->getTime();
        $report['Scrape'] = 'muskegon';
        $report['Total'] = 0;
        $report['Successful'] = 0;
        $report['Exists'] = 0;
        $report['Bad_Images'] = 0;
        $report['Other'] = 0;
        $report['Failed_New_Charges'] = 0;
        $report['New_Charges'] = array();
        $info = $muskegon->scrape();
        //$this->print_r2($info);
        if (isset($info['error_codes']))
        {
            foreach($info['error_codes'] as $error_code)
            {
                if ($error_code == 101)
                {
                    $report['Other'] = ($report['Other'] + 1);
                }
                if ($error_code == 102)
                {
                    $report['Bad_Images'] = ($report['Bad_Images'] + 1);
                }
                if ($error_code == 103)
                {
                    $report['Exists'] = ($report['Exists'] + 1);
                }
            }
        }
        if (isset($info['Total']))
        {
            $report['Total'] = ($report['Total'] + $info['Total']);
        }
        if (isset($info['Successful']))
        {
            $report['Successful'] = ($report['Successful'] + $info['Successful']);
        }
        if (isset($info['New_Charges']))
        {
            $report['New_Charges'] = array_merge($report['New_Charges'], $info['New_Charges']);
        }
        if (isset($report['New_Charges']))
        {
            # strip out new charge dups
            $report['New_Charges'] = array_unique($report['New_Charges']);
        }
        //echo "Total Scraped = " . $count . "\n";
        echo "SUCCESS!!\n";
        echo $report['Scrape'] . "\n";
        echo 'TOTAL: ' . $report['Total'] . "\n";
        echo 'SUCCESSFUL: ' . $report['Successful'] . "\n";
        echo "NEW CHARGES: \n";
        if (is_array($report['New_Charges']))
        {
            foreach ($report['New_Charges'] as $charge)
            {
                echo $charge . "\n";
            }
        }
        $end = $scrape->getTime();
        echo "Time taken = ".number_format(($end - $start),2)." secs\n";
        # now load the report
        $week_report = Mango::factory('report', array(
            'scrape' => 'muskegon',
            'year'   => date('Y'),
            'week'   => $scrape->find_week(time())
        ))->load();
        if (!$week_report->loaded())
        {
            $week_report = Mango::factory('report', array(
                'scrape'        => 'muskegon',
                'year'          => date('Y'),
                'week'          => $scrape->find_week(time()),
                'total'         => 0,
                'successful'    => 0,
            ))->create();
        }
        $week_report->total = ($week_report->total + $report['Total']);
        $week_report->successful = ($week_report->successful + $report['Successful']);
        $week_report->update();
        # now create a new scrape report for this scrape
        $scrape_report = Mango::factory('rscrape')->create();
        $scrape_report->county      = $report['Scrape'];
        $scrape_report->total       = $report['Total'];
        $scrape_report->successful  = $report['Successful'];
        $scrape_report->new_charges = $report['Failed_New_Charges'];
        $scrape_report->bad_images  = $report['Bad_Images'];
        $scrape_report->exists      = $report['Exists'];
        $scrape_report->other       = $report['Other'];
        $scrape_report->start_time  = $start;
        $scrape_report->stop_time   = $end;
        $scrape_report->time_taken  = $scrape_report->stop_time - $scrape_report->start_time;
        $scrape_report->failed      = $scrape_report->new_charges + $scrape_report->bad_images + $scrape_report->exists + $scrape_report->other;
        $scrape_report->update();
        ## check for any duplicate booking_ids and delete them
        $scrape->bid_dupe_check($report['Scrape']);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $scrape->profile_dupe_check($report['Scrape']);
    }

    public function action_missouri()
    {
        $scrape = 'missouri';
        $missouri = new Model_Missouri;
        $missouri->scrape();
        ## check for any duplicate booking_ids and delete them
        $missouri->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $missouri->profile_dupe_check($scrape);
    }

    public function action_tarrant()
    {
        $scrape = 'tarrant';
        $tarrant = new Model_Tarrant;
        $tarrant->scrape();
        $tarrant->bid_dupe_check($scrape);
        $tarrant->profile_dupe_check($scrape);
    }

    public function action_txdps()
    {
        $scrape = 'txdps';
        $txdps = new Model_Txdps;
        $txdps->scrape();
        $txdps->bid_dupe_check($scrape);
        $txdps->profile_dupe_check($scrape);
    }

    public function action_putnam()
    {
        $scrape = 'putnam';
        $putnam = new Model_Putnam;
        $putnam->scrape();
        $putnam->bid_dupe_check($scrape);
        $putnam->profile_dupe_check($scrape);
    }


    public function action_njdoc()
    {
        $scrape = 'njdoc';
        $njdoc = new Model_Njdoc;
        $njdoc->scrape();
        ## check for any duplicate booking_ids and delete them
        $njdoc->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $njdoc->profile_dupe_check($scrape);
    }


    public function action_orange()
    {
        $scrape = 'orange';
        $orange = new Model_Orange;
        $orange->scrape();
        ## check for any duplicate booking_ids and delete them
        $orange->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $orange->profile_dupe_check($scrape);
    }

    public function action_platte()
    {
        $scrape = 'platte';
        $model = new Model_Platte;
        $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }

    /**
    * saltlake
    *
    * @url      http://iml.slsheriff.org/IML
    * @author   Winter King
    *
    * @params
    * @return
    */
    function action_saltlake()
    {
        $scrape = 'saltlake';
        $az = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
        $saltlake = new Model_Saltlake();
        foreach ($az as $letter)
        {
            $saltlake->scrape($letter);
        }
        ## check for any duplicate booking_ids and delete them
        $saltlake->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $saltlake->profile_dupe_check($scrape);
    }

    /**
    * saltlake
    *
    * @url      http://iml.slsheriff.org/IML
    * @author   Winter King
    *
    * @params
    * @return
    */
    function action_saltlakefix()
    {
        $model = new Model_Saltlakefix;
        $model->fix();
    }

    /**
     *Scott County scrape controller method
     *
     * @url     http://www.co.scott.mn.us/PUBLICSAFETYJUSTICE/COUNTYJAIL/Pages/JailRoster.aspx
     * @author  Jiran Dowlati
     */
    function action_scott()
    {
        $scrape = 'scott';
        $model = new Model_Scott;
        $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }


    public function action_seminole()
    {
        $scrape = 'seminole';
        $seminole = new Model_Seminole;
        $seminole->scrape();
        ## check for any duplicate booking_ids and delete them
        $seminole->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $seminole->profile_dupe_check($scrape);
    }

    /**
     *Stearns County scrape controller method
     *
     * @url     http://www.clickcomplete.com/jail/default.cfm?PID=1.3&inq_key=10023&start=101&jump=true&order=5&type=Desc&action=Park_List&searchdate=&searchenddate=&Crit1_Operator=&Crit1_Value=&Crit2_Operator=&Crit2_Value=
     * @author  Jiran Dowlati
     */
    function action_stearns()
    {
        $scrape = 'stearns';
        $model = new Model_Stearns;
        $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
        echo "\r\nDONE";
    }

    /**
     * stjohns
     *
     * @url      http://www.sjso.org/?page_id=191
     * @author   Justin Bowers
     *
     * @params
     * @return
     */
    function action_stjohns()
    {
        $scrape = 'stjohns';
        $model = new Model_Stjohns;
        $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }

    /**
     * stlucie
     *
     * @url      http://www.stluciesheriff.com
     * @author   Justin Bowers
     *
     * @params
     * @return
     */
    function action_stlucie()
    {
        $scrape = 'stlucie';
        $model = new Model_Stlucie;
        $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }

    /**
     * Sherburne Controller action
     *
     */
    function action_sherburne()
    {
        $scrape = 'sherburne';
        $model = new Model_Sherburne;
        $model->scrape();
        $model->bid_dupe_check($scrape);
        $model->profile_dupe_check($scrape);
        echo "\r\nDONE";
    }

    /**
    * summit - activates summit model
    *
    * @return void
    * @author
    */
    public function action_summit($loops = 1)
    {
        //set_time_limit(0);
        $report['Scrape'] = 'summit';
        $report['Total'] = 0;
        $report['Successful'] = 0;
        $report['Exists'] = 0;
        $report['Bad_Images'] = 0;
        $report['Other'] = 0;
        $report['Failed_New_Charges'] = 0;
        $report['New_Charges'] = array();
        $scrape = new Model_Scrape();
        $summit = new Model_Summit();
        //$summit = $summit->scrape('LLOYD', 'LOUIS');
        //echo '<pre>';
        //print_r($summit);
        //echo '</pre>';
        //exit;
        $start = $scrape->getTime();

        //$info = $summit->scrape()
        //$kz = array('k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z');
        $az = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
        $za = array('Z','Y','X','W','V','U','T','S','R','Q','P','O','N','M','L','K','J','I','H','G','F','E','D','C','B','A');
        $count = 0;
        foreach ($az as $key => $value)
        {
            foreach ($az as $key2 => $value2)
            {
                $info = $summit->scrape($value, $value2);
                if (isset($info['Total']))
                {
                    $report['Total'] = ($report['Total'] + $info['Total']);
                }
                if (isset($info['Successful']))
                {
                    $report['Successful'] = ($report['Successful'] + $info['Successful']);
                }
                if (isset($info['New_Charges']))
                {
                    $report['New_Charges'] = array_merge($report['New_Charges'], $info['New_Charges']);
                }
                $count++;
                //if ($count >= $loops) { break; }
            }
            //if ($count >= $loops) { break; }
        }
        if (isset($report['New_Charges']))
        {
            # strip out new charge dups
            $report['New_Charges'] = array_unique($report['New_Charges']);
        }
        //echo "Total Scraped = " . $count . "\n";
        echo "SUCCESS!!\n";
        echo $report['Scrape'] . "\n";
        echo 'TOTAL: ' . $report['Total'] . "\n";
        echo 'SUCCESSFUL: ' . $report['Successful'] . "\n";
        echo "NEW CHARGES: \n";
        foreach ($report['New_Charges'] as $charge)
        {
            echo $charge . "\n";
        }
        $end = $scrape->getTime();
        echo "Time taken = ".number_format(($end - $start),2)." secs\n";
        # now load the report

        $week_report = Mango::factory('report', array(
            'scrape' => 'summit',
            'year'   => date('Y'),
            'week'   => $scrape->find_week(time())
        ))->load();
        if (!$week_report->loaded())
        {
            $week_report = Mango::factory('report', array(
                'scrape'        => 'summit',
                'year'          => date('Y'),
                'week'          => $scrape->find_week(time()),
                'total'         => 0,
                'successful'    => 0,
            ))->create();
        }
        $week_report->total = ($week_report->total + $report['Total']);
        $week_report->successful = ($week_report->successful + $report['Successful']);
        $week_report->update();
        # now create a new scrape report for this scrape
        $scrape_report = Mango::factory('rscrape')->create();
        $scrape_report->county      = $info['Scrape'];
        $scrape_report->total       = $report['Total'];
        $scrape_report->successful  = $report['Successful'];
        $scrape_report->new_charges = $info['Failed_New_Charges'];
        $scrape_report->bad_images  = $info['Bad_Images'];
        $scrape_report->exists      = $info['Exists'];
        $scrape_report->other       = $info['Other'];
        $scrape_report->start_time  = $start;
        $scrape_report->stop_time   = $end;
        $scrape_report->time_taken  = $scrape_report->stop_time - $scrape_report->start_time;
        $scrape_report->failed      = $scrape_report->new_charges + $scrape_report->bad_images + $scrape_report->exists + $scrape_report->other;
        $scrape_report->update();
        ## check for any duplicate booking_ids and delete them
        $scrape->bid_dupe_check($report['Scrape']);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $scrape->profile_dupe_check($report['Scrape']);
    }







    /**
    * utah
    *
    * @url      http://www.utahcountyonline.org/Dept/Sheriff/Corrections/InmateSearch.asp
    * @author   Winter King
    *
    * @params
    * @return
    */
    function action_utah()
    {
        $scrape = new Model_Scrape();
        $utah = new Model_Utah();

        # set the reporting vars
        $report['Scrape'] = '';
        $report['Total'] = 0;
        $report['Successful'] = 0;
        $report['Exists'] = 0;
        $report['Bad_Images'] = 0;
        $report['Other'] = 0;
        $report['Failed_New_Charges'] = 0;

        $report['New_Charges'] = array();
        $scrape = new Model_Scrape();
        $start = $scrape->getTime();
        $ncharges = array();
        # lets loop through the past 5 days
        $date = date('m/d/Y');
        $time = strtotime($date);
        $days_array = array();
        for ( $i = 0; $i < 7; $i++)
        {
            if ($i == 0) { $days_array[] = $time; }
            else
            {
                $time = $time - 86400;
                $days_array[] = $time;
            }
        }

        foreach ($days_array as $day)
        {
            $info = $utah->scrape($day);
            $report['Scrape'] = $info['Scrape'];
            $report['Total'] += $info['Total'];
            $report['Successful'] += $info['Successful'];
            if (@is_array($info['New_charges']))
            {
                foreach ($info['New_Charges'] as $value)
                {
                    $report['New_Charges'][] = $value;
                }
            }

        }
            ## echo display for console for manual scrape
        echo "SUCCESS!!\n";
        echo $report['Scrape'] . "\n";
        echo 'TOTAL: ' . $report['Total'] . "\n";
        echo 'SUCCESSFUL: ' . $report['Successful'] . "\n";
        echo "NEW CHARGES: \n";
        foreach ($report['New_Charges'] as $charge)
        {
            echo $charge . "\n";
        }

        $end = $scrape->getTime();
        echo "Time taken = ".number_format(($end - $start),2)." secs\n";
        # do database update for weekly report
        $week_report = Mango::factory('report', array(
                    'scrape' => 'utah',
                    'year'   => date('Y'),
                    'week'   => $scrape->find_week(time())
                ))->load();
        if (!$week_report->loaded())
        {
            $week_report = Mango::factory('report', array(
                'scrape'        => 'utah',
                'year'          => date('Y'),
                'week'          => $scrape->find_week(time()),
                'total'         => 0,
                'successful'    => 0,

            ))->create();
        }

        $week_report->total += $report['Total'];
        $week_report->successful += $report['Successful'];
        $week_report->update();
        # now create a new scrape report for this scrape
        $scrape_report = Mango::factory('rscrape')->create();
        //$scrape_report->county      = $info['Scrape'];
        $scrape_report->total       = $info['Total'];
        $scrape_report->successful  = $info['Successful'];
        $scrape_report->new_charges = $info['Failed_New_Charges'];
        $scrape_report->bad_images  = $info['Bad_Images'];
        $scrape_report->exists      = $info['Exists'];
        $scrape_report->other       = $info['Other'];
        $scrape_report->start_time  = $start;
        $scrape_report->stop_time   = $end;
        $scrape_report->time_taken  = $scrape_report->stop_time - $scrape_report->start_time;
        $scrape_report->failed      = $scrape_report->new_charges + $scrape_report->bad_images + $scrape_report->exists + $scrape_report->other;
        $scrape_report->update();
        ## check for any duplicate booking_ids and delete them
        $scrape->bid_dupe_check($report['Scrape']);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $scrape->profile_dupe_check($report['Scrape']);
    }

    /**
     * Volusia scrape controller method
     *
     * @url     http://www.volusiamug.vcgov.org/search.cfm
     * @author  Winter King
     */
    function action_volusia()
    {
        $scrape = 'volusia';
        $model = new Model_Volusia;
        $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
        echo "\r\nDONE";
    }

    /**
     * weber
     *
     * @url      http://www.standard.net/jail-mugs
     * @author   Winter King
     *
     * @params
     * @return
     */
    function action_weber()
    {
        $scrape = 'weber';
        $model = new Model_Weber;
        $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }

    /**
    * wyandotee
    *
    * @author   Winter King
    * @param
    * @return
    */
    function action_wyandotee()
    {
        $scrape = 'wyandotee';
        $model = new Model_Wyandotee;
        $model->scrape();
        ## check for any duplicate booking_ids and delete them
        $model->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $model->profile_dupe_check($scrape);
    }

    /**
    * yamhill
    *
    * @author   Bryan
    * @param
    * @return
    */
    function action_yamhill()
    {
        $scrape = 'yamhill';
        $wyandotee = new Model_Yamhill;
        $wyandotee->scrape();
        ## check for any duplicate booking_ids and delete them
        $wyandotee->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $wyandotee->profile_dupe_check($scrape);
    }


    function action_passaic()
    {
        $scrape = 'passaic';
        $passaic = new Model_Passaic;
        $passaic->scrape();
        ## check for any duplicate booking_ids and delete them
        $passaic->bid_dupe_check($scrape);
        ## check for duplicate firstname, lastname, and booking_id and flag them
        $passaic->profile_dupe_check($scrape);
    }


    public function action_delete_offender($id)
    {
        exit;
        $offender = Mango::factory('offender', array('_id' => $id))->load();
        $offender->delete();

        exit;
        $offender->update();
        $this->print_r2($offender);

    }
}