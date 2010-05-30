<?php
	set_time_limit(0);
	include("config.php");
	include("libs/core.php");
	include("controllers/main.php");
	try {
		$HappyBot = new HappyBot($__IRCConfig);
		while ($HappyBot->connected()) {
			$HappyBot->parseReceive();
		}
	} catch ( Exception $wtf) {
		echo $wtf;
	};
	
?> 
