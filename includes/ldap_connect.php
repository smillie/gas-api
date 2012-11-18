<?php
$server = $conf['ldap_server'];
$dn = $conf['userdn'];
$groupdn = $conf['groupdn'];

$con = ldap_connect($server);
ldap_set_option($con, LDAP_OPT_PROTOCOL_VERSION, 3);

?>