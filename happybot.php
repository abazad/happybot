<?php
	set_time_limit(0);
	include("config.php");
	include("libs/core.php");
	include("controllers/happy_bot_controller.php");
	$HappyBot = new HappyBotController($__IRCConfig);
	$HappyBot->start();
?> 
