<?php defined('SYSPATH') or die('No direct script access.');
 
class Task_Bandit extends Minion_Task
{
    protected $_options = [
        'county' => NULL,
        'state'  => NULL,
    ];
 
    /**
     * This task will run our scrapes from command line in a more secure format
     *
     * @return null
     */
    protected function _execute(array $params)
    {
        $state  = ucwords($this->_options['state']);
        $county = ucwords($this->_options['county']);

    	$class = 'Model_'.$state.'_'.$county;
    	$scrape = new $class;

    	$scrape->scrape();
    }
    
    
    public function build_validation(Validation $validation)
    {
        return parent::build_validation($validation)
            ->rule('county', 'not_empty') 
            ->rule('state', 'not_empty');
    }
}