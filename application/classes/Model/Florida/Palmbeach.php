<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Palm Beach, Florida Scrape
 *
 * This scrape is using JS and a popup to try and validate that you're a human being. To get around this,
 * we are going to hit the ajax urls directly
 *
 *  http://www.pbso.org/index.cfm?fa=blotter
 *  http://services.palmbeachpost.com/editorial/blotter/static/images/pbso_2013023001.jpg
 *
 * @package   Bandit
 * @author    Clay McIlrath
 * @see       http://www.pbso.org/index.cfm?fa=blotter
 */
class Model_Florida_Palmbeach extends Model_Bandit
{
    protected $name     = 'palmbeach';   // name of scrape goes here
    protected $county   = 'palm beach';    // if it is a single county, put it here, otherwise remove this property
    protected $state    = 'florida';  //  state goes here
    private $cookie     = '/tmp/palmbeach_cookie.txt';

    protected $urls = [
        'main'      => 'http://www.pbso.org/index.cfm?fa=blotter',
        'image'     => 'http://services.palmbeachpost.com/editorial/blotter/static/images/pbso_2013023001.jpg'
    ];

    protected $errors = FALSE;
    protected $_offender = NULL;


    /**
     * Construct
     *
     * For now sets a timelimit, deletes cookie if they exist and creates mscrape model in DB.
     */
    public function __construct()
    {
        if ( file_exists($this->cookie) )
            unlink($this->cookie);   // Unlink only works for files, use rmdir for Directories.

        // Creates a mscrape model in Mongo DB.
        $this->scrape_model($this->name, $this->state, $this->county);
    }

    /**
     * Scrape
     *
     * main scrape function makes the curl calls and sends details to the extraction function
     *
     *   POST /index.cfm HTTP/1.1
     *   Host: www.pbso.org
     *   Content-Type: application/x-www-form-urlencoded; charset=UTF-8
     *   X-Requested-With: XMLHttpRequest
     *   Referer: http://www.pbso.org/index.cfm?fa=blotter
     *   Content-Length: 336
     *   Cookie:
     *      __utma=228346332.1783698338.1370239911.1370239911.1370239911.1;
     *      __utmb=228346332.2.10.1370239911;
     *      __utmc=228346332;
     *      __utmz=228346332.1370239911.1.1.utmcsr=(direct)|utmccn=(direct)|utmcmd=(none)
     *   xsearch=5
     *      &fa=blottersearchwpAJAX
     *      &requesttimeout=500
     *      &fromrec=1
     *      &xisi=a0395836-112c-4cac-a3f2-8981857c78c7
     *      &cp=51113CE7D52E1DEEBE1148673EF68696
     *      &pjkh=08AA7311-F62D-0009-8E69A255EC89C6D8&book=
     *      &file=
     *      &thumb=
     *      &start_date=06%2F02%2F2013
     *      &end_date=06%2F03%2F2013
     *      &last_name=
     *      &first_name=
     *      &street_name=
     *      &city_name=
     *      &statute=
     *      &arrestingAgency=
     *      &captcha_id=-1
     *
     * @return true - on completed scrape
     * @return false - on failed scrape
     */
    public function scrape($pager = 1)
    {
        echo "<h1>round: $pager</h1>";

        $post = $this->get_post_data($pager);

        $list = $this->load_url([
            'target'    => $this->urls['main'],
            'referrer'  => $this->urls['main'],
            'method'    => 'POST',
            'cookie'    => $this->cookie,
            'data'      => $post
        ]);

        if ( $list['error'] )
            throw new Bandit_Exception('could not load the offender list', 'severe');

        $list = $this->clean_html($list['result']);

        echo $list; exit;
        $dom = new DOMDocument();

        if ( ! $dom->loadHTML($list) )
            throw new Bandit_Exception('could not parse html as DOMDocument', 'severe');

        $xpath = new DOMXPath($dom);

        preg_match('/(\d).*(?=matches)/', $xpath->query("//table[2]/tr[1]")->item(0)->nodeValue, $count);
        $count = intval(str_replace(',', '', $count[0]));


        if ( $pager < $count )
        {
            foreach ($xpath->query("//table[2]/tr[2]//table[@class='contentTxt c22' or @class='contentTxt c23']") as $r)
            {
                $pager ++;

                if ( ! $r->hasChildNodes() )
                    continue;

                try
                {
                    $row = $xpath->query("tr/td", $r);
                    $o_name = array_filter(explode('&nbsp;', trim(htmlentities($row->item(0)->nodeValue))));
                    $o_book_id = array_filter(explode('&nbsp;', trim(htmlentities($row->item(13)->nodeValue))));
                    $o_book_time = array_filter(explode('&nbsp;', trim(htmlentities($row->item(7)->nodeValue))));
                    $o_dob = array_filter(explode('&nbsp;', trim(htmlentities($row->item(2)->nodeValue))));
                    $o_race = array_filter(explode('&nbsp;', trim(htmlentities($row->item(1)->nodeValue))));
                    $o_charges = array_filter(explode('&nbsp;', trim(htmlentities($row->item(15)->nodeValue))));
                    $o_address = $row->item(3) ? trim(array_filter(explode('&nbsp;', trim(htmlentities($row->item(3)->nodeValue))))[3]) : NULL;
                }
                catch ( ErrorException $e )
                {
                    $this->errors[] = [
                        'key' => "failed on or near record: ".$pager-1,
                        'issue' => $e->xdebug_message,
                        'offender' => @$row->item(0)
                    ];
                }

                if ( ! $o_book_id OR ! isset($o_name[3]) )
                    continue;

                $this->_offender = [
                    'booking_id'    => $this->name.'_'.trim($o_book_id[2]),
                    'scrape_time'   => time(),
                    'updated'       => time(),
                    'firstname'     => $o_name[3],
                    'lastname'      => $o_name[2],
                    'middlename'    => @$o_name[4],
                    'booking_date'  => strtotime($o_book_time[2]),
                    'address'       => $o_address,
                    'charges'       => [trim($o_charges[4])],   // 'OBSTRUCTING JUSTICE / FAILURE TO APPEAR'
                    'gender'        => strtoupper(array_filter(explode('&nbsp;', trim(htmlentities($row->item(4)->nodeValue))))[1]),
                    'race'          => strtoupper($o_race[1]),
                    'dob'           => strtotime($o_dob[2]),
                    'state'         => $this->state,
                    'county'        => $this->county,
                    'scrape'        => $this->name
                ];

                var_dump($this->_offender);
            }

            // $this->scrape($pager);
        }
        else
        {
            if ( isset($errors) )
            {
                $data['scrape'] = ucfirst($this->county).' county, '.ucfirst($this->state);
                $data['errors'] = new ArrayIterator( $errors );
                Model_Annex_Email::factory()->send('mail.exception.generic', 'system', $data);
            }
        }
    }

    /**
     * Get Post Data
     *
     * Returns post data containing validations, sessions, and other bits that are needed to make
     * other pages load properly
     *
     * @return  array   $post   Post Data
     */
    public function get_post_data($fromrec = '1')
    {
        $home = $this->load_url([
            'target'    => $this->urls['main'],
            'referrer'  => $this->urls['main'],
            'method'    => 'GET',
            'cookie'    => $this->cookie
        ]);

        if ( $home['error'] )
            throw new Bandit_Exception('could not load the home page', 'severe');

        $home = $this->clean_html($home['result']);
        $dom = new DOMDocument();

        if ( ! $dom->loadHTML($home) )
            throw new Bandit_Exception('could not parse html as DOMDocument', 'severe');

        $forms = $dom->getElementsByTagName('form');

        foreach ( $forms as $form )
        {
            $inputs = $form->getElementsByTagName('input');

            foreach ( $inputs as $input )
            {
                $post[$input->getAttribute('name')] = $input->getAttribute('value');
            }
        }

        $post['end_date'] = date('m/d/Y');
        $post['start_date'] = date('m/d/Y', strtotime('-31 days'));
        $post['xsearch'] = '5';
        $post['last_name'] = '';
        $post['first_name'] = '';
        $post['street_name'] = '';
        $post['city_name'] = '';
        $post['statute'] = '';
        $post['arrestingAgency'] = '';
        $post['captcha_id'] = '-1';
        $post['fromrec'] = $fromrec; // Starting point, can only do 5 at a time

        return $post;
    }

    public function get_mug($post)
    {
        // We need to get the url we've been redirected too, plus update the cookie which it uses to validate
        // and spit out the right image. Not much more we can do to simplify here
        $referrer = $this->load_url([
            'target'    => $this->urls['main'],
            'referrer'  => $this->urls['main'],
            'method'    => 'POST',
            'cookie'    => $this->cookie,
            'data'      => $post
        ])['status']['url'];

        $raw = $this->load_url([
            'target'    => $this->urls['mug'],
            'referrer'  => $referrer,
            'cookie'    => $this->cookie
        ])['result'];

        $mug = $this->mug_info($this->_offender);
        $mug_raw = $mug['raw'].$mug['name'];
        $mug_prod = $mug['prod'].$mug['name'];

        // Write the raw file if it doesnt exist
        if ( ! file_exists($mug_raw) )
        {
            if ( ! is_dir($mug['raw']) )
                $this->create_path($mug['raw']);

            // make the file
            $f = fopen($mug_raw, 'wb');
            fwrite($f, $raw);
            fclose($f);
        }

        if ( ! is_dir($mug['prod']) )
            $this->create_path($mug['prod']);

        try
        {
            if ( file_exists($mug_raw) )
            {
                echo $this->mug_stamp(
                    $mug_raw,
                    $mug_prod,
                    $this->_offender['firstname'].' '.$this->_offender['lastname'],
                    $this->_offender['charges'][0],
                    @$this->_offender['charges'][1]
                );
            }
        }
        catch ( Exception $e )
        {
            var_dump($e);
        }
    }
}