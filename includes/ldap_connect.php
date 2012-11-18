<?php
$server = "ldap://ldap.geeksoc.org";
$dn = "ou=People,dc=geeksoc,dc=org";
$groupdn = "ou=Groups,dc=geeksoc,dc=org";

$con = ldap_connect($server);
ldap_set_option($con, LDAP_OPT_PROTOCOL_VERSION, 3);

?>