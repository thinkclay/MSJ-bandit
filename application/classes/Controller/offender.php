<?php defined('SYSPATH') or die('No direct script access.');
  
class Controller_Offender extends Controller 
{
	public function action_random ( $booking_id = null )
	{
		if ( $_GET )
			$criteria = $_GET;
		
		elseif ( $_POST )
			$criteria = $_POST;
			
		else
			$criteria = array();
					
		$offender = Mango::factory('offender')->load(1, array(), rand(1, 100), array(), $criteria)->as_array(false);	
		
		$image = Image::factory($offender['image']);
		$watermark = Image::factory('public/images/watermark.png');
		$image->watermark($watermark, 130, 15);
		$offender['image'] = base64_encode($image->render());

		$this->request->response = json_encode($offender);
	}
	
	
	/**
	 * Public Action - Rate
	 * 
	 * @description		Rate the user 1-10 on 'hot or not' with 10 being hot
	 * @param			$rating; Set the numerical rating via routes/public url
	 */
	public function action_rate ()
	{
		if ( $_POST )
		{
			$offender = Mango::factory('offender', array('booking_id' => $_POST['id']))->load();
			$rating = $offender->rating->as_array(false);
			$rating[] .= $_POST['rating'];
			$offender->rating = $rating;
			$offender->update();			
		}
	}
	
	
	/**
	 * Public Action - Slider Mugshot
	 * 
	 * @description		Get the offender image for the Slider
	 */
	public function action_slider_mugshot ( $booking_id )
	{
		$offender = Mango::factory('offender', array('booking_id' => $booking_id))->load()->as_array(false);
        
        $this->request->headers['Content-Type'] = 'image/png';
        $this->request->response = Image::get_mugshot(
        	array(
        		'path' 			=> $offender['image'],
        		'crop'			=> true,
        		'crop_w'		=> 344,
        		'crop_h'		=> 430,
        		'crop_q'		=> 50,
        		'resize'		=> true,
        		'resize_w'		=> 180,
        		'resize_h'		=> null,
        		'watermark'		=> true,
        		'watermark_x' 	=> 5,
        		'watermark_y'	=> 30
        	)
        );
	}
	
	/**
	 * Public Action - Full Mugshot
	 * 
	 * @description		Get the offender image and return it with the proper MIME
	 */
	public function action_full_mugshot ( $booking_id )
	{
		$offender = Mango::factory('offender', array('booking_id' => $booking_id))->load()->as_array(false);
        
        $this->request->headers['Content-Type'] = 'image/png';
        $this->request->response = Image::get_mugshot(
        	array(
        		'path' 			=> $offender['image'],
        		'resize'		=> true,
        		'resize_w'		=> 280,
        		'resize_h'		=> 420,
        		'watermark'		=> true,
        		'watermark_x' 	=> 20,
        		'watermark_y'	=> 120
        	)
        );
	}
	
	
	/**
	 * Public Action - Details
	 * 
	 * @description		Get the detials of an offender from the Mongo Document
	 * @param			$booking_id; Pass the booking id, which is structured as county_1234
	 */
	public function action_details ( $booking_id )
	{	
		$offender = Mango::factory('offender', array('booking_id' => $booking_id))->load()->as_array(false);
		$view = View::factory('widgets/search/modal');
        $view->offender = $offender;
        $this->request->response = $view->render();
	}
}