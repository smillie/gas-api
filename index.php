<?php
error_reporting(0);

include_once 'config.php';
include_once './vendor/epiphany/Epi.php';
include_once './includes/ldap_connect.php';
include_once './includes/functions.php';

function autoloadControllers($class_name) {
  include './controllers/'.$class_name . '.php';
}
spl_autoload_register('autoloadControllers');

Epi::setPath('base', './vendor/epiphany');
Epi::setSetting('exceptions', true);
Epi::init('route');

getRoute()->get('/', 'showEndPoints');

getRoute()->post('/authenticate/(\w+)', array('UserController', 'authenticate')); //works :D

getRoute()->get('/users(/*)', array('UserController','getUsers')); //works :D 
getRoute()->post('/users(/*)', array('UserController','createUser')); //works :D 
getRoute()->get('/users/(\w+)', array('UserController','getUser')); //works :D
getRoute()->put('/users/(\w+)', array('UserController','updateUser')); //works :D
getRoute()->delete('/users/(\w+)', array('UserController','deleteUser')); //works :D
getRoute()->post('/users/(\w+)/resetpassword', array('UserController','resetPassword')); //works :D 
getRoute()->post('/users/(\w+)/changepassword', array('UserController','changePassword')); //works :D


getRoute()->get('/groups(/*)', array('GroupController','getGroups')); //works :D 
getRoute()->post('/groups(/*)', array('GroupController','createGroup')); //works :D 
getRoute()->get('/groups/(\w+)', array('GroupController','getGroup')); //works :D 
getRoute()->put('/groups/(\w+)', array('GroupController','updateGroup')); //works :D
getRoute()->post('/groups/(\w+)/adduser', array('GroupController','addUserToGroup'));//works :D 
getRoute()->post('/groups/(\w+)/deleteuser', array('GroupController','deleteUserFromGroup'));//works :D 
getRoute()->delete('/groups/(\w+)', array('GroupController','deleteGroup')); //works :D 


//MySql stuff down here...
getRoute()->get('/newmembers(/*)', array('NewMemberController','getNewMembers'));
getRoute()->post('/newmembers(/*)', array('NewMemberController','createNewMembers'));
getRoute()->get('/newmembers/(\w+)', array('NewMemberController','getNewMember'));
// getRoute()->put('/newmembers/(\w+)', array('NewMemberController','updateNewMember'));
getRoute()->post('/newmembers/(\w+)', array('NewMemberController','activateNewMember'));

getRoute()->get('/search/(\w+)', 'search');
//seperate user and group search?
//search as filters on GET /users/ (query strings?)


getRoute()->run();


?>
