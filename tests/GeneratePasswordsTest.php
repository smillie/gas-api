<?php

require_once(dirname(__FILE__) . '/../controllers/UserController.php');

class GeneratePasswordsTest extends PHPUnit_Framework_TestCase
{
    public function testDefaultLength()
    {
        $password = UserController::generatePassword();
        $this -> assertEquals(8, strlen($password));
    }
    
    public function testMaxLength()
    {
        $password = UserController::generatePassword(46);
        $this -> assertEquals(46, strlen($password));
        
        $passwordLong = UserController::generatePassword(48);
        $this -> assertEquals(46, strlen($passwordLong));
    }
    

}
