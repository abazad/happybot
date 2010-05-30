<?php
/**
 * This class is going to do the heavy work of making sure things are getting done when they need to be and sending messages to the dispatcher
 * Genrally this is not going to be something you're going to want to alter, instead, add a new controller via plugin. 
 */
	class HappyBotController extends HappyController {
	
		//the current "active" server. This is a shortcut to make life easier when dealing with multiple servers.
		public $Server;
 
		
		public function __construct() {
			//Server connection info
			include("config.php");
			$this->Server = new HappyView($IrcConfig);
			if ($this->Server) {
				$this->Server->connect();
			}
		}

		/**
		 * This is the bot's main loop.
		 */
		public function start() {
			$keepAlive = true;

			while ($this->Server->connected() && $keepAlive) {
				try {
					$Msg = $this->Server->receive();
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

			$this->Server->disconnect();
		}
	
		public function beforeMessage(&$Msg) {
			if (!$Msg->locked) {
				//fill out more info on a private message.
				if ($this->command == "PRIVMSG") {
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
			if ($Msg->msgNumber == "376") {
				// 376 is the message number for the End of the MOTD for the server (The last thing displayed after a successful connection)
				$this->afterConnect();
			}
			if ($Msg->command == "PING") {
				// IRC Sends a "PING" command to the client which must be anwsered with a "PONG" or the client gets Disconnected
				// Some irc servers have a "No Spoof" feature that sends a key after the PING Command that must be replied with PONG and the same key sent.
				$this->sendCommand("PONG " . $Msg->content, true);
			}
		}

		
	}
?>
