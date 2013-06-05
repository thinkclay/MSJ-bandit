<ul class="demographics">
<?php foreach ( $races as $k => $v ) :
	$criteria['race'] = $v;
	$offenders = Mango::factory('offender', $criteria)->load(false)->count();
	$perc = round($offenders / Mango::factory('offender')->load(false)->count(), 3);
?>
	<li style="background-position: <?=-220+($perc*220);?>px 0">
		<a href="/browse-mugshots?race=<?=urlencode($criteria['race']);?>">
			<strong><?=($criteria['race'] == 'ASIAN OR PACIFIC ISLANDER')?'ASIAN':$criteria['race'];?></strong>
			<em><?=$offenders;?> / <?=$perc*100;?>%</em>
		</a>
	</li>
<?php endforeach; ?>
</ul>