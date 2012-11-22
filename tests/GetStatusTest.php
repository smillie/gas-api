<?php

require_once(dirname(__FILE__) . '/../controllers/UserController.php');

class GetStatusTest extends PHPUnit_Framework_TestCase
{
    
    private $today;
    
    public function setUp()
    {
      $this -> today = intval(time()/(60*60*24));
    }
    
    public function testActive()
    {
        $status = UserController::getStatus($this->today+61, "TRUE");
        $this -> assertContains("Active", $status);
    }
    
    public function testNoExpiry()
    {
        $status = UserController::getStatus(NULL, "TRUE");
        $this -> assertContains("Active", $status);
    }
    
    public function testNoExpiryNotPaid()
    {
        $status = UserController::getStatus(NULL, "FALSE");
        $this -> assertContains("Not Paid", $status);
    }
    
    public function testNoExpiryDoesntPay()
    {
        $status = UserController::getStatus(NULL, NULL);
        $this -> assertContains("Active", $status);
    }
      
    public function testNotPaid()
    {
        $status = UserController::getStatus($this->today+61, "FALSE");
        $this -> assertContains("Not Paid", $status);
    }
    
    public function testDisabled()
    {
        $status = UserController::getStatus(1, "TRUE");
        $this -> assertContains("Disabled", $status);
    }
    
    public function testDisabledNotPaid()
    {
        $status = UserController::getStatus(1, "FALSE");
        $this -> assertContains("Disabled", $status);
    }
    
    public function testExpiring()
    {
        $status = UserController::getStatus($this->today+60, "TRUE");
        $this -> assertContains("Expiring", $status);
    }
    
    public function testExpiringNotPaid()
    {
         $status = UserController::getStatus($this->today+60, "FALSE");
         $this -> assertContains("Expiring (Not Paid)", $status);
    }
    
    public function testExpired()
    {
        $status = UserController::getStatus($this->today, "TRUE");
        $this -> assertContains("Expired", $status);
    }
    
    public function testExpiredNotPaid()
    {
        $status = UserController::getStatus($this->today, "FALSE");
        $this -> assertContains("Expired", $status);
    }
}
