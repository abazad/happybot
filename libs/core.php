<?php

/**
 * this really needs to be rebuilt into the server registry
 */

final class ConnectionManager {
	//Connection config
	private static $__serverList = array();
	private static $__activeServer = null;
	public function __construct(){
	}

	public function __destruct() {
	}
	
	/**
	 * Add a server or sever to the registry of known servers
	 *
	 * @param array serverList One or more servers to register, using format from config file
	 * @return the id of the server that was registered, or null for failure.
	 * @todo set up proper validation for the server configuration, once that has been stablized
	 * @todo get multiple server registration working
	 */
	public static function registerServer($serverList) {
		$serverId = null;
		if (is_array($serverList)) {
			//TODO Redo this check with a full validation of the server
			if (isset($serverList['server'])) {
				self::$__serverList[] = new HappyServer($serverList, true);
				$serverId = count(self::$__serverList) - 1;
			} else {
				//TODO  implement multiple server registration.
			}
		}
		return $serverId;
	}


	/**
	 * Set a server as actvive or clear the active server
	 * @param serverId mixed numeric server id or null to clear active server
	 * @return boolian was operation successful
	 */
	public static function setActive($serverId) {
		$result = false;
		if (is_null($serverId)) {
			self::$__activeServer = null;
			$result = true;
		}
		if (isset(self::$__serverList[$serverId])) {
			self::$__activeServer = &self::$__serverList[$serverId];
			$result = true;
		}
		return $result;
	}

	/**
	 * Connect to a server or servers, specified in $serverId
	 *
	 * @param mixed serverId the server(s) to connect too.  int or array of ints
	 * @return boolean success
	 */
	public static function connect($serverId = 'null') {
		$result = false;  //was connection successful?
		$errno = null;		//used to catch error numbers for socket connect
		$errstr = null;	//used to catch error details for socket connect
		$Msg = null;	//used to make catch inbound server messages.
		$currentServer = null; //refrence to the server that is being operated on.
			
		if (is_null($serverId)) {
			$serverId = array_keys(self::$__serverList);
		}
		if (!is_array($serverId)) {
			$serverId = array($serverId);
		}
		foreach ($serverId as $currentId) {
			if (self::setActive($currentId)) {
				$currentServer = &self::$__serverList[$currentId];
				$currentServer['Socket'] = @fsockopen($currentServer['server'], $currentServer['server_port'], $errno, $errstr, 2);
				if ($currentServer['Socket']) {
					$currentServer['connect_status'] = 'connecting';
					//We have connected to the server, now we have to send the login commands.
					//self::sendCommand("PASS {$currentServer['server_password']}", null, true); //Sends the password not needed for most servers FIXME we need a null/empty password check
					self::sendCommand("NICK {$currentServer['bot_name'][0]}", null, true); //sends the nickname FIXME. This only sends first nickname, won't cycle through to secondary ones
					self::sendCommand("USER {$currentServer['bot_name'][0]} USING YOAR MOMS", null, true); //sends the user must have 4 paramters

					//wait for the MOTD or for the server to disconnect us.
					//this is not optimum, once we have each server in its own object, let it manage watching for messages.
					while ($currentServer['connect_status'] == 'connecting' && !feof($currentServer['Socket'])) {
						$Msg = self::receive($currentId);
						if (!is_null($Msg) && $Msg->msgNumber == 376) {
							$currentServer['connect_status'] = 'connected';
						}
					}
				}
			}
		}
		return $result;
	}

	
	/**
	 * Disconnect the server. Uh.
	 * @todo uh.. do this? heh.
	 */
	public static function disconnect($serverId = null, $quitMessage = null) {
		if (self::connected()) {

		}
		return false;
	}
	/**
	 * check to see if a server is connected
	 *  @param array server the server object
	 *  @todo change this to check a specified server
	 */
	public static function connected ($server = null) {
		if (is_null($server)) {
			$server = self::$__activeServer;
		}
		if (array_key_exists($server['list_id'], self::$__serverList)) {
			return ($server['connect_status'] == 'connected' && !feof($server['Socket']));
		}
	}
	/**
	 * Checks the active server for a message.  prolly should be inside the Server Object and
	 *  be replaced by a polling function.
	 *  @param integer serverId the id of the server to check {optional}
	 */
	public static function receive($serverId = null) {
		$Msg = null;
		$line = fgets(self::$__activeServer['Socket'], 1024); //get a line of data from the server
		if (!empty($line)) {
			$Msg = new IrcMessage($line);
		}
		if (!is_null($Msg)) {
			self::__afterReceive($Msg);
		}
		return $Msg;
	}
	/**
	 * Send a command to the IRC server
	 * @param string cmd Command to send,
	 * @param integer serverId the server id to check for.
	 * @param bool override don't check for connection. Needed for connecting.
	 * @todo need to seperate this into the server object and the code necc. for safty checking server.
	 */
	public static function sendCommand ($cmd, $serverId = null, $override = false) {
		$cmd = $cmd . "\n\r";
		if ($override || self::connected()) {
			echo "[SEND] $cmd"; //displays it on the screen
			@fwrite(self::$__activeServer['Socket'], $cmd, strlen($cmd)); //sends the command to the server
			return true;
		} else {
			return false;
		}
	}
}


/**
 * Server object to be filled out
 */
class HappyServer {

	/**
	 * Configuration array for the server, with some defaults.
	 * 
	 * @var array
	 * @access private
	 */
	private $__config = array(
		'server' => null,
		'server_port' => 6667,
		'server_pass' => null,
		'bot_name' => array(),
		'nickserv_pass' => null,
		'channel' => null);

	/**
	 * State of this server.  Either 'disconnected', 'connecting', or 'connected'
	 *  check with ->connected()
	 * 
	 * @var string
	 * @access private
	 */
	private $__connectStatus = 'disconnected';

	/**
	 * The actual connection to the server
	 * 
	 * @var resource
	 * @access private
	 */
	private $__Socket = null;

	/**
	 * Used internally to override the ->connected() check on ->sendMessage()
	 *  before the server is fully connected.
	 * @var <type>
	 */
	private $__override = true;
	
	/**
	 * Build the server, and hey, what the hell, lets connect it.
	 *
	 * @param array $config server configuration to use
	 * @param boolean $connect automatically connect the server
	 */
	public function __construct($config, $connect = false) {
		$result = false;
		//need proper validation here
		if (is_array($config) && isset($config['server'])) {
			$this->__config = array_merge($this->__config, $config);
			$result = true;
			if ($connect === true) {
				$result = $this->connect();
			}
		}
	}

	/**
	 * Connect to a server or servers, specified in $serverId
	 *
	 * @param mixed serverId the server(s) to connect too.  int or array of ints
	 * @return boolean success
	 * @todo fix the password command
	 * @todo fix the nickname logic to allow for fallback to secondary nicknames
	 */
	public function connect() {
		$result = false;  //was connection successful?
		if (!$this->connected()) {
			$errno = null;		//used to catch error numbers for socket connect
			$errstr = null;	//used to catch error details for socket connect
			$Msg = null;	//used to make catch inbound server messages.

			$this->__Socket = @fsockopen($this->__config['server'], $this->__config['server_port'], $errno, $errstr, 2);
			if (is_resource($this->__Socket)) {
				$this->__connectStatus = 'connecting';
				//We have connected to the server, now we have to send the login commands.
				//self::sendCommand("PASS {$this->__config['server_password']}", null, true); //Sends the password not needed for most servers FIXME we need a null/empty password check
				$this->sendCommand("NICK {$this->__config['bot_name'][0]}", null, true); //sends the nickname FIXME. This only sends first nickname, won't cycle through to secondary ones
				$this->sendCommand("USER {$this->__config['bot_name'][0]} USING YOAR MOMS", null, true); //sends the user must have 4 paramters

				//wait for the MOTD or for the server to disconnect us.
				//this is not optimum, once we have each server in its own object, let it manage watching for messages.
				while ($this->__connectStatus == 'connecting' && !feof($this->__Socket)) {
					$Msg = $this->receive();
					if (!is_null($Msg) && $Msg->msgNumber == 376) {
						$this->__connectStatus = 'connected';
						stream_set_blocking($this->__Socket, TRUE);
						stream_set_timeout($this->__Socket, 5);
						$this->__afterConnect();
					}
				}

				if (feof($this->__Socket)) {
					$this->__connectStatus = 'disconnected';
				}
			}
		}
		return $result;
	}
	/**
	 * Checks the active server for a message.  prolly should be inside the Server Object and
	 *  be replaced by a polling function.
	 *  @param integer serverId the id of the server to check {optional}
	 */
	public function receive($serverId = null) {
		$Msg = null;
		$line = fgets($this->__Socket, 1024); //get a line of data from the server
		if (!empty($line)) {
			$Msg = new IrcMessage($line);
			echo "[RECV] " . $Msg->buffer . "\n";
			if (!is_null($Msg)) {
				$this->__afterReceive($Msg);
			}
		}
		return $Msg;
	}

	/**
	 * Send a text command to the server
	 *
	 * @param string $cmd command to send
	 * @return boolian success of command being sent.
	 */
	public function sendCommand ($cmd) {
		$result = false;
		$cmd = $cmd . "\n\r";
		if ($this->__connectStatus == 'connecting' || $this->connected()) {
			echo "[SEND] $cmd"; //displays it on the screen
			@fwrite($this->__socket, $cmd, strlen($cmd)); //sends the command to the server
			$result = true;
		}
		return $result;
	}

	/**
	 * check to see if the server is connected
	 *  @param array server the server object
	 */
	public function connected () {
		return ($this->__connectStatus == 'connected' && !feof($this->__Socket));
	}

	/**
	 * Disconnect the server. Uh. yeh.
	 * @todo uh.. do this? heh.  need to see how to gracefully disconnect.
	 */
	public function disconnect($serverId = null, $quitMessage = null) {
		if ($this->connected()) {

		}
		return false;
	}

	/**
	 * Maintains the connection to the server and takes care of other incidentals.
	 *
	 * @param Object $Msg message object to procoss.
	 */
	private function __afterReceive(&$Msg) {
		if ($Msg->command == "PING") {
			// IRC Sends a "PING" command to the client which must be anwsered with a "PONG" or the client gets Disconnected
			// Some irc servers have a "No Spoof" feature that sends a key after the PING Command that must be replied with PONG and the same key sent.
			$this->sendCommand("PONG " . $Msg->content, null, true);
			$Msg = null; //reset, nobody else needs to see this.

		}
	}

	/**
	 * Does stuff like joining channels
	 * @todo get multi-channel joining working
	 * @todo nickserv
	 */
	private function __afterConnect() {
		$this->sendCommand("JOIN " . $this->activeServer['channel']);
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

	/**
	 * Reset the object to default status.
	 */
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
	 * @param string $msg @string The message to parse
	 * @return none
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

		//if it's a PRIVMSG check for channel info
		if ($this->command == "PRIVMSG") {
			//adding info like channel name to the message
			$window = strpos($this->content, " ");
			if ($this->content[0] == '#') {
				$this->channel = substr($this->content, 0, $window);
			}
			$this->content = substr($this->content, $window + 2);
		}
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
	/**
	 * Getter method.  All the accessible properties of the object are stored in a private array.
	 * This method checks the existance and returns it.
	 */
	public function __get($name) {
		if (array_key_exists($name, $this->__messageData)) {
			return $this->__messageData[$name];
		} elseif ($name === 'locked') {
			return $this->__locked;
		}
		trigger_error("Attempting to access an undefined property ( {$name} ) of an IrcMessage via __get()");
	}

	/**
	 *  Setter method.  Only allows for existing properties, and will not allow protected properties to be set when the object is locked
	 */
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

	protected function say($target, $message, $serverId = null) {
		return ConnectionManager::sendCommand("PRIVMSG {$target} :{$message}", $serverId);
	}
	//needs methods to connect to the server list, register for callbacks, and to accept/clear target server.
}

?>
