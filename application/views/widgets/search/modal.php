<?php if( isset($offender) ) : ?>
	<?php $formatted_image = Image::factory($offender['image'])->crop(440, 550, null, 50)->resize(250, 313); ?>
	<div class="mugshot"><img src="data:image/png;base64,<?php echo base64_encode($formatted_image->render()); ?>" /></div>

	<?php if ( isset($offender['firstname']) AND isset($offender['lastname']) ) : ?>
	<h3><?php echo ucfirst(strtolower($offender['firstname'])).' '.ucfirst(strtolower($offender['lastname'])); ?></h3>
	<?php endif; ?>
			
	<table class="demographic">
		<?php if ( isset($offender['age']) ) : ?>
		<tr>
			<td><strong>Age:</strong></td>
			<td><?php echo ucfirst(strtolower($offender['age'])); ?>
			
			<?php if ( isset($offender['height']) ) : ?>
			<td><strong>Height:</strong></td>
			<td><?php echo $offender['height']; ?></td>
			<?php endif; ?>
		</tr>
		<?php endif; ?>
		
		<?php if ( isset($offender['gender']) ) : ?>
		<tr>
			<td><strong>Gender:</strong></td>
			<td><?php echo ucfirst(strtolower($offender['gender'])); ?>
				
			<?php if ( isset($offender['weight']) ) : ?>
			<td><strong>Weight:</strong></td>
			<td><?php echo ucfirst(strtolower($offender['weight'])); ?>
			<?php endif; ?>
		</tr>
		<?php endif; ?>
		
		<?php if ( isset($offender['race']) AND $offender['race'] != 0 ) : ?>
		<tr>
			<td><strong>Race:</strong></td>
			<td><?php echo ucfirst(strtolower($offender['race'])); ?>
		</tr>
		<?php endif; ?>
		
		<?php if ( isset($offender['state']) ) : ?>
		<tr>
			<td><strong>State:</strong></td>
			<td><?php echo ucfirst(strtolower($offender['state'])); ?>
				
			<?php if ( isset($offender['scrape']) ) : ?>
			<td><strong>County:</strong></td>
			<td><?php echo ucfirst(strtolower($offender['scrape'])); ?></td>
			<?php endif; ?>
		</tr>
		<?php endif; ?>
		
		
		<?php if ( isset($offender['booking_date']) ): ?>
		<tr>
			<td><strong>Date:</strong></td>
			<td><?php echo date('m/d/Y', $offender['booking_date']) ?></td>
		</tr>	
		<?php endif; ?>
		
		<?php if ( isset($offender['rating']) ): ?>
		<tr>
			<td><strong>Rating:</strong></td>
			<td>
			<?php 
			echo round(array_sum($offender['rating'])/count($offender['rating']), 1)
				.' from '.count($offender['rating']).' votes'; 
			?>
			</td>
		</tr>	
		<?php endif; ?>
		
		<?php if ( isset($offender['charges']) ): ?>
		<tr>
			<td><strong>Charges:</strong></td>
			<td colspan="3"><p><?php foreach ( $offender['charges'] as $charge ) echo $charge.'<br />'; ?></p></td>
		</tr>	
		<?php endif; ?>
	</table>
<?php else: ?>
	<p>Ooops. We're sorry, but there seems to be a holdup on retrieving this user's data.</p>
<?php endif; ?>