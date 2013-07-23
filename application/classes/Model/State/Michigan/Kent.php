<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Kent County Scrape
 *
 * This is a very clean site, with great markup
 *
 *  https://mcd911.net/p2c/jqHandler.ashx?op=s
 *
 * @package   Bandit
 * @author    Clay McIlrath
 * @see       https://www.accesskent.com/
 */
class Model_State_Michigan_Kent extends Model_Bandit
{
    protected $name     = 'kent';   // name of scrape goes here
    protected $county   = 'kent';    // if it is a single county, put it here, otherwise remove this property
    protected $state    = 'michigan';  //  state goes here
    private $cookie     = '/tmp/kent_cookie.txt';

    protected $urls = [
        'main'      => 'https://www.accesskent.com/InmateLookup/',
        'referrer'  => 'https://www.accesskent.com/InmateLookup/newSearch.do',
        'list'      => 'https://www.accesskent.com/InmateLookup/search.do',
        'next'      => 'https://www.accesskent.com/InmateLookup/searchNext.do',
        'detail'    => 'https://www.accesskent.com/InmateLookup/showDetail.do?bookNo=',
        'charges'   => 'https://www.accesskent.com/InmateLookup/showCharge.do',
        'mug'       => 'https://www.accesskent.com/appImages/MugShots/'
    ];

    protected $errors = FALSE;
    protected $_offender = NULL;
    protected $_booking_ids = [];


    /**
     * Construct
     *
     * For now sets a timelimit, deletes cookie if they exist and creates mscrape model in DB.
     */
    public function __construct()
    {
        if ( file_exists($this->cookie) )
            unlink($this->cookie);   // Unlink only works for files, use rmdir for Directories.
    }

    /**
     * Scrape
     *
     * main scrape function makes the curl calls and sends details to the extraction function
     *
     * @return true - on completed scrape
     * @return false - on failed scrape
     */
    public function scrape()
    {
        $post = $this->setup_sessions();

        $list = $this->load_url([
            'target'    => $this->urls['list'],
            'referrer'  => $this->urls['referrer'],
            'method'    => 'POST',
            'cookie'    => $this->cookie,
            'data'      => [
                'lastName'  => '',
                'firstName' => '',
                'dobMonth'  => '',
                'dobDay'    => '',
                'dobYear'   => '',
                'startMonth'=> date('m'),
                'startDay'  => date('d'),
                'startYear' => date('Y'),
                'Submit'    => 'Search'
            ]
        ]);

        $list = $this->clean_html($list['result']);
        $dom = new DOMDocument();

        if ( ! $dom->loadHTML($list) )
            throw new Bandit_Exception('could not parse html as DOMDocument', 'severe');

        $xpath = new DOMXpath($dom);

        $pagination_text = trim($xpath->query('/html/body/div[4]/form/div/text()')->item(1)->wholeText);

        if ( preg_match('/\d+$/', $pagination_text, $total_records) )
        {
            $count = round($total_records[0]/10);
            $count += ($total_records[0]%10) ? 1 : 0;
        }

        $this->_booking_ids = array_merge($this->_booking_ids, $this->parse_list($list));

        for ( $i = 0; $i < $count; $i++ )
        {
            $next = $this->load_url([
                'target'    => $this->urls['next'],
                'method'    => 'GET',
                'cookie'    => $this->cookie,
                'debug'     => TRUE
            ]);
            $list = $this->clean_html($next['result']);

            $this->_booking_ids = array_merge($this->_booking_ids, $this->parse_list($list));
        }

        foreach ( $this->_booking_ids as $booking_id )
        {
            $offender = $this->get_details($booking_id);
            $charges = $this->get_charges();
            $offender_name = explode(' ', $offender['NAME']);
            $middlename = isset($offender_name[2]) ? $offender_name[1] : '';
            $lastname = isset($offender_name[2]) ? $offender_name[2] : $offender_name[1];

            if ( ! $offender OR ! $charges OR ! count($charges['CHARGES']) )
            {
                error_log('could not get charges or offender information');
                continue;
            }

            $doc = Brass::factory('Brass_Offender', [
                'booking_id' => $this->name.'_'.$booking_id
            ])->load();

            $this->_offender = [
                'booking_id'    => $this->name.'_'.$booking_id,
                'scrape_time'   => time(),
                'updated'       => time(),
                'firstname'     => $offender_name[0],
                'middlename'    => $middlename,
                'lastname'      => $lastname,
                'booking_date'  => strtotime($offender['BOOKING_DATE']),
                'charges'       => $charges['CHARGES'],
                'height'        => $offender['HEIGHT'],
                'weight'        => $offender['WEIGHT'],
                'eye_color'     => $offender['EYE_COLOR'],
                'hair_color'    => $offender['HAIR_COLOR'],
                'gender'        => strtoupper($offender['SEX']),
                'race'          => strtoupper($offender['RACE']),
                'dob'           => strtotime($offender['DOB']),
                'state'         => $this->state,
                'county'        => $this->county,
                'scrape'        => $this->name
            ];

            // Record already exists
            if ( $doc->loaded() )
                $doc->delete();

            try
            {
                $doc->values($this->_offender);

                if ( $doc->check() )
                {
                    $doc->create();
                }
            }
            catch ( Brass_Validation_Exception $e )
            {
                foreach ( $e->array->errors('brass') as $k => $v )
                {
                    $this->errors[] = [
                        'key' => $k,
                        'issue' => $v,
                        'offender' => $offender['NAME'].': '.$this->name.'_'.$this->state.'_'.$this->_offender['booking_id']
                    ];
                }
            }

            if ( $this->errors )
                var_dump($this->errors);

            $this->get_mug($booking_id);

            // sleep(rand(5,100));
        }

        if ( isset($errors) )
        {
            $data['scrape'] = ucfirst($this->county).' county, '.ucfirst($this->state);
            $data['errors'] = new ArrayIterator( $errors );
            Model_Annex_Email::factory()->send('mail.exception.generic', 'system', $data);
        }
    }

    /**
     * Get Post Data
     *
     * Sets up sessions that are needed to make other pages load properly
     *
     * @return  array   $post   Post Data
     */
    public function setup_sessions()
    {
        $home = $this->load_url([
            'target'    => $this->urls['main'],
            'referrer'  => $this->urls['main'],
            'method'    => 'GET',
            'cookie'    => $this->cookie
        ]);

        if ( $home['error'] )
            throw new Bandit_Exception('could not load the home page', 'severe');
    }

    /**
     * Parse List
     *
     * Goes through a table list each time to build an array of booking_id's
     *
     * @param Tidy a clean html object
     * @return array booking_id's
     */
    public function parse_list($html)
    {
        $data = [];
        $dom = new DOMDocument();

        if ( ! $dom->loadHTML($html) )
            throw new Bandit_Exception('could not parse html as DOMDocument', 'severe');

        $xpath = new DOMXpath($dom);

        $rows = $xpath->query('//table/tr/td[1]/a');

        foreach ( $rows as $row )
        {
            if ( ! preg_match('/(?<=bookNo=).*/', $row->getAttribute('href'), $matches) )
                $this->raise('preg match might be off on the table list match', 'report');

            $data[] = $matches[0];
        }

        return $data;
    }

    public function get_details($booking_id)
    {
        $details = $this->load_url([
            'target'    => $this->urls['detail'].$booking_id,
            'cookie'    => $this->cookie
        ]);

        $html = $this->clean_html($details['result']);
        $dom = new DOMDocument();

        if ( ! $dom->loadHTML($html) )
            throw new Bandit_Exception("could not parse detail page for $booking_id", 'severe');

        $xpath = new DOMXpath($dom);

        $details = $xpath->query('//form/div[1]/fieldset/div[2]/p[1]')->item(0);
        $details_cleaned = trim(htmlentities(Bandit_DOM::inner_html($details)));

        $offender = [];

        foreach ( explode('&lt;br/&gt;', $details_cleaned) as $row )
        {
            $row_cleaned = explode(':&nbsp;&nbsp;', htmlentities(strip_tags(html_entity_decode($row))));

            if ( $row_cleaned[0] AND $row_cleaned[1] )
                $offender[preg_replace('/\s/', '_', trim(strtoupper($row_cleaned[0])))] = $row_cleaned[1];
        }

        if ( ! isset($offender['NAME']) )
        {
            error_log('Failed to acquire offender details for '.$booking_id);
            return FALSE;
        }
        else
        {
            return $offender;
        }
    }

    public function get_charges()
    {
        $details = $this->load_url([
            'target'    => $this->urls['charges'],
            'cookie'    => $this->cookie
        ]);
        $html = $this->clean_html($details['result']);
        $dom = new DOMDocument();

        if ( ! $dom->loadHTML($html) )
            throw new Bandit_Exception('could not parse charges as DOMDocument', 'severe');

        $xpath = new DOMXpath($dom);

        if ( preg_match_all('/Charge\sInfo/i', $html, $matches) )
            if ( count($matches[0]) > 1 )
            {
                $rows = $xpath->query('//form//table//tr[3]/td[2]');

                if ( $rows->item(0) )
                    error_log('Charge info is pending: '.trim($rows->item(0)->nodeValue));
                else
                    error_log(Console::log($html));

                return false;
            }

        $data = [];

        foreach ( $dom->getElementsByTagName('table') as $table )
            foreach ( $table->getElementsByTagName('tr') as $row )
            {
                $cells = $row->getElementsByTagName('td');
                $key = strtoupper(preg_replace('/:/', '', preg_replace('/\s/', '_', trim(strtolower($cells->item(0)->nodeValue)))));
                $val = $cells->item(1) ? trim($cells->item(1)->nodeValue) : 'null';

                if ( $key AND $val )
                    if ( $key == 'CHARGE_DESCRIPTION' )
                        $data['CHARGES'][] = $val;
                    else
                        $data[$key] = $val;
            }

        return $data;
    }

    public function get_mug($booking_id)
    {
        $raw = $this->load_url([
            'target'    => $this->urls['mug'].$booking_id.'.jpg',
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
            if ( file_exists($mug_raw) AND ! file_exists($mug_prod) )
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