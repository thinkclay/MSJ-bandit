<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Denton
 *
 * @package Scrape
 * @author Winter King
 * @URL: http://www.dentoncounty.org/jaillookup/search.jsp
 */
class Model_Denton extends Model 
{
	private $county 	= 'denton';
	private $state		= 'texas';
	private $user_agent = "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.15) Gecko/20110303 Firefox/3.6.15";
    private $cookies 	= '/tmp/denton_cookies.txt';
	private $csv		= '/mugs/texas/denton/lists/denton.csv';
	
	public function __construct()
	{	
		set_time_limit(86400); //make it go forever	
		if ( file_exists($this->cookies) ) { unlink($this->cookies); } //delete cookie file if it exists      	
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
	* @$race - B(Black), A(Asian),  H(Hispanic), N(Non-Hispanic), W(White)
	* @$sex  - M(Male), F(Female)
	* @params $date - timestamp of begin date
	* @return $info - passes to the controller for reporting
	*/
    function scrape($fn = 'a', $ln = 'a', $race = 'B', $sex = 'M') 
    {
    	$scrape = new Model_Scrape;
    	# set report variables
    	$info['Scrape'] = 'Denton County';
    	$info['Total'] = 0;
		$info['Successful'] = 0;
		$info['New_Charges'] = array();
    	$scrape = new Model_Scrape;
		$index = $this->curl_index();
		exit;
		$search = $this->curl_search();
		$index = $this->curl_index($fn, $ln, $race, $sex);
		# use table extractor to build a list of detail links
		$scrape->source = $index; 
	    $scrape->anchor = 'Defendant';
	    $scrape->anchorWithin = true;
		$scrape->headerRow = false;
		$scrape->stripTags = false;
		$scrape->startRow = 2;
		//$scrape->maxCols  = 2;
	    $index_table  = $scrape->extractTable();
		$detail_links = array();
		if(!empty($value['Defendant']))
		{
			foreach ($index_table as $key => $value)
			{
				$check = preg_match('/ahref\=\"([^"]*)\"/', $value['Defendant'], $links);
				$link = preg_replace('/&amp;/', '&', $links[1]);
				$link = 'http://www.dentoncounty.org/jaillookup/' . $link;
				$detail_links[] = $link;
			}
			#TODO loop here instead of specifiying index 
			$pre_charges = array();
			foreach ($detail_links as $detail_link)
			{
				$details = $this->curl_details($detail_link);
				$extraction = $this->extraction($details);
				if (is_array($extraction))
				{
					foreach ($extraction as $value)
					{
						$pre_charges[] = $value;
					}
				}
				else { $pre_charges[] = $value; }
			}
			return $pre_charges;	
		} else { return false; }
		
		exit;
		//http://www.dentoncounty.org/jaillookup/defendant_detail.do?recno=C600EC95-019B-F317-5B48-1474F04A9413&bookinNumber=11020957&bookinDate=1301341320000&dob=1972-12-12&lastName=ADAMS&firstName=ANTHONY&sex=Male&race=Black
		
		if ($check)
		{
			# hmm no eventvalidation on this one for some reason ... interesting
			//preg_match('/id\=\"\_\_VIEWSTATE\"\svalue\=\"([^"]*)"/', $index,  $vs);
			//$vs = $vs[1];
			$scrape->source = $index; 
	        $scrape->anchor = 'ctl00_cphMain_tblResults';
		    $scrape->anchorWithin = true;
			$scrape->headerRow = true;
			$scrape->stripTags = false;
			//$scrape->startRow = 2;
			//$scrape->maxCols  = 2;
	        $index_table = $scrape->extractTable();
			$total = 0;
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
					if ($extraction == 'success') { $info['Successful'] += 1; }
					if (is_array($extraction)) // this means that the extraction failed because new charges
					{
						# loop through the new charges and add them to the $info['New_Charges'] array
						foreach ($extraction as $charge)
						{
							$info['New_Charges'][] = $charge;		
						}
					}
					$total++;
				}
				$info['New_Charges'] = array_unique($info['New_Charges']);
				$info['New_Charges'] = array_merge($info['New_Charges']);
				$info['Total'] = $total;
				return $info;	
			}
		} else { return false; } // row match not found
		
		//$this->print_r2($rows);
		//http://jail.lfucg.com/QueryProfile.aspx?oid=1
    	///wEPDwUJNDQzNDgyNzA1D2QWAmYPZBYCAgMPZBYIAgMPDxYCHhdFbmFibGVBamF4U2tpblJlbmRlcmluZ2gWAh4Fc3R5bGUFDWRpc3BsYXk6bm9uZTtkAgUPZBYCZg9kFgQCAQ8PFgIeBFRleHQFE21zajc3N0BodXNobWFpbC5jb21kZAIDDw8WAh8CBQ9NYXJjaCwgMjIsIDIwMTFkZAIHD2QWAgIBDxQrAAIUKwACDxYCHwBoZBAWB2YCAQICAgMCBAIFAgYWBxQrAAIPFgQfAgUESG9tZR4LTmF2aWdhdGVVcmwFAn4vZGQUKwACDxYEHwIFAXweC0lzU2VwYXJhdG9yZ2RkFCsAAg8WBB8CBQ5QdWJsaWMgcmVjb3Jkcx8DBRx+L1B1YmxpYy9QdWJsaWNfcmVjb3Jkcy5hc3B4ZBAWAmYCARYCFCsAAg8WBB8CBRJDdXJyZW50IHBvcHVsYXRpb24fAwU0fi9RdWVyeS5hc3B4P09RPWYxYzZkZWMzLTk1OWEtNDVkNy04NjE5LWY2ZDIxYTc5ZDFmYmRkFCsAAg8WBB8CBR9Cb29rZWQgd2l0aGluIHRoZSBsYXN0IDQ4IGhvdXJzHwMFNH4vUXVlcnkuYXNweD9PUT1mMWM2ZGVjMy05MzliLTQ1ZDctODYxOS1mNmQyMWE3OWQxZmJkZA8WAmZmFgEFdFRlbGVyaWsuV2ViLlVJLlJhZE1lbnVJdGVtLCBUZWxlcmlrLldlYi5VSSwgVmVyc2lvbj0yMDEwLjMuMTIxNS40MCwgQ3VsdHVyZT1uZXV0cmFsLCBQdWJsaWNLZXlUb2tlbj0xMjFmYWU3ODE2NWJhM2Q0FCsAAg8WBB8CBQF8HwRnZGQUKwACDxYEHwIFD0xhdyBlbmZvcmNlbWVudB8DBR1+L1B1YmxpYy9MYXdfZW5mb3JjZW1lbnQuYXNweGQQFgJmAgEWAhQrAAIPFgQfAgUTSW5tYXRlIEFsaWFzIFNlYXJjaB8DBTR+L1F1ZXJ5LmFzcHg/T1E9OEU2QzQwN0QtRERGMy00QjNDLTlCQjktQTAxQjQ0NUU5RjgyZGQUKwACDxYEHwIFDVBheSBNeSBKYWlsZXIfAwU0fi9RdWVyeS5hc3B4P09RPThFNkM0MDdELURERjMtNEIzQy05QkI5LUEwMUI0NDVFOUY4M2RkDxYCZmYWAQV0VGVsZXJpay5XZWIuVUkuUmFkTWVudUl0ZW0sIFRlbGVyaWsuV2ViLlVJLCBWZXJzaW9uPTIwMTAuMy4xMjE1LjQwLCBDdWx0dXJlPW5ldXRyYWwsIFB1YmxpY0tleVRva2VuPTEyMWZhZTc4MTY1YmEzZDQUKwACDxYEHwIFAXwfBGdkZBQrAAIPFgYfAgUHQWNjb3VudB4IUG9zdEJhY2toHwMFCX4vU2VjdXJlL2RkDxYHZmZmZmZmZhYBBXRUZWxlcmlrLldlYi5VSS5SYWRNZW51SXRlbSwgVGVsZXJpay5XZWIuVUksIFZlcnNpb249MjAxMC4zLjEyMTUuNDAsIEN1bHR1cmU9bmV1dHJhbCwgUHVibGljS2V5VG9rZW49MTIxZmFlNzgxNjViYTNkNGQWDmYPDxYEHwIFBEhvbWUfAwUCfi9kZAIBDw8WBB8CBQF8HwRnZGQCAg8PFgQfAgUOUHVibGljIHJlY29yZHMfAwUcfi9QdWJsaWMvUHVibGljX3JlY29yZHMuYXNweGQWBGYPDxYEHwIFEkN1cnJlbnQgcG9wdWxhdGlvbh8DBTR+L1F1ZXJ5LmFzcHg/T1E9ZjFjNmRlYzMtOTU5YS00NWQ3LTg2MTktZjZkMjFhNzlkMWZiZGQCAQ8PFgQfAgUfQm9va2VkIHdpdGhpbiB0aGUgbGFzdCA0OCBob3Vycx8DBTR+L1F1ZXJ5LmFzcHg/T1E9ZjFjNmRlYzMtOTM5Yi00NWQ3LTg2MTktZjZkMjFhNzlkMWZiZGQCAw8PFgQfAgUBfB8EZ2RkAgQPDxYEHwIFD0xhdyBlbmZvcmNlbWVudB8DBR1+L1B1YmxpYy9MYXdfZW5mb3JjZW1lbnQuYXNweGQWBGYPDxYEHwIFE0lubWF0ZSBBbGlhcyBTZWFyY2gfAwU0fi9RdWVyeS5hc3B4P09RPThFNkM0MDdELURERjMtNEIzQy05QkI5LUEwMUI0NDVFOUY4MmRkAgEPDxYEHwIFDVBheSBNeSBKYWlsZXIfAwU0fi9RdWVyeS5hc3B4P09RPThFNkM0MDdELURERjMtNEIzQy05QkI5LUEwMUI0NDVFOUY4M2RkAgUPDxYEHwIFAXwfBGdkZAIGDw8WBh8CBQdBY2NvdW50HwVoHwMFCX4vU2VjdXJlL2RkAgkPZBYIAgEPDxYCHwBoFgIfAQUNZGlzcGxheTpub25lO2QCBQ8PFgIfAgUSQ3VycmVudCBwb3B1bGF0aW9uZGQCDw9kFggCAQ8PFgQeCENzc0NsYXNzBQRsaWtlHgRfIVNCAgJkZAIEDw8WBB8GBQRsaWtlHwcCAmRkAgcPDxYEHwYFBGxpa2UfBwICZGQCCg8PFgQfBgUEbGlrZR8HAgJkZAIRDw8WAh4HVmlzaWJsZWdkZBgCBR5fX0NvbnRyb2xzUmVxdWlyZVBvc3RCYWNrS2V5X18WAwUXY3RsMDAkUmFkZm9ybWRlY29yYXRvcjEFF2N0bDAwJG5hdkdsb2JhbCRyYWRNZW51BR9jdGwwMCRjcGhNYWluJFJhZGZvcm1kZWNvcmF0b3IxBRBjdGwwMCRMb2dpbnZpZXcxDw9kAgFkcl/PiDfdraOgvoz+UCnOFN0+TgzwNWOKKDM8oI36GFw=
		/*
		# ok first thing is to login
		//  UN: msj777@hushmail.com
		//  PW: mugs
    	# set report variables
    	$info['Scrape'] = 'denton County';
    	$info['Total'] = 0;
		$info['Successful'] = 0;
		$info['New_Charges'] = array();
    	# set flag and start loop for paging
		$hash = $this->get_hash();
		$index = $this->curl_index($hash);
		$index = json_decode($index, true);
		# build booking_id array
		$booking_ids = array();
		foreach($index['data'] as $key => $value)
		{
			$booking_ids[] = $value['ArrestNo']; 
		}
		$total = 0;
		foreach ($booking_ids as $key => $booking_id)
		{
			$details 	= $this->curl_details($hash, $booking_id);
			sleep(3);
			$details = json_decode($details, true);
			//$this->print_r2($details);
			$extraction		= $this->extraction($hash, $details, $booking_id);
			if ($extraction == 'success') { $info['Successful'] += 1; }
			if (is_array($extraction)) // this means that the extraction failed because new charges
			{
				# loop through the new charges and add them to the $info['New_Charges'] array
				foreach ($extraction as $charge)
				{
					$info['New_Charges'][] = $charge;		
				}
			}
			$total++;
			//if ($count > 10) { break; }
		}
		$info['New_Charges'] = array_unique($info['New_Charges']);
		$info['New_Charges'] = array_merge($info['New_Charges']);
		$info['Total'] = $total;
		return $info;	
		 * */ 
	}
	
	/**
	* curl_search - get the search page 
	*
	*@url http://jail.lfucg.com/Query.aspx?OQ=f1c6dec3-959a-45d7-8619-f6d21a79d1fb
	*  
	* 
	* 
	* 
	*/
	function curl_search()
	{
		$url = 'http://www.dentoncounty.org/jaillookup/search.jsp';
		$ch = curl_init();   
     	curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:2.0) Gecko/20100101 Firefox/4.0');
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $search = curl_exec($ch);
        curl_close($ch);
		return $search;
		
	}
	
	
	/**
	* curl_index - get the index of a search results page based on criteria
	*
	*@url http://jail.lfucg.com/Query.aspx?OQ=f1c6dec3-959a-45d7-8619-f6d21a79d1fb
	*  
	* 
	* 
	* 
	*/

	/*
		$url = 'http://www.dentoncounty.org/jaillookup/search.jsp';
		$headers = array(
			//'Host: www.dentoncounty.org',
			'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:2.0) Gecko/20100101 Firefox/4.0',
			//'Accept-Language: en-us,en;q=0.5',
			//'Accept-Encoding: gzip, deflate',
			//'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7',
			//'Keep-Alive: 115',
			//'Connection: keep-alive',
			//'Cache-Control: max-age=0',
			);
		$ch = curl_init();   
     	curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		//curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $search = curl_exec($ch);
        curl_close($ch);
		return $search;
	*/	
	
	
	
	/**
	* curl_index - get the index of a search results page based on criteria
	*
	*@url http://jail.lfucg.com/Query.aspx?OQ=f1c6dec3-959a-45d7-8619-f6d21a79d1fb
	*  
	* 
	*/
	function curl_index($fn, $ln, $race, $sex)
	{
		$url = 'http://www.dentoncounty.org/jaillookup/search.do';
		$fields = 'lastName='.$ln.'&firstName='.$fn.'&dobMonth=&dobDay=&dobYear=&race='.$race.'&sex='.$sex.'&searchType=Search+By+Prisoner+Info&bookinNumber=&caseNumber=';
		$ch = curl_init();   
     	curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:2.0) Gecko/20100101 Firefox/4.0');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $index = curl_exec($ch);
        curl_close($ch);
		return $index;
	}
	
	/**
	* curl_index - get the index of a search results page based on criteria
	*
	*@url http://jail.lfucg.com/Query.aspx?OQ=f1c6dec3-959a-45d7-8619-f6d21a79d1fb
	*http://www.dentoncounty.org/jaillookup/defendant_detail.do?recno=C600EC95-019B-F317-5B48-1474F04A9413&bookinNumber=11020957&bookinDate=1301341320000&dob=1972-12-12&lastName=ADAMS&firstName=ANTHONY&sex=Male&race=Black
	*http://www.dentoncounty.org/jaillookup/defendant_detail.do?recno=C600EC95-019B-F317-5B48-1474F04A9413&bookinNumber=11020957&bookinDate=1301341320000&dob=1972-12-12&lastName=ADAMS&firstName=ANTHONY&sex=Male&race=Black 
	*http://www.dentoncounty.org/jaillookup/defendant_detail.do?recno=7401DC97-2B00-8113-EFA9-D4C7BA49BE08&amp;bookinNumber=11018797&amp;bookinDate=1300669440000&amp;dob=1986-05-22&amp;lastName=ANDERSON&amp;firstName=AARON&amp;sex=Male&amp;race=Black
	 * */
	function curl_details($url)
	{
		$ch = curl_init();   
     	curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:2.0) Gecko/20100101 Firefox/4.0');
		
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $details = curl_exec($ch);
        curl_close($ch);
		return $details;
	}
	
	
	//https://jailtracker.com/JTClientWeb/(S(cnwrnhb4uf1v2h55rgy15s20))/JailTracker/GetImage/
	
	/**
	* extraction - validates and extracts all data
	*
	* 
	* @params $details  - offenders details page
	* @return $ncharges - numerical array of new charges found
	* @return false  	- on failed extraction
	* @return true 		- on successful extraction
	* 
	*/
	function extraction($details)
	{
		$scrape = new Model_Scrape;
		/*
		 * Charge</STRONG></div>
                                                            </TD>

                                                            <TD align="right" bgcolor="#CCCCCC">
                                                                <DIV align="left">
                                                                    PROBATION VIOLATION - MAN DEL CS PG 1 >=1G<4G
                                                                </DIV>
		 * 
		 */
		
		///Charge<\/STRONG><\/div>.*<\/TD>.*<TD.*>.*<DIV.*>(.*)<\/DIV>/s
		//$check = preg_match_all('/Charge\<\/STRONG\>\<\/div\>[^<]*\<\/TD\>[^<]*\<[^>]*\>[^<]*\<[^>]*\>([^<\/DIV]*)\<\/DIV/', $details, $charges);
		
		$check = preg_match_all("/Charge<\/STRONG><\/div>.*<\/TD>.*<TD.*>.*<DIV.*>(.*)<\/DIV>/sU", $details, $matches);
		if ($check)
		{
			$charges = array();
			foreach ($matches[1] as $key => $value)
			{
				$charges[] = trim(preg_replace('/\s\s+/', ' ', $value));
				
			}
			return $charges;
		} else { return false; } // no charges found
		$this->print_r2($charges);
		
		exit;
				
		$booking_id = 'denton_' . $booking_id;
		$scrape 	= new Model_Scrape;
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
							$race = $scrape->race_mapper($race);
							if ($race)
							{
						 		$extra_fields['race'] = $race;
							}
						}
						
						$gender = null;
						$check = preg_match('/Gender\<\/li\>\<li\>([^<]*)\</', $details, $gender);
						if ($check) { $gender = trim($gender[1]); }
						if ($gender == 'M') {$gender = 'MALE';} else if ($gender == 'F') { $gender = 'FEMALE'; }
						
						$height = null;
						$check = preg_match('/Height\<\/li\>\<li\>([^<]*)\</', $details, $height);
						if ($check) { $height = trim($height[1]); }
						
						$weight = null;
						$check = preg_match('/Weight\<\/li\>\<li\>([^<]*)\</', $details, $weight);
						if ($check) { $weight = trim($weight[1]); }
						
						# get charges
						$scrape->source = $details; 
				        $scrape->anchor = 'Current Offenses';
					    $scrape->anchorWithin = true;
						$scrape->headerRow = false;
						$scrape->stripTags = false;
						$scrape->cleanHTML = true;
						$scrape->startRow = 3;
				        $charges_table = $scrape->extractTable();
						//$this->print_r2($charges_table);
						$charges = array();
						foreach($charges_table as $key => $value)
						{
							$charges[] = trim($value[1]);
						}
						$ncharges = array();
						$ncharges = $scrape->charges_check2($charges, $this->csv);
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
							//http://jail.lfucg.com/OffenderImage.ashx?img=0&u=6fb3b833-6154-4c54-847e-76a233bfcbf3
							$image_link = array();
							$check = preg_match('/\/OffenderImage\.ashx\?img\=([^"]*)\"/', $details, $image_link);
							if ($check)
							{
								$image_link = 'http://jail.lfucg.com/OffenderImage.ashx?img='.$image_link[1]; 
								# set image name
								$imagename = date('(m-d-Y)', $booking_date) . '_' . $lastname . '_' . $firstname . '_' . $booking_id;
								# set image path
						        $imagepath = '/mugs/kentucky/denton/'.date('Y', $booking_date).'/week_'.$scrape->find_week($booking_date).'/';
						        # create mugpath
						        $mugpath = $scrape->set_mugpath($imagepath);
								//@todo find a way to identify extension before setting ->imageSource
								$scrape->imageSource    = $image_link;
						        $scrape->save_to        = $imagepath.$imagename;
						        $scrape->set_extension  = true;
								$scrape->cookie			= $this->cookies;
						        $scrape->download('curl');
								if (file_exists($scrape->save_to . '.jpg')) //validate the image was downloaded
								{
									#@TODO make validation for a placeholder here probably
									# ok I got the image now I need to do my conversions
							        # convert image to png.
							        $scrape->convertImage($mugpath.$imagename.'.jpg');
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
							            $mcharges 	= $scrape->charges_prioritizer2($this->csv, $fcharges);
										if ($mcharges == false) { mail('winterpk@bychosen.com', 'Your prioritizer failed in denton scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
							            $mcharges 	= array_merge($mcharges);   
							            $charge1 	= $mcharges[0];
							            $charge2 	= $mcharges[1];    
							            $charges 	= $scrape->charges_abbreviator($this->csv, $charge1, $charge2); 
							            $scrape->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);
							        }
							        else if ( $chargeCount == 2 )
							        {
							            $fcharges 	= array_merge($fcharges);
							            $charge1 	= $fcharges[0];
							            $charge2 	= $fcharges[1];   
							            $charges 	= $scrape->charges_abbreviator($this->csv, $charge1, $charge2);
							            $scrape->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0], $charges[1]);           
							        }
							        else 
							        {
							            $fcharges 	= array_merge($fcharges);
							            $charge1 	= $fcharges[0];    
							            $charges 	= $scrape->charges_abbreviator($this->csv, $charge1);       
							            $scrape->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0]);   
							        }
									
									// Abbreviate FULL charge list
									$dbcharges = $scrape->charges_abbreviator_db($this->csv, $dbcharges);
									$dbcharges = array_unique($dbcharges);
									# BOILERPLATE DATABASE INSERTS
									$offender = Mango::factory('offender', 
						                array(
						                	'scrape'		=> $this->county,
						                	'state'			=> strtolower($this->state),
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
									$county = Mango::factory('county', array('name' => $this->county, 'state' => $this->state))->load();
									if (!$county->loaded())
									{
										$county = Mango::factory('county', array('name' => $this->county, 'state' => $this->state))->create();
									}
									$county->booking_ids[] = $booking_id;
									$county->update();
									# END DATABASE INSERTS
									return 'success';
										### END EXTRACTION ###
										
								} else { return false; } // get failed				
							} else { return false; } // image link failed 
						} else {
							# add new charges to the report
				            $ncharges = preg_replace('/\s/', '', $ncharges);
				            $week_report = Mango::factory('report', array(
				                'scrape' => $this->county,
				                'year'   => date('Y'),
				                'week'   => $scrape->find_week(time())
				            ))->load();
							if (!$week_report->loaded())
							{
								$week_report = Mango::factory('report', array(
					                'scrape' => $this->county,
					                'year'   => date('Y'),
					                'week'   => $scrape->find_week(time())
				            	))->create();
							}
				            $db_new_charges = $week_report->new_charges->as_array();
				            if (is_array($db_new_charges))
				            {
				                $merged = array_merge($db_new_charges, $ncharges);
				                $merged = array_unique($merged);
				                $merged = array_merge($merged);
				                sort($merged);
				                $week_report->new_charges = $merged;    
				            }
				            else 
				            {
				                sort($ncharges); 
				                $week_report->new_charges = $ncharges;   
				            }
				            $week_report->update(); 
				            $info['New_Charges'] = array_unique($ncharges); 
				            return $ncharges; 
						} // ncharges validation	
					} else { return false; } // booking_date validation failed
				} else { return false; } // firstname validation failed
			} else { return false; } // lastname validation	failed
		} else { return false; } // database validation failed
	} // end extraction
} // class end