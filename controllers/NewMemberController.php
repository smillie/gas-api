<?php

include_once './includes/ldap_connect.php';

/**
* Controller for working with the new members queue
*/
class NewMemberController
{
  
  static public function getNewMembers() {
    global $con, $dn, $conf;

    requireAuthentication($con);
    requireAdminUser($con);

    header('Content-type: application/json');
    
    $mysqli = new mysqli($conf["db_host"], $conf["db_user"], $conf["db_pass"], $conf["db_name"]);
    if (mysqli_connect_errno()) {
      printf('{"error":"Connect failed: %s"}', mysqli_connect_error());
      exit();
    }
    
    if ($stmt = $mysqli->prepare("SELECT ID, firstname, lastname, username, studentnumber, email FROM newusers WHERE IS_DELETED = false ORDER BY ID")) {

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
  
  static public function getNewMember($id) {
    global $con, $dn, $conf;

    requireAuthentication($con);
    requireAdminUser($con);

    header('Content-type: application/json');
    
    $mysqli = new mysqli($conf["db_host"], $conf["db_user"], $conf["db_pass"], $conf["db_name"]);
    if (mysqli_connect_errno()) {
      printf('{"error":"Connect failed: %s"}', mysqli_connect_error());
      exit();
    }
    
    if ($stmt = $mysqli->prepare("SELECT ID, firstname, lastname, username, studentnumber, email FROM newusers WHERE ID = ? AND IS_DELETED = false ORDER BY ID")) {

      $stmt->bind_param('i', $id);
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
      
      if (sizeof($users) == 0) {
        header('HTTP/1.1 404 Not Found');
        echo '{"error": "Not Found"}';
        exit;
      } else {
        echo json_encode($users[0]);
      }
    } else {
      header('HTTP/1.1 400 Bad Request');
      echo '{"error": "Bad Request"}';
      exit;
    }
  }
  
  static public function createNewMember() {
    global $con, $dn, $conf;

    header('Content-type: application/json');

    $input = json_decode(file_get_contents("php://input"), true);
    
    $user = new NewUser();
    $user -> setName($input['firstname'], $input['lastname']);
    $user -> setStudentNumber($input['studentnumber']);
    $user -> setEmail($input['email']);
    
    $first = $user -> firstName();
    $last = $user -> lastName();
    $email = $user -> email();
    $stuno = $user -> studentNumber();
    $uid = $user -> username();
    if (count($user -> validate()) == 0) {
      $mysqli = new mysqli($conf['db_host'], $conf['db_user'], $conf['db_pass'], $conf['db_name']);
      if (mysqli_connect_errno()) {
        header('HTTP/1.1 500 Internal Server Error');
        echo '{"error": "Internal Server Error"}';
        exit;
      }
      if ($stmt = $mysqli->prepare("INSERT INTO newusers (firstname, lastname, username, studentnumber, email) values (?, ?, ?, ?, ?)")) {
        $stmt->bind_param('sssis', $first, $last, $uid, $stuno, $email);
        $stmt->execute();
        $stmt->close(); 
        
        ircNotify("$first $last has joined GeekSoc and is awaiting account activation.");

        $adminEmail = <<<EOT
$first $last has joined GeekSoc. Please verify they have paid then create their account through GAS.

Username: $uid
First name: $first
Last name: $last
Student Number: $stuno
Email: $email

EOT;
        mailNotify("gsag@geeksoc.org", "[GeekSoc] New member registered", $adminEmail);

      }
      else {
        header('HTTP/1.1 400 Bad Request');
        echo '{"error": "Bad Request"}';
        exit;
      } 
    } else {
      header('HTTP/1.1 400 Bad Request');
      echo '{"error": "Bad Request"}';
      exit;
    }
  } 
  
  static public function deleteNewMember($id) {
    global $con, $dn, $conf;

    requireAuthentication($con);
    requireAdminUser($con);

    header('Content-type: application/json');
    
    $mysqli = new mysqli($conf["db_host"], $conf["db_user"], $conf["db_pass"], $conf["db_name"]);
    if (mysqli_connect_errno()) {
      printf('{"error":"Connect failed: %s"}', mysqli_connect_error());
      exit();
    }
    
    if ($stmt = $mysqli->prepare("SELECT ID, firstname, lastname, username, studentnumber, email FROM newusers WHERE ID = ? AND IS_DELETED = false ORDER BY ID")) {

      $stmt->bind_param('i', $id);
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
      
      if (sizeof($users) == 0) {
        header('HTTP/1.1 404 Not Found');
        echo '{"error": "Not Found"}';
        exit;
      } else {
        if ($stmt = $mysqli->prepare("DELETE FROM newusers WHERE id = ?")) {
        
              $stmt->bind_param('i', $id);
        
              $stmt->execute();
        
              $stmt->close(); 
        }
        else {
          header('HTTP/1.1 400 Bad Request');
          echo '{"error": "Bad Request"}';
          exit;
        }
      }
    } else {
      header('HTTP/1.1 400 Bad Request');
      echo '{"error": "Bad Request"}';
      exit;
    }
    AuditController::recordAuditEntry("Removed $first $last from the new members queue");
  }

  static public function activateNewMember($id) {
    
    global $con, $dn, $conf;

    requireAuthentication($con);
    requireAdminUser($con);

    header('Content-type: application/json');
    
    $mysqli = new mysqli($conf["db_host"], $conf["db_user"], $conf["db_pass"], $conf["db_name"]);
    if (mysqli_connect_errno()) {
      printf('{"error":"Connect failed: %s"}', mysqli_connect_error());
      exit();
    }
    
    if ($stmt = $mysqli->prepare("SELECT ID, firstname, lastname, username, studentnumber, email FROM newusers WHERE ID = ? AND IS_DELETED = false ORDER BY ID")) {

      $stmt->bind_param('i', $id);
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
      
      if (sizeof($users) == 0) {
        header('HTTP/1.1 404 Not Found');
        echo '{"error": "Not Found"}';
        exit;
      } else {  
        $returnMessage = UserController::createLdapUser($con, $uid, $first, $last, $stuno, $email);
        if (isset($returnMessage)) {
            $success = $returnMessage;
            AuditController::recordAuditEntry("Created account '$uid'");
        } else {
            header('HTTP/1.1 400 Bad Request');
            echo '{"error": "Bad Request"}';
            exit;
        }
        
        if ((!$success==false) && $stmt = $mysqli->prepare("UPDATE newusers SET IS_DELETED=true WHERE id = ?")) {
        
              $stmt->bind_param('i', $id);
        
              $stmt->execute();
        
              $stmt->close(); 
        } else {
          header('HTTP/1.1 400 Bad Request');
          echo '{"error": "Bad Request"}';
          exit;
        }
      }
    } else {
      header('HTTP/1.1 400 Bad Request');
      echo '{"error": "Bad Request"}';
      exit;
    }
  }
    
  

}

?>
