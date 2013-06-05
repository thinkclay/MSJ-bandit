<!DOCTYPE html>
<html lagn="en-US">
	<head>
		<title><?php echo $title ?></title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		
		<!-- STYLES --> 
		<?php foreach ($styles as $file => $type) echo HTML::style($file, array('media' => /* edit */  'all')), "\n"; ?>
		<?php foreach ($scripts as $file) echo HTML::script($file), "\n" ?>
		<?php echo @$head; ?>
	</head>
	<body>
		<div class="container">
			<?php if(isset($h1)) echo "<h1>$h1</h1>"; ?>
			<?php if(isset($view)) echo $view; ?>		
		</div>
	</body>
</html>