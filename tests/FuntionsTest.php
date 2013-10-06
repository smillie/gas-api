<?php

require_once(dirname(__FILE__) . '/../includes/functions.php');

class FunctionsTest extends PHPUnit_Framework_TestCase
{

    public function testToDate() {
        $this -> assertEquals('1970-01-01', toDate(0));
        $this -> assertEquals('1970-01-02', toDate(1));
        $this -> assertEquals('1969-12-31', toDate(-1));
        $this -> assertEquals('2013-10-18', toDate(15996));
    }

    public function testToBoolean() {
        $this -> assertEquals(true, toBoolean('TRUE'));
        $this -> assertEquals(false, toBoolean('FALSE'));
        $this -> assertEquals(false, toBoolean('isdjkflksjdhfsdjf'));
    }

    public function testToLDAPBoolean() {
        $this -> assertEquals("TRUE", booleanToLdapBoolean(true));
        $this -> assertEquals("FALSE", booleanToLdapBoolean(false));
    }

    public function testSetIfDefined() {
        $array = array();
        setIfDefined('wibble', $array, 'test');
        $this -> assertArrayHasKey('test', $array);
        $this -> assertEquals('wibble', $array['test']);
    }

    public function testSetIfDefinedNullAttribute() {
        $array = array();
        setIfDefined(null, $array, 'test');
        $this -> assertArrayNotHasKey('test', $array);
    }

    public function testSetIfDefinedEmptyString() {
        $array = array();
        setIfDefined('', $array, 'test');
        $this -> assertArrayHasKey('test', $array);
        $this -> assertEquals(array(), $array['test']);
    }

}

?>
