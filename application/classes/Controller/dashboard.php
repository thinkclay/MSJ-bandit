<?php defined('SYSPATH') or die('No direct script access.');
  
class Controller_Dashboard extends Controller_Private 
{
	public $auth_required = TRUE;
  
  	public function before()
	{
		parent::before(); 
		$this->template->view = View::factory('pages/home');
  		$this->template->title 	 = 'Mugshot Junkie Dashboard';
		$this->template->h1		 = 'Mugshot Junkie Dashboard';
		
	}
	
	public function action_index()
	{
	}
} 
