<?php if( isset($offender) ) : ?>
<div itemscope itemtype="http://schema.org/Person">
	<div class="mugshot">
		<img itemprop="image" src="http://sys.mugshotjunkie.com/offender/full_mugshot/<?=$offender['booking_id'];?>" />
	</div>

	<?php if ( isset($offender['firstname']) AND isset($offender['lastname']) ) : ?>
	<h3 itemprop="name"><?=ucfirst(strtolower($offender['firstname'])); ?> <?=ucfirst(strtolower($offender['lastname']));?></h3>
	<?php endif; ?>
			
	<table class="demographic">
		<?php if ( isset($offender['age']) ) : ?>
		<tr>
			<td><strong>Age:</strong></td>
			<td itemprop="age"><?php echo ucfirst(strtolower($offender['age'])); ?>
		</tr>	
		<?php endif; ?>
		
		<?php if ( isset($offender['height']) ) : ?>
		<tr>
			<td><strong>Height:</strong></td>
			<td itemprop="height"><?php echo $offender['height']; ?></td>
		</tr>
		<?php endif; ?>
		
		<?php if ( isset($offender['gender']) ) : ?>
		<tr>
			<td><strong>Gender:</strong></td>
			<td itemprop="gender"><?php echo ucfirst(strtolower($offender['gender'])); ?>
		</tr>
		<?php endif; ?>
		
		<?php if ( isset($offender['weight']) ) : ?>
		<tr>
			<td><strong>Weight:</strong></td>
			<td itemprop="weight"><?php echo ucfirst(strtolower($offender['weight'])); ?>
		</tr>
		<?php endif; ?>
		
		<?php if ( isset($offender['race']) AND $offender['race'] != 0 ) : ?>
		<tr>
			<td><strong>Race:</strong></td>
			<td itemprop="ethnicity"><?php echo ucfirst(strtolower($offender['race'])); ?>
		</tr>
		<?php endif; ?>
		
		<?php if ( isset($offender['state']) ) : ?>
		<tr>
			<td><strong>State:</strong></td>
			<td><?php echo ucfirst(strtolower($offender['state'])); ?>
		</tr>
		<?php endif; ?>
		
		<?php if ( isset($offender['scrape']) ) : ?>
		<tr>
			<td><strong>County:</strong></td>
			<td><?php echo ucfirst(strtolower($offender['scrape'])); ?></td>
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
			<td itemprop="description" colspan="3">
				<p><?php foreach ( $offender['charges'] as $charge ) echo $charge.'<br />'; ?></p>
			</td>
		</tr>	
		<?php endif; ?>
	</table>

	<div id="fb-root"></div>
	<script src="http://connect.facebook.net/en_US/all.js#xfbml=1"></script>
	<fb:comments href="http://mugshotjunkie.com/inmate/mugshot/<?php echo $offender['booking_id']; ?>/" num_posts="2" width="650"></fb:comments>
</div>
<?php endif; ?>