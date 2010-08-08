<?php
/**
 * This class is going to do the heavy work of making sure things are getting done when they need to be and sending messages to the dispatcher
 * Generally this is not going to be something you're going to want to alter, instead, add a new controller via plugin.
 */
class HappyBotController extends HappyController {

	//the current "active" server. This is a shortcut to make life easier when dealing with multiple servers.
	public $Server;


	public function __construct($IrcConfig) {
		$serverId = ConnectionManager::registerServer($IrcConfig);
		ConnectionManager::connect($serverId);
	}


	public function start() {
		$keepAlive = true;
		while (ConnectionManager::connected() && $keepAlive) {
			try {
				$Msg = ConnectionManager::receive();
				if ($Msg) {
					$this->beforeMessage($Msg);
					$this->onMessage($Msg);
					//need a call to the dispatcher here
					$this->afterMessage($Msg);
				}
			} catch (Exception $e) {
				echo "oi! WTF! {$e} \n";
			}
		}

		ConnectionManager::disconnect();
	}

	public function beforeMessage(&$Msg) {
		if (!$Msg->locked) {
			//fill out more info on a private message.
			if ($Msg->command == "PRIVMSG") {
				$window = strpos($Msg->content, " ");
				if ($Msg->content[0] == '#') {
					$Msg->channel = substr($Msg->content, 0, $window);
				}
				$Msg->content = substr($Msg->content, $window + 2);
			}
			//need call to check for known triggers here
			$Msg->lock();
		} else {
			throw new Exception('Incoming message is already locked!');
		}
	}

	public function onMessage($Msg) {

		//			print_r($Msg);
		if (strpos(strtolower($Msg->content), 'hi') !== false) {
			$target = (is_null($Msg->channel))? $Msg->sender : $Msg->channel;
			$this->say($target, "Well hi {$Msg->sender}!");
			echo "responded";
		}
	}

	public function afterMessage($Msg) {
		echo "[RECV] " . $Msg->buffer . "\n";
	}
}
?>
