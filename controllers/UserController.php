<?php

include_once './includes/ldap_connect.php';

/**
* Controller for working with users
*/
class UserController
{
  
  static public function authenticate($username) {
    global $con, $dn;

    $input = json_decode(file_get_contents("php://input"), true);
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
      $user = formatUserArray($ldap_user, $con);
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

    $created = createLdapUser($con, $username, $firstname, $lastname, $studentnumber, $email);
    if ($created == true) {
      echo "{\"success\": \"$username created with password $created\"}";
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

    $output = formatUserArray($result[0], $con);

    echo json_encode($output);
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

    $groups = getGroupsForUser($con, $username);
    foreach ($groups as $g) {
        $attr['memberUid'] = $username;
        ldap_mod_del($con, "cn=" . $g . ",ou=groups,dc=geeksoc,dc=org", $attr);
    }

    ircNotify("Account deleted: $username");

  }

  static public function resetPassword($username) {
    global $con, $dn;

    requireAuthentication($con);

    header('Content-type: application/json');

    $search = ldap_search($con, $dn, "(uid=$username)");
    exitIfNotFound($con, $search);

    $pass = generatePassword(); 
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
      //send password notifications
    }

  }
  
  static private function generatePassword ($length = 8) {
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
  
  
}


?>