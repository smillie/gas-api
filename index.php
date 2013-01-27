<?php
error_reporting(0);

/* TODO
  * tests (can hopefully port some of the GAS ones)
  * fix compute expiry date
*/

include_once 'config.php';
include_once './vendor/epiphany/Epi.php';
include_once './includes/ldap_connect.php';
include_once './includes/functions.php';
include_once './classes/newuser.php';

function autoloadControllers($class_name) {
  include './controllers/'.$class_name . '.php';
}
spl_autoload_register('autoloadControllers');

Epi::setPath('base', './vendor/epiphany');
Epi::setSetting('exceptions', true);
Epi::init('route');

getRoute()->get('/', 'showEndPoints');

getRoute()->post('/authenticate/([a-zA-Z0-9\-]+)', array('UserController', 'authenticate')); //works :D

getRoute()->get('/users(/*)', array('UserController','getUsers')); //works :D 
getRoute()->post('/users(/*)', array('UserController','createUser')); //works :D 
getRoute()->get('/users/([a-zA-Z0-9\-]+)', array('UserController','getUser')); //works :D
getRoute()->put('/users/([a-zA-Z0-9\-]+)', array('UserController','updateUser')); //works :D
getRoute()->delete('/users/([a-zA-Z0-9\-]+)', array('UserController','deleteUser')); //works :D
getRoute()->post('/users/([a-zA-Z0-9\-]+)/resetpassword', array('UserController','resetPassword')); //works :D 
getRoute()->post('/users/([a-zA-Z0-9\-]+)/changepassword', array('UserController','changePassword')); //works :D


getRoute()->get('/groups(/*)', array('GroupController','getGroups')); //works :D 
getRoute()->post('/groups(/*)', array('GroupController','createGroup')); //works :D 
getRoute()->get('/groups/([a-zA-Z0-9\-]+)', array('GroupController','getGroup')); //works :D 
getRoute()->put('/groups/([a-zA-Z0-9\-]+)', array('GroupController','updateGroup')); //works :D
getRoute()->post('/groups/([a-zA-Z0-9\-]+)/adduser', array('GroupController','addUserToGroup')); //works :D 
getRoute()->post('/groups/([a-zA-Z0-9\-]+)/deleteuser', array('GroupController','deleteUserFromGroup'));//works :D 
getRoute()->delete('/groups/([a-zA-Z0-9\-]+)', array('GroupController','deleteGroup')); //works :D 


//MySql stuff down here...
getRoute()->get('/newmembers(/*)', array('NewMemberController','getNewMembers')); //works :D 
getRoute()->post('/newmembers(/*)', array('NewMemberController','createNewMember')); //works :D 
getRoute()->get('/newmembers/([a-zA-Z0-9\-]+)', array('NewMemberController','getNewMember')); //works :D 
// getRoute()->put('/newmembers/([a-zA-Z0-9\-]+)', array('NewMemberController','updateNewMember'));
getRoute()->post('/newmembers/([a-zA-Z0-9\-]+)', array('NewMemberController','activateNewMember')); //works :D
getRoute()->delete('/newmembers/([a-zA-Z0-9\-]+)', array('NewMemberController','deleteNewMember')); //works :D


getRoute()->get('/search/([a-zA-Z0-9\-]+)', array('UserController', 'search')); //works :D


getRoute()->run();

?>
