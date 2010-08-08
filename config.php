<?php
// The server host is the IP or DNS of the IRC server.
$IrcConfig['server'] = "irc.esper.net";

// Server Port, this is the port that the irc server is running on. Deafult: 6667
$IrcConfig['server_port'] = 6667;

// Server password.  This is NOT your nickserv password, it is the password used to connect if any.
// If you do not need a password, leave as NOPASS
$IrcConfig['server_pass'] = 'NOPASS';

// The nick to connect to the server as.  Specify multiple fallbacks if you'd like.
$IrcConfig['bot_name'][]= "happybot";
$IrcConfig['bot_name'][]= "yoarmom";


// **THIS** is your nickserv password, leave empty to not identify
$IrcConfig['nickserv_pass'] = '';


//Server Chanel, After connecting to the IRC server this is the channel it will join.
$IrcConfig['channel'] = "#happyplace";
?> 
