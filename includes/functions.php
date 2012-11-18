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
  
  function booleanToLdapBoolean($boolean) {
    if ($boolean) {
      return "TRUE";
    } else {
      return "FALSE";
    }
  }
  
  function getAllGroups($con) {
      $group_search = ldap_search($con, "ou=groups,dc=geeksoc,dc=org", "(objectClass=posixGroup)");
      ldap_sort($con, $group_search, 'cn');
      $results = ldap_get_entries($con, $group_search);

      return array_slice($results, 1);
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
  
  function requireAdminUser($con) {
    if (isset($_SERVER['PHP_AUTH_USER'])) {
      $username = $_SERVER['PHP_AUTH_USER'];
      if (UserController::isUserInGroup($con, $username, "gsag")) {
        return true;
      }
    }
    
    header('HTTP/1.0 403 Forbidden');
    exit;
    
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
    
    if ($conf['ircNotifications']) {
      $ircmessage = $conf['ircChannel']." [GAS] $message";
      $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
      socket_connect($sock, $conf['ircServer'], $conf['ircBotPort']);
      socket_write($sock, $ircmessage, strlen($ircmessage));
      socket_close($sock);
    }
  }
  
  function mailNotify($to, $subject, $message) {
    global $conf;
    $from = $conf['mailFrom'];
    
    if ($conf['mailNotifications']) {
      mail($to, $subject, $message, "From: $from");
    }
  }
  
  function setIfDefined($newvalue, &$array, $key) {
    if (isset($newvalue)) {
      if ($newvalue == "") {
          $array[$key] = array();
      } else {
        $array[$key] = $newvalue;
      }
    }
  }
  
  
  
  
?>
