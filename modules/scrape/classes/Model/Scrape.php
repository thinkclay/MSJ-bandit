<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Model_Scrape
 *
 * @package
 * @notes This thing is peiced together from various sources.  Partially ported from http://www.bitrepository.com/download-image.html
 * @author  Winter King
 * @TODO  1. Clean this whole thing up
 *
 **/
class Model_Scrape extends Model
{
    var $report = NULL;
    # vars for table extractor
	var $source			= NULL;
    var $anchor			= NULL;
    var $anchorWithin	= false;
	#@note the headerRow often causes errors for some reason, try not to use it
    var $headerRow		= true;
    var $startRow		= 0;
    var $maxRows		= 0;
    var $startCol		= 0;
    var $maxCols		= 0;
    var $stripTags		= false;
    var $extraCols		= array();
    var $rowCount		= 0;
    var $dropRows		= NULL;
    var $cleanHTML		= NULL;
    var $rawArray		= NULL;
    var $finalArray		= NULL;
    var $handle         = NULL;
    # vars for image download
    var $headers		= NULL;
    var $cookie			= NULL;
	var $imageSource	= NULL;
	var $save_to		= NULL;
	var $set_extension  = NULL;
	var $quality 		= NULL;
	var $alphabet 		= array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');

	public function __construct()
	{
		ini_set('gd.jpeg_ignore_warning', 1);
	}

    private function print_r2($val)
	{
        echo '<pre>';
        print_r($val);
        echo  '</pre>';
	}

    /**
     * ftp_download() performs an automatic syncing of files and folders from a remote location
     * preserving folder and file names and structure
     *
     * @author  Clay McIlrath
     * @param   $local_dir: The directory to put the files, must be in app path and be writeable
     * @param   $remote_dir: The directory to start traversing from. Use "." for root dir
     *
     * @return  null
     */
    public function ftp_download($local_dir, $remote_dir, $ftp_conn)
    {

        if ($remote_dir != ".") {
            if (ftp_chdir($ftp_conn, $remote_dir) == false) {
                echo ("Change Dir Failed: $dir<br />\r\n");
                return;
            }
            if (!(is_dir($dir)))
                mkdir($dir);
            chdir ($dir);
        }

        $contents = ftp_nlist($ftp_conn, ".");
        foreach ($contents as $file) {

            if ($file == '.' || $file == '..')
                continue;

            if (@ftp_chdir($ftp_conn, $file)) {
                ftp_chdir ($ftp_conn, "..");
                FTP::download($local_dir, $file, $ftp_conn);
            }
            else
                ftp_get($ftp_conn, "$local_dir/$file", $file, FTP_BINARY);
        }

        ftp_chdir ($ftp_conn, "..");
        chdir ("..");
    }

	public function download($method = 'curl') // default method: cURL
	{
		set_time_limit(86400);
		$info = @GetImageSize($this->imageSource);
		$mime = $info['mime'];

		// What sort of image?
		$type = substr(strrchr($mime, '/'), 1);

		switch ($type)
		{
			case 'jpeg':
			    $image_create_func = 'ImageCreateFromJPEG';
			    $image_save_func = 'ImageJPEG';
				$new_image_ext = 'jpg';

				// Best Quality: 100
				$quality = isSet($this->quality) ? $this->quality : 100;
			    break;

			case 'png':
			    $image_create_func = 'ImageCreateFromPNG';
			    $image_save_func = 'ImagePNG';
				$new_image_ext = 'png';

				// Compression Level: from 0  (no compression) to 9
				$quality = isSet($this->quality) ? $this->quality : 0;
			    break;

			case 'bmp':
			    $image_create_func = 'ImageCreateFromBMP';
			    $image_save_func = 'ImageBMP';
				$new_image_ext = 'bmp';
			    break;

			case 'gif':
			    $image_create_func = 'ImageCreateFromGIF';
			    $image_save_func = 'ImageGIF';
				$new_image_ext = 'gif';
			    break;

			case 'vnd.wap.wbmp':
			    $image_create_func = 'ImageCreateFromWBMP';
			    $image_save_func = 'ImageWBMP';
				$new_image_ext = 'bmp';
			    break;

			case 'xbm':
			    $image_create_func = 'ImageCreateFromXBM';
			    $image_save_func = 'ImageXBM';
				$new_image_ext = 'xbm';
			    break;

			default:
				$image_create_func = 'ImageCreateFromJPEG';
			    $image_save_func = 'ImageJPEG';
				$new_image_ext = 'jpg';
		}

		if ( isSet($this->set_extension) )
		{
			$ext = strrchr($this->imageSource, ".");
			$strlen = strlen($ext);
			//$new_name = basename(substr($this->imageSource, 0, -$strlen)).'.'.$new_image_ext;
		}
		else
		{
			//$new_name = basename($this->imageSource);
		}

		//$save_to = $this->save_to.$new_name; OLD
		$save_to = $this->save_to.'.'.$new_image_ext; // NEW
		# @edit so I can save it with my own filename.
		# @edit added the supression @ to elseif because I need it to return null on fail
		# @TODO rework so that my mugstamp looks for the literal filename and change this back to save with literal filename.
		#		also it might be better to use curl rather then gd once I have this working correctly
	    if ( $method == 'curl' )
		{
	    	$save_image = $this->LoadImageCURL($save_to);
		}

		elseif ( $method == 'gd' )
		{
		@$img = $image_create_func($this->imageSource);

		    if ( isSet($quality) )
		    {
			   @$save_image = $image_save_func($img, $save_to, $quality);
			}
			else
			{
			   @$save_image = $image_save_func($img, $save_to);
			}
		}

		return $save_image;
	}

	public function LoadImageCURL($save_to)
	{
		if (!$this->handle)
        {
            $ch = curl_init($this->imageSource);
        }
        else
        {
            $ch = $this->handle;
            curl_setopt($ch, CURLOPT_URL, $this->imageSource);
        }
		$fp = fopen($save_to, "wb");
		# edit for cookie setting
		if ($this->cookie)
		{
			//curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie);
        	curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie);
		}
		# edit for setting headers in curl request
		if ($this->headers)
		{
			//Referer: http://jail.lfucg.com/QueryProfile.aspx?oid=0
			curl_setopt($ch, CURLOPT_HEADER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
		}
		// set URL and other appropriate options
		$options = array(CURLOPT_FILE => $fp,
		                 //CURLOPT_HEADER => 0,
		                 CURLOPT_FOLLOWLOCATION => 1,
			             CURLOPT_TIMEOUT => 60); // 1 minute timeout (should be enough)

		curl_setopt_array($ch, $options);

		curl_exec($ch);
		curl_close($ch);
		fclose($fp);
	}

	public function make_county_directory($fulldir)
	{
		if(!is_dir($fulldir))
		{
			$oldumask = umask(0);
			mkdir($fulldir, 0777);
			umask($oldumask);

		}
	}

	public function extractTable()
	{
	    $this->cleanHTML();
	    $this->prepareArray();

	    return $this->createArray();
	}


    public function cleanHTML()
    {

        // php 4 compatibility functions
    	if ( !function_exists('stripos') )
    	{
        	function stripos($haystack,$needle,$offset = 0)
        	{
           		return(strpos(strtolower($haystack),strtolower($needle),$offset));
	        }
	    }

        // find unique string that appears before the table you want to extract
        if ($this->anchorWithin) {
            /*------------------------------------------------------------
                With thanks to Khary Sharp for suggesting and writing
                the anchor within functionality.
            ------------------------------------------------------------*/
            $anchorPos = stripos($this->source, $this->anchor) + strlen($this->anchor);
            $sourceSnippet = strrev(substr($this->source, 0, $anchorPos));
            $tablePos = stripos($sourceSnippet, strrev(("<table"))) + 6;
            $startSearch = strlen($sourceSnippet) - $tablePos;
        }
        else {
            $startSearch = stripos($this->source, $this->anchor);
        }

        // extract table
        $startTable = @stripos($this->source, '<table', $startSearch);
        $endTable = @stripos($this->source, '</table>', $startTable) + 8;
        $table = @substr($this->source, $startTable, $endTable - $startTable);

        if(!function_exists('lcase_tags')) {
            function lcase_tags($input) {
                return strtolower($input[0]);
            }
        }

        // lowercase all table related tags
        $table = preg_replace_callback('/<(\/?)(table|tr|th|td)/is', 'lcase_tags', $table);

        // remove all thead and tbody tags
        $table = preg_replace('/<\/?(thead|tbody).*?>/is', '', $table);

        // replace th tags with td tags
        $table = preg_replace('/<(\/?)th(.*?)>/is', '<$1td$2>', $table);

        // clean string
        $table = trim($table);
        $table = str_replace("\r\n", "", $table);

        $this->cleanHTML = $table;

    }

    public function prepareArray() {

        // split table into individual elements
        $pattern = '/(<\/?(?:tr|td).*?>)/is';
            $table = preg_split($pattern, $this->cleanHTML, -1, PREG_SPLIT_DELIM_CAPTURE);

            // define array for new table
        $tableCleaned = array();

        // define variables for looping through table
        $rowCount = 0;
        $colCount = 1;
        $trOpen = false;
        $tdOpen = false;

        // loop through table
        foreach($table as $item) {

            // trim item
            $item = str_replace(' ', '', $item);
            $item = trim($item);

            // save the item
            $itemUnedited = $item;

            // clean if tag
            $item = preg_replace('/<(\/?)(table|tr|td).*?>/is', '<$1$2>', $item);

                // pick item type
                switch ($item) {


                    case '<tr>':
                    // start a new row
                    $rowCount++;
                    $colCount = 1;
                    $trOpen = true;
                    break;

                case '<td>':
                    // save the td tag for later use
                    $tdTag = $itemUnedited;
                    $tdOpen = true;
                    break;

                case '</td>':
                    $tdOpen = false;
                    break;

                case '</tr>':
                    $trOpen = false;
                    break;

                default :

                    // if a TD tag is open
                    if($tdOpen) {

                        // check if td tag contained colspan
                        if(preg_match('/<td [^>]*colspan\s*=\s*(?:\'|")?\s*([0-9]+)[^>]*>/is', $tdTag, $matches))
                            $colspan = $matches[1];
                        else
                            $colspan = 1;

                        // check if td tag contained rowspan
                        if(preg_match('/<td [^>]*rowspan\s*=\s*(?:\'|")?\s*([0-9]+)[^>]*>/is', $tdTag, $matches))
                            $rowspan = $matches[1];
                        else
                            $rowspan = 0;

                        // loop over the colspans
                        for($c = 0; $c < $colspan; $c++) {

                            // if the item data has not already been defined by a rowspan loop, set it
                            if(!isset($tableCleaned[$rowCount][$colCount]))
                                $tableCleaned[$rowCount][$colCount] = $item;
                            else
                                $tableCleaned[$rowCount][$colCount + 1] = $item;

                            // create new rowCount variable for looping through rowspans
                            $futureRows = $rowCount;

                            // loop through row spans
                            for($r = 1; $r < $rowspan; $r++) {
                                $futureRows++;
                                if($colspan > 1)
                                    $tableCleaned[$futureRows][$colCount + 1] = $item;
                                else
                                    $tableCleaned[$futureRows][$colCount] = $item;
                            }

                            // increase column count
                            $colCount++;

                        }

                        // sort the row array by the column keys (as inserting rowspans screws up the order)
                        ksort($tableCleaned[$rowCount]);
                    }
                    break;
            }
        }
        // set row count
        if($this->headerRow)
            $this->rowCount    = count($tableCleaned) - 1;
        else
            $this->rowCount    = count($tableCleaned);

        $this->rawArray = $tableCleaned;

    }
	// true to remove extra white space
	public function clean_string_utf8($string_to_clean, $bool = false)
	{
		if (!is_string($string_to_clean))
			return false;
		$clean_string = strtoupper(trim(preg_replace('/[\x7f-\xff]/', '', $string_to_clean)));
		$clean_string = str_replace('"', '', $clean_string);
		if ($bool == true)
			$clean_string = preg_replace('/\s\s+/', ' ', $clean_string); // replace all extra spaces
		return htmlspecialchars_decode(trim($clean_string), ENT_QUOTES);
	}

    public function createArray() {

        // define array to store table data
        $tableData = array();

        // get column headers
        if($this->headerRow) {

            // trim string
            $row = $this->rawArray[$this->headerRow];

            // set column names array
            $columnNames = array();
            $uniqueNames = array();

            // loop over column names
            $colCount = 0;
            foreach($row as $cell) {

                $colCount++;

                $cell = strip_tags($cell);
                $cell = trim($cell);

                // save name if there is one, otherwise save index
                if($cell) {

                    if(isset($uniqueNames[$cell])) {
                        $uniqueNames[$cell]++;
                        $cell .= ' ('.($uniqueNames[$cell] + 1).')';
                        }
                        else {
                            $uniqueNames[$cell] = 0;
                        }

                        $columnNames[$colCount] = $cell;

                    }
                    else
                        $columnNames[$colCount] = $colCount;

                }

                // remove the headers row from the table
            unset($this->rawArray[$this->headerRow]);

        }

        // remove rows to drop
        foreach(explode(',', $this->dropRows) as $key => $value) {
            unset($this->rawArray[$value]);
        }

        // set the end row
        if($this->maxRows)
            $endRow = $this->startRow + $this->maxRows - 1;
        else
            $endRow = count($this->rawArray);

        // loop over row array
        $rowCount = 0;
        $newRowCount = 0;
        foreach($this->rawArray as $row) {

            $rowCount++;

            // if the row was requested then add it
            if($rowCount >= $this->startRow && $rowCount <= $endRow) {

                $newRowCount++;

                // create new array to store data
                $tableData[$newRowCount] = array();

                //$tableData[$newRowCount]['origRow'] = $rowCount;
                //$tableData[$newRowCount]['data'] = array();
                $tableData[$newRowCount] = array();

                // set the end column
                if($this->maxCols)
                    $endCol = $this->startCol + $this->maxCols - 1;
                else
                    $endCol = count($row);

                // loop over cell array
                $colCount = 0;
                $newColCount = 0;
                foreach($row as $cell) {

                    $colCount++;

                    // if the column was requested then add it
                    if($colCount >= $this->startCol && $colCount <= $endCol) {

                        $newColCount++;

                        if($this->extraCols) {
                            foreach($this->extraCols as $extraColumn) {
                                if($extraColumn['column'] == $colCount) {
                                    if(preg_match($extraColumn['regex'], $cell, $matches)) {
                                        if(is_array($extraColumn['names'])) {
                                            $this->extraColsCount = 0;
                                            foreach($extraColumn['names'] as $extraColumnSub) {
                                                $this->extraColsCount++;
                                                $tableData[$newRowCount][$extraColumnSub] = $matches[$this->extraColsCount];
                                            }
                                        } else {
                                            $tableData[$newRowCount][$extraColumn['names']] = $matches[1];
                                        }
                                    } else {
                                        $this->extraColsCount = 0;
                                        if(is_array($extraColumn['names'])) {
                                            $this->extraColsCount = 0;
                                            foreach($extraColumn['names'] as $extraColumnSub) {
                                                $this->extraColsCount++;
                                                $tableData[$newRowCount][$extraColumnSub] = '';
                                            }
                                        } else {
                                            $tableData[$newRowCount][$extraColumn['names']] = '';
                                        }
                                    }
                                }
                            }
                        }

                        if($this->stripTags)
                            $cell = strip_tags($cell);

                        // set the column key as the column number
                        $colKey = $newColCount;

                        // if there is a table header, use the column name as the key
                        if($this->headerRow)
                            if(isset($columnNames[$colCount]))
                                $colKey = $columnNames[$colCount];

                        // add the data to the array
                        //$tableData[$newRowCount]['data'][$colKey] = $cell;
                        $tableData[$newRowCount][$colKey] = $cell;
                    }
                }
            }
        }

        $this->finalArray = $tableData;
        return $tableData;
    }

	public function array2string( $myarray, &$output, &$parentkey )
	{
		foreach($myarray as $key=>$value)
		{
			if ( is_array($value) )
			{
				$parentkey .= $key.": ";
				$this->array2string($value,$output,$parentkey);
				$parentkey = "";
			}
			else
			{
				$output .= $parentkey.$key.": ".$value."\n";
			}
		}
		return $output;
	}

	/**
	* convertImage - converts any image to a PNG
	*
	* @todo expand this to include adding the FN LN and Charge(s?).
	* @return void
	* @author Winter King
	*/
	public function convertImage($image)
	{
		// check for valid image
        $check = getimagesize($image);
		if ($check === false)
		{
			return false;
		}
		$info = @GetImageSize($image);
		$mime = $info['mime'];
		// What sort of image?
		$type = substr(strrchr($mime, '/'), 1);
		switch ($type)
		{
			case 'jpeg':
				$image_s = imagecreatefromjpeg($image);
			    break;
			case 'png':
			    $image_s = imagecreatefrompng($image);
			    break;
			case 'bmp':
				$image_s = imagecreatefromwbmp($image);
			    break;
			case 'gif':
				$image_s = imagecreatefromgif($image);
			    break;
			case 'xbm':
			    $image_s = imagecreatefromxbm($image);
			    break;
			default:
				$image_s = imagecreatefromjpeg($image);
		}
		# ok so now I have $image_s set as the sourceImage and open as
		# now change the image extension
		$ext = '.png';
		$replace = preg_replace('/\.[a-zA-Z]*/', $ext, $image);
		# save the image with the same name but new extension
		$pngimg = imagepng($image_s, $replace);
		# if successful delete orginal source image
		if ($pngimg)
		{
			chmod($replace, 0777);
			//chown($replace, 'mugs');
			@unlink($image);
			return $pngimg;
		}
		else
		{
			return false;
		}
	}


	/**
	* mugStamp - Takes an image and adds space at the bottom for name and charges
	*
	* @todo
	* @return
	* @author Winter King
	*/
    public function mugStamp_test($imgpath, $fullname, $charge1, $charge2 = null)
    {
    	# todo: check to make sure the $imgpath is an image, if not then return string 'not an image'

        //header('Content-Type: image/png');
        //$imgpath = DOCROOT.'images/scrape/ohio/summit/test.png';
        # resize image to 400x480 and save it
        $image = Image::factory($imgpath);
        $image->resize(400, 480, Image::NONE)->save();
        # open original image with GD
        $orig = @imagecreatefrompng($imgpath);
		if ($orig)
		{
			# create a blank 400x600 canvas
	        $canvas = imagecreatetruecolor(400, 600);
	        # allocate white
	        $white = imagecolorallocate($canvas, 255, 255, 255);
	        # draw a filled rectangle on it
	        imagefilledrectangle($canvas, 0, 0, 400, 600, $white);
	        # copy original onto white painted canvas
	        imagecopy($canvas, $orig, 0, 0, 0, 0, 400, 480);

	        # start text stamp
	        # create a new text canvas box @ 400x120
	        $txtCanvas = imagecreatetruecolor(400, 120);
	        # allocate white
	        $white = imagecolorallocate($txtCanvas, 255, 255, 255);
	        # draw a filled rectangle on it
	        imagefilledrectangle($txtCanvas, 0, 0, 400, 120, $white);
	        # set font file
	        $font = DOCROOT.'includes/arial.ttf';

	        # fullname
	        # find dimentions of the text box for fullname

	    	$dims = imagettfbbox(18 , 0 , $font , $fullname );
			# set width
	        $width = $dims[2] - $dims[0];
			# check to see if the name fits
			if ($width < 390)
			{
				# find center
		        $center = ceil((400 - $width)/2);
				# write text
		        imagettftext($txtCanvas, 18, 0, $center, 35, 5, $font, $fullname);
			}
			# if it doesn't fit cut it down to size 12
			else
			{
				$dims = imagettfbbox(12 , 0 , $font , $fullname );
				# set width
		        $width = $dims[2] - $dims[0];
				# find center
		        $center = ceil((400 - $width)/2);
				# write text
		        imagettftext($txtCanvas, 12, 0, $center, 35, 5, $font, $fullname);
			}
	        //@todo: make a check for text that is too long for the box and cut out middle name if so

	        # charge1
	        # find dimentions of the text box for charge1
	        $dims = imagettfbbox(18 , 0 , $font , $charge1 );
	        # set width
	        $width = $dims[2] - $dims[0];
			# check to see if charge1 description fits
			if ($width < 390)
			{
				# find center
		        $center = ceil((400 - $width)/2);
				# write text
		        imagettftext($txtCanvas, 18, 0, $center, 65, 5, $font, $charge1);
			}
			# if it doesn't fit cut it down to size 12
			else
			{
				$dims = imagettfbbox(12 , 0 , $font , $charge1 );
				# set width
		        $width = $dims[2] - $dims[0];
				# find center
		        $center = ceil((400 - $width)/2);
				# write text
		        imagettftext($txtCanvas, 12, 0, $center, 65, 5, $font, $charge1);
			}

	        # check for a 2nd charge
			if ($charge2)
	        {
	            # charge2
	            # find dimentions of the text box for charge2
	            $dims = imagettfbbox(18 , 0 , $font , $charge2 );
	            # set width
	            $width = $dims[2] - $dims[0];
				# check to see if charge1 description fits
				if ($width < 390)
				{
					# find center
			        $center = ceil((400 - $width)/2);
					# write text
			        imagettftext($txtCanvas, 18, 0, $center, 95, 5, $font, $charge2);
				}
				# if it doesn't fit cut it down to size 12
				else
				{
					$dims = imagettfbbox(12 , 0 , $font , $charge2 );
					# set width
			        $width = $dims[2] - $dims[0];
					# find center
			        $center = ceil((400 - $width)/2);
					# write text
			        imagettftext($txtCanvas, 12, 0, $center, 95, 5, $font, $charge2);
				}
			}
			#doesn't exist for some reason
			//imageantialias($txtCanvas);
			# copy text canvas onto the image
			imagecopy($canvas, $txtCanvas, 0, 480, 0, 0, 400, 120);
	        $imgName = $fullname . ' ' . date('(m-d-Y)');
	        $mugStamp = $imgpath;
	        # save file
	        $check = imagepng($canvas, $mugStamp);
			chmod($mugStamp, 0777); //not working for some reason
	        if ($check) {return true;} else {return false;}
		}
		else
		{
			return false;
		}
    }


	/**
	* mugStamp - Takes an image and adds space at the bottom for name and charges
	*
	* @todo
	* @return
	* @author Winter King
	*/
    public function mugStamp($imgpath, $fullname, $charge1, $charge2 = null)
    {
    	$max_width = 380;

    	$font = DOCROOT.'includes/arial.ttf';
		$font_18_dims = imagettfbbox( 18 , 0 , $font , $charge1);
		$font_18_charge_width = $font_18_dims[2] - $font_18_dims[0];
		$font_12_dims = imagettfbbox( 12 , 0 , $font , $charge1);
		$font_12_charge_width = $font_12_dims[2] - $font_12_dims[0];
		$cropped = false;
		if($font_12_charge_width > $max_width)
		{
			unset($charge2);
			$cropped_charge = $this->charge_cropper($charge1, $max_width);
			if ($cropped_charge === false)
			{
				return false;
			}
			$cropped = true;
			$charge1 = $cropped_charge[0];
			$charge2 = @$cropped_charge[1];
		}
		if (isset($charge2))
		{
			$font = DOCROOT.'includes/arial.ttf';
			$font_18_dims = imagettfbbox( 18 , 0 , $font , $charge2);
			$font_18_charge_width = $font_18_dims[2] - $font_18_dims[0];
			$font_12_dims = imagettfbbox( 12 , 0 , $font , $charge2);
			$font_12_charge_width = $font_12_dims[2] - $font_12_dims[0];
			if($font_12_charge_width > $max_width)
			{
				unset($charge2);
			}
		}
		if (isset($charge1))
		{
			$font = DOCROOT.'includes/arial.ttf';
			$font_18_dims = imagettfbbox( 18 , 0 , $font , $charge1);
			$font_18_charge_width = $font_18_dims[2] - $font_18_dims[0];
			$font_12_dims = imagettfbbox( 12 , 0 , $font , $charge1);
			$font_12_charge_width = $font_12_dims[2] - $font_12_dims[0];
			if($font_12_charge_width > ($max_width * 2) )
			{
				return false;
			}
		}
        # todo: check to make sure the $imgpath is an image, if not then return string 'not an image'
        $charge1 = trim($charge1);
        //header('Content-Type: image/png');
        //$imgpath = DOCROOT.'images/scrape/ohio/summit/test.png';
        # resize image to 400x480 and save it
        $image = Image::factory($imgpath);
        $image->resize(400, 480, Image::NONE)->save();
        # open original image with GD
        // check for valid image
        $check = getimagesize($imgpath);
		if ($check === false)
		{
			return false;
		}
        $orig = imagecreatefrompng($imgpath);
        # create a blank 400x600 canvas
        $canvas = imagecreatetruecolor(400, 600);
        # allocate white
        $white = imagecolorallocate($canvas, 255, 255, 255);
        # draw a filled rectangle on it
        imagefilledrectangle($canvas, 0, 0, 400, 600, $white);
        # copy original onto white painted canvas
        imagecopy($canvas, $orig, 0, 0, 0, 0, 400, 480);

        # start text stamp
        # create a new text canvas box @ 400x120
        $txtCanvas = imagecreatetruecolor(400, 120);
        # allocate white
        $white = imagecolorallocate($txtCanvas, 255, 255, 255);
        # draw a filled rectangle on it
        imagefilledrectangle($txtCanvas, 0, 0, 400, 120, $white);
        # set font file
        $font = DOCROOT.'includes/arial.ttf';

        # fullname
        # find dimentions of the text box for fullname

    	$dims = imagettfbbox(18 , 0 , $font , $fullname );
		# set width
        $width = $dims[2] - $dims[0];
		# check to see if the name fits
		if ($width < 390)
		{
			$fontsize = 18;
			# find center
	        $center = ceil((400 - $width)/2);
			# write text
	        imagettftext($txtCanvas, $fontsize, 0, $center, 35, 5, $font, $fullname);
		}
		# if it doesn't fit cut it down to size 12
		else
		{
			$fontsize = 12;
			$dims = imagettfbbox(12 , 0 , $font , $fullname );
			# set width
	        $width = $dims[2] - $dims[0];
			# find center
	        $center = ceil((400 - $width)/2);
			# write text
	        imagettftext($txtCanvas, $fontsize, 0, $center, 35, 5, $font, $fullname);
		}
        //@todo: make a check for text that is too long for the box and cut out middle name if so

        # charge1
        # find dimentions of the text box for charge1
        $dims = imagettfbbox(18 , 0 , $font , $charge1 );
        # set width
        $width = $dims[2] - $dims[0];
		# check to see if charge1 description fits
		if ($width < 390)
		{
			$cfont = 18;
			# find center
	        $center = ceil((400 - $width)/2);
			# write text
	        imagettftext($txtCanvas, $cfont, 0, $center, 65, 5, $font, $charge1);
		}
		# if it doesn't fit cut it down to size 12
		else
		{
			$cfont = 12;
			$dims = imagettfbbox(12 , 0 , $font , $charge1 );
			# set width
	        $width = $dims[2] - $dims[0];
			# find center
	        $center = ceil((400 - $width)/2);
			# write text
	        imagettftext($txtCanvas, $cfont, 0, $center, 65, 5, $font, $charge1);
		}

        # check for a 2nd charge
		if (isset($charge2))
        {
        	if ($cropped === true)
			{
				$dims = imagettfbbox($cfont , 0 , $font , $charge2 );
	            # set width
	            $width = $dims[2] - $dims[0];
				# find center
		        $center = ceil((400 - $width)/2);
				# write text
		        imagettftext($txtCanvas, $cfont, 0, $center, 95, 5, $font, $charge2);
			}
			else
			{
				# charge2
	            # find dimentions of the text box for charge2
	            $dims = imagettfbbox(18 , 0 , $font , $charge2 );
	            # set width
	            $width = $dims[2] - $dims[0];
				# check to see if charge1 description fits
				if ($width < 390 && $cfont == 18)
				{
					# find center
			        $center = ceil((400 - $width)/2);
					# write text
			        imagettftext($txtCanvas, $cfont, 0, $center, 95, 5, $font, $charge2);
				}
				# if it doesn't fit cut it down to size 12
				else
				{
					$cfont = 12;
					$dims = imagettfbbox(12 , 0 , $font , $charge2 );
					# set width
			        $width = $dims[2] - $dims[0];
					# find center
			        $center = ceil((400 - $width)/2);
					# write text
			        imagettftext($txtCanvas, $cfont, 0, $center, 95, 5, $font, $charge2);
				}
			}
		}
		#doesn't exist for some reason
		//imageantialias($txtCanvas);
		# copy text canvas onto the image
		imagecopy($canvas, $txtCanvas, 0, 480, 0, 0, 400, 120);
        $imgName = $fullname . ' ' . date('(m-d-Y)');
        $mugStamp = $imgpath;
        # save file
        $check = imagepng($canvas, $mugStamp);
		chmod($mugStamp, 0777); //not working for some reason
        if ($check) {return true;} else {return false;}
    }

	/**
	 * Used to trim the charge down to where it just fits with our image width
	 *
	 * @return 	string	trimmed charge
	 * @author  Winter King
	 */
	function charge_trim($charge)
	{
		$charge = trim($charge);
		$max_width = 380;
		$font = DOCROOT.'includes/arial.ttf';
		$font_12_dims = imagettfbbox( 12 , 0 , $font , $charge);
		$font_12_charge_width = $font_12_dims[2] - $font_12_dims[0];
		if (($font_12_charge_width / 2) <= $max_width)
		{
			return $charge;
		}
		else
		{
			$flag = false;
			while($flag == false)
			{

				$font_12_dims = imagettfbbox( 12 , 0 , $font , $charge . '...');
				$font_12_charge_width = $font_12_dims[2] - $font_12_dims[0];
				if (($font_12_charge_width / 2) <= $max_width)
				{
					$flag = true;
				}
				else
				{
					$substr = strlen($charge) - 1;
					$charge = substr($charge, 0, $substr);
				}
			}
			return $charge . '...';
		}
	}

	function charge_cropper($charge, $max_width, $large_string_length = 30)
	{
		$check = preg_match('/\s/', $charge);//if the string contains no spaces then return false
		if($check)
		{
			$font = DOCROOT.'includes/arial.ttf';
			$font_18_dims = imagettfbbox( 18 , 0 , $font , $charge);
    		$font_18_charge_width = $font_18_dims[2] - $font_18_dims[0];
    		$font_12_dims = imagettfbbox( 12 , 0 , $font , $charge);
    		$font_12_charge_width = $font_12_dims[2] - $font_12_dims[0];
			if(($font_18_charge_width / 2) < $max_width)
			{
				$fontsize = 18;
			}
			elseif(($font_12_charge_width / 2) < $max_width)
			{
				$fontsize = 12;
			}
			else
			{
				return false;
			}
			$charges = array();
			if(strlen($charge) > $large_string_length)
			{
				$words = array();
				$words = explode(' ', $charge);
				$word_count = count($words) - 1;
				$charges = array();
				$word_width = array();
				$total_width = array();
				$total_width[0] = 0;
				$total_width[1] = 0;
				$charges[0] = '';
				$charges[1] = '';
				$i = 0;
				foreach($words as $word)
				{
					$dims = imagettfbbox($fontsize , 0 , $font , $word);
       				$word_width[$i] = $dims[2] - $dims[0];
					$i++;
				}
				$c1 = 0;
				for($i = 0; $i <= $word_count; $i++)
				{
					if(((int)$total_width[0] + (int)$word_width[$i]) <= (int)$max_width && $c1 == 0)
					{
						$total_width[0] = $total_width[0] + $word_width[$i];
						$charges[0] = $charges[0] . ' ' . $words[$i];
					}
					else
					{
						$c1 = 1;
						if(((int)$total_width[1] + (int)$word_width[$i]) <= (int)$max_width)
						{
							$total_width[1] = $total_width[1] + $word_width[$i];
							$charges[1] = $charges[1] . ' ' . $words[$i];
						} else { break; }
					}
				}
				return $charges;
			} else { return false; }
		} else { return false; }
	}

	function charge_cropper_test1($charge, $max_width, $large_string_length = 30)
	{
		$check = preg_match('/\s/', $charge);//if the string contains no spaces then return false
		if($check)
		{
			$font = DOCROOT.'includes/arial.ttf';
			$font_18_dims = imagettfbbox( 18 , 0 , $font , $charge);
    		$font_18_charge_width = $font_18_dims[2] - $font_18_dims[0];
    		$font_12_dims = imagettfbbox( 12 , 0 , $font , $charge);
    		$font_12_charge_width = $font_12_dims[2] - $font_12_dims[0];
			if(($font_18_charge_width / 2) < $max_width)
			{
				$fontsize = 18;
			}
			elseif(($font_12_charge_width / 2) < $max_width)
			{
				$fontsize = 12;
			}
			else
			{
				return false;
			}
			$charges = array();
			if(strlen($charge) > $large_string_length)
			{
				$words = array();
				$words = explode(' ', $charge);
				$word_count = count($words) - 1;
				$charges = array();
				$word_width = array();
				$total_width = array();
				$total_width[0] = 0;
				$total_width[1] = 0;
				$charges[0] = '';
				$charges[1] = '';
				$i = 0;
				foreach($words as $word)
				{
					$dims = imagettfbbox($fontsize , 0 , $font , $word);
       				$word_width[$i] = $dims[2] - $dims[0];
					$i++;
				}
				$c1 = 0;
				for($i = 0; $i <= $word_count; $i++)
				{
					if(((int)$total_width[0] + (int)$word_width[$i]) <= (int)$max_width && $c1 == 0)
					{
						$total_width[0] = $total_width[0] + $word_width[$i];
						$charges[0] = $charges[0] . ' ' . $words[$i];
					}
					else
					{
						$c1 = 1;
						if(((int)$total_width[1] + (int)$word_width[$i]) <= (int)$max_width)
						{
							$total_width[1] = $total_width[1] + $word_width[$i];
							$charges[1] = $charges[1] . ' ' . $words[$i];
						} else { break; }
					}
				}
				return $charges;
			} else { return false; }
		} else { return false; }
	}

    public function mugStamp_test1($imgpath, $fullname, $charge1, $charge2 = null)
    {
    	$max_width = 380;
    	$font = DOCROOT.'includes/arial.ttf';
		$font_18_dims = imagettfbbox( 18 , 0 , $font , $charge1);
		$font_18_charge_width = $font_18_dims[2] - $font_18_dims[0];
		$font_12_dims = imagettfbbox( 12 , 0 , $font , $charge1);
		$font_12_charge_width = $font_12_dims[2] - $font_12_dims[0];
		$cropped = false;
		if($font_12_charge_width > $max_width)
		{
			unset($charge2);
			$cropped_charge = $this->charge_cropper_test1($charge1, $max_width);
			if ($cropped_charge === false)
			{
				echo 'xfngr1';
				return false;
			}
			$cropped = true;
			$charge1 = $cropped_charge[0];
			$charge2 = @$cropped_charge[1];
		}
		if (isset($charge2))
		{
			$font = DOCROOT.'includes/arial.ttf';
			$font_18_dims = imagettfbbox( 18 , 0 , $font , $charge2);
			$font_18_charge_width = $font_18_dims[2] - $font_18_dims[0];
			$font_12_dims = imagettfbbox( 12 , 0 , $font , $charge2);
			$font_12_charge_width = $font_12_dims[2] - $font_12_dims[0];
			if($font_12_charge_width > $max_width)
			{
				unset($charge2);
			}
		}
		if (isset($charge1))
		{
			$font = DOCROOT.'includes/arial.ttf';
			$font_18_dims = imagettfbbox( 18 , 0 , $font , $charge1);
			$font_18_charge_width = $font_18_dims[2] - $font_18_dims[0];
			$font_12_dims = imagettfbbox( 12 , 0 , $font , $charge1);
			$font_12_charge_width = $font_12_dims[2] - $font_12_dims[0];
			if($font_12_charge_width > ($max_width * 2) )
			{
				echo 'xfngr2';
				return false;
			}
		}
        # todo: check to make sure the $imgpath is an image, if not then return string 'not an image'
        $charge1 = trim($charge1);
        //header('Content-Type: image/png');
        //$imgpath = DOCROOT.'images/scrape/ohio/summit/test.png';
        # resize image to 400x480 and save it
        $image = Image::factory($imgpath);
        $image->resize(400, 480, Image::NONE)->save();
        # open original image with GD
        // check for valid image
        $check = getimagesize($imgpath);
		if ($check === false)
		{
			echo 'xfngr3';
			return false;
		}
        $orig = imagecreatefrompng($imgpath);
        # create a blank 400x600 canvas
        $canvas = imagecreatetruecolor(400, 600);
        # allocate white
        $white = imagecolorallocate($canvas, 255, 255, 255);
        # draw a filled rectangle on it
        imagefilledrectangle($canvas, 0, 0, 400, 600, $white);
        # copy original onto white painted canvas
        imagecopy($canvas, $orig, 0, 0, 0, 0, 400, 480);

        # start text stamp
        # create a new text canvas box @ 400x120
        $txtCanvas = imagecreatetruecolor(400, 120);
        # allocate white
        $white = imagecolorallocate($txtCanvas, 255, 255, 255);
        # draw a filled rectangle on it
        imagefilledrectangle($txtCanvas, 0, 0, 400, 120, $white);
        # set font file
        $font = DOCROOT.'includes/arial.ttf';

        # fullname
        # find dimentions of the text box for fullname

    	$dims = imagettfbbox(18 , 0 , $font , $fullname );
		# set width
        $width = $dims[2] - $dims[0];
		# check to see if the name fits
		if ($width < 390)
		{
			$fontsize = 18;
			# find center
	        $center = ceil((400 - $width)/2);
			# write text
	        imagettftext($txtCanvas, $fontsize, 0, $center, 35, 5, $font, $fullname);
		}
		# if it doesn't fit cut it down to size 12
		else
		{
			$fontsize = 12;
			$dims = imagettfbbox(12 , 0 , $font , $fullname );
			# set width
	        $width = $dims[2] - $dims[0];
			# find center
	        $center = ceil((400 - $width)/2);
			# write text
	        imagettftext($txtCanvas, $fontsize, 0, $center, 35, 5, $font, $fullname);
		}
        //@todo: make a check for text that is too long for the box and cut out middle name if so

        # charge1
        # find dimentions of the text box for charge1
        $dims = imagettfbbox(18 , 0 , $font , $charge1 );
        # set width
        $width = $dims[2] - $dims[0];
		# check to see if charge1 description fits
		if ($width < 390)
		{
			$cfont = 18;
			# find center
	        $center = ceil((400 - $width)/2);
			# write text
	        imagettftext($txtCanvas, $cfont, 0, $center, 65, 5, $font, $charge1);
		}
		# if it doesn't fit cut it down to size 12
		else
		{
			$cfont = 12;
			$dims = imagettfbbox(12 , 0 , $font , $charge1 );
			# set width
	        $width = $dims[2] - $dims[0];
			# find center
	        $center = ceil((400 - $width)/2);
			# write text
	        imagettftext($txtCanvas, $cfont, 0, $center, 65, 5, $font, $charge1);
		}

        # check for a 2nd charge
		if (isset($charge2))
        {
        	if ($cropped === true)
			{
				$dims = imagettfbbox($cfont , 0 , $font , $charge2 );
	            # set width
	            $width = $dims[2] - $dims[0];
				# find center
		        $center = ceil((400 - $width)/2);
				# write text
		        imagettftext($txtCanvas, $cfont, 0, $center, 95, 5, $font, $charge2);
			}
			else
			{
				# charge2
	            # find dimentions of the text box for charge2
	            $dims = imagettfbbox(18 , 0 , $font , $charge2 );
	            # set width
	            $width = $dims[2] - $dims[0];
				# check to see if charge1 description fits
				if ($width < 390 && $cfont == 18)
				{
					# find center
			        $center = ceil((400 - $width)/2);
					# write text
			        imagettftext($txtCanvas, $cfont, 0, $center, 95, 5, $font, $charge2);
				}
				# if it doesn't fit cut it down to size 12
				else
				{
					$cfont = 12;
					$dims = imagettfbbox(12 , 0 , $font , $charge2 );
					# set width
			        $width = $dims[2] - $dims[0];
					# find center
			        $center = ceil((400 - $width)/2);
					# write text
			        imagettftext($txtCanvas, $cfont, 0, $center, 95, 5, $font, $charge2);
				}
			}
		}
		#doesn't exist for some reason
		//imageantialias($txtCanvas);
		# copy text canvas onto the image
		imagecopy($canvas, $txtCanvas, 0, 480, 0, 0, 400, 120);
        $imgName = $fullname . ' ' . date('(m-d-Y)');
        $mugStamp = $imgpath;
        # save file
        $check = imagepng($canvas, $mugStamp);
		chmod($mugStamp, 0777); //not working for some reason
        if ($check) {return true;} else { echo 'xfngr4'; return false;}
    }


	public function set_mugpath_test($imagepath)
    {
		$yearpath = preg_replace('/\/week.*/', '', $imagepath);

        # check if year path exists
    	if (!is_dir($yearpath))
    	{
    		# create mugpath if it doesn't exist
    		$oldumask = umask(0);
    		mkdir($yearpath, 0777);
    		umask($oldumask);
    	}
        # check if image path exists
        if (!is_dir($imagepath))
        {
            # create imagepath if it doesn't exist
            $oldumask = umask(0);
            mkdir($imagepath, 0777);
            umask($oldumask);
        }
		return $imagepath;
	}

	public function set_mugpath_old($imagepath)
    {
		$yearpath = preg_replace('/\/week.*/', '', $imagepath);
        # check if year path exists
    	if (!is_dir($yearpath))
    	{
    		# create mugpath if it doesn't exist
    		$oldumask = umask(0);
    		mkdir($yearpath, 0777);
    		umask($oldumask);
    	}
        # check if image path exists
        if (!is_dir($imagepath))
        {
            # create imagepath if it doesn't exist
            $oldumask = umask(0);
            mkdir($imagepath, 0777);
            umask($oldumask);
        }
		return $imagepath;
	}

	public function set_mugpath($imagepath)
    {
    	$imagepath = strtolower($imagepath);
    	$check = preg_match('/\/mugs\/.*\//Uis', $imagepath, $match);
		if ( ! $check)
		{
			return false;
		}
		$statepath = $match[0];
		if (!is_dir($statepath))
		{
			$oldumask = umask(0);
    		mkdir($statepath, 0777);
    		umask($oldumask);
			echo 'executed state';
		}
		$check = preg_match('/\/mugs\/.*\/.*\//Uis', $imagepath, $match);
		if ( ! $check)
		{
			//return false;
		}
		$countypath = $match[0];
		if (!is_dir($countypath))
		{
			$oldumask = umask(0);
    		mkdir($countypath, 0777);
    		umask($oldumask);
			echo 'executed county';
		}
		$yearpath = preg_replace('/\/week.*/', '', $imagepath);
        # check if year path exists
    	if (!is_dir($yearpath))
    	{
    		# create mugpath if it doesn't exist
    		$oldumask = umask(0);
    		mkdir($yearpath, 0777);
    		umask($oldumask);
			echo 'executed year';
    	}
        # check if image path exists
        $imagepath;
        if (!is_dir($imagepath))
        {
            # create imagepath if it doesn't exist
            $oldumask = umask(0);
            mkdir($imagepath, 0777);
            umask($oldumask);
			echo 'executed image path';
        }
		return $imagepath;
	}

	/**
	 * Based on an example by ramdac at ramdac dot org
	 * Returns a multi-dimensional array from a CSV file optionally using the
	 * first row as a header to create the underlying data as associative arrays.
	 * @param string $file Filepath including filename
	 * @param bool $head Use first row as header.
	 * @param string $delim Specify a delimiter other than a comma.
	 * @param int $len Line length to be passed to fgetcsv
	 * @return array or false on failure to retrieve any rows.
	 */
    public function import_csv($file,$head=false,$delim=",",$len=1000) {
	    $return = false;
	    $handle = fopen($file, "r");
	    if ($head)
	    {
	        $header = fgetcsv($handle, $len, $delim);
	    }
	    while (($data = fgetcsv($handle, $len, $delim)) !== FALSE) {
	        if ($head AND isset($header))
	        {
	            foreach ($header as $key=>$heading)
	            {
	                $row[$heading]=(isset($data[$key])) ? $data[$key] : '';
	            }
	            $return[]=$row;
	        }
	        else
	        {
	            $return[]=$data;
	        }
	    }
	    fclose($handle);
	    return $return;
	}


	/**
	* find_week - undocumeted
	*
	* @return void
	* @author
	*/
    public function find_week($timestamp)
	{
		//@HACK - add 10k to timestamp so it will accuratly reflect the day.
		// 		  otherwise it will be one day behind for some reason, not sure why
		$week = date('W', $timestamp) + 1;
		/*
		$timestamp = $timestamp + 10000;
		$start_date = mktime(0,0,0,1,1,date('Y', $timestamp));
		//echo date('m/d/Y h:i:s', $start_date);
		//exit;
		$first_day = date('w', $start_date);
		$week = 1;
		if ($first_day > 2)
		{
			$week = 2;
			$start_date += 86400 * (9-$first_day);
		}
		else if ($first_day < 2)
		{
			$week = 2;
			$start_date += 86400 * (2-$first_day);
		}
		$week += floor(( ($timestamp - $start_date) / (86400 * 7) ));
		 */
		return $week;
	}


	/**
	* charges_check - new check uses the same list as charges abbreviator
	*
	* @author
	*/
	public function charges_check($charges, $list)
	{
		$full_charges_list = array();
		foreach($list as $key => $value)
		{
			$full_charges_list[] = $key;
		}
		# set new_charges array which will contain any charge that isn't found
		$new_charges = array();
		if (is_array($charges))
		{
			# loop through the $charges array
			foreach ($charges as $ncharge)
			{
				$ncharge = trim(strtoupper($ncharge));
				$ncharge = preg_replace('/\"/', '', $ncharge); // remove quotes added by CSV
				//$ncharge = preg_replace('/\//', '', $ncharge); // remove forward slash "/"
				//$ncharge = preg_replace('/\\\/', '', $ncharge); // remove backward slash "\"
				# loop through $list
				$flag = false;
				foreach($full_charges_list as $ocharge)
				{

					$ocharge = trim(strtoupper($ocharge));
					$ocharge = preg_replace('/\"/', '', $ocharge); // remove quotes added by CSV
					//$ocharge = preg_replace('/\//', '', $ocharge); // remove forward slash "/"
					//$ocharge = preg_replace('/\\\/', '', $ocharge); // remove backward slash "\"
					if (preg_replace('/\s/', '', $ocharge) == preg_replace('/\s/', '', $ncharge))
					{
						$flag = true;
						break;
					}
				}
				# check to see if it couldn't find a match for this charge
				# if id didn't, then add it to the $new_charges array
				if ($flag == false)
				{
					$new_charges[] = $ncharge;
				}
			}
			return $new_charges;
		}
		else
		{
			$ncharge = trim(strtoupper($charges));
			$ncharge = preg_replace('/\"/', '', $ncharge); // remove quotes added by CSV
			foreach($full_charges_list as $ocharge)
			{
				$flag = false;
				$ocharge = trim(strtoupper($ocharge));
				$ocharge = preg_replace('/\"/', '', $ocharge); // remove quotes added by CSV
				//$ocharge = preg_replace('/\//', '', $ocharge); // remove forward slash "/"
				//$ocharge = preg_replace('/\\\/', '', $ocharge); // remove backward slash "\"
				if (preg_replace('/\s/', '', $ocharge) == preg_replace('/\s/', '', $ncharge))
				{
					$flag = true;
					break;
				}
			}
			# check to see if it couldn't find a match for this charge
			# if id didn't, then add it to the $new_charges array
			if ($flag == false)
			{
				$new_charges[] = $ncharge;
			}
			return $new_charges;
		}
	}


	/**
	* charges_abbreviator_db
	*
	* @return $charges array of abbreviated charges
	* @author
	*/
	public function charges_abbreviator_db($abbr, $charges)
	{
		# set flag to see if a match was found
		$flag = false;
		$ncharges = array();
		foreach ($charges as $charge)
		{
			foreach ($abbr as $key => $value)
			{
				# strip out any " characters added by csv
				#trim and strtoupper everything just in case
				$key 		= strtoupper(trim($key));
				$value 		= strtoupper(trim($value));
				$charge 	= strtoupper(trim($charge));
				//@bug this could potentially bug if a charge actually has quotes
				$key = preg_replace('/\"/', '', $key);
				#remove any spaces and compare (this will not actually remove spaces from the returned charges array)
				if (preg_replace('/\s/', '', $charge) == preg_replace('/\s/', '', $key))
				{
					// ok so it found a match between the two
					$ncharges[] = $value;
					break;
				}
			}
		}
		#remove any empty values
		foreach($charges as $key => $chg)
		{
			if(empty($chg))
			{
				unset($charges[$key]);
			}
		}
		#remove any empty values
		foreach($ncharges as $key => $ncharge)
		{
			if(empty($ncharge))
			{
				unset($ncharges[$key]);
			}
		}
		if (count($charges) != count($ncharges))
		{
			return false;
		} // ok the count doesn't match so return false
		else
		{
			#remove any empty values
			foreach($ncharges as $key => $ncharge)
			{
				if(empty($ncharge))
				{
					unset($ncharges[$key]);
				}
			}
			return $ncharges;
		}
	}


	/**
	* charges_abbreviator
	*
	* @return $charges array of abbreviated charges
	* @author
	*/
	public function charges_abbreviator($abbr, $charge1, $charge2 = NULL)
	{
		$charge1 = trim($charge1);
		$charge2 = trim($charge2);

		# this gives me a $key => $value array of FULL => ABBR
		# set flag to see if a match was found
		$flag = false;
		foreach ($abbr as $key => $value)
		{
			# strip out any " characters added by csv
			#trim and strtoupper everything just in case
			$key 		= strtoupper(trim($key));
			$value 		= strtoupper(trim($value));
			$charge1 	= strtoupper(trim($charge1));
			if ($charge2) { $charge2 	= strtoupper(trim($charge2)); }
			//@bug this could potentially bug if a charge actually has quotes
			$key = preg_replace('/\"/', '', $key);
			#remove any spaces and compare (this will not actually remove spaces from the returned charges array)
			if (preg_replace('/\s/', '', $charge1) == preg_replace('/\s/', '', $key))
			{
				$charge1 = $value;
				$flag = true;
			}
			if ($charge2)
			{
				if (preg_replace('/\s/', '', $charge2) == preg_replace('/\s/', '', $key))
				{
					$charge2 = $value;
				}
			}
		}
		if ($flag == false) { return false; }
		else
		{

			$charges = array($charge1, $charge2);
			return $charges;
		}
	}


	/**
	* new_charges
	* @TODO: needs work.  Haven't tested, just a prototype so far.
	* @return void
	* @author
	*/
	public function new_charges($ncharges, $csv_order)
	{
		# first open the order_cvs and make it into an array
		$charges_order = file($csv_order);

		$new = array();

		# now loop though it and compare it against the $ncharges array
		foreach ($ncharges as $ncharge)
		{
			# set a flag for a non existing charge to true
			$flag = false;
			foreach ($charges_order as $charge)
			{
				if ($ncharges == $ncharge)
				{
					$flag = true;
					break;
				}
			}
			// ok so if my condition was not met that means this is a new charge
			if ($flag = true)
			{
				$new[] = $ncharge;
			}
		}
		return $new;
	}

	/**
	* charges_prioritizer - Compares two arrays for matches and reorders them based on a csv list
	*
	* @notes 	-=DO NOT USE THIS UNLESS ALREADY CHECKED FOR CHARGES IN THE LIST=-
	* 	     ok I need this to take a list of list or charges as well as a priority list
	*        and compare the two.  If I am checking in my model for all charges then this
	*        will never have a problem.  Just make sure ALL $charges exist in $csv_order
	*
	* @params $csv_order path to csv file (/mugs/<state>/<county>/list/csv_order)
	* @params $charges array of charges pulled directly from the external site
	*
	* @return $mcharges array consiting of two charges arranged in order of priority
	* @return false if $mcharges is empty or only has one
	*/
    public function charges_prioritizer_OLD($list, $charges, $scrape = null)
    {
    	# get just the full charge names in an array
    	$full_charges_list = array();
		foreach($list as $key => $value)
		{
			$full_charges_list[] = $key;
		}
    	$charges = array_merge($charges); //reset keys if its not already done
    	$mcharges = array(); //set mcharges array
		$charges_ordered = array(); //set charges_ordered array
    	foreach ($charges as $key => $value)
        {
        	$value = trim(strtoupper($value));
			$value = preg_replace('/\"/', '', $value); // remove quotes
			//$value = preg_replace('/\//', '', $value); // remove forward slash "/"
			//$value = preg_replace('/\\\/', '', $value); // remove backward slash "\"
            // ok so basically I need to compare arrays and build a $charges_ordered array
            // with the keys according to the keys from $csv_order
            foreach ($full_charges_list as $key2 => $value2) // loop through priority list
            {
				$value2 = trim(strtoupper($value2)); //make sure everything is uppercase/trimmed
				$value2 = preg_replace('/\"/', '', $value2); // remove quotes added by CSV
				//$value2 = preg_replace('/\//', '', $value2); // remove forward slash "/"
				//$value2 = preg_replace('/\\\/', '', $value2); // remove backward slash "\"
                # strip any spaces out and compare the two values
                if (preg_replace('/\s/', '', $value2) == preg_replace('/\s/', '', $value))
                {
                    $charges_ordered[$key2] = $value2;
                }
            }
        }
        # sort charges by key
        ksort($charges_ordered);
        $count = 1;
		if (!empty($charges_ordered)) // make sure $mcharges isn't empty
		{
			if (count($charges_ordered) > 1) // take just the fist two
	        {
	            foreach ($charges_ordered as $key => $value)
	            {
	                $mcharges[] = $value;
	                if ($count == 2) { break; }
	                $count++;
	            }
				return $mcharges;
	        }
			else
			{
				echo 'in here1';
				exit;
				//mail('bustedreport@gmail.com', 'conditional was triggered on line 951 in scrape model', "scrape.php model conditional \n\nif (count($charges_ordered) > 1)\n\n was triggered");
				return false;
			}
		}
		else
		{
			echo 'in here1';
				exit;
			//mail('bustedreport@gmail.com', 'conditional was triggered on line 949 in scrape model', "scrape.php model conditional \n\n(!empty($charges_ordered))\n\n was triggered");
			return false;
		}
    }


	/**
	* charges_prioritizer2 - the new one that uses the same list as the abbreviator
	*
	* @return void
	* @author
	*/
	public function charges_prioritizer2_OLD($csv_order, $charges, $scrape = null)
    {
    	$csv = $this->import_csv($csv_order, true);
		$charges_order = array();
		foreach($csv as $key => $value)
		{
			$charges_order[] = $value['FULL'];
		}

    	$charges = array_merge($charges); //reset keys if its not already done
    	$mcharges = array(); //set mcharges array
		$charges_ordered = array(); //set charges_ordered array
    	foreach ($charges as $key => $value)
        {
        	$value = trim(strtoupper($value));
			$value = preg_replace('/\"/', '', $value); // remove quotes
			//$value = preg_replace('/\//', '', $value); // remove forward slash "/"
			//$value = preg_replace('/\\\/', '', $value); // remove backward slash "\"
            // ok so basically I need to compare arrays and build a $charges_ordered array
            // with the keys according to the keys from $csv_order
            foreach ($charges_order as $key2 => $value2) // loop through priority list
            {
				$value2 = trim(strtoupper($value2)); //make sure everything is uppercase/trimmed
				$value2 = preg_replace('/\"/', '', $value2); // remove quotes added by CSV
				//$value2 = preg_replace('/\//', '', $value2); // remove forward slash "/"
				//$value2 = preg_replace('/\\\/', '', $value2); // remove backward slash "\"
                # strip any spaces out and compare the two values
                if (preg_replace('/\s/', '', $value2) == preg_replace('/\s/', '', $value))
                {
                    $charges_ordered[$key2] = $value2;
                }
            }
        }
        # sort charges by key
        ksort($charges_ordered);
        $count = 1;
		if (!empty($charges_ordered)) // make sure $mcharges isn't empty
		{
			if (count($charges_ordered) > 1) // take just the fist two
	        {
	            foreach ($charges_ordered as $key => $value)
	            {
	                $mcharges[] = $value;
	                if ($count == 2) { break; }
	                $count++;
	            }
				return $mcharges;
	        }
			else
			{
				mail('bustedreport@gmail.com', 'conditional was triggered on line 951 in scrape model', "scrape.php model conditional \n\nif (count($charges_ordered) > 1)\n\n was triggered");
				return false;
			}
		}
		else
		{
			mail('bustedreport@gmail.com', 'conditional was triggered on line 949 in scrape model', "scrape.php model conditional \n\n(!empty($charges_ordered))\n\n was triggered");
			return false;
		}
    }


	/**
	* charges_prioritizer2 - the new one that uses the same list as the abbreviator
	*
	* @return void
	* @author
	*/
	public function charges_prioritizer($list, $charges, $scrape = null)
    {
    	# get just the full charge names in an array
    	$full_charges_list = array();
		foreach($list as $key => $value)
		{
			$full_charges_list[] = $key;
		}
    	$charges = array_merge($charges); //reset keys if its not already done
    	$mcharges = array(); //set mcharges array
		$charges_ordered = array(); //set charges_ordered array
    	foreach ($charges as $key => $value)
        {
        	$value = trim(strtoupper($value));
			$value = preg_replace('/\"/', '', $value); // remove quotes
			//$value = preg_replace('/\//', '', $value); // remove forward slash "/"
			//$value = preg_replace('/\\\/', '', $value); // remove backward slash "\"
            // ok so basically I need to compare arrays and build a $charges_ordered array
            // with the keys according to the keys from $csv_order
            foreach ($full_charges_list as $key2 => $value2) // loop through priority list
            {
				$value2 = trim(strtoupper($value2)); //make sure everything is uppercase/trimmed
				$value2 = preg_replace('/\"/', '', $value2); // remove quotes added by CSV
				//$value2 = preg_replace('/\//', '', $value2); // remove forward slash "/"
				//$value2 = preg_replace('/\\\/', '', $value2); // remove backward slash "\"
                # strip any spaces out and compare the two values
                if (preg_replace('/\s/', '', $value2) == preg_replace('/\s/', '', $value))
                {
                    $charges_ordered[$key2] = $value2;
                }
            }
        }
        # sort charges by key
        ksort($charges_ordered);
        $count = 1;
		if (!empty($charges_ordered)) // make sure $mcharges isn't empty
		{
			if (count($charges_ordered) > 1) // take just the fist two
	        {
	            foreach ($charges_ordered as $key => $value)
	            {
	                $mcharges[] = $value;
	                if ($count == 2) { break; }
	                $count++;
	            }
				return $mcharges;
	        }
			else
			{
				//mail('bustedreport@gmail.com', 'conditional was triggered on line 951 in scrape model', "scrape.php model conditional \n\nif (count($charges_ordered) > 1)\n\n was triggered");
				return false;
			}
		}
		else
		{
			//mail('bustedreport@gmail.com', 'conditional was triggered on line 949 in scrape model', "scrape.php model conditional \n\n(!empty($charges_ordered))\n\n was triggered");
			return false;
		}
    }


	/**
	* keyword_prioritizer
	*
	* @sudo takes a list of keywords from a csv file compares it against a list of charges
	*       if it finds one of the charges in the list then build a new array with the
	*       index keys of the keyword list so they are ordered properly.  If it only finds
	*       one in the list, then just return that (with keyword list index key).
	*       If it finds two keywords in the same
	*
	*
	* @return $mcharges array consiting of two charges in order
	*/
	public function keyword_prioritizer($keyword_list, $charges)
	{
		$charges = array_merge($charges); //reset keys if its not already done
        $keywords = file($keyword_list); // this gives me my priority list
		$mcharges = array();
		foreach ($charges as $key => $charge)
        {
        	$charge = strtoupper(trim($charge));
        	foreach ($keywords as $key2 => $keyword)
			{
				$keyword = strtoupper(trim($keyword));
				$pos = strpos($charge, $keyword);
				if ($pos !== false)
				{
					$mcharges[$key2] = $charge;
					break;
				}
			}
		}
		ksort($mcharges); // sort by keys
		$mcharges = array_merge($mcharges);
		// remove duplicates (incase it found two of the keywords in the same string)
		// this technique may cause problems later
		// $mcharges = array_unique($mcharges);
		if (empty($mcharges))
		{
			return false;
		}
		else
		{
			return $mcharges;
		}
	}


	/**
	* height_conversion - takes a height variable where the first number on the left is feet,
	*			   		  and the following digits are the inches
	*
	* @params $height   - a height value of either x' x" or xxx (ft. in.)
	* @return $height 	- (int)height converted to inches
	*
	*/
	public function height_conversion($height)
	{
		$height = preg_replace('/[^0-9]/', '', $height); // rip out ALL but numbers
		$feet 	= substr($height, 0, 1); // get the first number (feet)
		$inches = substr($height, 1, 2); // get everything after the first number (inches)
		$height = ($feet * 12) + $inches; // set height
		return $height;
	}


	/**
	* getTime - undocumented
	*
	* @todo document this
	* @return void
	*/
	public function getTime()
    {
	    $a = explode (' ',microtime());
	    return(double) $a[0] + $a[1];
	}


	/**
     * Converts a simpleXML element into an array. Preserves attributes and everything.
     * You can choose to get your elements either flattened, or stored in a custom index that
     * you define.
     * For example, for a given element
     * <field name="someName" type="someType"/>
     * if you choose to flatten attributes, you would get:
     * $array['field']['name'] = 'someName';
     * $array['field']['type'] = 'someType';
     * If you choose not to flatten, you get:
     * $array['field']['@attributes']['name'] = 'someName';
     * _____________________________________
     * Repeating fields are stored in indexed arrays. so for a markup such as:
     * <parent>
     * <child>a</child>
     * <child>b</child>
     * <child>c</child>
     * </parent>
     * you array would be:
     * $array['parent']['child'][0] = 'a';
     * $array['parent']['child'][1] = 'b';
     * ...And so on.
     * _____________________________________
     * @param simpleXMLElement $xml the XML to convert
     * @param boolean $flattenValues    Choose wether to flatten values
     *                                    or to set them under a particular index.
     *                                    defaults to true;
     * @param boolean $flattenAttributes Choose wether to flatten attributes
     *                                    or to set them under a particular index.
     *                                    Defaults to true;
     * @param boolean $flattenChildren    Choose wether to flatten children
     *                                    or to set them under a particular index.
     *                                    Defaults to true;
     * @param string $valueKey            index for values, in case $flattenValues was set to
            *                            false. Defaults to "@value"
     * @param string $attributesKey        index for attributes, in case $flattenAttributes was set to
            *                            false. Defaults to "@attributes"
     * @param string $childrenKey        index for children, in case $flattenChildren was set to
            *                            false. Defaults to "@children"
     * @return array the resulting array.
     */
    public function simpleXMLToArray($xml,
                    $flattenValues=true,
                    $flattenAttributes = true,
                    $flattenChildren=true,
                    $valueKey='@value',
                    $attributesKey='@attributes',
                    $childrenKey='@children'){

        $return = array();
        if(!($xml instanceof SimpleXMLElement)){return $return;}
        $name = $xml->getName();
        $_value = trim((string)$xml);
        if(strlen($_value)==0){$_value = null;};

        if($_value!==null){
            if(!$flattenValues){$return[$valueKey] = $_value;}
            else{$return = $_value;}
        }

        $children = array();
        $first = true;
        foreach($xml->children() as $elementName => $child){
            $value = $this->simpleXMLToArray($child, $flattenValues, $flattenAttributes, $flattenChildren, $valueKey, $attributesKey, $childrenKey);
            if(isset($children[$elementName])){
                if($first){
                    $temp = $children[$elementName];
                    unset($children[$elementName]);
                    $children[$elementName][] = $temp;
                    $first=false;
                }
                $children[$elementName][] = $value;
            }
            else{
                $children[$elementName] = $value;
            }
        }
        if(count($children)>0){
            if(!$flattenChildren){$return[$childrenKey] = $children;}
            else{$return = array_merge($return,$children);}
        }

        $attributes = array();
        foreach($xml->attributes() as $name=>$value){
            $attributes[$name] = trim($value);
        }
        if(count($attributes)>0){
            if(!$flattenAttributes){$return[$attributesKey] = $attributes;}
            else{$return = array_merge($return, $attributes);}
        }

        return $return;
    }

	/**
	* bid_dupe_check - Runs after every scrape to check fo duplicate booking_ids.
	* 			  If any are found, both are deleted.
	*
	* @return void
	* @author
	*/
	function bid_dupe_check($county = null)
	{
		//$offender = Mango::factory('offender', array('booking_id' => 'abc_001'))->load(false)->as_array(false);
		//$this->print_r2($offender);
		//exit;
		if ($county)
		{

	    	$scrape    = new Model_Scrape;
			$start = $scrape->getTime();
	        $offenders = Mango::factory('offender', array('scrape' => $county))->load(false)->as_array(false);
	        $bid_dupes = array();

			//$this->print_r2($offenders);
			//exit;
	        foreach($offenders as $offender)
	        {
	            //$this->print_r2($offender);
				//echo $offender['booking_id'];
	            $dupe_check = Mango::factory('offender', array('booking_id' => $offender['booking_id']))->load(false)->as_array(false);
	            //$this->print_r2($dupe_check);
	            $count = 0;

	            if (count($dupe_check) > 1)
	            {
	                // this means I found a duplicate
	                // loop through and delete each one individually
	                // email me which ones were deleted
	                $email = "Duplicate Offender Booking_ids:\n";
	                foreach($dupe_check as $dupe)
					{
						$dupe_offender = Mango::factory('offender', array('_id' => $dupe['_id']))->load();
						$email .= "\nFirstname: $dupe_offender->firstname\n";
						$email .= "Lastname: $dupe_offender->lastname\n";
						$email .= "Booking ID: $dupe_offender->booking_id\n";
						$email .= "County: $dupe_offender->scrape\n";
						$scrapetime = date('m/d/Y h:i:s', $dupe_offender->scrape_time);
						$email .= "Scrape Time: $scrapetime\n";
						$email .= "\n#####################################\n";
						# check if image exists
						if (file_exists($dupe_offender->image . '.png'))
						{
							# delete it
							unlink($dupe_offender->image . '.png');
						}
						$dupe_offender->delete();
					}
					mail('bustedreport@gmail.com', 'Dupes found in ' . $county, $email);
	            }
	        }
        } else { return false; }
	}


	/**
	* profile_dupe_check - used directly after scrape has finished to flag duplicate offenders
	* 			   based on firstname, lastname and booking_date.
	*
	* @return true - on success
	* @return false - on failure
	* @author Winter King
	*/
	public function profile_dupe_check($county = null)
    {
    	if ($county)
		{
	    	$scrape    = new Model_Scrape;
			$start = $scrape->getTime();
	        $offenders = Mango::factory('offender', array('scrape' => $county))->load(false)->as_array(false);
	        $profile_dupes = array();
	        foreach($offenders as $offender)
	        {
	        	if (!isset($offender['firstname']) OR !isset($offender['lastname']) OR !isset($offender['booking_date']))
				{
					$bad_offender = Mango::factory('offender', array('_id' => $offender['_id']))->load();
					$bad_offender->delete();
				}
				else
				{
					//$dupe_check = Mango::factory('offender', array('scrape' => , 'firstname' => $offender['firstname'], 'lastname' => $offender['lastname'], 'booking_date' => $offender['booking_date']))->load(array('limit' => FALSE, 'criteria' => array('status' => array('$ne' => 'accepted'))))->as_array(false);
		            $dupe_check = Mango::factory('offender', array('scrape' => $county, 'firstname' => $offender['firstname'], 'lastname' => $offender['lastname'], 'booking_date' => $offender['booking_date']))->load(false)->as_array(false);
		            $count = 0;
		            if (count($dupe_check) > 1)
		            {
		                // this means I found a duplicate
		                // build my $profile_dups array

		                $dupes = array();
		                foreach($dupe_check as $dupe)
		                {
		                    $dupes[] = $dupe['_id'];
		                }
		                $profile_dupes[] = $dupes;
		            }
				}
	        }

	        // get rid of duplicates sets
	        $profile_dupes = array_map("unserialize", array_unique(array_map("serialize", $profile_dupes)));
	        foreach($offenders as $offender)
	        {
	            //$this->print_r2($offender);
	            foreach($profile_dupes as $key => $value)
	            {
	                foreach ($value as $key2 => $value2)
	                {
	                    if ($offender['_id'] == $value2)
	                    {
	                        $profile_dupes[$key][$key2] = $offender;
	                    }
	                }
	            }
	        }
			// foreach ($profile_dupes as $dupe_set)
			// {
			// 	$set = array();
			// 	foreach($dupe_set as $dupe_profile)
			// 	{
			// 		$set[] = $dupe_profile['_id'];
			// 	}
			// 	sort($set);
			// 	$dupes_object = Mango::factory('dupe', array('county' => $county, 'ids' => $set))->load();
			// 	if (!$dupes_object->loaded())
			// 	{
			// 		# ok this means we have new dupes so immediately set status => denied in the offenders model
			// 		foreach($set as $id)
			// 		{
			// 			//4d9237292eab7311450000b8
			// 			//4d930c182eab737626000004
			// 			$offender = Mango::factory('offender', array('_id' => $id))->load();
			// 			$offender->status = 'denied';
			// 			$offender->update();
			// 		}
			// 		# now add the new dupe set to the database
			// 		$dupes_object = Mango::factory('dupe')->create();
			// 		$dupes_object->county = $county;
			// 		$dupes_object->ids  = $set;
			// 		$dupes_object->update();
			// 	}
			// }
			return true;
			$end = $scrape->getTime();
			//echo "Time taken = ".number_format(($end - $start),2)." secs\n";
	     	//$this->print_r2($profile_dupes);
			//exit;
	        //$this->template = View::factory('admin/dupes-panel')->bind('profile_dupes', $profile_dupes);
        } else { return false; }
    }

	/**
	* image_dupe_check - recursively looks through entire county directory and removes any duplicate images
	*
	* @return void
	* @author
	*/
	function image_dupe_check($state, $county)
	{

		### Config
	    $path = '/mugs/'.$state.'/'.$county.'/';        # Folder to search for duplicates

		### Misc vars
	    $time = microtime(true);
	    $files = $dupes = 0;

		### Dir Iteration
	    $dh = new RecursiveDirectoryIterator($path);
		$objects1 = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);
		$objects2 = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);


		foreach($objects1 as $image1 => $object)
		{
			$check = preg_match('/\.png/Uis', $image1, $match);
			if ($check)
			{
				//echo '<hr>TOP IMAGE: ' . $image1 . "<hr>";
				foreach($objects2 as $image2 => $object)
				{
					$check = preg_match('/\.png/Uis', $image2, $match);
					if ($check)
					{
						if (filesize($image2) > 4096)
						{
							if ( (filesize($image1) == filesize($image2)) && ($image1 != $image2) )
							{
								echo '<hr/>';
								$offender_image1 = preg_replace('/.*\//', '', $image1);
								$offender_image2 = preg_replace('/.*\//', '', $image2);
								echo $offender_image1 . '<br/>';
								echo $offender_image2 . '<br/><hr/>';
								@unlink($image1);
								@unlink($image2);
								$offender1 = Mango::factory('offender', array('image' => $offender_image1))->load();
								$offender1->delete();
								$offender2 = Mango::factory('offender', array('image' => $offender_image2))->load();
								$offender2->delete();
								//echo 'img1: ' . $image1 . '<br/>img2: ' . $image2 . '<br/>';
							}
						}
					}
				}
			}
		}
		exit;
	}



	function too_long_charges($charge, $max_str_length)
	{
		$check = preg_match('/\s/', $charge);
		if($check)
		{
			$charges = array();
			if(strlen($charge) > $max_str_length)
			{
				$words = array();
				$words = explode(' ', $charge);
				$word_count = count($words) - 1;
				$charges = array();
				$charges[0] = '';
				$charges[1] = '';
				for($i = 0; $i <= $word_count; $i++)
				{
					if(strlen($charges[0] . $words[$i]) <= $max_str_length)
					{
						$charges[0] = $charges[0] .' '. $words[$i];
					}
					else {
						if(strlen($charges[1] . $words[$i]) <= $max_str_length)
						{
							$charges[1] = $charges[1] .' '. $words[$i];
						}
					}
				}
				return $charges;
			}
		}
		else
		{
			return false;
		}
	}


	/**
	* delete_alachua_in_lexington - utility
	*
	* @return void
	* @author
	*/
	function delete_alachua_in_lexington($county = null)
	{
		### Config
	    $path = "/mugs/kentucky/lexington/";        # Folder to search for duplicates

		### Misc vars
	    $time = microtime(true);
	    $files = $dupes = 0;

		### Dir Iteration
	    $dh = new RecursiveDirectoryIterator($path);
		$objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST);
		foreach($objects as $name => $object){
			$check = preg_match('/\_alachua\_/Uis', $name, $match);
			if ($check)
			{
				unlink($name);
			}
		    //$this->print_r2($object);
		}
		exit;
	}

	/**
	* face_crop - used to intelligently crop an image with the face centered on X axis
	*
	* @todo maybe add some more parameter options
	* @params $imagepath - path to image which needs cropped
	* @return TRUE on successful detection
	* @return FALSE on failed detection
	* @author Winter King
	*/
	public function face_crop($imagepath)
	{
		$scrape = new Model_Scrape;
		$haarpath = 'public/includes/facedetect/haarcascade_frontalface_alt.xml';
		//$imagepath = '/mugs/ohio/bourbon/2011/week_16/(04-16-2011)_CROISSANT_JOHN_bourbon_2007010352.png';
		//$imagepath = '/mugs/kentucky/lexington/2011/week_17/(04-21-2011)_ROARK_STEVEN_lexington_143138.png';
		$coords = array();
		$total  = array();
		$total 	= face_count($imagepath, $haarpath);
		$coords = face_detect($imagepath, $haarpath);

		//$im = $scrape->load_jpg($imagepath);
		//$pink = imagecolorallocate($im, 255, 105, 180);
		$dims = getimagesize($imagepath);

		$width = $dims[0];

		# get the image width
		//704px × 480
		if(count($coords) > 0) // face was detected
		{
			$x = $coords[0]['x']; // x coordinates for left side of face
			$w = $coords[0]['w']; // width of the face
			$diff = (400 - $w)/2;
			$xoffset = $x - $diff;
			if (($xoffset + 400) > $width) // ok this means that the face is too close to the right
			{
				$diff = ($xoffset + 400) - $width;
				$xoffset = ($xoffset - $diff);
			}
			$img = Image::factory($imagepath);
		    $img->crop(400, 480, $xoffset)->save();
			return true;
		}
		else
		{
			return false;
		}
	}

	public function gender_mapper($sex)
	{
		if (trim(strtoupper($sex)) == 'M')
		{
			$sex = 'MALE';
		}
		else if (trim(strtoupper($sex)) == 'F')
		{
			$sex = 'FEMALE';
		}
		$sex = trim(strtoupper($sex));
		return $sex;
	}

	/**
	* race_mapper 		- Similar to the charge abbreviator this takes a race variable and compares it to the
	*			   		  and the following digits are the feet
	*
	* @params $race  	- race code from the website
	* @return $race 	- race converted from the mapping file (race.csv)
	*
	*/
	public function race_mapper($race)
	{
		$csv_races 	= '/mugs/races.csv';
		$race 		= trim($race);
		$csv = $this->import_csv($csv_races, true);


		foreach ($csv as $value)
		{
			$abbr[trim(preg_replace('/\"/', '', $value['FULL']))] = trim(preg_replace('/\"/', '', $value['ABBR'])); // trim and remove any quotes added by csv
		}
		# this gives me a $key => $value array of FULL => ABBR
		# set flag to see if a match was found
		$flag = false;
		foreach ($abbr as $key => $value)
		{
			# strip out any " characters added by csv
			#trim and strtoupper everything just in case
			$key 		= strtoupper(trim($key));
			$value 		= strtoupper(trim($value));
			$race		= strtoupper(trim($race));
			//@bug this could potentially bug if a charge actually has quotes
			$key = preg_replace('/\"/', '', $key);
			#remove any spaces and compare (this will not actually remove spaces from the returned charges array)
			if (preg_replace('/\s/', '', $race) == preg_replace('/\s/', '', $key))
			{
				$race = $value;
				$flag = true;
			}
		}
		if ($flag == false) { return false; }
		else
		{
			return $race;
		}
	}




	/**
	* state_mapper - used to turn a state abbreviation into the full version
	*
	* @return string - full name of state
	* @author Winter King
	*/
	function state_mapper($abbr)
	{
		$state_list = array('AL'=>"Alabama",
            'AK'=>"Alaska",
            'AZ'=>"Arizona",
            'AR'=>"Arkansas",
            'CA'=>"California",
            'CO'=>"Colorado",
            'CT'=>"Connecticut",
            'DE'=>"Delaware",
            'DC'=>"District Of Columbia",
            'FL'=>"Florida",
            'GA'=>"Georgia",
            'HI'=>"Hawaii",
            'ID'=>"Idaho",
            'IL'=>"Illinois",
            'IN'=>"Indiana",
            'IA'=>"Iowa",
            'KS'=>"Kansas",
            'KY'=>"Kentucky",
            'LA'=>"Louisiana",
            'ME'=>"Maine",
            'MD'=>"Maryland",
            'MA'=>"Massachusetts",
            'MI'=>"Michigan",
            'MN'=>"Minnesota",
            'MS'=>"Mississippi",
            'MO'=>"Missouri",
            'MT'=>"Montana",
            'NE'=>"Nebraska",
            'NV'=>"Nevada",
            'NH'=>"New Hampshire",
            'NJ'=>"New Jersey",
            'NM'=>"New Mexico",
            'NY'=>"New York",
            'NC'=>"North Carolina",
            'ND'=>"North Dakota",
            'OH'=>"Ohio",
            'OK'=>"Oklahoma",
            'OR'=>"Oregon",
            'PA'=>"Pennsylvania",
            'RI'=>"Rhode Island",
            'SC'=>"South Carolina",
            'SD'=>"South Dakota",
            'TN'=>"Tennessee",
            'TX'=>"Texas",
            'UT'=>"Utah",
            'VT'=>"Vermont",
            'VA'=>"Virginia",
            'WA'=>"Washington",
            'WV'=>"West Virginia",
            'WI'=>"Wisconsin",
            'WY'=>"Wyoming");
    	foreach ($state_list as $key => $value)
    	{
    		if ($abbr == $key)
			{
				return $value;
			}
    	}
		return false;
	}

	function set_county_path($state, $county)
	{
		$fullpath = '/mugs/' . $state . '/' . strtolower($county);
		if(!is_dir($fullpath))
		{
			$oldumask = umask(0);
    		mkdir($fullpath, 0777);
    		umask($oldumask);
		}
		return $fullpath;
	}
}