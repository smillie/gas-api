<?php

// include_once './includes/ldap_connect.php';

/**
* Controller for working with users
*/
class UserController
{
  
  static public function authenticate() {
    global $con, $dn;

    $input = json_decode(file_get_contents("php://input"), true);
		$username = $input['username'];
    $password = $input['password'];

    if (ldap_bind($con, "uid=$username,$dn", $password)===false){
      header('Content-type: application/json');
      header('HTTP/1.1 401 Unauthorized');
    }
  }
  
  static public function getUsers() {
    global $con, $dn;

    requireAuthentication($con);

    header('Content-type: application/json');

    $searchPattern = "(objectclass=posixaccount)";
    $search = ldap_search($con, $dn, $searchPattern);
    ldap_sort($con, $search, 'uid');
    $results = ldap_get_entries($con, $search);
    exitIfNotFound($con, $search);

    $output = array();
    foreach (array_slice($results, 1) as $ldap_user) {
      $user = self::formatUserArray($ldap_user, $con);
      $output[] = $user;
    }

    echo json_encode($output);
  }
  
  static public function createUser() {
    global $con, $dn;

    requireAuthentication($con);

    header('Content-type: application/json');

    $input = json_decode(file_get_contents("php://input"), true);

    $username = $input['username'];
    $firstname = $input['firstname'];
    $lastname = $input['lastname'];
    $studentnumber = $input['studentnumber'];
    $email = $input['email'];

    $search = ldap_search($con, $dn, "(uid=$username)");
    if (ldap_count_entries($con, $search) != 0) {
      header('HTTP/1.1 400 Bad Request');
      echo '{"error": "Already Exists"}';
      exit;
    }

    $created = self::createLdapUser($con, $username, $firstname, $lastname, $studentnumber, $email);
    if ($created == true) {
      echo "{\"success\": \"$username created with password $created\"}";
      AuditController::recordAuditEntry("Created user '$username'");
    } else {
      header('HTTP/1.1 400 Bad Request');
      echo '{"error": "Bad Request"}';
      exit;
    }

  }
  
  static public function getUser($username) {
    global $con, $dn;

    requireAuthentication($con);

    header('Content-type: application/json');

    $search = ldap_search($con, $dn, "(uid=$username)");
    ldap_sort($con, $search, 'uid');
    $result = ldap_get_entries($con, $search);
    exitIfNotFound($con, $search);
    

    $output = self::formatUserArray($result[0], $con);

    echo json_encode($output);
  }

  static public function updateUser($username) {
    global $con, $dn;

    requireAuthentication($con);

    header('Content-type: application/json');

    $search = ldap_search($con, $dn, "(uid=$username)");
    ldap_sort($con, $search, 'uid');
    exitIfNotFound($con, $search);

    $input = json_decode(file_get_contents("php://input"), true);

    $attrs=array();
  
    setIfDefined($input["firstname"], $attrs, "givenname");
    setIfDefined($input["lastname"], $attrs, "sn");
    setIfDefined($input["displayname"], $attrs, "cn");
    setIfDefined($input["email"], $attrs, "mail");
    setIfDefined($input["title"], $attrs, "title");
    setIfDefined($input["studentnumber"], $attrs, "studentnumber");
    //can't set status directly - manipulate expiry date instead...
    if (isset($input["expiry"])) {
      $edate = strtotime($input['expiry']);
      $attrs['shadowexpire'] = intval($edate/(60*60*24))+1;
    }
    if (isset($input["paid"])) {
      $attrs["haspaid"] = booleanToLdapBoolean($input["paid"]);
    }
    setIfDefined($input["loginshell"], $attrs, "loginshell");
    setIfDefined($input["homedirectory"], $attrs, "homedirectory");
    setIfDefined($input["notes"], $attrs, "notes");
    setIfDefined($input["uidnumber"], $attrs, "uidnumber");
    setIfDefined($input["gidnumber"], $attrs, "gidnumber");
    setIfDefined($input["sshkeys"], $attrs, "sshpublickey");
    //groups... can't do due to insufficient access

    ldap_modify($con, "uid=$username,$dn", $attrs);
    if (ldap_error($con) != "Success") {
      header('HTTP/1.1 400 Bad Request');
      echo '{"error": "Bad Request"}';
      exit;
    }

    AuditController::recordAuditEntry("Edited account '$username'");

  }
  
  static public function search($query) {
    global $con, $dn;

    requireAuthentication($con);

    header('Content-type: application/json');

    $searchPattern = "(&(objectclass=posixaccount)(|(uid=*$query*)(cn=*$query*)(mail=*$query*)(studentnumber=*$query*)))";
    
    $search = ldap_search($con, $dn, $searchPattern);
    ldap_sort($con, $search, 'uid');
    $results = ldap_get_entries($con, $search);
    exitIfNotFound($con, $search);

    $output = array();
    foreach (array_slice($results, 1) as $ldap_user) {
      $user = self::formatUserArray($ldap_user, $con);
      $output[] = $user;
    }

    echo json_encode($output);
  }
  
  
  static private function setIfDefined($newvalue, &$array, $key) {
    if (isset($newvalue)) {
      if ($newvalue == "") {
          $array[$key] = array();
      } else {
        $array[$key] = $newvalue;
      }
    }
  }


  static public function deleteUser($username) {
    global $con, $dn;

    requireAuthentication($con);

    header('Content-type: application/json');

    $search = ldap_search($con, $dn, "(uid=$username)");
    exitIfNotFound($con, $search);

    ldap_delete($con,"uid=".$username.",".$dn);
    if (ldap_error($con) != "Success") {
        if (ldap_error($con) == "Insufficient access") {
          header('HTTP/1.1 403 Forbidden');
          echo '{"error": "Not Permitted"}';
          exit;
        } else {
          header('HTTP/1.1 400 Bad Request');
          echo '{"error": "Bad Request"}';
          exit;
        }
    } 

    $groups = self::getGroupsForUser($con, $username);
    foreach ($groups as $g) {
        $attr['memberUid'] = $username;
        ldap_mod_del($con, "cn=" . $g . ",ou=groups,dc=geeksoc,dc=org", $attr);
    }

    $user = $_SERVER['PHP_AUTH_USER'];
    AuditController::recordAuditEntry("Deleted account '$username'");
    ircNotify("Account deleted: $username (by $user)");

  }

  static public function resetPassword($username) {
    global $con, $dn;

    requireAuthentication($con);

    header('Content-type: application/json');

    $search = ldap_search($con, $dn, "(uid=$username)");
    exitIfNotFound($con, $search);
    $result = ldap_get_entries($con, $search);

    $pass = self::generatePassword(); 
    mt_srand((double)microtime()*1000000);
    $salt = pack("CCCCCCCC", mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand());
    $hashedpass = "{SSHA}" . base64_encode( sha1( $pass . $salt, true) . $salt );

    $entry['userpassword'] = $hashedpass;
    ldap_modify($con,"uid=$username,$dn",$entry);
    if (ldap_error($con) != "Success") {
        if (ldap_error($con) == "Insufficient access") {
          header('HTTP/1.1 403 Forbidden');
          echo '{"error": "Not Permitted"}';
          exit;
        } else {
          header('HTTP/1.1 400 Bad Request');
          echo '{"error": "Bad Request"}';
          exit;
        }
    } else {
      $output["password"]=$pass;
      echo json_encode($output);
      $mailmessage = <<<EOT
Your GeekSoc password has been reset by an administrator.

Username: $username
New Password: $pass

GeekSoc
http://www.geeksoc.org/
EOT;

      mailNotify($result[0]['mail'][0], "[GeekSoc] Your password has been reset", $mailmessage);

      $user = $_SERVER['PHP_AUTH_USER'];
      ircNotify("Password reset for $username (by $user)");
      
      AuditController::recordAuditEntry("Reset password for '$username'");
    }

  }
  
  static public function changePassword($username) {
    global $con, $dn;

    requireAuthentication($con);

    header('Content-type: application/json');

    $search = ldap_search($con, $dn, "(uid=$username)");
    exitIfNotFound($con, $search);

    $input = json_decode(file_get_contents("php://input"), true);
    $pass = $input['password'];
    mt_srand((double)microtime()*1000000);
    $salt = pack("CCCCCCCC", mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand());
    $hashedpass = "{SSHA}" . base64_encode( sha1( $pass . $salt, true) . $salt );

    $entry['userpassword'] = $hashedpass;
    ldap_modify($con,"uid=$username,$dn",$entry);
    if (ldap_error($con) != "Success") {
        if (ldap_error($con) == "Insufficient access") {
          header('HTTP/1.1 403 Forbidden');
          echo '{"error": "Not Permitted"}';
          exit;
        } else {
          header('HTTP/1.1 400 Bad Request');
          echo '{"error": "Bad Request"}';
          exit;
        }
    }

  }
  
  static public function generatePassword ($length = 8) {
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
  
  static public function createLdapUser($con, $username, $firstname, $lastname, $studentnumber, $email) {
    
    global $dn;
    
    //compute uid
    $users = ldap_get_entries($con, ldap_search($con, $dn, "(objectclass=posixaccount)"));
    $uidno = 10000;
    foreach($users as $u) {
        if ($u['uidnumber'][0] > $uidno) 
            $uidno = $u['uidnumber'][0];
    }
    $uidno += 1;
    
    //compute expiry date
    $expiry = self::computeExpiry(time());
    
    //generate password
    $pass = self::generatePassword(); 
    mt_srand((double)microtime()*1000000);
    $salt = pack("CCCCCCCC", mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand(), mt_rand());
    $hashedpass = "{SSHA}" . base64_encode( sha1( $pass . $salt, true) . $salt );
    
    $newuser['objectclass'][0] = "inetOrgPerson";
    $newuser['objectclass'][1] = "organizationalPerson";
    $newuser['objectclass'][2] = "person";
    $newuser['objectclass'][3] = "top";
    $newuser['objectclass'][4] = "posixAccount";
    $newuser['objectclass'][5] = "shadowAccount";
    $newuser['objectclass'][6] = "gsAccount";
    $newuser['objectclass'][7] = "inetLocalMailRecipient";
    $newuser['cn'] = "$firstname $lastname";
    $newuser['sn'] = $lastname;
    $newuser['givenName'] = $firstname;
    $newuser['title'] = "Member";
    $newuser['uid'] = $username;
    $newuser['uidnumber'] = (int) $uidno;
    $newuser['gidNumber'] = (int) 500;
    $newuser['homeDirectory'] = "/home/$username";
    $newuser['loginShell'] = "/bin/bash";
    $newuser['gecos'] = "$firstname $lastname,,,";
    $newuser['shadowLastChange'] = (int) 10877;
    $newuser['shadowMax'] = (int) 99999;
    $newuser['shadowWarning'] = (int) 7;
    $newuser['mail'] = $email;
    $newuser['studentNumber'] = (int) $studentnumber;
    $newuser['hasPaid'] = "TRUE";
    $newuser['hasSignedTOS'] = "TRUE";
    $newuser['shadowExpire'] = (int) $expiry;
    $newuser['userpassword'] = $hashedpass;
    
    //add to directory
    ldap_add($con,"uid=$username,$dn", $newuser);
    if (ldap_error($con) != "Success") {
        $return = false;
    } else {
          //irc notification
          $user = $_SERVER['PHP_AUTH_USER'];
          ircNotify("Account created for $firstname $lastname: Username: $username, Email: $email (by $user)");

          //email user confirmation
          $userEmail = <<<EOT
Welcome to GeekSoc $firstname!

You may find your new account details below, but please change your password at http://accounts.geeksoc.org/ as soon as possible.

Username: $username
Password: $pass

You may login to the shell server via SSH at shell.geeksoc.org on port 22. IRC may be found at irc.geeksoc.org on port 6667 - #geeksoc is the official channel.

On Windows the program PuTTY may be used to login to the SSH server, while Mac/Linux users will already have SSH installed and may connect using the 'ssh' command from a terminal.

The recommended way of accessing IRC is setting up a persistent connection on Shell using screen and irssi, see http://quadpoint.org/articles/irssi for details on how to set this up.

You can access your @geeksoc.org email account at http://webmail.geeksoc.org/ or by setting up your email client to use mail.geeksoc.org.

Have fun, but please be responsible and abide with the terms of service.

GeekSoc
http://www.geeksoc.org/
EOT;
          mailNotify($email, "[GeekSoc] Your account has been created", $userEmail);

          //email creation notice to gsag
          $adminEmail = <<<EOT
An account has been created by $user for $firstname $lastname:

Username: $username
Email: $email
EOT;
          mailNotify("gsag@geeksoc.org", "[GeekSoc] New account created", $adminEmail);

        }    
    
    //adduser to members group
    $newmember['memberUid'] = $username;
    ldap_mod_add($con, "cn=members,ou=groups,dc=geeksoc,dc=org", $newmember);
    if (ldap_error($con) != "Success") {
        // $return = false; -- ignore failure to add to group
    }
    
    if (!isset($return)) {
      $return = $pass;
    }
    return $return; 
  }
  
  static public function getStatus($expiry, $paid="TRUE") {
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
  
  static private function formatUserArray($ldap_user, $con) {
    $user["username"] = $ldap_user["uid"][0];
    $user["firstname"] = $ldap_user["givenname"][0];
    $user["lastname"] = $ldap_user["sn"][0];
    $user["displayname"] = $ldap_user["cn"][0];
    $user["email"] = $ldap_user["mail"][0];
    $user["title"] = $ldap_user["title"][0];
    $user["studentnumber"] = $ldap_user["studentnumber"][0];
    $user["status"] = self::getStatus($ldap_user["shadowexpire"][0], $ldap_user["haspaid"][0]);
    $user["expiry"] = toDate($ldap_user["shadowexpire"][0]);
    $user["paid"] = toBoolean($ldap_user["haspaid"][0]);
    $user["notes"] = $ldap_user["notes"][0]; //only populated if allowed to see...
    $user["loginshell"] = $ldap_user["loginshell"][0];
    $user["homedirectory"] = $ldap_user["homedirectory"][0];
    $user["uidnumber"] = $ldap_user["uidnumber"][0];
    $user["gidnumber"] = $ldap_user["gidnumber"][0];
    $user["groups"] = self::getGroupsForUser($con, $user["username"]);
    $user["isAdmin"] = in_array("gsag", $user["groups"]);
    
    $sshkeys = array();
    foreach (array_slice($ldap_user["sshpublickey"], 1) as $key) {
      $sshkeys[] = $key;
    }
    $user["sshkeys"] = $sshkeys;
    
    return $user;
  }
  
  static public function computeExpiry($now) {
	
    $nextExpiry = strtotime('first Friday of October', $now);
    $threshold = strtotime('last Friday of May', $now);
      
	  if ($now < $threshold) {
		  $expiry = $nextExpiry;
	  } else {
		 $expiry = strtotime('first Friday of October', $now + 365 * 24 * 60 * 60);
	 }
   $expiry = $expiry/(24 * 60 * 60);
	 return $expiry;        
  }
  
  static private function getGroupsForUser($con, $user) {
      $groups = array();
      foreach (getAllGroups($con) as $groupEntry) {
          if (self::isUserInGroup($con, $user, $groupEntry['cn'][0])) {
              $groups[] = $groupEntry['cn'][0];
          }
      }
      return $groups;
  }
  
  static public function isUserInGroup($con, $user, $group) {
      $group_search = ldap_search($con, "cn=$group,ou=groups,dc=geeksoc,dc=org", "(memberUid=$user)");
      if (ldap_count_entries($con, $group_search) >= 1) {
        return true;
      } else {
        return false;
      }
  }

  
  
  
}


?>
