<?php

class AuditController
{

    static public function getLog() {

        global $con, $conf;

        requireAuthentication($con);
        requireAdminUser($con);

      $mysqli = new mysqli($conf["db_host"], $conf["db_user"], $conf["db_pass"], $conf["db_name"]);
      if (mysqli_connect_errno()) {
        printf('{"error":"Connect failed: %s"}', mysqli_connect_error());
        exit();
      }
    
      if ($stmt = $mysqli->prepare("SELECT timestamp, user, message FROM audit ORDER BY timestamp DESC LIMIT 100")) {
        $stmt->execute();

        $users = array();
        $stmt->bind_result($timestamp, $user, $message);
        while ($stmt->fetch()) {
            $log[] = array(
                'timestamp' => $timestamp,
                'user'=> $user,
                'message'=> $message,
            );
        }
        $stmt->close();
      
      echo json_encode($log);
        }
    }
      
    static public function recordAuditEntry($logMessage) {
        global $conf;
        
        $user = $_SERVER['PHP_AUTH_USER'];

        $mysqli = new mysqli($conf["db_host"], $conf["db_user"], $conf["db_pass"], $conf["db_name"]);
        if (mysqli_connect_errno()) {
            header('HTTP/1.1 500 Internal Server Error');
            printf('{"error":"Connect failed: %s"}', mysqli_connect_error());
            exit();
        }
        if ($stmt = $mysqli->prepare("INSERT INTO audit (user, message) values (?, ?)")) {
            $stmt->bind_param('ss', $user, $logMessage);
            $stmt->execute();
            $stmt->close(); 
        }       else {
        header('HTTP/1.1 400 Bad Request');
        echo '{"error": "Bad Request"}';
        exit;
      } 
    }


}
?>

