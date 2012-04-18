<?php
require_once "../data/class.user.php";
require_once "../data/class.group.php";

class UserTest extends PHPUnit_Framework_TestCase {
	/**
	* @covers User::create
	* @test
	*/
	public function testCreate() {
		User::create("test1", "Test User 1", "password", "test1@example.com");
		
		User::create("test2", "Test User 2", "password", "test2@example.com", false, false);
		
		User::create("test3", "Test User 3", "password", "test3@example.com", true, "stan", $userid);
		$this->assertEquals(3, $userid);
	}
	
	/**
	* @depends testCreate
	* @covers User::openWithId
	* @test
	*/
	public function testOpenWithId() {
		$user = new User();
		$this->assertTrue($user->openWithId(1));
		
		$user = new User();
		$this->assertTrue($user->openWithId(2));
		
		$user = new User();
		$this->assertTrue($user->openWithId(3));
	}
	
	/**
	* @depends testOpenWithId
	* @covers User::getId
	* @test
	*/
	public function testGetId() {
		$user = new User();
		$user->openWithId(1);
		$this->assertEquals(1, $user->getId());
	}
	
	/**
	* @depends testOpenWithId
	* @covers User::loggedIn
	* @test
	*/
	public function testLoggedIn() {
		$user = new User();
		$user->openWithId(1);
		$this->assertFalse($user->loggedIn());
	}
	
	/**
	* @depends testOpenWithId
	* @covers User::setUsername
	* @covers User::getUsername
	* @test
	*/
	public function testUsername() {
		$user = new User();
		$user->openWithId(1);
		$newUsername = "Test User 1 (changed)";
		$user->setUsername($newUsername);
		$this->assertEquals($newUsername, $user->getUsername());
	}
	
	/**
	* @depends testOpenWithId
	* @covers User::setLoginname
	* @covers User::getLoginname
	* @test
	*/
	public function testLoginname() {
		$user = new User();
		$user->openWithId(1);
		$newLoginname = "test1_changed";
		$user->setLoginname($newLoginname);
		$this->assertEquals($newLoginname, $user->getLoginname());
	}
	
	/**
	* @depends testOpenWithId
	* @covers User::setEmail
	* @covers User::getEmail
	* @test
	*/
	public function testEmail() {
		$user = new User();
		$user->openWithId(1);
		$newEmail = "test1_changed@example.com";
		$user->setEmail($newEmail);
		$this->assertEquals($newEmail, $user->getEmail());
	}
	
	/**
	* @depends testOpenWithId
	* @covers User::getStatus
	* @test
	*/
	public function testGetStatus() {
		$user = new User();
		$user->openWithId(1);
		$this->assertEquals(User::STATUS_NORMAL, $user->getStatus());
		
		$user = new User();
		$user->openWithId(2);
		$this->assertFalse($user->getEmailactivated());
	}
	
	/**
	* @depends testGetStatus
	* @covers User::activateEmail
	* @test
	*/
	public function testActivateEmail() {
		$user = new User();
		$user->openWithId(1);
		$this->assertEquals(User::ACTIVATEEMAIL_ALREADYACTIVATED, $user->activateEmail(""));
		$this->assertEquals(User::STATUS_NORMAL, $user->getStatus());
		
		$userid = 2;
		$dbCon = new DatabaseConnection();
		$stmt = $dbCon->prepare("SELECT `activationcode` FROM `{dbpre}registrations` WHERE `id`=? LIMIT 1;");
		$stmt->bind_param("i", $userid);
		$stmt->execute();
		$stmt->bind_result($activationCode);
		$stmt->fetch();
		$dbCon->close();
		
		$user = new User();
		$user->openWithId(2);
		$this->assertEquals(User::ACTIVATEEMAIL_OK, $user->activateEmail($activationCode));
		$this->assertEquals(User::STATUS_UNAPPROVED, $user->getStatus());
	}
	
	/**
	* @depends testGetStatus
	* @covers User::approve
	* @test
	*/
	public function testApprove() {
		$user = new User();
		$user->openWithId(1);
		$this->assertFalse($user->approve());
		$this->assertEquals(User::STATUS_NORMAL, $user->getStatus());
		
		$user = new User();
		$user->openWithId(2);
		$this->assertTrue($user->approve());
		$this->assertEquals(User::STATUS_NORMAL, $user->getStatus());
	}
	
	/**
	* @depends testGetStatus
	* @covers User::block
	* @covers User::unblock
	* @test
	*/
	public function testBlock() {
		$user = new User();
		$user->openWithId(1);
		
		$this->assertEquals(User::STATUS_NORMAL, $user->getStatus());
		$this->assertTrue($user->block());
 		$this->assertFalse($user->block());
		
		$this->assertEquals(User::STATUS_BLOCK, $user->getStatus());
		
		$this->assertTrue($user->unblock());
		$this->assertFalse($user->unblock());
		$this->assertEquals(User::STATUS_NORMAL, $user->getStatus());
	}
	
	/**
	* @depends testOpenWithId
	* @covers User::addCustomField
	* @covers User::saveCustomField
	* @covers User::getCustomField
	* @covers User::removeCustomField
	* @test
	*/
	public function testCustomField() {
		$user = new User();
		$user->openWithId(1);
		$integer = 200;
		
		$user->addCustomField("testfield", "INT NOT NULL");
		
		$this->assertTrue($user->saveCustomField("testfield", $integer));
		
		$this->assertEquals($integer, $user->getCustomField("testfield"));
		
		$user->removeCustomField("testfield");
	}
	
	/**
	* @depends testOpenWithId
	* @covers User::getGroups
	* @covers User::inGroup
	* @covers User::addGroup
	* @covers User::getInGroupLevel
	* @covers User::setInGroupLevel
	* @covers User::removeGroup
	* @test
	*/
	public function testGroup() {
		Group::create("Test Group1", $testGroup1Id);
		Group::create("Test Group2", $testGroup2Id);
		
		$user = new User();
		$user->openWithId(1);
		
		$this->assertFalse($user->inGroup($testGroup1Id));
		$this->assertFalse($user->removeGroup($testGroup1Id));
		$this->assertFalse($user->setInGroupLevel($testGroup1Id, 51));
		$this->assertFalse($user->getInGroupLevel($testGroup1Id));
		
		$this->assertTrue($user->addGroup($testGroup1Id));
		$this->assertTrue($user->inGroup($testGroup1Id));
		$this->assertTrue($user->setInGroupLevel($testGroup1Id, 51));
		$this->assertEquals(51, $user->getInGroupLevel($testGroup1Id));
		
		$this->assertFalse($user->inGroup($testGroup2Id));
		$this->assertTrue($user->addGroup($testGroup2Id, 55));
		$this->assertTrue($user->inGroup($testGroup2Id));
		
		$userGroups = $user->getGroups();
		$this->assertEquals(2, count($userGroups));
		
		$this->assertTrue($user->removeGroup($testGroup2Id));
		$this->assertFalse($user->inGroup($testGroup2Id));
		
		$userGroups = $user->getGroups();
		$this->assertEquals(1, count($userGroups));
		$this->assertEquals(1, $userGroups[0]->getId());
		
		$this->assertTrue($user->removeGroup($testGroup1Id));
		$this->assertFalse($user->inGroup($testGroup1Id));
		$this->assertFalse($user->removeGroup($testGroup1Id));
		$this->assertFalse($user->setInGroupLevel($testGroup1Id, 51));
		$this->assertFalse($user->getInGroupLevel($testGroup1Id));
		
		$group = new Group();
		$group->openWithId($testGroup1Id);
		$group->deleteGroup();
		
		$group = new Group();
		$group->openWithId($testGroup2Id);
		$group->deleteGroup();
	}
	
	/**
	* @depends testGroup
	* @covers User::addPermission
	* @covers User::hasPermission
	* @covers User::hasOwnPermission
	* @covers User::removePermission
	* @test
	*/
	public function testPermission() {
		Group::create("Test Permission Group1", $testGroupId);
		$group = new Group();
		$group->openWithId($testGroupId);
		$user = new User();
		$user->openWithId(1);
		
		$this->assertFalse($user->hasPermission("not.existing.permission"));
		$this->assertFalse($user->hasOwnPermission("not.existing.permission"));
		
		$this->assertFalse($user->hasPermission("test.permission"));
		$this->assertFalse($user->hasOwnPermission("test.permission"));
		
		$this->assertTrue($user->addPermission("test.permission"));
		$this->assertFalse($user->addPermission("test.permission"));
		$this->assertTrue($user->hasPermission("test.permission"));
		$this->assertTrue($user->hasOwnPermission("test.permission"));
		
		$user->addGroup($testGroupId);
		$this->assertTrue($group->addPermission("test2.permission"));
		$this->assertTrue($user->hasPermission("test2.permission"));
		$this->assertFalse($user->hasOwnPermission("test2.permission"));
		
		$group->removePermission("test2.permission");
		$this->assertFalse($user->hasPermission("test2.permission"));
		$this->assertFalse($user->hasOwnPermission("test2.permission"));
		
		$this->assertTrue($user->removePermission("test.permission"));
		$this->assertFalse($user->removePermission("test.permission"));
		$this->assertFalse($user->hasPermission("test.permission"));
		$this->assertFalse($user->hasOwnPermission("test.permission"));
		
		$user->removeGroup($testGroupId);
		$group->deleteGroup();
	}
	
	/**
	* @depends testCreate
	* @covers User::getAllUsers
	* @test
	*/
	public function testGetAllUsers() {
		$users = User::getAllUsers();
		
		$this->assertEquals(3, count($users));
		$this->assertEquals(1, $users[0]->getId());
	}
	
	/**
	* @depends testOpenWithId
	* @covers User::deleteUser
	* @test
	*/
	public function testDeleteUser() {
		$user = new User();
		$user->openWithId(1);
		$this->assertTrue($user->deleteUser());
		
		$user = new User();
		$user->openWithId(2);
		$this->assertTrue($user->deleteUser());
		
		$user = new User();
		$user->openWithId(3);
		$this->assertTrue($user->deleteUser());
	}
	
	public static function tearDownAfterClass() {
		$dbCon = new DatabaseConnection();
		$stmt = $dbCon->prepare("TRUNCATE TABLE `{dbpre}users`");
		$stmt->execute();
		$stmt->close();
		$stmt = $dbCon->prepare("TRUNCATE TABLE `{dbpre}groups`");
		$stmt->execute();
		$stmt->close();
		$stmt = $dbCon->prepare("TRUNCATE TABLE `{dbpre}registrations`");
		$stmt->execute();
		$stmt->close();
		$dbCon->close();
	}
}
?>