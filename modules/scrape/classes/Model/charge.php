<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Model_Charge - Mango database model used for storing charges
 *
 * @package scrape
 * @author  Winter King
 */
class Model_Charge extends Mango {

	protected $_fields = array(
	 	'charge'    => array('type'=>'string','required'=>TRUE,'min_length'=>0,'max_length'=>255),
		'abbr'      => array('type'=>'string','required'=>FALSE,'min_length'=>0,'max_length'=>255),
		'county'	=> array('type'=>'string','required'=>FALSE,'min_length'=>0,'max_length'=>127), // this needs removed eventually
		'scrape'    => array('type'=>'string','required'=>FALSE,'min_length'=>0,'max_length'=>127),
		'order'     => array('type'=>'int','required'=>TRUE,'min_length'=>0,'max_length'=>127),
		'new'       => array('type'=>'int','required'=>TRUE,'min_length'=>0,'max_length'=>1)
	);
	protected $_db = 'busted'; //don't use default db config
}