<?php
$total_offenders = Mango::factory('offender')->load(false)->count();
?>

<ul class="demographics">
	<?php foreach ( $races as $k => $v ) :
	$criteria['race'] = $v;
	$offenders = Mango::factory('offender', $criteria)->load(false)->count();
	$perc = round($offenders / $total_offenders, 3);
	?>
	<li style="background-position: <?=-220+($perc*220);?>px 0">
		<a href="/browse-mugshots?race=<?=urlencode($criteria['race']);?>">
			<strong><?=($criteria['race'] == 'ASIAN OR PACIFIC ISLANDER')?'ASIAN':$criteria['race'];?></strong>
			<em><?=$offenders;?> / <?=$perc*100;?>%</em>
		</a>
	</li>
	<?php endforeach; ?>

	<hr />

	<?php
	$offender = Mango::factory('offender', array('gender' => 'MALE'))->load(false)->count();
	$perc = round($offender / $total_offenders, 3);
	?>
	<li style="background-position: <?=-220+($perc*220);?>px 0">
		<a href="/browse-mugshots?gender=MALE">
			<strong>MALE</strong>
			<em><?=$offender;?> / <?=$perc*100;?>%</em>
		</a>
	</li>

	<?php
	$offender = Mango::factory('offender', array('gender' => 'FEMALE'))->load(false)->count();
	$perc = round($offender / $total_offenders, 3);
	?>
	<li style="background-position: <?=-220+($perc*220);?>px 0">
		<a href="/browse-mugshots?gender=FEMALE">
			<strong>FEMALE</strong>
			<em><?=$offender;?> / <?=$perc*100;?>%</em>
		</a>
	</li>
</ul>