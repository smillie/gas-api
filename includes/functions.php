<?php
  function getStatus($expiry, $paid="TRUE") {
      $day = intval(time()/(60*60*24));
      $i = (int)$expiry;
      if (!isset($expiry)) $i=99999;

      if ($i == 1) {
          $status = "Administratively Disabled";
      } elseif ($i <= $day) {
          $status = "Expired";
      } elseif ($i <= ($day+60) && $paid == "FALSE") {
          $status = "Expiring (Not Paid)";
      } elseif ($i <= ($day+60)) {
          $status = "Expiring";
      } elseif ($paid == "FALSE") {
          $status = "Not Paid";
      } else {
          $status = "Active";
      }

      return "$status";
  }
  
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
  
  function getGroupsForUser($con, $user) {
      $groups = array();
      foreach (getAllGroups($con) as $groupEntry) {
          if (isUserInGroup($con, $user, $groupEntry['cn'][0])) {
              $groups[] = $groupEntry['cn'][0];
          }
      }
      return $groups;
  }
  
  function isUserInGroup($con, $user, $group) {
      $group_search = ldap_search($con, "cn=$group,ou=groups,dc=geeksoc,dc=org", "(memberUid=$user)");
      if (ldap_count_entries($con, $group_search) >= 1) {
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
  
  function formatUserArray($ldap_user, $con) {
    $user["username"] = $ldap_user["uid"][0];
    $user["firstname"] = $ldap_user["givenname"][0];
    $user["lastname"] = $ldap_user["sn"][0];
    $user["displayname"] = $ldap_user["cn"][0];
    $user["email"] = $ldap_user["mail"][0];
    $user["title"] = $ldap_user["title"][0];
    $user["studentnumber"] = $ldap_user["studentnumber"][0];
    $user["status"] = getStatus($ldap_user["shadowexpire"][0]);
    $user["expiry"] = toDate($ldap_user["shadowexpire"][0]);
    $user["paid"] = toBoolean($ldap_user["haspaid"][0]);
    $user["notes"] = $ldap_user["notes"][0]; //only populated if allowed to see...
    $user["loginshell"] = $ldap_user["loginshell"][0];
    $user["homedirectory"] = $ldap_user["homedirectory"][0];
    $user["uidnumber"] = $ldap_user["uidnumber"][0];
    $user["gidnumber"] = $ldap_user["gidnumber"][0];
    $user["groups"] = getGroupsForUser($con, $user["username"]);
    
    $sshkeys = array();
    foreach (array_slice($ldap_user["sshpublickey"], 1) as $key) {
      $sshkeys[] = $key;
    }
    $user["sshkeys"] = $sshkeys;
    
    return $user;
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
  
  function generatePassword ($length = 8) {
      $password = "";
      $possible = "2346789bcdfghjkmnpqrtvwxyzBCDFGHJKLMNPQRTVWXYZ";
      $maxlength = strlen($possible);
      if ($length > $maxlength) {
          $length = $maxlength;
      }
      $i = 0; 
      while ($i < $length) { 
          $char = substr($possible, mt_rand(0, $maxlength-1), 1);
          if (!strstr($password, $char)) { 
              $password .= $char;
              $i++;
          }
      }
      return $password;
  }
  
?>