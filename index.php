<?php
error_reporting(0);
include_once './vendor/epiphany/Epi.php';
include_once './includes/ldap_connect.php';
include_once './includes/functions.php';

Epi::setPath('base', './vendor/epiphany');
Epi::setSetting('exceptions', true);
Epi::init('route');

getRoute()->get('/', 'showEndPoints');

getRoute()->post('/authenticate/(\w+)', 'authenticate'); //works :D

getRoute()->get('/users', 'getUsers'); //works :D 
getRoute()->post('/users', 'createUser'); //works :D 
getRoute()->get('/users/(\w+)', 'getUser'); //works :D
getRoute()->put('/users/(\w+)', 'updateUser');
getRoute()->delete('/users/(\w+)', 'deleteUser'); //works :D
getRoute()->post('/users/(\w+)/resetpassword', 'resetPassword'); //works :D 

getRoute()->get('/groups', 'getGroups'); //works :D 
getRoute()->post('/groups', 'createGroup'); //works :D 
getRoute()->get('/groups/(\w+)', 'getGroup'); //works :D 
getRoute()->put('/groups/(\w+)', 'updateGroup');
getRoute()->post('/groups/(\w+)/adduser', 'addUserToGroup');
getRoute()->post('/groups/(\w+)/deleteUser', 'deleteUserFromGroup');
getRoute()->delete('/groups/(\w+)', 'deleteGroup'); //works :D 


//MySql stuff down here...
getRoute()->get('/newmembers', 'getNewMembers');
getRoute()->post('/newmembers', 'createNewMembers');
getRoute()->get('/newmembers/(\w+)', 'getNewMember');
getRoute()->put('/newmembers/(\w+)', 'updateNewMember');
getRoute()->post('/newmembers/(\w+)', 'activateNewMember');

getRoute()->get('/search/(\w+)', 'search');
//seperate user and group search?
//search as filters on GET /users/ (query strings?)


getRoute()->run();


function authenticate($username) {
  global $con, $dn;
  
  $input = json_decode(file_get_contents("php://input"), true);
  $password = $input['password'];
  
  if (ldap_bind($con, "uid=$username,$dn", $password)===false){
    header('Content-type: application/json');
    header('HTTP/1.1 401 Unauthorized');
  }
}


function getUsers() {
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

function createUser() {
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

function getUser($username) {
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

function deleteUser($username) {
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

function resetPassword($username) {
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


function getGroups() {
  global $con, $groupdn;
  
  requireAuthentication($con);
  
  header('Content-type: application/json');
  
  $searchPattern = "(objectclass=posixgroup)";
  $search = ldap_search($con, $dn, $searchPattern);
  ldap_sort($con, $search, 'cn');
  $results = ldap_get_entries($con, $search);
  exitIfNotFound($con, $search);

  $output = array();
  foreach (array_slice($results, 1) as $ldap_group) {
    $group = formatGroupArray($ldap_group, $con);
    $output[] = $group;
  }
  
  echo json_encode($output);
}

function createGroup() {
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
  
  ircNotify("Group created: $groupname");
  
}

function getGroup($groupname) {
  global $con, $groupdn;
  
  requireAuthentication($con);
  
  header('Content-type: application/json');
  
  $search = ldap_search($con, $groupdn, "(cn=$groupname)");
  ldap_sort($con, $search, 'cn');
  $result = ldap_get_entries($con, $search);
  exitIfNotFound($con, $search);
  
  $output = formatGroupArray($result[0], $con);
  
  echo json_encode($output); 
}

function deleteGroup($groupname) {
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

  ircNotify("Group deleted: $groupname");
}

?>
