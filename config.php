<?php

$conf = array();

$conf['db_name'] = 'gas-users';
$conf['db_host'] = 'localhost';
$conf['db_user'] = 'gas-users';
$conf['db_pass'] = 'carotgyskadu';
       
$conf['ircNotifications'] = FALSE;
$conf['ircServer'] = 'irc.geeksoc.org';
$conf['ircChannel'] = '#gsag';
$conf['ircBotPort'] = '5050';
       
$conf['mailNotifications'] = FALSE;
$conf['mailFrom'] = 'support@geeksoc.org';

$conf['notificationPrefix'] = "[AS]";

$conf['ldap_server'] = "ldap://ldap.geeksoc.org";
$conf['userdn'] = "ou=People,dc=geeksoc,dc=org";
$conf['groupdn'] = "ou=Groups,dc=geeksoc,dc=org";

?>