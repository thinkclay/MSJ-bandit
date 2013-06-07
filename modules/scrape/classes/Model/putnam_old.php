<?php defined('SYSPATH') or die('No direct script access.');
 
 
 
/**
 * Model_Essex
 *
 * @package Scrape
 * @author Bryan Galli
 * @url 
 */
class Model_Putnam extends Model_Scrape
{
    private $scrape     = 'putnam';
    private $state      = 'florida';
    private $cookies    = '/tmp/putnam_cookies.txt';
	
    public function __construct()
    {
        set_time_limit(86400); //make it go forever 
        if ( file_exists($this->cookies) ) { unlink($this->cookies); } //delete cookie file if it exists        
        # create mscrape model if one doesn't already exist
        $mscrape = Mango::factory('mscrape', array('name' => $this->scrape, 'state' => $this->state))->load();
        if (!$mscrape->loaded())
        {
            $mscrape = Mango::factory('mscrape', array('name' => $this->scrape, 'state' => $this->state))->create();
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
    * scrape - main scrape function calls the curls and handles paging
    *
    * @params $date - timestamp of begin date
    * @return true - on completed scrape
    * @return false - on failed scrape
    */
    function scrape() 
    {
    	
    	
    	$county = 'putnam';
    	$home_url = 'http://public.pcso.us/jail/inmates.aspx';
        $thepage = $this->curl_to_home($home_url);
        $check = preg_match('/VIEWSTATE.*value\=\"(.*)\"/Uis', $thepage, $match);
        $check2 = preg_match('/EVENTVALIDATION.*value\=\"(.*)\"/Uis', $thepage, $match2);
        if ($check && $check2)
        { 
            $this->vs = $match[1];  
            $this->ev = $match2[1];
            $thepage  = $this->curl_to_url($home_url);
    		$count = 1;
    		$total = 14;
    		while($count <= $total)
    		{
    			$check = preg_match_all("/SYSID\=(.*)\"/Uis", $thepage, $matches);
    			foreach($matches[1] as $value)
    			{
    				$link = "http://public.pcso.us/jail/bookingDetails.aspx?SYSID=" . $value;
    				$details = $this->curl_to_url2($link);
    				$extraction = $this->extraction($details, $county);
					if ($extraction == 100) { $this->report->successful = ($this->report->successful + 1); $this->report->update(); }
                    if ($extraction == 101) { $this->report->other = ($this->report->other + 1); $this->report->update(); }
                    if ($extraction == 102) { $this->report->bad_images = ($this->report->bad_images + 1); $this->report->update(); }
                    if ($extraction == 103) { $this->report->exists = ($this->report->exists + 1); $this->report->update(); }
                    if ($extraction == 104) { $this->report->new_charges = ($this->report->new_charges + 1); $this->report->update(); }
                    $this->report->total = ($this->report->total + 1); $this->report->update();
    			}
    			$count = $count + 1;
    			$post_vars = "__LASTFOCUS=&__EVENTTARGET=MyGridView&__EVENTARGUMENT=Page%24" .$count. "&__VIEWSTATE=%2FwEPDwUKMjA4NzIyMzk1Mg9kFgICAg9kFgQCBw88KwANAQAPFgYeDERhdGFTb3VyY2VJRAUDRFMyHgtfIURhdGFCb3VuZGceC18hSXRlbUNvdW50AtsCZBYCZg9kFjQCAQ9kFhJmDw8WAh4EVGV4dAUUSE9XRUxMICAgICAgICAgICAgICBkZAIBDw8WAh8DBQxST0dFUiAgICAgICBkZAICDw8WAh8DBQxXQVlORSAgICAgICBkZAIDDw8WAh8DBQk2LzA3LzE5NjZkZAIEDw8WAh8DBQFNZGQCBQ8PFgIfAwUCVyBkZAIGD2QWAmYPDxYEHwMFDDExLTAyMzU4ICAgIB4LTmF2aWdhdGVVcmwFKmJvb2tpbmdEZXRhaWxzLmFzcHg%2FU1lTSUQ9NzY3Mzc4JklNRz00NzQ3M2RkAgcPDxYCHwMFEzcvOC8yMDExIDI6MTE6MDAgUE1kZAIIDw8WAh8DBQYmbmJzcDtkZAICD2QWEmYPDxYCHwMFFEZJVFpTSU1NT05TICAgICAgICAgZGQCAQ8PFgIfAwUMR1JFR09SWSAgICAgZGQCAg8PFgIfAwUGJm5ic3A7ZGQCAw8PFgIfAwUJOC8xMy8xOTg5ZGQCBA8PFgIfAwUBTWRkAgUPDxYCHwMFAlcgZGQCBg9kFgJmDw8WBB8DBQwxMS0wMjM1NiAgICAfBAUqYm9va2luZ0RldGFpbHMuYXNweD9TWVNJRD03NjczNzYmSU1HPTQzNDQ3ZGQCBw8PFgIfAwUUNy84LzIwMTEgMTI6NTQ6NDcgUE1kZAIIDw8WAh8DBQYmbmJzcDtkZAIDD2QWEmYPDxYCHwMFFFRPQkxFUiAgICAgICAgICAgICAgZGQCAQ8PFgIfAwUMQU5UT05JTyAgICAgZGQCAg8PFgIfAwUMREVXQVlORSAgICAgZGQCAw8PFgIfAwUKMTAvMjcvMTk4NWRkAgQPDxYCHwMFAU1kZAIFDw8WAh8DBQJCIGRkAgYPZBYCZg8PFgQfAwUMMTEtMDIzNTIgICAgHwQFKmJvb2tpbmdEZXRhaWxzLmFzcHg%2FU1lTSUQ9NzY3MzcyJklNRz00NzM3M2RkAgcPDxYCHwMFEzcvOC8yMDExIDE6NDA6NDcgQU1kZAIIDw8WAh8DBQYmbmJzcDtkZAIED2QWEmYPDxYCHwMFFEhBUkRZICAgICAgICAgICAgICAgZGQCAQ8PFgIfAwUMREFWQVJPTiAgICAgZGQCAg8PFgIfAwUMU0hFUlJBUkQgICAgZGQCAw8PFgIfAwUKMTEvMjIvMTk5MGRkAgQPDxYCHwMFAU1kZAIFDw8WAh8DBQJCIGRkAgYPZBYCZg8PFgQfAwUMMTEtMDIzNDggICAgHwQFKmJvb2tpbmdEZXRhaWxzLmFzcHg%2FU1lTSUQ9NzY3MzY4JklNRz01ODMyM2RkAgcPDxYCHwMFEzcvNy8yMDExIDQ6MDg6MDkgUE1kZAIIDw8WAh8DBQYmbmJzcDtkZAIFD2QWEmYPDxYCHwMFFEtFTExFUiAgICAgICAgICAgICAgZGQCAQ8PFgIfAwUMUEhJTExJUCAgICAgZGQCAg8PFgIfAwUMSk9SREFOICAgICAgZGQCAw8PFgIfAwUJNC8wNy8xOTkwZGQCBA8PFgIfAwUBTWRkAgUPDxYCHwMFAlcgZGQCBg9kFgJmDw8WBB8DBQwxMS0wMjM0NiAgICAfBAUqYm9va2luZ0RldGFpbHMuYXNweD9TWVNJRD03NjczNjUmSU1HPTI5NTIxZGQCBw8PFgIfAwUTNy83LzIwMTEgMTo0Mjo0NyBQTWRkAggPDxYCHwMFBiZuYnNwO2RkAgYPZBYSZg8PFgIfAwUUSFVHSEVTICAgICAgICAgICAgICBkZAIBDw8WAh8DBQxHUkVHT1JZICAgICBkZAICDw8WAh8DBQxLWUxFICAgICAgICBkZAIDDw8WAh8DBQoxMC8wNy8xOTg2ZGQCBA8PFgIfAwUBTWRkAgUPDxYCHwMFAlcgZGQCBg9kFgJmDw8WBB8DBQwxMS0wMjM0MSAgICAfBAUqYm9va2luZ0RldGFpbHMuYXNweD9TWVNJRD03NjczNTcmSU1HPTMwNDU2ZGQCBw8PFgIfAwUTNy83LzIwMTEgMzoyMzozNyBBTWRkAggPDxYCHwMFBiZuYnNwO2RkAgcPZBYSZg8PFgIfAwUUSkFOT1dJVFogICAgICAgICAgICBkZAIBDw8WAh8DBQxOSUNLICAgICAgICBkZAICDw8WAh8DBQYmbmJzcDtkZAIDDw8WAh8DBQkzLzA4LzE5ODhkZAIEDw8WAh8DBQFNZGQCBQ8PFgIfAwUCVyBkZAIGD2QWAmYPDxYEHwMFDDExLTAyMzM4ICAgIB8EBSpib29raW5nRGV0YWlscy5hc3B4P1NZU0lEPTc2NzM1NCZJTUc9NjEzNzJkZAIHDw8WAh8DBRM3LzYvMjAxMSA2OjE2OjM4IFBNZGQCCA8PFgIfAwUGJm5ic3A7ZGQCCA9kFhJmDw8WAh8DBRRLUkFVU1MgICAgICAgICAgICAgIGRkAgEPDxYCHwMFDFRJTU9USFkgICAgIGRkAgIPDxYCHwMFDEogICAgICAgICAgIGRkAgMPDxYCHwMFCTIvMDUvMTk4NGRkAgQPDxYCHwMFAU1kZAIFDw8WAh8DBQJXIGRkAgYPZBYCZg8PFgQfAwUMMTEtMDIzMzcgICAgHwQFKmJvb2tpbmdEZXRhaWxzLmFzcHg%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%2FU1lTSUQ9NzY3MzM1JklNRz02MzY3OWRkAgcPDxYCHwMFFDcvNS8yMDExIDExOjU4OjA5IEFNZGQCCA8PFgIfAwUGJm5ic3A7ZGQCDw9kFhJmDw8WAh8DBRRFU1RSRUxMQSAgICAgICAgICAgIGRkAgEPDxYCHwMFDERVU1RJTiAgICAgIGRkAgIPDxYCHwMFDFAgICAgICAgICAgIGRkAgMPDxYCHwMFCTQvMjEvMTk4N2RkAgQPDxYCHwMFAU1kZAIFDw8WAh8DBQJXIGRkAgYPZBYCZg8PFgQfAwUMMTEtMDIzMTkgICAgHwQFKmJvb2tpbmdEZXRhaWxzLmFzcHg%2FU1lTSUQ9NzY3MzMyJklNRz01NDU0NGRkAgcPDxYCHwMFFDcvNS8yMDExIDEwOjQ3OjIyIEFNZGQCCA8PFgIfAwUGJm5ic3A7ZGQCEA9kFhJmDw8WAh8DBRREQVdTT04gICAgICAgICAgICAgIGRkAgEPDxYCHwMFDENIUklTVE9QSEVSIGRkAgIPDxYCHwMFDFJBWSAgICAgICAgIGRkAgMPDxYCHwMFCTkvMjAvMTk4MWRkAgQPDxYCHwMFAU1kZAIFDw8WAh8DBQJXIGRkAgYPZBYCZg8PFgQfAwUMMTEtMDIzMDMgICAgHwQFKmJvb2tpbmdEZXRhaWxzLmFzcHg%2FU1lTSUQ9NzY3MzE1JklNRz0zODY0MWRkAgcPDxYCHwMFEzcvMy8yMDExIDY6NTI6MDcgUE1kZAIIDw8WAh8DBQYmbmJzcDtkZAIRD2QWEmYPDxYCHwMFFEdBWSAgICAgICAgICAgICAgICAgZGQCAQ8PFgIfAwUMVE9NICAgICAgICAgZGQCAg8PFgIfAwUMTUlYICAgICAgICAgZGQCAw8PFgIfAwUJNy8yOS8xOTQ2ZGQCBA8PFgIfAwUBTWRkAgUPDxYCHwMFAlcgZGQCBg9kFgJmDw8WBB8DBQwxMS0wMjI5NyAgICAfBAUqYm9va2luZ0RldGFpbHMuYXNweD9TWVNJRD03NjczMDkmSU1HPTYyMDE1ZGQCBw8PFgIfAwUUNy8zLzIwMTEgMTI6NTQ6NDQgQU1kZAIIDw8WAh8DBQYmbmJzcDtkZAISD2QWEmYPDxYCHwMFFEpPTkVTICAgICAgICAgICAgICAgZGQCAQ8PFgIfAwUMQ0xBWVRPTiAgICAgZGQCAg8PFgIfAwUMRVVHRU5FICAgICAgZGQCAw8PFgIfAwUJOC8yOS8xOTU5ZGQCBA8PFgIfAwUBTWRkAgUPDxYCHwMFAlcgZGQCBg9kFgJmDw8WBB8DBQwxMS0wMjI1NyAgICAfBAUqYm9va2luZ0RldGFpbHMuYXNweD9TWVNJRD03NjcyNjMmSU1HPTU4MzU1ZGQCBw8PFgIfAwUUNi8zMC8yMDExIDI6MjM6MzMgQU1kZAIIDw8WAh8DBQYmbmJzcDtkZAITD2QWEmYPDxYCHwMFFExFV0lTICAgICAgICAgICAgICAgZGQCAQ8PFgIfAwUMRUxMSU9UVCAgICAgZGQCAg8PFgIfAwUMQVJUSFVSICAgICAgZGQCAw8PFgIfAwUJNy8wMi8xOTcxZGQCBA8PFgIfAwUBTWRkAgUPDxYCHwMFAlcgZGQCBg9kFgJmDw8WBB8DBQwxMS0wMjI1MSAgICAfBAUqYm9va2luZ0RldGFpbHMuYXNweD9TWVNJRD03NjcyNTYmSU1HPTM5OTU5ZGQCBw8PFgIfAwUUNi8yOS8yMDExIDE6MzM6MjAgUE1kZAIIDw8WAh8DBQYmbmJzcDtkZAIUD2QWEmYPDxYCHwMFFE1FRERFUlMgICAgICAgICAgICAgZGQCAQ8PFgIfAwUMTUFSVklOICAgICAgZGQCAg8PFgIfAwUMTUFaRSAgICAgICAgZGQCAw8PFgIfAwUJNS8yMy8xOTQ1ZGQCBA8PFgIfAwUBTWRkAgUPDxYCHwMFAlcgZGQCBg9kFgJmDw8WBB8DBQwxMS0wMjI0OCAgICAfBAUqYm9va2luZ0RldGFpbHMuYXNweD9TWVNJRD03NjcyNTMmSU1HPTQwOTEwZGQCBw8PFgIfAwUVNi8yOS8yMDExIDEyOjA0OjQ2IFBNZGQCCA8PFgIfAwUGJm5ic3A7ZGQCFQ9kFhJmDw8WAh8DBRRNQVhXRUxMICAgICAgICAgICAgIGRkAgEPDxYCHwMFDENSQUlHICAgICAgIGRkAgIPDxYCHwMFDEpFUk9NRSAgICAgIGRkAgMPDxYCHwMFCTkvMzAvMTk4MWRkAgQPDxYCHwMFAU1kZAIFDw8WAh8DBQJCIGRkAgYPZBYCZg8PFgQfAwUMMTEtMDIyNDQgICAgHwQFKmJvb2tpbmdEZXRhaWxzLmFzcHg%2FU1lTSUQ9NzY3MjQ5JklNRz0zMDM3NmRkAgcPDxYCHwMFFTYvMjkvMjAxMSAxMToyMDo0NSBBTWRkAggPDxYCHwMFBiZuYnNwO2RkAhYPZBYSZg8PFgIfAwUUTEVFICAgICAgICAgICAgICAgICBkZAIBDw8WAh8DBQxBTFZJTiAgICAgICBkZAICDw8WAh8DBQxKICAgICAgICAgICBkZAIDDw8WAh8DBQk2LzAzLzE5ODlkZAIEDw8WAh8DBQFNZGQCBQ8PFgIfAwUCQiBkZAIGD2QWAmYPDxYEHwMFDDExLTAyMjQ1ICAgIB8EBSpib29raW5nRGV0YWlscy5hc3B4P1NZU0lEPTc2NzI1MCZJTUc9NTk5MjhkZAIHDw8WAh8DBRU2LzI5LzIwMTEgMTE6MzE6MjMgQU1kZAIIDw8WAh8DBQYmbmJzcDtkZAIXD2QWEmYPDxYCHwMFFEFQUExJTkcgICAgICAgICAgICAgZGQCAQ8PFgIfAwUMTUlDSEFFTCAgICAgZGQCAg8PFgIfAwUMWEFWSUVSICAgICAgZGQCAw8PFgIfAwUKMTEvMDUvMTk4NWRkAgQPDxYCHwMFAU1kZAIFDw8WAh8DBQJCIGRkAgYPZBYCZg8PFgQfAwUMMTEtMDIyMzcgICAgHwQFKmJvb2tpbmdEZXRhaWxzLmFzcHg%2FU1lTSUQ9NzY3MjQxJklNRz0yOTcxMmRkAgcPDxYCHwMFFDYvMjkvMjAxMSA5OjEzOjIyIEFNZGQCCA8PFgIfAwUGJm5ic3A7ZGQCGA9kFhJmDw8WAh8DBRRURU5OQU5UICAgICAgICAgICAgIGRkAgEPDxYCHwMFDERFVklOICAgICAgIGRkAgIPDxYCHwMFDEJBUlRPVyAgICAgIGRkAgMPDxYCHwMFCjEwLzE0LzE5OTJkZAIEDw8WAh8DBQFNZGQCBQ8PFgIfAwUCVyBkZAIGD2QWAmYPDxYEHwMFDDExLTAyMjI2ICAgIB8EBSpib29raW5nRGV0YWlscy5hc3B4P1NZU0lEPTc2NzIyOCZJTUc9MzA0OTlkZAIHDw8WAh8DBRQ2LzI4LzIwMTEgMzoxNTowMyBQTWRkAggPDxYCHwMFBiZuYnNwO2RkAhkPZBYSZg8PFgIfAwUUV0VCQiAgICAgICAgICAgICAgICBkZAIBDw8WAh8DBQxSQUxQSCAgICAgICBkZAICDw8WAh8DBQxFVUdFTkUgICAgICBkZAIDDw8WAh8DBQkyLzI3LzE5ODJkZAIEDw8WAh8DBQFNZGQCBQ8PFgIfAwUCVyBkZAIGD2QWAmYPDxYEHwMFDDExLTAyMjIxICAgIB8EBSpib29raW5nRGV0YWlscy5hc3B4P1NZU0lEPTc2NzIyMiZJTUc9NDM0ODBkZAIHDw8WAh8DBRQ2LzI4LzIwMTEgOToyMjozNyBBTWRkAggPDxYCHwMFBiZuYnNwO2RkAhoPDxYCHgdWaXNpYmxlaGRkAgkPD2QPEBYBZhYBFgIeDlBhcmFtZXRlclZhbHVlZRYBZmRkGAEFCk15R3JpZFZpZXcPPCsACgICAggIAg5kXNZrrDHCdp%2BNk%2FbwMX8R2WtIgiU%3D&__EVENTVALIDATION=%2FwEWDgLL%2BPn7CAKdxfucCAKs34rGBgLbo7GGCAK7lInMBQK7lP3MBQK7lIHMBQK7lJXMBQK7lJnMBQK7lI3MBQK7lJHMBQK7lKXMBQLVns38BAK%2Bt%2B%2BBAQ5YJEV3SocHAE18vJzryVCOnnoe&txtLASTNAME=";
    			$thepage = $this->curl_to_url3($home_url, $post_vars);
    		}
    		$this->report->failed = ($this->report->other + $this->report->bad_images + $this->report->exists + $this->report->new_charges);
	        $this->report->finished = 1;
	        $this->report->stop_time = time();
	        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
	        $this->report->update();
	        return true;
    	}
	} //end scrape function
		
		

    //clean_string - removes everything but utf8 chars 1-126 - trims string - makes it uppercase
	function clean_string_utf8($thing_to_clean)
	{
		if(is_array($thing_to_clean))
		{
			foreach($thing_to_clean as $clean_me)
			{
				//$patterns = array('/[\x7f-\xff]/', '/\r\n/', '/\r/', '/\n/');
				$clean_array[] = strtoupper(trim(preg_replace('/[\x7f-\xff]/', ' ', $clean_me)));
			}
			return $clean_array;
		}
		else
		{
			$clean_string = strtoupper(trim(preg_replace('/[\x7f-\xff]/', ' ', $thing_to_clean)));
			return $clean_string;
		}
	}

	function curl_to_home($url)
    {
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
	
    function curl_to_url($url)
    {
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, 
			"__LASTFOCUS=&__EVENTTARGET=&__EVENTARGUMENT=&__VIEWSTATE=".urlencode($this->vs)."&__EVENTVALIDATION=".urlencode($this->ev)."&txtLASTNAME=&button2=Show+All+Inmates"
		);
        $index = curl_exec($ch);
        curl_close($ch);
        return $index;
    } 
    
    function curl_to_url2($url)
    {
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		//curl_setopt($ch, CURLOPT_POST, true);
		//curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vars);
        $index = curl_exec($ch);
        curl_close($ch);
        return $index;
    }
    
    function curl_to_url3($url, $post_vars)
    {
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_vars);
        $index = curl_exec($ch);
        curl_close($ch);
        return $index;
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
    function extraction($details_page, $county)
    {
    	$check = preg_match('/id\=\"DataList1\_ctl00\_PINLabel\"\>\<b\>(.*)\</Uis', $details_page, $booking_id);
		$booking_id = $county .'_'. $this->clean_string_utf8($booking_id[1]);
		if(isset($booking_id))
		{
			$offender = Mango::factory('offender', array(
            	'booking_id' => $booking_id
        	))->load(); 
			if(!$offender->loaded())
			{
				
				$check = preg_match('/FIRSTLabel"><b>(.*)\</Uis', $details_page, $firstname); 
				if($check)
				{
					$firstname = strtoupper(trim($firstname[1]));
					$check  = preg_match('/LAST\_NAMELabel\"\>\<b\>(.*)\<\/b/', $details_page, $lastname);
					if($check)
					{
						
						$lastname = strtoupper($this->clean_string_utf8($lastname[1]));
						$check = preg_match_all('/\<b\>Charges.*color\=\"\#333333\".*\#333333\"\>(.*)\</Uis', $details_page, $charges);
						if($check)
						{
							
							$charges = $this->clean_string_utf8($charges[1][0]);
							$check = preg_match('/DATELabel\"\>\<b\>(.*)\s/Uis', $details_page, $booking_date);
							if($check)
							{
								
								$booking_date = strtotime(trim($this->clean_string_utf8($booking_date[1])));
								$check = preg_match('/amp\%3bIMG\=(.*)\"/Uis', $details_page, $image_id);
								if($check)
								{
									
									$image_id = $image_id[1];
									$image_link = "http://public.pcso.us/jail/adb.asp?IMG=" . $image_id;
									$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
									$imagepath = '/mugs/florida/'.$county.'/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
									$county_directory = '/mugs/florida';
									$this->make_county_directory($county_directory);
									$extra_fields = array();
									$check = preg_match('/SEXLabel\"\>\<b\>(.*)\</Uis', $details_page, $match);
									if($check)
									{
										$extra_fields['gender'] = $this->gender_mapper($this->clean_string_utf8($match[1]));
									}
									$check = preg_match('/RACELabel\"\>\<b\>(.*)\</Uis', $details_page, $match);
									if($check)
									{
										$extra_fields['race'] = $this->race_mapper($this->clean_string_utf8($match[1]));
									}
									if(!empty($charges))
									{
										$charges_object = Mango::factory('charge', array('county' => $this->scrape, 'new' => 0))->load(false)->as_array(false);
										$list = array();
	                                    foreach($charges_object as $row)
	                                    {
	                                        $list[$row['charge']] = $row['abbr'];
	
	                                    }
	                                    $ncharges = array();
	                                    $ncharges = $this->charges_check($charges, $list);
										if(empty($ncharges))
										{
											$fcharges   = array();
											//foreach($charges as $charge)
											//{
	                                        	$fcharges[] = trim(strtoupper($charges));
											//} 
	                                        # make it unique and reset keys
	                                        $fcharges = array_unique($fcharges);
	                                        $fcharges = array_merge($fcharges);
	                                        $dbcharges = $fcharges;
	                                        $mugpath = $this->set_mugpath($imagepath);
	                                        //@todo find a way to identify extension before setting ->imageSource
	                                        $this->imageSource    = $image_link;
	                                        $this->save_to        = $imagepath.$imagename;
	                                        $this->set_extension  = true;
	                                        $this->cookie         = $this->cookies;
	                                        $this->download('curl');
	                                        if (file_exists($this->save_to . '.jpg')) //validate the image was downloaded
	                                        {
	                                            #@TODO make validation for a placeholder here probably
	                                            # ok I got the image now I need to do my conversions
	                                            # convert image to png.
	                                            $this->convertImage($mugpath.$imagename.'.jpg');
	                                            $imgpath = $mugpath.$imagename.'.png';
	                                            $img = Image::factory($imgpath);
	                                            $imgpath = $mugpath.$imagename.'.png';	
												if(strlen($fcharges[0]) >= 15)
												{
													$charges = $this->charge_cropper($fcharges[0], 400, 15);
													if(!$charges)
													{
														$fcharges = $charges;
													}
													else
													{
														//$charges = $this->charges_abbreviator($list, $charges[0], $charges[1]); 
	                                                	$this->mugStamp_test1($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
														$offender = Mango::factory('offender', 
		                                                array(
		                                                    'scrape'        => $this->scrape,
		                                                    'state'         => $this->state,
		                                                    'county'        => $county,
		                                                    'firstname'     => $firstname,
		                                                    'lastname'      => $lastname,
		                                                    'booking_id'    => $booking_id,
		                                                    'booking_date'  => $booking_date,
		                                                    'scrape_time'   => time(),
		                                                    'image'         => $imgpath,
		                                                    'charges'       => $charges,                                      
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
													}
												}
												$fcharges = $fcharges;	
	                                            $chargeCount = count($fcharges);
	                                            # run through charge logic  
	                                            $mcharges   = array(); // reset the array
	                                            if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
	                                            {
	                                                $mcharges   = $this->charges_prioritizer($list, $fcharges);
	                                                if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in kbi scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
	                                                $mcharges   = array_merge($mcharges);   
	                                                $charge1    = $mcharges[0];
	                                                $charge2    = $mcharges[1];    
	                                                $charges    = $this->charges_abbreviator($list, $charge1, $charge2); 
	                                                $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
	                                            }
	                                            else if ( $chargeCount == 2 )
	                                            {
	                                                $fcharges   = array_merge($fcharges);
	                                                $charge1    = $fcharges[0];
	                                                $charge2    = $fcharges[1];   
	                                                $charges    = $this->charges_abbreviator($list, $charge1, $charge2);
	                                                $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);           
	                                            }
	                                            else 
	                                            {
	                                            	if(is_array($fcharges))
													{
	                                                	$fcharges   = array_merge($fcharges);
													}
	                                                $charge1    = $fcharges[0];    
	                                                $charges    = $this->charges_abbreviator($list, $charge1);       
	                                                $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0]);   
	                                            }
	                                            
	                                            // Abbreviate FULL charge list
	                                            $dbcharges = $this->charges_abbreviator_db($list, $dbcharges);
	                                            $dbcharges = array_unique($dbcharges);
	                                            # BOILERPLATE DATABASE INSERTS
	                                            $offender = Mango::factory('offender', 
	                                                array(
	                                                    'scrape'        => $this->scrape,
	                                                    'state'         => $this->state,
	                                                    'county'        => $county,
	                                                    'firstname'     => $firstname,
	                                                    'lastname'      => $lastname,
	                                                    'booking_id'    => $booking_id,
	                                                    'booking_date'  => $booking_date,
	                                                    'scrape_time'   => time(),
	                                                    'image'         => $imgpath,
	                                                    'charges'       => $dbcharges,                                      
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
	                                        } else { return 102; } // get failed 
										///this is where I was///    
										}
										else//add new charges to DB
										{
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
														$charge->abbr 	= $value;
	                                                    $charge->order 	= (int)0;
	                                                    $charge->county = $this->scrape;
	                                                    $charge->scrape = $this->scrape;
	                                                    $charge->new    = (int)0;
	                                                    $charge->update();
	                                                }   
	                                           	}
	                                     	}
	                                        return 104; 
	                                    }
									} else { return 101; } //no charge found
								} else { return 101; } //no mugshot found
							} else { return 101; } //no booking date found 
						} else { return 101; } //no charge found 
					} else { return 101; } // no lastname found
				} else { return 101; } // no firstname found
			} else { return 103; } //offender already in DB
		} else { return 101; } //no booking id
	}
}

