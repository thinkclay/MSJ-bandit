<?php defined('SYSPATH') or die('No direct script access.');
 
/**
 * Model_Summit
 * @TODO 	Something is wrong with the booking date
 * @notes   If this scrape breaks its likely that they changed the facility number in the search curl fields near line 119
 * @package Scrape
 * @url		http://scsojms.summitoh.net/matrixjms/publicsearch/masterfilenamesearch.aspx
 * @author  Marketermatt
 */
class Model_State_Ohio_Summit extends Model_Scrape
{
    private $county = 'summit';
	private $scrape = 'summit';
	private $state 	= 'ohio';
    private $cookies = '/tmp/summit_cookies.txt';
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
    
	function isValidTimeStamp($timestamp)
	{
	    return ((string) (int) $timestamp === $timestamp) 
	        && ($timestamp <= PHP_INT_MAX)
	        && ($timestamp >= ~PHP_INT_MAX);
	}
	
	function scrape($ln = 'b', $fn = 'b') 
    {
        $scrape = new Model_Scrape();
		$info = array(); 
		$info['Scrape'] = 'summit';
        $info['Failed_New_Charges'] = 0;
        $info['Exists'] = 0;
        $info['Bad_Images'] = 0;
        $info['Other'] = 0;
		$charge_list = array();
		$vs = '';
		$ev = '';
        # initial curl request to:
        # http://scsojms.summitoh.net/matrixjms/publicsearch/masterfilenamesearch.aspx     
        # set user agent
       	$user_agent = "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6";
        $url = 'https://scsojms.summitoh.net/MatrixJMS/PublicSearch/MasterFileNameSearch.aspx'; 
        $ch = curl_init();   
        curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent); 
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
        curl_setopt($ch, CURLOPT_REFERER, $url); 
        curl_setopt($ch, CURLOPT_POST, false);
        $result = curl_exec($ch); 
        curl_close($ch);

        # get __VIEWSTATE 
        //<input type="hidden" name="__VIEWSTATE" id="__VIEWSTATE" value="/wEPDwUKMTYwNTM2Nzk4OQ9kFgICAQ9kFgYCAw9kFgICAQ9kFgICAw8PFgoeBFRleHQFJFN1bW1pdCBDb3VudHkgSmFpbCBNYW5hZ2VtZW50IFN5c3RlbR4JRm9yZUNvbG9yCqQBHglGb250X1NpemUoKiJTeXN0ZW0uV2ViLlVJLldlYkNvbnRyb2xzLkZvbnRVbml0BVNtYWxsHgpGb250X05hbWVzFQEFQXJpYWweBF8hU0IChAxkZAIFD2QWBAIBD2QWBAIBD2QWCAIFDw9kFgIeCW9ua2V5ZG93bgVwaWYgKChldmVudC53aGljaCA9PSAxMykgfHwgKGV2ZW50LmtleUNvZGUgPT0gMTMpKSB7ZG9jdW1lbnQuZ2V0RWxlbWVudEJ5SWQoJ2J0blN1Ym1pdCcpLmNsaWNrKCk7IHJldHVybiBmYWxzZTt9IGQCCQ8PZBYCHwUFcGlmICgoZXZlbnQud2hpY2ggPT0gMTMpIHx8IChldmVudC5rZXlDb2RlID09IDEzKSkge2RvY3VtZW50LmdldEVsZW1lbnRCeUlkKCdidG5TdWJtaXQnKS5jbGljaygpOyByZXR1cm4gZmFsc2U7fSBkAg0PD2QWAh8FBXBpZiAoKGV2ZW50LndoaWNoID09IDEzKSB8fCAoZXZlbnQua2V5Q29kZSA9PSAxMykpIHtkb2N1bWVudC5nZXRFbGVtZW50QnlJZCgnYnRuU3VibWl0JykuY2xpY2soKTsgcmV0dXJuIGZhbHNlO30gZAIRDxAPFgIeC18hRGF0YUJvdW5kZ2QQFQMADUdsZW53b29kIEphaWwSU3VtbWl0IENvdW50eSBKYWlsFQMCLTEBNAEzFCsDA2dnZ2RkAgMPZBYCAgMPDxYCHgdWaXNpYmxlaGRkAgUPZBYGAgEPD2QWAh4Hb25DbGljawUpd2luZG93Lm9wZW4oJ2h0dHA6Ly93d3cuZW1lcmFsZHN5cy5jb20nKTtkAgMPDxYGHwAFPEluZm9ybWF0aW9uIGluIHRoaXMgU3lzdGVtIGlzIHRoZSBQcm9wZXJ0eSBvZiBTdW1taXQgQ291bnR5Lh8BCiMfBAIEZGQCBQ8PFgIeB1Rvb2xUaXAFHFdlZG5lc2RheSwgRmVicnVhcnkgMDIsIDIwMTFkZAIGDxYSHghTdHJhdGVneQspjwFTdHJlbmd0aENvbnRyb2xzLlNjcm9sbGluZy5TY3JvbGxTdHJhdGVneSwgU3RyZW5ndGhDb250cm9scy5TY3JvbGxpbmcsIFZlcnNpb249MS4yLjEzMzEuMTU5NzYsIEN1bHR1cmU9bmV1dHJhbCwgUHVibGljS2V5VG9rZW49YjQwNzBmNTJiZDA5NDgyNQEeCU1haW50YWluWGgeCE1haW50YWluZx4NTGFzdFBvc3RiYWNrWQUBMB4NTGFzdFBvc3RiYWNrWAUBMB4LVXNlT25zY3JvbGxnHglNYWludGFpbllnHgxUYXJnZXRPYmplY3RlHglVc2VPbmxvYWRnZGQND51Wu+8/4HzOy2H8iI5kPxCOEQ==" />     
        preg_match_all('/id\=\"\_\_VIEWSTATE\"\svalue\=\"([^"]*)"/', $result,  $matches,  PREG_PATTERN_ORDER);       
       
        @$vs = $matches[1][0];
        
        # get __EVENTVALIDATION
        //<input type="hidden" name="__EVENTVALIDATION" id="__EVENTVALIDATION" value="/wEWCALUu+aNBAK7zu7HAgK7zqYkArvOspsPAvqxlIIPAvWx2IEPAvSx2IEPAsKL2t4DO1kp3Q2GcOzI/TmQVfQHevcD8gk=" />
        preg_match_all('/id\=\"\_\_EVENTVALIDATION\"\svalue\=\"([^"]*)"/', $result, $matches, PREG_PATTERN_ORDER);
        @$ev = $matches[1][0];
		# download that captcha image for processing 
		// https://scsojms.summitoh.net/MatrixJMS/PublicSearch/CaptchaImage.axd?guid=7a5f331c-80ac-4b57-962e-6874fc134590
		// src="CaptchaImage.axd?guid=edd5013d-42db-47aa-8526-4fd4cf2fcc4e"
		// <img width="200" height="40" border="0" alt="Captcha" src="CaptchaImage.axd?guid=fa1fec8b-b4db-448d-9b98-0fdfab2261b9">
		$check = preg_match('/CaptchaImage\.axd\?guid\=([^"]*)\"/Uis', $result, $match);
		
		if ($check)
		{
			
			$captcha_link = 'https://scsojms.summitoh.net/MatrixJMS/PublicSearch/CaptchaImage.axd?guid='.$match[1];
			$scrape->quality = 100;
            $scrape->imageSource = $captcha_link;
            $scrape->set_extension = true;
            $scrape->save_to = '/var/www/public/images/captcha/summit_captcha'; 
            # download and save
            $image_saved = $scrape->download('curl');
            # load up decaptcher
            
            $host = 'api.de-captcher.info';
			$port = '24802';
			$login = 'mugshotjunkie';
			$password = 'banjo444';
			$flag = false;
			$ccp = new ccproto();
			$ccp->init();
			//$login_status = $ccp->login( $host, $port, $login, $password );	
			try
			{
				$login_status = $ccp->login( $host, $port, $login, $password );	

			}
			catch(ErrorException $e)
			{
				$flag = true;
			}
			
			if ( $flag === false ) 
			{
				
				$system_load = 0;
				$major_id	= 0;
				$minor_id	= 0;
				$pict = file_get_contents('/var/www/public/images/captcha/summit_captcha.jpg');
				$solve = '';
				$pict_to	= ptoDEFAULT;
				$pict_type	= ptUNSPECIFIED;
				try
				{
					$res = $ccp->picture2( $pict, $pict_to, $pict_type, $solve, $major_id, $minor_id );
				} 
				catch (ErrorException $e)
				{
					$flag = true;
				}
				if ($flag === false)
				{
					$ccp->close();
					# I should have my solve now - $solve
					if (!empty($ev) && !empty($vs)) 
					{
						# second curl request this time with POST values to:
				        # http://scsojms.summitoh.net/matrixjms/publicsearch/masterfilenamesearch.aspx
				        //sleep(2);
				        $url    = 'https://scsojms.summitoh.net/MatrixJMS/PublicSearch/MasterFileNameSearch.aspx'; 														
				        $fields = '__EVENTTARGET=&__EVENTARGUMENT=&__VIEWSTATE='.urlencode($vs).'&__EVENTVALIDATION='.urlencode($ev).'&txtLName='.$ln.'&txtFName='.$fn.'&txtMName=&ddlFacilityList=3&txtVerify='.$solve.'&btnSubmit=Submit&SmartScroller1_ScrollX=0&SmartScroller1_ScrollY=0';
				        $ch = curl_init();   
				        curl_setopt($ch, CURLOPT_URL, $url);
						curl_setopt($ch, CURLOPT_TIMEOUT, 0);
				        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
				        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent); 
				        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
				        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
				        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
				        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
				        curl_setopt($ch, CURLOPT_REFERER, $url); 
				        curl_setopt($ch, CURLOPT_POST, true);
				        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
				        $result = curl_exec($ch);
				        curl_close($ch);
						// this is a fail safe check to make sure that the search worked.
						var_dump($result);
						if (stristr($result, 'id="txt_UserName"') !== FALSE)
						{
							exit;
						}
						# validate captcha solve
						$check = preg_match('/Captcha/Uis', $result, $match);
						if (!$check)
						{
					        // __VIEWSTATE=%2FwEPDwUKMTYwNTM2Nzk4OQ9kFgICAQ9kFgYCAw9kFgICAQ9kFgICAw8PFgoeBFRleHQFJFN1bW1pdCBDb3VudHkgSmFpbCBNYW5hZ2VtZW50IFN5c3RlbR4JRm9yZUNvbG9yCqQBHglGb250X1NpemUoKiJTeXN0ZW0uV2ViLlVJLldlYkNvbnRyb2xzLkZvbnRVbml0BVNtYWxsHgpGb250X05hbWVzFQEFQXJpYWweBF8hU0IChAxkZAIFD2QWBAIBD2QWBAIBD2QWCAIFDw9kFgIeCW9ua2V5ZG93bgVwaWYgKChldmVudC53aGljaCA9PSAxMykgfHwgKGV2ZW50LmtleUNvZGUgPT0gMTMpKSB7ZG9jdW1lbnQuZ2V0RWxlbWVudEJ5SWQoJ2J0blN1Ym1pdCcpLmNsaWNrKCk7IHJldHVybiBmYWxzZTt9IGQCCQ8PZBYCHwUFcGlmICgoZXZlbnQud2hpY2ggPT0gMTMpIHx8IChldmVudC5rZXlDb2RlID09IDEzKSkge2RvY3VtZW50LmdldEVsZW1lbnRCeUlkKCdidG5TdWJtaXQnKS5jbGljaygpOyByZXR1cm4gZmFsc2U7fSBkAg0PD2QWAh8FBXBpZiAoKGV2ZW50LndoaWNoID09IDEzKSB8fCAoZXZlbnQua2V5Q29kZSA9PSAxMykpIHtkb2N1bWVudC5nZXRFbGVtZW50QnlJZCgnYnRuU3VibWl0JykuY2xpY2soKTsgcmV0dXJuIGZhbHNlO30gZAIRDxAPFgIeC18hRGF0YUJvdW5kZ2QQFQMADUdsZW53b29kIEphaWwSU3VtbWl0IENvdW50eSBKYWlsFQMCLTEBNAEzFCsDA2dnZ2RkAgMPZBYCAgMPDxYCHgdWaXNpYmxlaGRkAgUPZBYGAgEPD2QWAh4Hb25DbGljawUpd2luZG93Lm9wZW4oJ2h0dHA6Ly93d3cuZW1lcmFsZHN5cy5jb20nKTtkAgMPDxYGHwAFPEluZm9ybWF0aW9uIGluIHRoaXMgU3lzdGVtIGlzIHRoZSBQcm9wZXJ0eSBvZiBTdW1taXQgQ291bnR5Lh8BCiMfBAIEZGQCBQ8PFgIeB1Rvb2xUaXAFHFdlZG5lc2RheSwgRmVicnVhcnkgMDIsIDIwMTFkZAIGDxYSHghTdHJhdGVneQspjwFTdHJlbmd0aENvbnRyb2xzLlNjcm9sbGluZy5TY3JvbGxTdHJhdGVneSwgU3RyZW5ndGhDb250cm9scy5TY3JvbGxpbmcsIFZlcnNpb249MS4yLjEzMzEuMTU5NzYsIEN1bHR1cmU9bmV1dHJhbCwgUHVibGljS2V5VG9rZW49YjQwNzBmNTJiZDA5NDgyNQEeCU1haW50YWluWGgeCE1haW50YWluZx4NTGFzdFBvc3RiYWNrWQUBMB4NTGFzdFBvc3RiYWNrWAUBMB4LVXNlT25zY3JvbGxnHglNYWludGFpbllnHgxUYXJnZXRPYmplY3RlHglVc2VPbmxvYWRnZGQND51Wu%2B8%2F4HzOy2H8iI5kPxCOEQ%3D%3D&__EVENTVALIDATION=%2FwEWCALUu%2BaNBAK7zu7HAgK7zqYkArvOspsPAvqxlIIPAvWx2IEPAvSx2IEPAsKL2t4DO1kp3Q2GcOzI%2FTmQVfQHevcD8gk%3D&txtLName=s&txtFName=s&txtMName=&ddlFacilityList=-1&btnSubmit=Submit&SmartScroller1_ScrollX=0&SmartScroller1_ScrollY=0
					        $vs = '';
							$ev = '';
					        # get __VIEWSTATE 
					        //<input type="hidden" name="__VIEWSTATE" id="__VIEWSTATE" value="/wEPDwUKMTYwNTM2Nzk4OQ9kFgICAQ9kFgYCAw9kFgICAQ9kFgICAw8PFgoeBFRleHQFJFN1bW1pdCBDb3VudHkgSmFpbCBNYW5hZ2VtZW50IFN5c3RlbR4JRm9yZUNvbG9yCqQBHglGb250X1NpemUoKiJTeXN0ZW0uV2ViLlVJLldlYkNvbnRyb2xzLkZvbnRVbml0BVNtYWxsHgpGb250X05hbWVzFQEFQXJpYWweBF8hU0IChAxkZAIFD2QWBAIBD2QWBAIBD2QWCAIFDw9kFgIeCW9ua2V5ZG93bgVwaWYgKChldmVudC53aGljaCA9PSAxMykgfHwgKGV2ZW50LmtleUNvZGUgPT0gMTMpKSB7ZG9jdW1lbnQuZ2V0RWxlbWVudEJ5SWQoJ2J0blN1Ym1pdCcpLmNsaWNrKCk7IHJldHVybiBmYWxzZTt9IGQCCQ8PZBYCHwUFcGlmICgoZXZlbnQud2hpY2ggPT0gMTMpIHx8IChldmVudC5rZXlDb2RlID09IDEzKSkge2RvY3VtZW50LmdldEVsZW1lbnRCeUlkKCdidG5TdWJtaXQnKS5jbGljaygpOyByZXR1cm4gZmFsc2U7fSBkAg0PD2QWAh8FBXBpZiAoKGV2ZW50LndoaWNoID09IDEzKSB8fCAoZXZlbnQua2V5Q29kZSA9PSAxMykpIHtkb2N1bWVudC5nZXRFbGVtZW50QnlJZCgnYnRuU3VibWl0JykuY2xpY2soKTsgcmV0dXJuIGZhbHNlO30gZAIRDxAPFgIeC18hRGF0YUJvdW5kZ2QQFQMADUdsZW53b29kIEphaWwSU3VtbWl0IENvdW50eSBKYWlsFQMCLTEBNAEzFCsDA2dnZ2RkAgMPZBYCAgMPDxYCHgdWaXNpYmxlaGRkAgUPZBYGAgEPD2QWAh4Hb25DbGljawUpd2luZG93Lm9wZW4oJ2h0dHA6Ly93d3cuZW1lcmFsZHN5cy5jb20nKTtkAgMPDxYGHwAFPEluZm9ybWF0aW9uIGluIHRoaXMgU3lzdGVtIGlzIHRoZSBQcm9wZXJ0eSBvZiBTdW1taXQgQ291bnR5Lh8BCiMfBAIEZGQCBQ8PFgIeB1Rvb2xUaXAFHFdlZG5lc2RheSwgRmVicnVhcnkgMDIsIDIwMTFkZAIGDxYSHghTdHJhdGVneQspjwFTdHJlbmd0aENvbnRyb2xzLlNjcm9sbGluZy5TY3JvbGxTdHJhdGVneSwgU3RyZW5ndGhDb250cm9scy5TY3JvbGxpbmcsIFZlcnNpb249MS4yLjEzMzEuMTU5NzYsIEN1bHR1cmU9bmV1dHJhbCwgUHVibGljS2V5VG9rZW49YjQwNzBmNTJiZDA5NDgyNQEeCU1haW50YWluWGgeCE1haW50YWluZx4NTGFzdFBvc3RiYWNrWQUBMB4NTGFzdFBvc3RiYWNrWAUBMB4LVXNlT25zY3JvbGxnHglNYWludGFpbllnHgxUYXJnZXRPYmplY3RlHglVc2VPbmxvYWRnZGQND51Wu+8/4HzOy2H8iI5kPxCOEQ==" />     
					        preg_match_all('/id\=\"\_\_VIEWSTATE\"\svalue\=\"([^"]*)"/', $result,  $matches,  PREG_PATTERN_ORDER);       
					        @$vs = $matches[1][0];
					        
					        # get __EVENTVALIDATION
					        //<input type="hidden" name="__EVENTVALIDATION" id="__EVENTVALIDATION" value="/wEWCALUu+aNBAK7zu7HAgK7zqYkArvOspsPAvqxlIIPAvWx2IEPAvSx2IEPAsKL2t4DO1kp3Q2GcOzI/TmQVfQHevcD8gk=" />
					        preg_match_all('/id\=\"\_\_EVENTVALIDATION\"\svalue\=\"([^"]*)"/', $result, $matches, PREG_PATTERN_ORDER);
					        @$ev = $matches[1][0];
					        
							if (!empty($ev) && !empty($vs)) 
							{
							    
								# get the links to each offender
						        // doPostBack('DataGrid1$_ctl3$_ctl0','')" style="color:Black;">Select
						        preg_match_all('/doPostBack\(\'([^\']*)[^\>]*\>Select/', $result, $matches, PREG_PATTERN_ORDER);
						        $links = $matches[1];
						        
						        # check for empty result
						      	if ( !empty($links) ) 
								{
									
							        # loop through the links and do another curl request for each one
							        $count = 0;
							        $new = 0; // set new scraps
									$total = count($links); // set total
							        foreach ($links as $key => $value) 
							        {
							        	
							        	#destroy all previously used variables
							        	unset($charges);
										unset($fcharges);
										unset($mcharges);
							            $url = 'https://scsojms.summitoh.net/MatrixJMS/PublicSearch/OffenderSearchResults.aspx';
							            $fields = '__EVENTTARGET='.urlencode($value).'&__EVENTARGUMENT=&__VIEWSTATE='.urlencode($vs).'&__EVENTVALIDATION='.urlencode($ev).'&SmartScroller1_ScrollX=0&SmartScroller1_ScrollY=0';           
							            $ch = curl_init();   
										curl_setopt($ch, CURLOPT_TIMEOUT, 0);
							           	curl_setopt($ch, CURLOPT_URL, $url);
							            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
							            curl_setopt($ch, CURLOPT_USERAGENT, $user_agent); 
							            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
							            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
							            //curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
							            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookies);
							            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookies);
							            curl_setopt($ch, CURLOPT_REFERER, $url); 
							            curl_setopt($ch, CURLOPT_POST, true);
							            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
							            $result = curl_exec($ch);
							            curl_close($ch);
							            //sleep(15);
										# get booking ID
										# use table extractor to get the booking ID
							          	$scrape->source = $result;
										// var_dump($result);
							            $scrape->anchor = 'Booking ID';
										$scrape->anchorWithin = true;
							            $scrape->headerRow = false;
							            $scrape->stripTag = true;
							            $table = $scrape->extractTable();
										if (!$table[1][2]) { break; } //make sure it got the booking_id and break if not
										$profile[$count]['booking_id'] = 'summit_' . $table[1][2];
										// strip tags didn't work so rip out html <span> tag	
										// <spanid="lblBookingIDValue"class="lblSentinel_Label">10-0040499</span>
										$profile[$count]['booking_id'] = preg_replace('/\<[^\>]*\>/', '', $profile[$count]['booking_id']);
							          	$booking_id = $profile[$count]['booking_id'];
										# check for existing offender
										# MANGO BABY!
										$offender = Mango::factory('offender', array(
											'booking_id' => $profile[$count]['booking_id']
										))->load();	
										# check here for either an existing offender or an image placeholder
							            //src="/MatrixJMS/OffenderPictures/99/1.SCSO.10-0040499.001"
							            $check = preg_match_all('/MatrixJMS\/OffenderPictures\/([^\"]*)/', $result, $image);
										if ($check > 0) // check to make sure we didn't get the convict.gif image or if they exist in the mongoDB
										{
	                                    	if (empty($offender->booking_id))
				                            {
				    							# get the image			
				    				            //https://scsojms.summitoh.net/MatrixJMS/OffenderPictures/99/1.SCSO.10-0040499.001
				    				            $imglink = '<img src="https://scsojms.summitoh.net/MatrixJMS/OffenderPictures/' . $image[1][0] . '"/>';
				    				            $profile[$count]['image'] = $imglink;        
				    							# get the charges
				    				            $check = preg_match_all('/Description :\<\/td\>\<td[^\>]*>([^\<]*)/', $result, $charges);
				    							# validate for existing charges
				    							if ($check > 0) 
				    							{
				    								$chrgs = array();
				    								$chrgs = $charges[1]; // rip out just charges
				    								$chrgs = array_unique($chrgs);
													$chrgs = array_merge($chrgs);
				    								$dbcharges = $chrgs;
													$smashed_charges = array();
													foreach($dbcharges as $charge)
													{
														// smash it
														$smashed_charges[] = preg_replace('/\s/', '', $charge);
													}
				    								# format array for the check
				    								# now validate against new charges
				    								$format_charges = array();	
				    								foreach ($chrgs as $key => $value)
				    								{	
				    									$format_charges[] = strtoupper(trim(preg_replace('/\s/', '', $value)));	
				    								}
				    								
				    								
				    								# this creates a charges object for all charges that are not new for this county
				    								$charges_object = Mango::factory('charge', array('county' => $this->county, 'new' => 0))->load(false)->as_array(false);
				    								# I can loop through and pull out individual arrays as needed:
				    								$list = array();
				    								foreach($charges_object as $row)
				    								{
				    									$list[$row['charge']] = $row['abbr'];
				    								}
				    								# this gives me a list array with key == fullname, value == abbreviated
				    								$ncharges = array();
				    								# Run my full_charges_array through the charges check
				    								$ncharges = $scrape->charges_check($format_charges, $list);
													$ncharges2 = $this->charges_check($smashed_charges, $list);
													if (!empty($ncharges)) // this means it found new charges (unsmashed)
													{
													    if (empty($ncharges2)) // this means our smashed charges were found in the db
													    {
													        $ncharges = $ncharges2;
													    }
													}
				    								if (empty($ncharges)) // skip the offender if a new charge was found
				    								{
				    									$profile[$count]['charges'] = $charges[1]; 
				    						            # get fullname
				    						            $check = preg_match_all('/Full Name :\<\/span\>\<\/TD\>[^\<]*\<TD[^\>]*\>[^\<]*\<span[^\>]*\>([^\<]*)/', $result, $fullname);
				    						            if ($check == 0) { break; }
				    						            $fullname = $fullname[1][0];
				    									# remove a dot in the name because it will cause and Image class error
				    									$fullname = preg_replace('/\./', '', $fullname);
				    									$profile[$count]['fullname'] = $fullname;
				    									# Explode and trim fullname
				    									# Set first and lastname
				    									$explode = explode(',', trim($profile[$count]['fullname']));
				    									$lastname = trim($explode[0]);
				    									$explode = explode(' ', trim($explode[1]));
				    									$firstname = trim($explode[0]);				
				    									# get booking date
				    									//<span id="lblBookingDateValue" class="lblSentinel_Label" style="width:166px;">12/30/2010 02:25</span></TD>
				    									$check = preg_match('/lblBookingDateValue[^\>]*\>([^\<]*)\</', $result, $bookingdate);
				    									if ($check)
														{
					    									$booking_date = strtotime($bookingdate[1]);
															if ( ! $this->isValidTimeStamp($booking_date) )
															{
						    									$profile[$count]['booking_date'] = $booking_date;
						    									$bd = $booking_date;
						    						            # download and save image
						    						            $scrape->quality = 100;
						    						            $scrape->imageSource = 'https://scsojms.summitoh.net/MatrixJMS/OffenderPictures/'.$image[1][0];
						    						            $scrape->set_extension = true;
						    						            # set image name
						    						            $imgName = date('(m-d-Y)', $bd) .'_'. $lastname . '_' . $firstname . '_' . $profile[$count]['booking_id'];
						    				                    $imagepath = '/mugs/ohio/summit/'.date('Y', $bd).'/'.'week_'.$scrape->find_week($bd).'/';
						    									$mugpath = $scrape->set_mugpath($imagepath);
						    						            # set save path
						    						            $scrape->save_to = $mugpath . $imgName  ;//DOCROOT.'public/images/scrape/ohio/summit/'.$imgName;
						    						            
						    						            # download and save
						    						            $scrape->download('gd');
																# make sure it downloaded ok
																if (file_exists($scrape->save_to . '.jpg'))
																{
							    						            # convert to png
							    						            //@todo: check for actual extension here
							    						            $scrape->convertImage($scrape->save_to . '.jpg');
							    						            $imgpath = $scrape->save_to . '.png';
							    						            # charge logic
							    						            $charges = $profile[$count]['charges'];
							    									
							    									# now run through charge logic
							    						            # trim and uppercase the charges
							    						            # also lets set a charge array for the list
							    						            
							    						            foreach ($charges as $value)
							    						            {
							    						            	$charge_list[] = strtoupper(trim($value));
							    						               	$fcharges[] = strtoupper(trim($value));		
							    						            }
							    						            $fcharges = array_unique($fcharges); //remove duplicates   
							    						            $chargeCount = count($fcharges); //set charge count   
							    						            $fcharges = array_merge($fcharges); //this resets the keys
							    						            #set list paths
							    						            
							    									# run through charge logic
							    						            if ( $chargeCount > 2 ) //if more then 2, run through charges prioritizer
							    						            {
							    						                $mcharges 	= $scrape->charges_prioritizer($list, $fcharges);
							    										if ($mcharges == false) { mail('bustedreport@gmail.com', 'Your prioritizer failed in Summit scrape', "******Debug Me****** \n-=" . $fullname ."=-" . "\n-=" . $booking_id . "=-"); exit; } // debugging
							    						               	$mcharges = array_merge($mcharges);   
							    						                $charge1 = $mcharges[0];
							    						                $charge2 = $mcharges[1];    
							    						                $charges 	= $scrape->charges_abbreviator($list, $charge1, $charge2); 
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
							    										$charges 	= $scrape->charges_abbreviator($list, $charge1, $charge2);
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
							    						                $charges 	= $scrape->charges_abbreviator($list, $charge1);       
							    						                $check = $this->mugStamp($imgpath, $firstname . ' ' . $lastname, $charges[0]);
																		if ($check === false)
																		{
																		    unlink($imgpath);
																		    return 101;
																		} 
							    						            }
							    				
							    									# Now get extra fields
							    									$extra_fields = array();
							    									
							    									//<span id="lblAgeValue" class="lblSentinel_Label" style="width:168px;">23</span>
							    									$check = preg_match('/lblAgeValue[^>]*\>([^<]*)\</', $result, $age);
							    									if ($check) { $age = preg_replace("/\D/", "", $age[1]); $age = trim($age); $age = (int)$age; }
							    									if (isset($age)) { $extra_fields['age'] = $age;}
							    									
							    									//<span id="lblGenderValue" class="lblSentinel_Label" style="width:168px;">Male</span>
							    									$check = preg_match('/lblGenderValue[^>]*\>([^<]*)\</', $result, $gender);
							    									if ($check) {  $gender = trim(strtoupper($gender[1])); } // set and trim
							    									
							    									//<span id="lblHeightValue" class="lblSentinel_Label" style="width:168px;">5ft. 11 in.</span>
							    									$check = preg_match('/lblHeightValue[^>]*\>([^<]*)\</', $result, $height);
							    									if ($check) {  $height = trim($height[1]); } // set and trim
							    									if (isset($height)) 
							    									{
							    										$extra_fields['height'] = $scrape->height_conversion($height);
							    									}
							    									
							    									//<span id="lblWeightValue" class="lblSentinel_Label" style="width:168px;">160</span>
							    									$check = preg_match('/lblWeightValue[^>]*\>([^<]*)\</', $result, $weight);
							    									if ($check) {  $weight = trim($weight[1]); } // set and trim
							    									if (isset($weight)) 
							    									{
							    										$extra_fields['weight'] = preg_replace('/[^0-9]/', '', $weight); 
							    									}
							    									
							    									//<span id="lblEyeColorValue" class="lblSentinel_Label" style="width:168px;">Brown</span>
							    									$check = preg_match('/lblEyeColorValue[^>]*\>([^<]*)\</', $result, $eye_color);
							    									if ($check) {  $eye_color = trim($eye_color[1]); } // set and trim
							    									if (isset($eye_color)) { $extra_fields['eye_color'] = $eye_color;}
							    									
							    									//<span id="lblComplexionValue" class="lblSentinel_Label" style="width:168px;">Fair</span>
							    									$check = preg_match('/lblComplexionValue[^>]*\>([^<]*)\</', $result, $complexion);
							    									if ($check) {  $complexion = trim($complexion[1]); } // set and trim
							    									if (isset($complexion)) { $extra_fields['complexion'] = $complexion;}
							    									
							    									//<span id="lblRaceValue" class="lblSentinel_Label" style="width:168px;">Caucasian/Spanish/Mexican/Puerto Rico/Cuban</span>
							    									$check = preg_match('/lblRaceValue[^>]*\>([^<]*)\</', $result, $race);
							    									if ($check) {  $race = trim($race[1]); } // set and trim
							    									if (isset($race)) 
							    									{
							    										if (isset($race)) 
							    										{
							    											 $race = $scrape->race_mapper($race);
							    											 if ($race)
							    											 {
							    										 	 	$extra_fields['race'] = $race;
							    											 }
							    										}
							    									}
							    									// Abbreviate FULL charge list
							    									$dbcharges = $scrape->charges_abbreviator_db($list, $dbcharges);
							    									$dbcharges = array_unique($dbcharges);
							    									# BOILERPLATE DATABASE INSERTS
							    									$offender = Mango::factory('offender', 
							    						                array(
							    						                	'scrape'		=> $this->county,
							    						                	'county'		=> $this->county,
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
																	$mscrape = Mango::factory('mscrape', array('name' => $this->scrape, 'state' => $this->state))->load();
																	if (!$mscrape->loaded())
																	{
																		$mscrape = Mango::factory('mscrape', array('name' => $this->scrape, 'state' => $this->state))->create();
																	}
																	$mscrape->booking_ids[] = $booking_id;
																	$mscrape->update();	 
							                                        # END DATABASE INSERTS
							    									
							    									# increment counters
							    									$count++;
							    									$new++; 
							    								} 
							    								else // ok there are new charges so lets add to the $info['New_Charges'] array
							    								{
							    								    # add new charges to the charges collection
							    									foreach ($ncharges as $key => $value)
							    									{
							    										//$value = preg_replace('/\s/', '', $value);
							    										#check if the new charge already exists FOR THIS COUNTY
							    										$check_charge = Mango::factory('charge', array('county' => $this->county, 'charge' => $value, 'new' => 1))->load();
							    										if (!$check_charge->loaded())
							    										{
							    											if (!empty($value))
							    											{
							    												$charge = Mango::factory('charge')->create();	
							    												$charge->charge = $value;
																				$charge->abbr = $value;
							    												$charge->order = (int)0;
							    												$charge->county = $this->county;
							    												$charge->new 	= (int)0;
							    												$charge->update();
							    											}	
							    										}
							    									}
							                                        $info['Failed_New_Charges'] += 1;
							    								} // new charges validatoin
						    								}
					    								} else { echo ' no booking_date'; }
				    								} else { echo 'bad image'; }
				    							} else { $info['Other'] += 1; } // empty charges validation
											} else { $info['Exists'] += 1; } // database validation
							            } else { $info['Bad_Images'] += 1; } // placeholder image 	
									} // end loop
									$info['Successful'] = $new;
									$info['Total']      = $total;
								} else { echo 'empty link validation'; }
							} else { echo 'ev and vs validation'; }
						} else {
							# report bad solve
							$ccp->init();
							if( $ccp->login( $host, $port, $login, $password ) == 0 ) 
							{
								$ccp->picture_bad2( $major_id, $minor_id );
								$ccp->close(); 
							}
						} // captcha solve was wrong flag this solve
					} else { echo 'ev and vs validation'; }					
				} else { echo 'Error with ccproto'; }
			} else { echo 'bad login for decaptcher'; } 
		} else { echo 'no captcha image found';  }
        return $info;  
    }   
}