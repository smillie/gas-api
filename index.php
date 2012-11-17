<?php
error_reporting(0);
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
getRoute()->put('/users/(\w+)', array('UserController','updateUser'));
getRoute()->delete('/users/(\w+)', array('UserController','deleteUser')); //works :D
getRoute()->post('/users/(\w+)/resetpassword', array('UserController','resetPassword')); //works :D 

getRoute()->get('/groups(/*)', array('GroupController','getGroups')); //works :D 
getRoute()->post('/groups(/*)', array('GroupController','createGroup')); //works :D 
getRoute()->get('/groups/(\w+)', array('GroupController','getGroup')); //works :D 
getRoute()->put('/groups/(\w+)', array('GroupController','updateGroup'));
getRoute()->post('/groups/(\w+)/adduser', array('GroupController','addUserToGroup'));
getRoute()->post('/groups/(\w+)/deleteUser', array('GroupController','deleteUserFromGroup'));
getRoute()->delete('/groups/(\w+)', array('GroupController','deleteGroup')); //works :D 


//MySql stuff down here...
getRoute()->get('/newmembers(/*)', 'getNewMembers');
getRoute()->post('/newmembers(/*)', 'createNewMembers');
getRoute()->get('/newmembers/(\w+)', 'getNewMember');
getRoute()->put('/newmembers/(\w+)', 'updateNewMember');
getRoute()->post('/newmembers/(\w+)', 'activateNewMember');

getRoute()->get('/search/(\w+)', 'search');
//seperate user and group search?
//search as filters on GET /users/ (query strings?)


getRoute()->run();


?>
