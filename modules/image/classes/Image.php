<?php defined('SYSPATH') or die('No direct script access.');

abstract class Image extends Kohana_Image 
{	
	public static function get_mugshot ( $args )
	{		
		$image = Image::factory($args['path']);
		
		if ( isset($args['crop']) )
			$image = $image->crop($args['crop_w'], $args['crop_h'], null, $args['crop_q']);
		
		if ( isset($args['resize']) )
			$image = $image->resize($args['resize_w'], $args['resize_h']);
			
		if ( isset($args['watermark']) ) 
			$watermark = Image::factory('public/images/watermark.png');
			$image->watermark($watermark, $args['watermark_y'], $args['watermark_x']);
			
		return $image->render();
	}
}
