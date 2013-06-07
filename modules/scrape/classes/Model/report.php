<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Model_Report - Mango database model used for scrape reporting 
 *
 * @package scrape
 * @author  Winter King
 */
//new model
 
class Model_Report extends Mango {

    protected $_fields = array(
        'scrape'         => array('type'=>'string','required'=>TRUE,'min_length'=>1,'max_length'=>255),
        'total'          => array('type'=>'int','required'=>FALSE,'min_length'=>0,'max_length'=>127),
        'successful'     => array('type'=>'int','required'=>FALSE,'min_length'=>0,'max_length'=>127),            
        'failed'         => array('type'=>'int','required'=>FALSE,'min_length'=>0,'max_length'=>127),
        'new_charges'    => array('type'=>'int','required'=>FALSE,'min_length'=>0,'max_length'=>127),
        'bad_images'     => array('type'=>'int','required'=>FALSE,'min_length'=>0,'max_length'=>127),
        'exists'         => array('type'=>'int','required'=>FALSE,'min_length'=>0,'max_length'=>127),
        'other'          => array('type'=>'int','required'=>FALSE,'min_length'=>0,'max_length'=>127),
        'start_time'     => array('type'=>'int','required'=>FALSE,'min_length'=>0,'max_length'=>127),
        'stop_time'      => array('type'=>'int','required'=>FALSE,'min_length'=>0,'max_length'=>127),
        'time_taken'     => array('type'=>'int','required'=>FALSE,'min_length'=>0,'max_length'=>127),
        'week'           => array('type'=>'int','required'=>FALSE,'min_length'=>0,'max_length'=>127),
        'year'           => array('type'=>'int','required'=>FALSE,'min_length'=>0,'max_length'=>127),
        'finished'       => array('type'=>'int','required'=>FALSE,'min_length'=>0,'max_length'=>1),
    	'info'			 => array('type'=>'string','required'=>FALSE,'min_length'=>0,'max_length'=>255)
	);
    protected $_db = 'busted'; 
}


// old model
/*
class Model_Report extends Mango {
    protected $_fields = array(
        'scrape'         => array(
            'type'     => 'string',
            'required' => false,
            'min_length' => 1,
            'max_length' => 127,
        ),  
        'week'         => array(
            'type'     => 'int',
            'required' => false,
            'min_length' => 1,
            'max_length' => 127,
        ),
        'year'         => array(
            'type'     => 'int',
            'required' => false,
            'min_length' => 1,
            'max_length' => 127,
        ),
        'total'         => array(
            'type'     => 'int',
            'required' => false,
            'min_length' => 1,
            'max_length' => 127,
        ),
        'successful'     => array(
            'type'       => 'int',
            'min_length' => 1,
            'max_length' => 127,
        ),
        'new_charges'    => array(
            'type'       => 'array',
            'required'  => false,
            'min_length' => 1,
            'max_length' => 127,
        ),
    );
    protected $_db = 'busted';
}
 * 
 */