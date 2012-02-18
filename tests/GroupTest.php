<?php
require_once "../data/user.php";
require_once "../data/group.php";

class GroupTest extends PHPUnit_Framework_TestCase {
	/**
	* @covers Group::create
	* @test
	*/
	public function testCreate() {
		Group::create("TestGroup", $groupid);
		Group::create("TestGroup2");
		
		$this->assertEquals(1, $groupid);
	}
	
	/**
	* @depends testCreate
	* @covers Group::openWithId
	* @test
	*/
	public function testOpenWithId() {
		$group = new Group();
		$this->assertTrue($group->openWithId(1));
		$this->testgroup = $group;
	}
	
	/**
	* @depends testOpenWithId
	* @covers Group::getId
	* @test
	*/
	public function testGetId() {
		$group = new Group();
		$group->openWithId(1);
		
		$this->assertEquals(1, $group->getId());
		
		$group = new Group();
		$group->openWithId(2);
		
		$this->assertEquals(2, $group->getId());
	}
	
	/**
	* @depends testOpenWithId
	* @covers Group::setName
	* @covers Group::getName
	* @test
	*/
	public function testName() {
		$group = new Group();
		$group->openWithId(1);
		
		$group->setName("TestGroup (changed)");
		$this->assertEquals("TestGroup (changed)", $group->getName());
	}
	
	/**
	* @depends testOpenWithId
	* @covers Group::addPermission
	* @covers Group::hasPermission
	* @covers Group::removePermission
	* @test
	*/
	public function testPermission() {
		$group = new Group();
		$group->openWithId(1);
		
		$this->assertFalse($group->hasPermission("test.permission"));
		
		$this->assertTrue($group->addPermission("test.permission"));
		
		$this->assertTrue($group->hasPermission("test.permission"));
		
		$this->assertFalse($group->addPermission("test.permission"));
		
		$this->assertTrue($group->removePermission("test.permission"));
		
		$this->assertFalse($group->hasPermission("test.permission"));
		
		$this->assertFalse($group->removePermission("test.permission"));
	}
	
	/**
	* @depends testOpenWithId
	* @covers Group::addCustomField
	* @covers Group::saveCustomField
	* @covers Group::getCustomField
	* @covers Group::removeCustomField
	* @test
	*/
	public function testCustomField() {
		$group = new Group();
		$group->openWithId(1);
		
		$group->addCustomField("testfield", "INT NOT NULL");
		
		$this->assertTrue($group->saveCustomField("testfield", 200));
		
		$this->assertEquals(200, $group->getCustomField("testfield"));
		
		$group->removeCustomField("testfield");
	}
	
	/**
	* @depends testOpenWithId
	* @covers Group::getAllUsersInGroup
	* @test
	*/
	public function testGetAllUsersInGroup() {
		$group = new Group();
		$group->openWithId(1);
		
		$this->assertEmpty($group->getAllUsersInGroup());
	}
	
	/**
	* @depends testCreate
	* @depends testGetId
	* @covers Group::getAllGroups
	* @test
	*/
	public function testGetAllGroups() {
		$groups = Group::getAllGroups();
		$this->assertEquals(2, count($groups));
		
		$this->assertEquals(1, $groups[0]->getId());
		$this->assertEquals(2, $groups[1]->getId());
	}
	
	/**
	* @depends testOpenWithId
	* @covers Group::deleteGroup
	* @test
	*/
	public function testDelete() {
		$group = new Group();
		$group->openWithId(1);
		$this->assertTrue($group->deleteGroup());
		
		$group = new Group();
		$group->openWithId(2);
		$this->assertTrue($group->deleteGroup());
	}
	
	public static function tearDownAfterClass() {
		$dbCon = new DatabaseConnection();
		$stmt = $dbCon->prepare("TRUNCATE TABLE `{dbpre}groups`");
		$stmt->execute();
		$dbCon->close();
	}
}
?>