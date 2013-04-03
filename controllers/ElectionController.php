<?php

/**
* 
*/
class ElectionController
{
  
  static public function getPositions() {
      global $conf;
      
      $mysqli = new mysqli($conf["db_host"], $conf["db_user"], $conf["db_pass"], $conf["db_name"]);
      if (mysqli_connect_errno()) {
        printf('{"error":"Connect failed: %s"}', mysqli_connect_error());
        exit();
      }
    
      if ($stmt = $mysqli->prepare("SELECT name, description FROM positions")) {

        $stmt->execute();

        $users = array();
        $stmt->bind_result($name, $description);
        while ($stmt->fetch()) {
            $offices[] = array(
                'name' => $name,
                'description'=> $description,
            );
        }
        $stmt->close();
      
      echo json_encode($offices);
    }
    
  }
  
  static public function getEligibleMembers() {
      global $con, $dn;
      
      requireAuthentication($con);
      
      $searchPattern = "(objectclass=posixaccount)";
      $search = ldap_search($con, $dn, $searchPattern);
      ldap_sort($con, $search, 'uid');
      $results = ldap_get_entries($con, $search);
      exitIfNotFound($con, $search);

      $output = array();
      foreach (array_slice($results, 1) as $ldap_user) {
        $user = $ldap_user["givenname"][0] ." ". $ldap_user["sn"][0];
        if (isset($ldap_user["haspaid"][0]) && toBoolean($ldap_user["haspaid"][0])) {
            if ($_SERVER['PHP_AUTH_USER'] != $ldap_user["uid"][0] ){
                $output[] = $user; 
            }
        }
      }
      
      echo json_encode($output);
  }
  
  static public function handleNomination() {
      global $con, $dn, $conf;
      
      requireAuthentication($con);
      
      $input = json_decode(file_get_contents("php://input"), true);
      
      foreach (array_keys($input['nominations']) as $position) {
           
          if (isset($input['nominations'][$position]) && $input['nominations'][$position] != "") {
              $nominee = $input['nominations'][$position];
              $nominator = $input['user'];

              ircnotify("$nominee has been nominated for $position by $nominator");
              
              $adminEmail = <<<EOT
$nominee has been nominated for $position by $nominator.

EOT;
              mailNotify("gsag@geeksoc.org", "[GeekSoc] Elections: Nomination for $position", $adminEmail);
              
              $mysqli = new mysqli($conf['db_host'], $conf['db_user'], $conf['db_pass'], $conf['db_name']);
              if (mysqli_connect_errno()) {
                header('HTTP/1.1 500 Internal Server Error');
                echo '{"error": "Internal Server Error"}';
                exit;
              }
              if ($stmt = $mysqli->prepare("INSERT INTO nominations (nominee, nominator, position) values (?, ?, ?)")) {
                $stmt->bind_param('sss', $nominee, $nominator, $position);
                $stmt->execute();
                $stmt->close(); 
            }
          
          }
          
      }  

  }
  
}

?>