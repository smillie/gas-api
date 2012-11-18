<?php

include_once './includes/ldap_connect.php';

/**
* Controller for working with the new members queue
*/
class NewMemberController
{
  
  static public function getNewMembers() {
    global $con, $dn;

    requireAuthentication($con);
    requireAdminUser($con);

    header('Content-type: application/json');
    
    $mysqli = new mysqli("localhost", "root", "wibble", "gas-users");
    if (mysqli_connect_errno()) {
      printf('{"error":"Connect failed: %s"}', mysqli_connect_error());
      exit();
    }
    
    if ($stmt = $mysqli->prepare("SELECT ID, firstname, lastname, username, studentnumber, email FROM newusers WHERE IS_DELETED = false")) {

      /* Execute the prepared Statement */
      $stmt->execute();

      $users = array();
      $stmt->bind_result($id, $first, $last, $uid, $stuno, $email);
      while ($stmt->fetch()) {
        // printf("%s %s %s %i %s\n", $first, $last, $uid, $stuno, $email);
        $u['id'] = $id;
        $u['firstname'] = $first;
        $u['lastname'] = $last;
        $u['username'] = $uid;
        $u['studentnumber'] = $stuno;
        $u['email'] = $email;

        $users[] = $u;
      }
      $stmt->close();
      echo json_encode($users);
      
  }
    else {
      /* Error */
    }
    
  }
  
}
?>