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

getRoute()->get('/users/', 'getUsers'); //works :D 
getRoute()->post('/users/', 'createUser');
getRoute()->get('/users/(\w+)', 'getUser'); //works :D
getRoute()->put('/users/(\w+)', 'updateUser');
getRoute()->delete('/users/(\w+)', 'deleteUser'); //works :D
getRoute()->post('/users/(\w+)/resetpassword', 'resetPassword');

getRoute()->get('/groups/', 'getGroups'); //works :D 
getRoute()->post('/groups/', 'createGroup');
getRoute()->get('/groups/(\w+)', 'getGroup'); //works :D 
getRoute()->put('/groups/(\w+)', 'updateGroup');
getRoute()->delete('/groups/(\w+)', 'deleteGroup');

//MySql stuff down here...
getRoute()->get('/newmembers/', 'getNewMembers');
getRoute()->post('/newmembers/', 'createNewMembers');
getRoute()->get('/newmembers/(\w+)', 'getNewMember');
getRoute()->put('/newmembers/(\w+)', 'updateNewMember');
getRoute()->post('/newmembers/(\w+)', 'activateNewMember');

getRoute()->get('/search/(\w+)', 'search');
//seperate user and group search?
//search as filters on GET /users/ (query strings?)


getRoute()->run(); 
//can this routing stuff be used with basic auth??


function authenticate($username) {
  global $con, $dn;
  
  $json = json_decode(file_get_contents("php://input"), true);
  $password = $json['password'];
  
  if (ldap_bind($con, "uid=".$username.",".$dn, $password)===false){
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
      }  
  } 

  $groups = getGroupsForUser($con, $username);
  foreach ($groups as $g) {
      $attr['memberUid'] = $username;
      ldap_mod_del($con, "cn=" . $g . ",ou=groups,dc=geeksoc,dc=org", $attr);
  }

  ircNotify("Account deleted: $username");
  
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


?>
