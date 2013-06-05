<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Kansasc
 *
 * @package Scrape
 * @author Winter King
 * @url 
 */
class Model_Kansasc extends Model_Scrape 
{
	private $scrape 	= 'kansasc';
	private $state		= 'kansas';
    private $cookies 	= '/tmp/kansasc_cookies.txt';
	
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
	* @return $info - passes to the controller for reporting
	*/
    function scrape($fn = 'a', $ln = 'a') 
    {
		$home  = $this->curl_home();
		preg_match('/id\=\"\_\_VIEWSTATE\"\svalue\=\"([^"]*)"/', $home,  $vs);
		preg_match('/id\=\"\_\_EVENTVALIDATION\"\svalue\=\"([^"]*)"/', $home,  $ev);
		$vs    = $vs[1];
		$ev    = $ev[1];
		$login = $this->curl_login($vs, $ev);
		$index = $this->curl_past($vs, $ev);
		# find the row number
		$rows  = array();
		$check = preg_match('/lblResultCount\"\>([0-9]+)\s[a-zA-Z]/', $index, $rows);
		if ($check)
		{
			# hmm no eventvalidation on this one for some reason ... interesting
			$this->source = $index; 
	        $this->anchor = 'ctl00_cphMain_tblResults';
		    $this->anchorWithin = true;
			$this->headerRow = true;
			$this->stripTags = false;
	        $index_table = $this->extractTable();
			$booking_ids = array();
	        foreach($index_table as $profile)
			{
				$booking_ids[] = $profile['inmateid'];
			}
			if (!empty($booking_ids))
			{
				foreach($booking_ids as $key => $booking_id)
				{
					$details 	= $this->curl_details($key);
					$extraction = $this->extraction($details, $booking_id);
                    if ($extraction == 100) { $this->report->successful = ($this->report->successful + 1); $this->report->update(); }
                    if ($extraction == 101) { $this->report->other = ($this->report->other + 1); $this->report->update(); }
                    if ($extraction == 102) { $this->report->bad_images = ($this->report->bad_images + 1); $this->report->update(); }
                    if ($extraction == 103) { $this->report->exists = ($this->report->exists + 1); $this->report->update(); }
                    if ($extraction == 104) { $this->report->new_charges = ($this->report->new_charges + 1); $this->report->update(); }
                    $this->report->total = ($this->report->total + 1); $this->report->update();
				}
				$this->report->finished = 1;
		        $this->report->stop_time = time();
		        $this->report->time_taken = ($this->report->stop_time - $this->report->start_time);
		        $this->report->update();
		        return true;	
			}
		} else { return false; } // row match not found
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
	function curl_search($vs, $ev)
	{
		$url = 'http://jail.lfucg.com/Query.aspx?OQ=f1c6dec3-959a-45d7-8619-f6d21a79d1fb';
		$ch = curl_init();   
     	curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $search = curl_exec($ch);
        curl_close($ch);
		return $search;
	}
	
	
	/**
	* curl_login - logs in to their website
	*
	*@url http://jail.lfucg.com/Query.aspx?OQ=f1c6dec3-959a-45d7-8619-f6d21a79d1fb
	*@notes Using login info:  msj777@hushmail.com / mugs 
	* 		sidenote, this is a HIGHLY insecure way to handle a username/password login
	*  
	*/
	function curl_login($vs, $ev)
	{
		$fields = '__LASTFOCUS=&ctl00_Radscriptmanager1_TSM=%3B%3BSystem.Web.Extensions%2C+Version%3D4.0.0.0%2C+Culture%3Dneutral%2C+PublicKeyToken%3D31bf3856ad364e35%3Aen-US%3A1f68db6e-ab92-4c56-8744-13e09bf43565%3Aea597d4b%3Ab25378d2%3BTelerik.Web.UI%2C+Version%3D2010.3.1215.40%2C+Culture%3Dneutral%2C+PublicKeyToken%3D121fae78165ba3d4%3Aen-US%3Aaa801ad7-53c4-4f5c-9fd3-11d99e4b92f4%3A16e4e7cd%3A86526ba7%3Ae330518b%3Af7645509%3A24ee1bba%3A1e771326%3Ac8618e41%3A874f8ea2%3A19620875%3Af46195d3%3A39040b5c%3Af85f9819%3A11e117d7&__EVENTTARGET=&__EVENTARGUMENT=&__VIEWSTATE='.urlencode($vs).'&__EVENTVALIDATION='.urlencode($ev).'&ctl00_Radformdecorator1_ClientState=&ctl00_navGlobal_radMenu_ClientState=&ctl00_cphMain_Radformdecorator1_ClientState=&ctl00_cphMain_Radtooltipmanager1_ClientState=&ctl00%24cphMain%24txtEmailAddress=msj777%40hushmail.com&ctl00%24cphMain%24txtNewPassword=mugs&ctl00%24cphMain%24txtConfirmNewPassword=&ctl00%24cphMain%24txtValidateCaptcha=&ctl00_cphMain_Radcaptcha1_ClientState=&ctl00%24cphMain%24txtLogin=msj777%40hushmail.com&ctl00%24cphMain%24txtPassword=mugs&ctl00%24cphMain%24btnLogin=Log+In';
		$url = 'http://jail.lfucg.com/Secure/Account/Login.aspx?ReturnUrl=%2f';
		$ch = curl_init();   
     	curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
		//$fields = '__LASTFOCUS=&ctl00_Radscriptmanager1_TSM=%3B%3BSystem.Web.Extensions%2C+Version%3D4.0.0.0%2C+Culture%3Dneutral%2C+PublicKeyToken%3D31bf3856ad364e35%3Aen-US%3A8f393b2b-3315-402f-b504-cd6d2db001f6%3Aea597d4b%3Ab25378d2%3BTelerik.Web.UI%2C+Version%3D2010.3.1215.40%2C+Culture%3Dneutral%2C+PublicKeyToken%3D121fae78165ba3d4%3Aen-US%3Aaa801ad7-53c4-4f5c-9fd3-11d99e4b92f4%3A16e4e7cd%3A86526ba7%3Ae330518b%3Af7645509%3A24ee1bba%3A1e771326%3Ac8618e41&__EVENTTARGET=&__EVENTARGUMENT=&__VIEWSTATE='.urlencode($vs).'&__EVENTVALIDATION='.urlencode($ev).'&ctl00_Radformdecorator1_ClientState=&ctl00_navGlobal_radMenu_ClientState=&ctl00_cphMain_Radformdecorator1_ClientState=&ctl00%24cphMain%24DynamicControl0='.$ln.'&ctl00%24cphMain%24DynamicControl1='.$fn.'&ctl00%24cphMain%24DynamicControl2=&ctl00%24cphMain%24DynamicControl3=&ctl00%24cphMain%24btnSearch=Search+database&ctl00%24cphMain%24hdnDownloadable=0';
		//$fields = '__LASTFOCUS=&ctl00_Radscriptmanager1_TSM=%3B%3BSystem.Web.Extensions%2C+Version%3D4.0.0.0%2C+Culture%3Dneutral%2C+PublicKeyToken%3D31bf3856ad364e35%3Aen-US%3A8f393b2b-3315-402f-b504-cd6d2db001f6%3Aea597d4b%3Ab25378d2%3BTelerik.Web.UI%2C+Version%3D2010.3.1215.40%2C+Culture%3Dneutral%2C+PublicKeyToken%3D121fae78165ba3d4%3Aen-US%3Aaa801ad7-53c4-4f5c-9fd3-11d99e4b92f4%3A16e4e7cd%3A86526ba7%3Ae330518b%3Af7645509%3A24ee1bba%3A1e771326%3Ac8618e41&__EVENTTARGET=&__EVENTARGUMENT=&__VIEWSTATE=%2FwEPDwUJNDQzNDgyNzA1D2QWAmYPZBYCAgMPZBYIAgMPDxYCHhdFbmFibGVBamF4U2tpblJlbmRlcmluZ2gWAh4Fc3R5bGUFDWRpc3BsYXk6bm9uZTtkAgUPZBYCZg9kFgQCAQ8PFgIeBFRleHQFE21zajc3N0BodXNobWFpbC5jb21kZAIDDw8WAh8CBQ9NYXJjaCwgMjIsIDIwMTFkZAIHD2QWAgIBDxQrAAIUKwACDxYCHwBoZBAWB2YCAQICAgMCBAIFAgYWBxQrAAIPFgQfAgUESG9tZR4LTmF2aWdhdGVVcmwFAn4vZGQUKwACDxYEHwIFAXweC0lzU2VwYXJhdG9yZ2RkFCsAAg8WBB8CBQ5QdWJsaWMgcmVjb3Jkcx8DBRx%2BL1B1YmxpYy9QdWJsaWNfcmVjb3Jkcy5hc3B4ZBAWAmYCARYCFCsAAg8WBB8CBRJDdXJyZW50IHBvcHVsYXRpb24fAwU0fi9RdWVyeS5hc3B4P09RPWYxYzZkZWMzLTk1OWEtNDVkNy04NjE5LWY2ZDIxYTc5ZDFmYmRkFCsAAg8WBB8CBR9Cb29rZWQgd2l0aGluIHRoZSBsYXN0IDQ4IGhvdXJzHwMFNH4vUXVlcnkuYXNweD9PUT1mMWM2ZGVjMy05MzliLTQ1ZDctODYxOS1mNmQyMWE3OWQxZmJkZA8WAmZmFgEFdFRlbGVyaWsuV2ViLlVJLlJhZE1lbnVJdGVtLCBUZWxlcmlrLldlYi5VSSwgVmVyc2lvbj0yMDEwLjMuMTIxNS40MCwgQ3VsdHVyZT1uZXV0cmFsLCBQdWJsaWNLZXlUb2tlbj0xMjFmYWU3ODE2NWJhM2Q0FCsAAg8WBB8CBQF8HwRnZGQUKwACDxYEHwIFD0xhdyBlbmZvcmNlbWVudB8DBR1%2BL1B1YmxpYy9MYXdfZW5mb3JjZW1lbnQuYXNweGQQFgJmAgEWAhQrAAIPFgQfAgUTSW5tYXRlIEFsaWFzIFNlYXJjaB8DBTR%2BL1F1ZXJ5LmFzcHg%2FT1E9OEU2QzQwN0QtRERGMy00QjNDLTlCQjktQTAxQjQ0NUU5RjgyZGQUKwACDxYEHwIFDVBheSBNeSBKYWlsZXIfAwU0fi9RdWVyeS5hc3B4P09RPThFNkM0MDdELURERjMtNEIzQy05QkI5LUEwMUI0NDVFOUY4M2RkDxYCZmYWAQV0VGVsZXJpay5XZWIuVUkuUmFkTWVudUl0ZW0sIFRlbGVyaWsuV2ViLlVJLCBWZXJzaW9uPTIwMTAuMy4xMjE1LjQwLCBDdWx0dXJlPW5ldXRyYWwsIFB1YmxpY0tleVRva2VuPTEyMWZhZTc4MTY1YmEzZDQUKwACDxYEHwIFAXwfBGdkZBQrAAIPFgYfAgUHQWNjb3VudB4IUG9zdEJhY2toHwMFCX4vU2VjdXJlL2RkDxYHZmZmZmZmZhYBBXRUZWxlcmlrLldlYi5VSS5SYWRNZW51SXRlbSwgVGVsZXJpay5XZWIuVUksIFZlcnNpb249MjAxMC4zLjEyMTUuNDAsIEN1bHR1cmU9bmV1dHJhbCwgUHVibGljS2V5VG9rZW49MTIxZmFlNzgxNjViYTNkNGQWDmYPDxYEHwIFBEhvbWUfAwUCfi9kZAIBDw8WBB8CBQF8HwRnZGQCAg8PFgQfAgUOUHVibGljIHJlY29yZHMfAwUcfi9QdWJsaWMvUHVibGljX3JlY29yZHMuYXNweGQWBGYPDxYEHwIFEkN1cnJlbnQgcG9wdWxhdGlvbh8DBTR%2BL1F1ZXJ5LmFzcHg%2FT1E9ZjFjNmRlYzMtOTU5YS00NWQ3LTg2MTktZjZkMjFhNzlkMWZiZGQCAQ8PFgQfAgUfQm9va2VkIHdpdGhpbiB0aGUgbGFzdCA0OCBob3Vycx8DBTR%2BL1F1ZXJ5LmFzcHg%2FT1E9ZjFjNmRlYzMtOTM5Yi00NWQ3LTg2MTktZjZkMjFhNzlkMWZiZGQCAw8PFgQfAgUBfB8EZ2RkAgQPDxYEHwIFD0xhdyBlbmZvcmNlbWVudB8DBR1%2BL1B1YmxpYy9MYXdfZW5mb3JjZW1lbnQuYXNweGQWBGYPDxYEHwIFE0lubWF0ZSBBbGlhcyBTZWFyY2gfAwU0fi9RdWVyeS5hc3B4P09RPThFNkM0MDdELURERjMtNEIzQy05QkI5LUEwMUI0NDVFOUY4MmRkAgEPDxYEHwIFDVBheSBNeSBKYWlsZXIfAwU0fi9RdWVyeS5hc3B4P09RPThFNkM0MDdELURERjMtNEIzQy05QkI5LUEwMUI0NDVFOUY4M2RkAgUPDxYEHwIFAXwfBGdkZAIGDw8WBh8CBQdBY2NvdW50HwVoHwMFCX4vU2VjdXJlL2RkAgkPZBYIAgEPDxYCHwBoFgIfAQUNZGlzcGxheTpub25lO2QCBQ8PFgIfAgUSQ3VycmVudCBwb3B1bGF0aW9uZGQCDw9kFggCAQ8PFgQeCENzc0NsYXNzBQRsaWtlHgRfIVNCAgJkZAIEDw8WBB8GBQRsaWtlHwcCAmRkAgcPDxYEHwYFBGxpa2UfBwICZGQCCg8PFgQfBgUEbGlrZR8HAgJkZAIRDw8WAh4HVmlzaWJsZWdkZBgCBR5fX0NvbnRyb2xzUmVxdWlyZVBvc3RCYWNrS2V5X18WAwUXY3RsMDAkUmFkZm9ybWRlY29yYXRvcjEFF2N0bDAwJG5hdkdsb2JhbCRyYWRNZW51BR9jdGwwMCRjcGhNYWluJFJhZGZvcm1kZWNvcmF0b3IxBRBjdGwwMCRMb2dpbnZpZXcxDw9kAgFkcl%2FPiDfdraOgvoz%2BUCnOFN0%2BTgzwNWOKKDM8oI36GFw%3D&__EVENTVALIDATION=%2FwEWBwKorsW4BAKOwZf9CwKPwZf9CwKQwZf9CwKRwZf9CwKL2%2BqrDgKor%2BSICe8%2F7du0VkXb39RwXPAykvwwclhkp1mGz2L19ujEipGe&ctl00_Radformdecorator1_ClientState=&ctl00_navGlobal_radMenu_ClientState=&ctl00_cphMain_Radformdecorator1_ClientState=&ctl00%24cphMain%24DynamicControl0=a&ctl00%24cphMain%24DynamicControl1=a&ctl00%24cphMain%24DynamicControl2=&ctl00%24cphMain%24DynamicControl3=&ctl00%24cphMain%24btnSearch=Search+database&ctl00%24cphMain%24hdnDownloadable=0';
		///wEWBwKorsW4BAKOwZf9CwKPwZf9CwKQwZf9CwKRwZf9CwKL2+qrDgKor+SICe8/7du0VkXb39RwXPAykvwwclhkp1mGz2L19ujEipGe
		///wEWBwKorsW4BAKOwZf9CwKPwZf9CwKQwZf9CwKRwZf9CwKL2+qrDgKor+SICe8/7du0VkXb39RwXPAykvwwclhkp1mGz2L19ujEipGe
		$ch = curl_init();   
     	curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		//curl_setopt($ch, CURLOPT_POST, true);
		//curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
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
	function curl_details($row)
	{
		$url = 'http://jail.lfucg.com/QueryProfile.aspx?oid=' . $row;		
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
	* @TODO get the correct county name for the offender
	* 
	* @params $details  - offenders details page
	* @return $ncharges - numerical array of new charges found
	* @return false  	- on failed extraction
	* @return true 		- on successful extraction
	* 
	*/
	function extraction($details, $booking_id)
	{
		$booking_id = 'kansasc_' . $booking_id;
		# database validation 
		$offender = Mango::factory('offender', array(
			'booking_id' => $booking_id
		))->load();	
		# validate against the database
		if (empty($offender->booking_id)) 
		{
			# extract profile details
			# required fields
			$check = preg_match('/Last\sName\<\/li\>\<li\>([^<]*)\</', $details, $lastname);
			if ($check)
			{
				$lastname = trim($lastname[1]);
				$check = preg_match('/First\sName\<\/li\>\<li\>([^<]*)\</', $details, $firstname);
				if ($check)
				{
					$firstname = trim($firstname[1]);
					$check = preg_match('/Booking\sDate\<\/li\>\<li\>([^<]*)\</', $details, $booking_date);
					if ($check)
					{
						$booking_date = strtotime($booking_date[1]);
						# extra fields
						$extra_fields = array();
						$middlename = null;
						$check = preg_match('/Middle\<\/li\>\<li\>([^<]*)\</', $details, $middlename);
						if ($check) { $middlename = trim($middlename[1]); }
						if (isset($middlename)) 
						{
							$extra_fields['middlename'] = $middlename; 
						}
						
						$dob = null;
						$check = preg_match('/Birth\sDate\<\/li\>\<li\>([^<]*)\</', $details, $dob);
						if ($check) { $dob = strtotime(trim($dob[1])); }
						if (isset($dob)) 
						{
							$extra_fields['dob'] = $dob; 
							$extra_fields['age'] = floor(($booking_date - $dob) / 31556926);
						}
						
						$race = null;
						$check = preg_match('/Race\<\/li\>\<li\>([^<]*)\</', $details, $race);
						if ($check) { $race = trim($race[1]); }
						if (isset($race)) 
						{
							# run it though the race mapper
							$race = $this->race_mapper($race);
							if ($race)
							{
						 		$extra_fields['race'] = $race;
							}
						}
						
						$gender = null;
						$check = preg_match('/Gender\<\/li\>\<li\>([^<]*)\</', $details, $gender);
						if ($check) { $gender = trim($gender[1]); }
						if ($gender == 'M') {$gender = 'MALE';} else if ($gender == 'F') { $gender = 'FEMALE'; }
						if (isset($gender)) 
						{
							$extra_fields['gender'] = $gender; 
						}
						
						$height = null;
						$check = preg_match('/Height\<\/li\>\<li\>([^<]*)\</', $details, $height);
						if ($check) { $height = trim($height[1]); }
						if (isset($height)) 
						{
							$extra_fields['height'] = $height; 
						}
						
						$weight = null;
						$check = preg_match('/Weight\<\/li\>\<li\>([^<]*)\</', $details, $weight);
						if ($check) { $weight = trim($weight[1]); }
						if (isset($weight)) 
						{
							$extra_fields['weight'] = $weight; 
						}
						
						# get charges
						$this->source = $details; 
				        $this->anchor = 'Current Offenses';
					    $this->anchorWithin = true;
						$this->headerRow = false;
						$this->stripTags = false;
						$this->cleanHTML = true;
						$this->startRow = 3;
				        $charges_table = $this->extractTable();
						
						$charges = array();
						
						foreach($charges_table as $key => $value)
						{
							$charges[] = trim($value[1]);
						}
						if (!empty($charges))
						{
							
							###
							# this creates a charges object for all charges that are not new for this county
							$charges_object = Mango::factory('charge', array('county' => $this->scrape, 'new' => 0))->load(false)->as_array(false);
							# I can loop through and pull out individual arrays as needed:
							$list = array();
							foreach($charges_object as $row)
							{
								$list[$row['charge']] = $row['abbr'];
							}
							# this gives me a list array with key == fullname, value == abbreviated
							$ncharges = array();
							# Run my full_charges_array through the charges check
							$ncharges = $this->charges_check($charges, $list);
							###
							
							# validate 
							if (empty($ncharges)) // skip the offender if ANY new charges were found
							{
								$fcharges 	= array();
								foreach($charges as $key => $value)
								{
									$fcharges[] = trim(strtoupper($value));	
								}
								# make it unique and reset keys
								$fcharges = array_unique($fcharges);
								$fcharges = array_merge($fcharges);
								$dbcharges = $fcharges;
								# begin image extraction
								
								$image_link = '';
								### CRITICAL BUG ### - I believe they will periodiacally change this when they suspect a scrape
								//ORIGINALLY: http://jail.lfucg.com/OffenderImage.ashx?img=0&u=6fb3b833-6154-4c54-847e-76a233bfcbf3
								//NEW: http://jail.lfucg.com/ImageOffender.ashx?i=0&u=16d4c767-324a-41d8-80e9-0b36920d59c0
								$check = preg_match('/\/ImageOffender\.ashx\?i\=([^"]*)\"/', $details, $image_link);
								if ($check)
								{
									
									$image_link = 'http://jail.lfucg.com/ImageOffender.ashx?img='.$image_link[1]; 
									# set image name
									$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
									# set image path
							        $imagepath = '/mugs/kansas/kansasc/'.date('Y', $booking_date).'/week_'.$this->find_week($booking_date).'/';
							        # create mugpath
							        $mugpath = $this->set_mugpath($imagepath);
									//@todo find a way to identify extension before setting ->imageSource
									$this->imageSource    = $image_link;
							        $this->save_to        = $imagepath.$imagename;
							        $this->set_extension  = true;
									$this->cookie			= $this->cookies;
							        $this->download('curl');
									if (file_exists($this->save_to . '.jpg')) //validate the image was downloaded
									{
										#@TODO make validation for a placeholder here probably
										# ok I got the image now I need to do my conversions
								        # convert image to png.
								        $this->convertImage($mugpath.$imagename.'.jpg');
								        $imgpath = $mugpath.$imagename.'.png';
										$img = Image::factory($imgpath);
					                	$img->crop(225, 280)->save();
								        $imgpath = $mugpath.$imagename.'.png';
										# now run through charge logic
										$chargeCount = count($fcharges);
										# run through charge logic	
										$mcharges 	= array(); // reset the array
								        if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
								        {
								            $mcharges 	= $this->charges_prioritizer($list, $fcharges);
											if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in kansasc scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
								            $mcharges 	= array_merge($mcharges);   
								            $charge1 	= $mcharges[0];
								            $charge2 	= $mcharges[1];    
								            $charges 	= $this->charges_abbreviator($list, $charge1, $charge2); 
								            $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
								        }
								        else if ( $chargeCount == 2 )
								        {
								            $fcharges 	= array_merge($fcharges);
								            $charge1 	= $fcharges[0];
								            $charge2 	= $fcharges[1];   
								            $charges 	= $this->charges_abbreviator($list, $charge1, $charge2);
								            $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);           
								        }
								        else 
								        {
								            $fcharges 	= array_merge($fcharges);
								            $charge1 	= $fcharges[0];    
								            $charges 	= $this->charges_abbreviator($list, $charge1);       
								            $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0]);   
								        }
										
										// Abbreviate FULL charge list
										$dbcharges = $this->charges_abbreviator_db($list, $dbcharges);
										$dbcharges = array_unique($dbcharges);
										# BOILERPLATE DATABASE INSERTS
										$offender = Mango::factory('offender', 
							                array(
							                	'scrape'		=> $this->scrape,
							                	'state'			=> strtolower($this->state),
							                	'county'		=> $county,
							                    'firstname'     => $firstname,
							                    'lastname'      => $lastname,
							                    'booking_id'    => $booking_id,
							                    'booking_date'  => $booking_date,
							                    'scrape_time'	=> time(),
							                    'image'         => $imgpath,
							                    'charges'		=> $dbcharges,                                      
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
								} else { return 102; } // image link failed 
							} else {
								# add new charges to the charges collection
								foreach ($ncharges as $key => $value)
								{
									$value = preg_replace('/\s/', '', $value);
									#check if the new charge already exists FOR THIS COUNTY
									$check_charge = Mango::factory('charge', array('county' => $this->scrape, 'charge' => $value, 'new' => 1))->load();
									if (!$check_charge->loaded())
									{
										if (!empty($value))
										{
											$charge = Mango::factory('charge')->create();	
											$charge->charge = $value;
											$charge->order = (int)0;
											$charge->county = $this->scrape;
											$charge->scrape = $this->scrape;
											$charge->new 	= (int)1;
											$charge->update();
										}	
									}
								}
					            return 104;
							} // ncharges validation	
						} else { return 101; } // no charges found at all
					} else { return 101; } // booking_date validation failed
				} else { return 101; } // firstname validation failed
			} else { return 101; } // lastname validation	failed
		} else { return 103; } // database validation failed
	} // end extraction
} // class end