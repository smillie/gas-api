<?php

  
  function toDate($datestring) {
    $expiry =  intval($datestring)*(60*60*24);
    return date("Y-m-d", $expiry);
  }
  
  function toBoolean($boolstring) {
    if ($boolstring == "TRUE"){
      return true;
    } else {
      return false;
    }
  }
  
  function getAllGroups($con) {
      $group_search = ldap_search($con, "ou=groups,dc=geeksoc,dc=org", "(objectClass=posixGroup)");
      ldap_sort($con, $group_search, 'cn');
      $results = ldap_get_entries($con, $group_search);

      return array_slice($results, 1);
  }
  
  
  function formatGroupArray($ldap_group, $con) {
    $group["name"] = $ldap_group["cn"][0];
    $group["gidnumber"] = $ldap_group["gidnumber"][0];
    
    $members = array();
    foreach (array_slice($ldap_group["memberuid"], 1) as $member) {
      $members[] = $member;
    }
    $group["members"] = $members;
    
    return $group;
  }
  
  function requireAuthentication($con) {
    if (!isset($_SERVER['PHP_AUTH_USER'])) {
        header('WWW-Authenticate: Basic realm="GAS API"');
        header('HTTP/1.0 401 Unauthorized');
        exit;
    } else {
      $userdn = "uid=".$_SERVER['PHP_AUTH_USER'].",ou=people,dc=geeksoc,dc=org";
      if (ldap_bind($con, $userdn, $_SERVER['PHP_AUTH_PW'])===false){
        header('WWW-Authenticate: Basic realm="GAS API"');
        header('HTTP/1.0 401 Unauthorized');
        exit;
      }
    }
  }
  
  function exitIfNotFound($con, $search) {
    if (ldap_count_entries($con, $search) == 0) {
      header('HTTP/1.1 404 Not Found');
      echo '{"error": "Not Found"}';
      exit;
    }
  }
  
  function ircNotify($message) {
      global $conf;
      
      if (false) {
          $ircmessage = "#gsag"." [GAS] $message";
          $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
          socket_connect($sock, 'irc.geksoc.org', '5050');
          socket_write($sock, $ircmessage, strlen($ircmessage));
          socket_close($sock);
      }
  }
  
  
  
  
?>