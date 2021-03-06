<?php

include_once './includes/ldap_connect.php';

/**
* Controller for working with groups
*/
class GroupController
{
  static public function getGroups() {
    global $con, $groupdn;

    requireAuthentication($con);

    header('Content-type: application/json');

    $searchPattern = "(objectclass=posixgroup)";
    $search = ldap_search($con, $groupdn, $searchPattern);
    ldap_sort($con, $search, 'cn');
    $results = ldap_get_entries($con, $search);
    exitIfNotFound($con, $search);

    $output = array();
    foreach (array_slice($results, 1) as $ldap_group) {
      $group = self::formatGroupArray($ldap_group, $con);
      $output[] = $group;
    }

    echo json_encode($output);
  }

  static public function createGroup() {
    global $con, $groupdn;

    requireAuthentication($con);

    header('Content-type: application/json');

    $input = json_decode(file_get_contents("php://input"), true);
    $groupname = $input['name'];

    $search = ldap_search($con, $groupdn, "(cn=$groupname)");
    if (ldap_count_entries($con, $search) != 0) {
      header('HTTP/1.1 400 Bad Request');
      echo '{"error": "Already Exists"}';
      exit;
    }

    $gidno = 10000;
    foreach(getAllGroups($con) as $g) {
        if ($g['gidnumber'][0] > $gidno) 
            $gidno = $g['gidnumber'][0];
    }
    $gidno += 1;


    $newgroup['objectclass'] = "posixGroup";
    $newgroup['cn'] = $groupname;
    $newgroup['userpassword'] = "{crypt}x";
    $newgroup['gidnumber'] = $gidno;

    ldap_add($con, "cn=$groupname,ou=groups,dc=geeksoc,dc=org", $newgroup);
    if (ldap_error($con) != "Success") {
        header('HTTP/1.1 400 Bad Request');
        echo '{"error": "'.ldap_error($con).'"}';
        exit;
    }

    $user = $_SERVER['PHP_AUTH_USER'];
    ircNotify("Group created: $groupname (by $user)");
    AuditController::recordAuditEntry("Created group '$groupname'");

  }

  static public function getGroup($groupname) {
    global $con, $groupdn;

    requireAuthentication($con);

    header('Content-type: application/json');

    $search = ldap_search($con, $groupdn, "(cn=$groupname)");
    ldap_sort($con, $search, 'cn');
    $result = ldap_get_entries($con, $search);
    exitIfNotFound($con, $search);

    $output = self::formatGroupArray($result[0], $con);

    echo json_encode($output); 
  }

  static public function updateGroup($groupname) {
    global $con, $groupdn;

    requireAuthentication($con);

    header('Content-type: application/json');

    $search = ldap_search($con, $groupdn, "(cn=$groupname)");
    ldap_sort($con, $search, 'cn');
    exitIfNotFound($con, $search);
    
    $input = json_decode(file_get_contents("php://input"), true);

    $attrs=array();
    
    setIfDefined($input["gidnumber"], $attrs, "gidnumber");
    setIfDefined($input["members"], $attrs, "memberUid");
    
    ldap_modify($con, "cn=$groupname,$groupdn", $attrs);
    if (ldap_error($con) != "Success") {
      header('HTTP/1.1 400 Bad Request');
      echo '{"error": "Bad Request"}';
      exit;
    }
    AuditController::recordAuditEntry("Edited group '$groupname'");
    
    
  }
  
  static public function addUserToGroup($groupname) {
    global $con, $groupdn;

    requireAuthentication($con);

    header('Content-type: application/json');

    $search = ldap_search($con, $groupdn, "(cn=$groupname)");
    ldap_sort($con, $search, 'cn');
    exitIfNotFound($con, $search);
    
    $input = json_decode(file_get_contents("php://input"), true);
    $user = $input["user"];
    $attrs["memberuid"] = $user;
    
    if (isset($user)) {
      ldap_mod_add($con, "cn=$groupname,$groupdn", $attrs);
      if (ldap_error($con) != "Success") {
        header('HTTP/1.1 400 Bad Request');
        echo '{"error": "Bad Request"}';
        exit;
      }
    } else {
      header('HTTP/1.1 400 Bad Request');
      echo '{"error": "Bad Request"}';
      exit;
    }
    AuditController::recordAuditEntry("Added user '$user' to group '$groupname'");
  }
  
  static public function deleteUserFromGroup($groupname) {
    global $con, $groupdn;

    requireAuthentication($con);

    header('Content-type: application/json');

    $search = ldap_search($con, $groupdn, "(cn=$groupname)");
    ldap_sort($con, $search, 'cn');
    exitIfNotFound($con, $search);
    
    $input = json_decode(file_get_contents("php://input"), true);
    $user = $input["user"];
    $attrs["memberuid"] = $user;
    
    if (isset($user)) {
      ldap_mod_del($con, "cn=$groupname,$groupdn", $attrs);
      if (ldap_error($con) != "Success") {
        header('HTTP/1.1 400 Bad Request');
        echo '{"error": "Bad Request"}';
        exit;
      }
    } else {
      header('HTTP/1.1 400 Bad Request');
      echo '{"error": "Bad Request"}';
      exit;
    }
    AuditController::recordAuditEntry("Removed user '$user' from group '$groupname'");
  }

  static public function deleteGroup($groupname) {
    global $con, $groupdn;

    requireAuthentication($con);

    header('Content-type: application/json');

    $search = ldap_search($con, $groupdn, "(cn=$groupname)");
    exitIfNotFound($con, $search);

    ldap_delete($con,"cn=".$groupname.",".$groupdn);
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

    $user = $_SERVER['PHP_AUTH_USER'];
    ircNotify("Group deleted: $groupname (by $user)");
    AuditController::recordAuditEntry("Deleted group '$groupname'");
  }
  
  static private function formatGroupArray($ldap_group, $con) {
    $group["name"] = $ldap_group["cn"][0];
    $group["gidnumber"] = $ldap_group["gidnumber"][0];
    
    $members = array();
    foreach (array_slice($ldap_group["memberuid"], 1) as $member) {
      $members[] = $member;
    }
    $group["members"] = $members;
    
    return $group;
  }
  
}

?>
