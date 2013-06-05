<div style="width:100%;">
	<?php 
	echo Form::open("/account/login", array("id"=>"login", "method"=>"post"));	
	echo Form::label("username", "Username:"); 
	echo Form::input("username", "", array("type"=>"text", "class"=>"required"));
	echo '<br/>';
	echo Form::label("password", "Password:"); 
	echo Form::input("password", "", array("type"=>"password", "class"=>"required"));
	echo '<br/>';
	echo Form::submit(Null, "Login", array("type"=>"submit"));
	Form::close(); 
	print_r(@$errors);
	?>
</div>
