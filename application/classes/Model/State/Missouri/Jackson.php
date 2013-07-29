<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Jackson County Scrape
 *
 * We've got FTP, we just have to parse the files daily
 *
 *  https://mcd911.net/p2c/jqHandler.ashx?op=s
 *
 * @package   Bandit
 * @author    Clay McIlrath
 * @see       https://www.accesskent.com/
 */
class Model_State_Missouri_Jackson extends Model_Bandit
{
    protected $name     = 'jacksonmo';   // name of scrape goes here
    protected $county   = 'jackson';    // if it is a single county, put it here, otherwise remove this property
    protected $state    = 'missouri';  //  state goes here

    protected $ftp = [
        'url'      => 'ftp.jacksongov.org',
        'username' => 'slammer',
        'password' => 'J@13MMQ90',
    ];
    protected $conn = FALSE;


    public function __construct()
    {
        if ( ! $this->conn = ftp_connect($this->ftp['url']) )
            throw new Bandit_Exception('Could not CONNECT to Jackson County, Missouri FTP', 'severe');

        if ( ! ftp_login($this->conn, $this->ftp['username'], $this->ftp['password']) )
            throw new Bandit_Exception('Could not LOGIN to Jackson County, Missouri FTP', 'severe');


        ftp_chdir($this->conn, './JCDCDATA');

        $local_path = $this->create_path(
            '/original/'.$this->state.'/'.$this->county.'/'.
            date('Y').'/week_'.$this->find_week(time()).'/'
        );
        $local_path = $this->create_path($local_path.date('j').'/');

        $InmatesAll = fopen($local_path.'InmatesAll.txt', 'w');
        $InmatesProcessed = fopen($local_path.'InmatesProcessed.txt', 'w');
        $InmatesRelease = fopen($local_path.'InmatesRelease.txt', 'w');

        if ( ! ftp_fget($this->conn, $InmatesAll, 'InmatesAll.txt', FTP_BINARY, 0) )
            throw new Bandit_Exception('Could not DOWNLOAD InmatesAll.txt from Jackson County, MO FTP', 'severe');

        if ( ! ftp_fget($this->conn, $InmatesProcessed, 'InmatesProcessed.txt', FTP_BINARY, 0) )
            throw new Bandit_Exception('Could not DOWNLOAD InmatesProcessed.txt from Jackson County, MO FTP', 'severe');

        if ( ! ftp_fget($this->conn, $InmatesRelease, 'InmatesRelease.txt', FTP_BINARY, 0) )
            throw new Bandit_Exception('Could not DOWNLOAD InmatesRelease.txt from Jackson County, MO FTP', 'severe');

        // go into the images directory and download all the images
        ftp_chdir($this->conn, './Images');

        foreach ( ftp_nlist($this->conn, '.') as $image )
        {
            $local_image = fopen($local_path.$image, 'w');

            if ( ! ftp_fget($this->conn, $local_image, $image, FTP_BINARY, 0) )
                throw new Bandit_Exception('Could not download image: '.$image.' for Jackson County, MO', 'severe');
        }
    }

    public function scrape()
    {

    }

    public function get_mug($booking_id)
    {


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