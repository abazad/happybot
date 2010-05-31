<?php

	/**
	 * this really needs to be rebuilt into the server registry
	 */
	
	final static class ConnectionManager {
		//Connection config
		private $currentConfig = array();

		//Connection to the server
		private $currentServer = array(
			'Socket' => null,
			'connect_status' => 'disconnected');

		public function __construct(){
		}

		public function connect($server = null, $port = null, $passwd = null, $nick = null) {
			$result = false;  //was connection successful?
			$errno = null;
			$errstr = null;
			$Msg = null;

			if (is_array($server)) {
				$this->$currentConfig = $server;
			} else {
				if (!is_null($server)) {
					$this->currentConfig['server'] = $server;
				}
				if (!is_null($port)) {
					$this->currentConfig['server_port'] = $port;
				}
				if (!is_null($passwd)) {
					$this->currentConfig['server_passwd'] = $passwd;
				}
				if (!is_null($nick)) {
					$this->currentConfig['bot_name'] = $nick;
				}
			}
			
			$this->currentServer['Socket'] = @fsockopen( $this->currentConfig['server'], $this->currentConfig['server_port'], $errno, $errstr, 2);	
			if ($this->currentServer['Socket']) {
				$this->currentServer['connect_status'] = 'connecting';
				//We have connected to the server, now we have to send the login commands.
//				$this->sendCommand("PASS {$this->currentConfig['server_password']}", true); //Sends the password not needed for most servers
				$this->sendCommand("NICK {$this->currentConfig['bot_name'][0]}", true); //sends the nickname FIXME
				$this->sendCommand("USER {$this->currentConfig['bot_name'][0]} USING YOAR MOMS", true); //sends the user must have 4 paramters

				//wait for the MOTD or for the server to disconnect us.
				while ($this->currentServer['connect_status'] == 'connecting' && !feof($this->currentServer['Socket'])) {
					$Msg = $this->receive();
					if (!is_null($Msg) && $Msg->msgNumber == 376) {
						$this->currentServer['connect_status'] = 'connected';
					}
				}
			}
			return $this->connected();
		}

		public function disconnect() {
			if ($this->connected()) {

			}
			return false;
		}

		public function connected () {
			return ($this->currentServer['connect_status'] == 'connected' && !feof($this->currentServer['Socket']));
		}

		public function receive() {
			$Msg = null;
			$line = fgets($this->currentServer['Socket'], 1024); //get a line of data from the server
			if (!empty($line)) {
				$Msg = new IrcMessage($line);
			}
			$this->afterReceive($Msg);
			return $Msg;
		}


		public function afterReceive(&$Msg) {
                        if ($Msg->msgNumber == "376") {
				// 376 is the message number for the End of the MOTD for the server (the last thing displayed after a successful connection)
				 
				$this->afterConnect();
			}
			if ($Msg->command == "PING") {
				// IRC Sends a "PING" command to the client which must be anwsered with a "PONG" or the client gets Disconnected
				// Some irc servers have a "No Spoof" feature that sends a key after the PING Command that must be replied with PONG and the same key sent.
				$this->sendCommand("PONG " . $Msg->content, true);
			}
		}



		private function afterConnect() {
			$this->currentServer['connect_status'] = 'connected';
			stream_set_blocking($this->currentServer['Socket'], TRUE);
		        stream_set_timeout($this->currentServer['Socket'], 5); 
			$this->sendCommand("JOIN {$this->currentConfig['channel']}");
		}

		/**
		 *	Send a command to the IRC server
		 *	$cmd Command to send,
		 *	$override don't check for connection (dangerous)
		 */
		private function sendCommand ($cmd, $override = false) {
			$cmd = $cmd . "\n\r";
			if ($override || $this->connected()) {	
				@fwrite($this->currentServer['Socket'], $cmd, strlen($cmd)); //sends the command to the server
				echo "[SEND] $cmd \n"; //displays it on the screen
				return true;
			} else {
				return false;
			}
		}

		private function say($target, $msg) {
			return $this->sendCommand("PRIVMSG {$target} :{$msg}");
		}
	}

	/**
	 * Message type that is passed between the view and controller. This should probably be refactored
	 *  While data is stored in an array, it should be accessed like $Msg = new IrcMessage(); $Msg->buffer = 'whatever';
	 *  Check your lock state before attempting to write to an existing IrcMessage. Failing to do so will have unexpected results;
	 *
	 *  The IrcMessage can be filled out in three ways:
	 *  1) Passing data to constructor:
	 *	$Msg = new IrcMessage($foo);  // $foo can be array with proper keys, an IrcMessage object, or a IRC format string
	 *
	 *  2) Passing data to an unlocked existing IrcMessage:
	 *	$Msg($foo);	//$foo can be the same as in example 1 **THIS WILL ERASE ANY EXISTING DATA IN THE OBJECT**
	 *
	 *  3) Filling out individual properties on an unlocked :
	 *	$Msg->timeStamp = time();	//This will not destroy other data in the object.  Will throw exception if unknown property or locked Object
	 */

	class IrcMessage {
		//holder
		private $__messageData = array();

		//default data layout, used for init and resets
		protected $_messageDataDefault = array(
			'buffer' => null,
			'timeStamp' => null,
			'prefix' => null,
			'command' => null,
			'msgNumber' => null,
			'channel' => null,
			'sender' => null,
			'trigger' => null,
			'content' => null);
		
		//object's lock state. will be locked before onMessage is called
		//if the object is locked, data will be accessable, but not 
		private $__locked = false;



		//List of valid commands
/*		private $commandList = array(
			'ADMIN', 'AWAY', 'CONNECT', 'DIE', 'ERROR', 'INFO', 'INVITE', 'ISON', 'JOIN', 'KICK', 'KILL', 'LINKS', 'LIST','LUSERS',
			'MODE', 'MOTD', 'NAMES', 'NICK', 'NJOIN', 'NOTICE', 'OPER', 'PART', 'PASS', 'PING', 'PONG', 'PRIVMSG', 'QUIT', 'REHASH',
			'RESTART', 'SERVER', 'SERVICE', 'SERVLIST', 'SQUERY', 'SQUIRT', 'SQUIT', 'STATS', 'SUMMON', 'TIME', 'TOPIC', 'TRACE',
			'USER', 'USERHOST', 'USERS', 'VERSION', 'WALLOPS', 'WHO', 'WHOIS', 'WHOWAS');
*/
		public function __construct($data = null) {
			if (!is_null($data)) {
				$this->__invoke($data);
			}
		}
	
		public function lock() {
			$this->__locked = true;
		}

		public function reset() {
			if (!$this->__locked) {
				$this->__messageData = $this->_messageDataDefault;
				return true;
			}
			return false;
		}
		/**
		 * Does basic parsing of a string format message (mostly inbound from irc server).
		 * The filled out data is inserted into the object
		 * $msg @string The message to parse
		 * return none
		 */
		private function __parseMessage($msg) {
			$this->buffer = $msg;
			$this->timeStamp = time();

			//check for a prefixed message/command
			if ($msg[0] == ':') {
				//extract the prefix
				$window = strpos($msg, " ");
				$this->prefix = substr($msg, 1, $window - 1);
				$msg = substr($msg, $window + 1);

				//check for a nick and extract it
				$window = strpos($this->prefix,'!');
				if ($window) {
					$this->sender = substr($this->prefix, 0, $window);
				}
			}
			//pull the command from the message
			$window = strpos($msg, " ");
			$chunk = substr($msg, 0, $window);
			if (is_numeric($chunk)) {
				$this->msgNumber = $chunk;
			} else {
				$this->command = $chunk;
			}
			$msg = substr($msg, $window + 1);
			
			$this->content = $msg;
			return true;
		}

		/**
		 * TODO: WRITE ME
		 * Take an array or IrcMessage object and fill out the correct properties in $this
		 * This will wipe any data already existant in the object
		 */
		private function __importMessage($msg) {
			if (!empty($msg)) {
			}
			return true;
		}
		/**
		 * Allows a created object (say.. $Msg) to be called like so $Msg($something);
		 * is a wrapper for __importMessage() or __parseMessage() depending on $data's type
		 * This is the primary method for filling out a whole message at once, and is also called by the constructor.
		 */
		public function __invoke($data = null) {
			$result = false;
			if (!$this->__locked) {
				$this->reset();
				if (is_array($data) or is_object($data)) {
					$result = $this->__importMessage($data);
				} elseif (is_string($data)) {
					$result = $this->__parseMessage($data);
				}
			}
			return $result;
		}

		public function __get($name) {
			if (array_key_exists($name, $this->__messageData)) {
				return $this->__messageData[$_name];
			} elseif ($name === 'locked') {
				return $this->__locked;
			}
			trigger_error("Attempting to access an undefined property ( {$name} ) of an IrcMessage via __get()");
		}

		public function __set($name, $value) {
			if (array_key_exists($name, $this->__messageData) ) {
				if (!$this->__locked) {
					$this->__messageData[$name] = $value;
				} else {
					throw new Exception('Attempting to set a protected property of a locked IrcMessage');
				}
			} elseif ($name !== 'locked') {
				throw new Exception("Attempting to set an undefined property or alter lock state of an IrcMessage (property name: {$name})");
			}
			
		}

	}

	//Really unsure what needs to be here yet :)
	class HappyController {
         
        //needs methods to connect to the server list, register for callbacks, and to accept/clear target server.
	}
?>
