<?php if( isset($offenders) ) : ?>
<?php foreach ( $offenders as $offender ) : ?>
	<?php $formatted_image = Image::factory($offender['image'])->crop(440, 550, null, 50)->resize(170, 213); ?>
	<a href="/inmate/mugshot/<?php echo $offender['booking_id']; ?>">
		<img 
			class="mug <?php echo strtolower($offender['gender']); ?>" 
			src="data:image/png;base64,<?php echo base64_encode($formatted_image->render()); ?>" />			
	</a>
<?php endforeach; ?>
<?php endif; ?>