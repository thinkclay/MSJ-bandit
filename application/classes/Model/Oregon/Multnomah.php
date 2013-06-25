<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Multnomah Scrape
 *
 *
 *  http://www.mcso.us/
 *
 * @package   Bandit
 * @author    Clay McIlrath
 * @see       http://www.mcd911.net/p2c/jailinmates.aspx
 */
class Model_Oregon_Multnomah extends Model_Bandit
{
    protected $name     = 'multnomah';   // name of scrape goes here
    protected $county   = 'multnomah';    // if it is a single county, put it here, otherwise remove this property
    protected $state    = 'oregon';  //  state goes here
    private $cookie     = '/tmp/multnomah_cookie.txt';

    protected $urls = [
        'main'      => 'http://www.mcso.us/PAID/Home/SearchResults',
        
        'referrer'  => 'https://mcd911.net/p2c/jailinmates.aspx',
        'list'      => 'https://mcd911.net/p2c/jqHandler.ashx?op=s',
        'detail'    => 'https://mcd911.net/p2c/jailinmates.aspx',
        'mug'       => 'https://mcd911.net/p2c/Mug.aspx',
        
        'login'     => 'http://www.mcso.us/PAID/Account/Login'
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
     * @return true - on completed scrape
     * @return false - on failed scrape
     */
    public function scrape()
    {
        $this->do_login();
        
        $raw_list = $this->load_url([
            'target'    => $this->urls['main'],
            'method'    => 'POST',
            'cookie'    => $this->cookie,
            'data'      => [
                'SearchType' => 3 // Set Search Type to 3 so we can just get the last week of Bookings
            ]
        ]);
        

        $list = $this->clean_html($raw_list['result']);
        $dom = new DOMDocument();

        if ( ! $dom->loadHTML($list) )
            throw new Bandit_Exception('could not parse html as DOMDocument', 'severe');

        $xpath = new DOMXpath($dom);
        $rows = $xpath->query('//table[@class="search-results"]/tbody/tr/td[1]/a');
        
        if ( $rows->length < 100 )
            throw new Bandit_Exception('very low results for offenders in xpath query', 'severe');

        foreach ( $rows as $row )
        {
            $booking_id = preg_replace('/\/PAID\/Home\/Booking\//i', '', $row->getAttribute('href'));
            
            if ( strlen($booking_id) < 5 )
                $this->raise('preg match might be off on the table list match', 'report');
                
            $this->extraction($booking_id);
        }        
    }
    
    /**
     * Login to the site
     */
    public function do_login()
    {
        $login_page = $this->load_url([
            'target'    => $this->urls['login'],
            'cookie'    => $this->cookie,
        ]);
        
        // If the login page didnt load for some reason, lets get a message
        if ( $login_page['error'] )
            throw new Bandit_Exception('could not load the login page', 'severe');
                
        // Preg match so we can get changing values.
        if ( ! preg_match('/name=\"__RequestVerificationToken\".*?value\=\"(.+?)\"/i', $login_page['result'], $verification) )
            throw new Bandit_Exception('could not get verification token for login page', 'severe');
        
        // Login
        $this->load_url([
            'target'    => $this->urls['main'],
            'method'    => 'POST',
            'cookie'    => $this->cookie,
            'data'      => [
                '__RequestVerificationToken' => $verification[1],
                'UserName' => 'Busted',
                'Password' => 'Summer3',
                'RememberMe' => 'true',
            ]
        ]);

    }
    
     /**
     * extraction - Validates and extracts all data
     *
     *
     * @params
     *  $page  - Offenders details page
     *  $state - Offender's state
     *  $county - Offender's county
     *
     * @returns
     *
     */
    public function extraction($booking_id)
    { 
        $details = $this->load_url([
            'target'    => 'http://www.mcso.us/PAID/Home/Booking/'.$booking_id,
            'ref'       => $this->urls['main'],
            'cookie'    => $this->cookie,
        ]);
        
        if ( $details['error'] )
            return $this->raise('preg match might be off for: '.$booking_id, 'report');
        
        $details = $details['result'];
        
        // Get the labels to get where the info is going to be.
        $pattern = '/<label.*?>(.*?)<\/label>/ism';
        $labels = $this->parse($pattern, $details);
        $labels = $labels[1];
        foreach ($labels as $key => $value)
        {
            $value = strip_tags( trim($value) );
            if($value == 'Name')
                $name_id = $key;
            if($value == 'Age')
                $age_id = $key;
            if($value == 'Booking Date')
                $booking_date_id = $key;
        }
        
        // Get all the divs with values in them.
        $pattern = '/<div\sclass="col-1-3\sdisplay-value">(.*?)<\/div>/ism';
        $divs = $this->parse($pattern, $details);
        $divs = $divs[1];
        // Compare to label ids to get correct info!
        foreach ($divs as $key => $value)
        {
            $value = strip_tags( trim($value) );
            if($key == $name_id)
                $fullname = $value;
            if($key == $age_id)
                $age = $value;
            if($key == $booking_date_id)
                $booking_date = $value;
        }
        
        // Extract last and first name.
        $fullname = explode(', ', $fullname);
        $lastname = $fullname[0];
        $firstname = explode(' ', $fullname[1]);
        $firstname = $firstname[0];
        
        $booking_date = strtotime($booking_date);
        
        // Pick up the charges now. 
        $pattern = '/class="charge-description-display">(.*?)<\/span>/ism';
        $charges = $this->parse($pattern, $details);
        $charges = $charges[1];
        
        // This section now creates the images.
        
        // Source of image
        $imagefile = "http://www.mcso.us/PAID/Home/HighResolutionMugshotImage/$booking_id";
        
        $imagename = date('(m-d-Y)', $booking_date).'_'.
            $lastname.'_'.
            $firstname.'_'.
            $booking_id.'.jpg';
        
        $imagepath = '/mugs/oregon/multnomah/'.date('Y', $booking_date).
                '/week_'.$this->find_week($booking_date).'/';
       
        $new_image = $imagepath.$imagename;
            
        $this->create_path($imagepath);
        
        
         try 
         {
            // We do get site because image is behind login so we are getting binary to download
            $data = $this->load_url([
                'target'    => $imagefile,
                'ref'       => $this->urls['main'],
                'cookie'    => $this->cookie,
            ]);
            $data = $data['result'];
            
            $destination = $new_image;
            $file = fopen($destination, "w+");
            fputs($file, $data);
            fclose($file);
            sleep(10);
            $this->convert_image($new_image);
            $imagepath = str_replace('.jpg', '.png', $new_image);
            $img = Image::factory($imagepath);

            // @todo convert to the mug_stamp method
            $check = $this->mugStamp(
                $imagepath,
                $firstname.' '.$lastname,
                $charges[0],
                @$charges[1]
            );
        } catch(Exception $e)
        {
            var_dump($e);
        }
        
    }
}