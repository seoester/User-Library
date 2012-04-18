<?php
//     This small libary helps you to integrate user managment into your website.
//     Copyright (C) 2011  Seoester <seoester@googlemail.com>
//
//     This program is free software: you can redistribute it and/or modify
//     it under the terms of the GNU General Public License as published by
//     the Free Software Foundation, either version 3 of the License, or
//     (at your option) any later version.
//
//     This program is distributed in the hope that it will be useful,
//     but WITHOUT ANY WARRANTY; without even the implied warranty of
//     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//     GNU General Public License for more details.
//
//     You should have received a copy of the GNU General Public License
//     along with this program.  If not, see <http://www.gnu.org/licenses/>.

require_once "class.databaseconnection.php";
require_once "class.group.php";
require_once "settings.php";

/**
* Die User Klasse ist die zentrale Klasse der User Library.
* Die meisten Funktionen geben false zurück falls der Benutzer noch nicht mit {@link User::check()}, {@link User::login()} oder {@link User::openWithId()} initalisiert wurde.
*
* @package userlib
* @version 0.7
*/
class User {
	
	//Constants
	const STATUS_NORMAL = 1;
	const STATUS_BLOCK = 2;
	const STATUS_UNAPPROVED = 3;
	
	const LOGIN_OK = 1;
	const LOGIN_WRONGPASSWORD = 2;
	const LOGIN_USERDOESNOTEXISTS = 3;
	const LOGIN_BLOCKED = 4;
	const LOGIN_LOGINDISABLED = 5;
	const LOGIN_EMAILUNACTIVATED = 6;
	const LOGIN_UNAPPROVED = 7;
	const LOGIN_TOOMANYATTEMPTS = 8;
	
	const REGISTER_OK = 1;
	const REGISTER_REGISTERDISABLED = 2;
	const REGISTER_LOGINNAMEEXISTSALREADY = 3;
	const REGISTER_USERNAMEEXISTSALREADY = 4;
	const REGISTER_EMAILEXISTSALREADY = 5;
	
	const ACTIVATEEMAIL_OK = 1;
	const ACTIVATEEMAIL_ALREADYACTIVATED = 2;
	const ACTIVATEEMAIL_ACTIVATIONCODEWRONG = 3;
	
	
	//Protected Vars
	protected $created = false;
	protected $userLoggedIn = false;
	protected $registeredOnly = false;
	protected $id;
	protected $deleted = false;
	protected $dbCache;
	protected $hookClasses = array();
	protected $settings;
	
	//##################################################################
	//######################   Initial methods    ######################
	//##################################################################
	public function __construct() {
		$this->dbCache = new Cache();
		$this->settings = new UserLibrarySettings();
	}
	
	/**
	* Versucht einen Login mit den übergebenen Daten. Es wird eine der LOGIN_ Konstanten zurückgegeben.
	* Die Methode erstellt auch alle notwendigen Sessions die {@link User::check()} braucht. Nach dem Login wird die User Klasse initialisiert.
	*
	*/
	public function login($loginname, $password, $force=false) {
		if ($this->created)
			throw new Exception("There is already a user assigned");
		$settings = $this->settings;
		
		if (! $settings::login_enabled)
			return self::LOGIN_LOGINDISABLED;
		
		$status = $this->preCheck($loginname, $db_userid, $db_password, $db_salt, $db_status, $db_loginattempts, $db_cookieString);
		if ($status != self::LOGIN_OK)
			return $status;
		if (!$force) {
			$status = $this->passwordCheck($password, $db_password, $db_salt);
			if ($status != self::LOGIN_OK) {
				$this->finishFaiLogin($db_userid, $db_loginattempts);
				return $status;
			}
		}
		$status = $this->postCheck($db_status);
		if ($status != self::LOGIN_OK) {
			$this->finishUnaLogin($db_userid);
			return $status;
		}
		$this->finishSucLogin($db_userid, $db_cookieString);
		return self::LOGIN_OK;
	}
	
	/**
	* Liest Session Daten ein, die von der {@link User::login()} Methode erstellt werden.
	*
	*/
	public function check() {
		if ($this->created)
			throw new Exception("There is already a user assigned");
		$this->cleanOnlineTable();
		if (isset($_COOKIE["USER_sessionid"]) && strlen($_COOKIE["USER_sessionid"]) > 0) {
 			if (isset($_COOKIE['USER_cookie_string']) && strlen($_COOKIE['USER_cookie_string']) > 0 && $this->isUserInDataBase($_COOKIE["USER_sessionid"], false, $userid) && $this->checkCookieString($userid, $_COOKIE['USER_cookie_string'])) {
				$this->userLoggedIn = true;
				$this->id = $userid;
				$this->updateOnlineTable();
			}
		} else {
			$sessionId = self::genCode(100);
			setcookie("USER_sessionid", $sessionId, 0, "/");
			$_COOKIE["USER_sessionid"] = $sessionId;
			$this->dbCache->unsetField("onlineid");
		}
		if (! $this->userLoggedIn) {
			if ($this->isUserInDatabase($_COOKIE["USER_sessionid"], true))
				$this->updateOnlineTable(true);
			else {
				$this->insertInOnlineTable(true);
			}
			$this->id = 0;
		}
		$this->created = true;
	}
	
	/**
	* Öffnet einen Benutzer mithilfe seiner ID in der Datenbank. Gibt es keinen Benutzer mit der angegebenen ID, wird false zurückgegeben.
	*
	*/
	public function openWithId($userid) {
		if ($this->created)
			throw new Exception("There is already a user assigned");
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}users` WHERE `id`=?");
		$stmt->bind_param("i", $userid);
		$stmt->execute();
		if ($stmt->fetch()) {
			$stmt->close();
			$this->id = $userid;
			$this->created = true;
			return true;
		}
		$stmt->close();
		
		$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}registrations` WHERE `id`=?");
		$stmt->bind_param("i", $userid);
		$stmt->execute();
		if ($stmt->fetch()) {
			$stmt->close();
			$this->id = $userid;
			$this->created = true;
			$this->registeredOnly = true;
			return true;
		}
		$stmt->close();
		return false;
	}
	
	//##################################################################
	//######################    Public methods    ######################
	//##################################################################
	/**
	* Vor der logout() Methode muss erst {@link check()} ausgeführt werden. logout() löscht wieder alle Sessions.
	*
	*/
	public function logout() {
		if (! $this->created || $this->deleted || ! $this->userLoggedIn)
			throw new Exception("There is no user assigned");
		
		$this->deleteFromOnlineTable();
		
		setcookie($_COOKIE["USER_sessionid"], ' ', time()-3600);
		setcookie('USER_cookie_string', ' ', time()-3600);
		$this->dbCache->unsetField("onlineid");
		
		return true;
	}
	
	/**
	* Blockiert den aktuellen Benutzer. Gibt false zurück, falls es einen Fehler gab.
	*
	*/
	public function block() {
		if (! $this->created || $this->deleted || $this->registeredOnly)
			throw new Exception("There is no user assigned");
		
		$status = $this->getRawStatus();
		if ($status == 12 || $status == 11)
			return false;
		$returnValue = false;
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		if ($status == 100)
			$blockStatus = 12;
		else
			$blockStatus = 11;
	
		$stmt = $dbCon->prepare("UPDATE `{dbpre}users` SET `status`=? WHERE `id`=? LIMIT 1");
		$stmt->bind_param("ii", $blockStatus, $this->id);
		$stmt->execute();
		
		if ($dbCon->affected_rows() == 1)
			$returnValue = true;
		
		$stmt->close();
		$this->dbCache->setField("rawstatus", $blockStatus);
		return $returnValue;
	}
	
	/**
	* Hebt die Blockierung des aktuellen Benutzers auf. Gibt false zurück, wenn es einen Fehler gab.
	*
	*/
	public function unblock() {
		if (! $this->created || $this->deleted || $this->registeredOnly)
			throw new Exception("There is no user assigned");
		
		$status = $this->getRawStatus();
		$blocked = true;
		$returnValue = false;
		
		if ($status == 12)
			$oldStatus = 100;
		elseif ($status == 11)
			$oldStatus = 1;
		else
			$blocked = false;
		
		if ($blocked) {
			$dbCon = DatabaseConnection::getDatabaseConnection();
			$stmt = $dbCon->prepare("UPDATE `{dbpre}users` SET `status`=? WHERE `id`=? LIMIT 1");
			$stmt->bind_param("ii", $oldStatus, $this->id);
			$stmt->execute();
			
			if ($dbCon->affected_rows() == 1)
				$returnValue = true;
			$stmt->close();
			$this->dbCache->setField("rawstatus", $oldStatus);
		}
		return $returnValue;
	}
	
	/**
	* Gibt den Status des aktuellen Benutzer zurück in Form einer STATUS_ Konstante zurück.
	*
	*/
	public function getStatus() {
		if (! $this->created || $this->deleted || $this->registeredOnly)
			throw new Exception("There is no user assigned");
		
		$status = $this->getRawStatus();
		
		if ($status == 100)
			return self::STATUS_NORMAL;
		elseif ($status == 11 || $status == 12)
			return self::STATUS_BLOCK;
		elseif ($status == 1)
			return self::STATUS_UNAPPROVED;
		else
			return false;
	}
	
	/**
	* Bestätigt den aktuellen Benutzer. Gibt false zurück, wenn es einen Fehler gab.
	*
	*/
	public function approve() {
		if (! $this->created || $this->deleted || $this->registeredOnly)
			throw new Exception("There is no user assigned");
		
		$status = $this->getRawStatus();
		if ($status == 11)
			$newStatus = 12;
		elseif ($status == 12 || $status == 100)
			return false;
		else
			$newStatus = 100;
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		$stmt = $dbCon->prepare("UPDATE `{dbpre}users` SET `status`='100' WHERE `id`=?");
		$stmt->bind_param("i", $this->id);
		$stmt->execute();
		$stmt->close();
		$this->dbCache->setField("rawstatus", $newStatus);
		return true;
	}
	
	/**
	* Gibt true zurück, wenn der Besucher eingeloggt ist, false, wenn nicht.
	*
	*/
	public function loggedIn() {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no user assigned");
		
		return $this->userLoggedIn;
	}
	
	/**
	* Gibt die ID in der _users Tabelle des aktuellen Benutzers zurück.
	*
	*/
	public function getId() {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no user assigned");
		
		return $this->id;
	}
	
	/**
	* Gibt die Email Adresse des aktuellen Benutzers zurück.
	*
	*/
	public function getEmail() {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no user assigned");
		
		if ($this->dbCache->inCache("email"))
			return $this->dbCache->getField("email");
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		$table = ($this->getEmailactivated())? "users" : "registrations";
		$stmt = $dbCon->prepare("SELECT `email` FROM `{dbpre}$table` WHERE `id`=?");
		$returnValue = false;
		
		$stmt->bind_param("i", $this->id);
		$stmt->execute();
		$stmt->bind_result($email);
		if ($stmt->fetch()) {
			$returnValue = $email;
			$this->dbCache->setField("email", $email);
		}
		
		$stmt->close();
		return $returnValue;
	}
	
	/**
	* Setzt die Email Adresse des aktuellen Benutzers.
	*
	*/
	public function setEmail($email) {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no user assigned");
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		$table = ($this->getEmailactivated())? "users" : "registrations";
		$stmt = $dbCon->prepare("UPDATE `{dbpre}$table` SET `email`=? WHERE `id`=?");
		
		$stmt->bind_param("si", $email, $this->id);
		$stmt->execute();
		$this->dbCache->setField("email", $email);
		$stmt->close();
	}
	
	/**
	* Gibt den Benutzernamen des aktuellen Benutzers zurück.
	*
	*/
	public function getUsername() {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no user assigned");
		
		if ($this->dbCache->inCache("username"))
			return $this->dbCache->getField("username");
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		$table = ($this->getEmailactivated())? "users" : "registrations";
		$returnValue = false;
		$stmt = $dbCon->prepare("SELECT `username` FROM `{dbpre}$table` WHERE `id`=?");
		
		$stmt->bind_param("i", $this->id);
		$stmt->execute();
		$stmt->bind_result($username);
		if ($stmt->fetch()) {
			$returnValue = $username;
			$this->dbCache->setField("username", $username);
		}
		
		$stmt->close();
		return $returnValue;
	}
	
	/**
	* Setzt den Benutzernamen des aktuellen Benutzers.
	*
	*/
	public function setUsername($username) {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no user assigned");
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		$table = ($this->getEmailactivated())? "users" : "registrations";
		$stmt = $dbCon->prepare("UPDATE `{dbpre}$table` SET `username`=? WHERE `id`=?");
		
		$stmt->bind_param("si", $username, $this->id);
		$stmt->execute();
		$this->dbCache->setField("username", $username);
		
		$stmt->close();
	}
	
	/**
	* Gibt den Loginnamen des aktuellen Benutzers zurück.
	*
	*/
	public function getLoginname() {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no user assigned");
		
		if ($this->dbCache->inCache("loginname"))
			return $this->dbCache->getField("loginname");
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		$table = ($this->getEmailactivated())? "users" : "registrations";
		$returnValue = false;
		$stmt = $dbCon->prepare("SELECT `login` FROM `{dbpre}$table` WHERE `id`=?");
		
		$stmt->bind_param("i", $this->id);
		$stmt->execute();
		$stmt->bind_result($loginname);
		if ($stmt->fetch()) {
			$returnValue = $loginname;
			$this->dbCache->setField("loginname", $loginname);
		}
		
		$stmt->close();
		return $returnValue;
	}
	
	/**
	* Setzt den Loginnamen des aktuellen Benutzers.
	*
	*/
	public function setLoginname($loginname) {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no user assigned");
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		$table = ($this->getEmailactivated())? "users" : "registrations";
		$stmt = $dbCon->prepare("UPDATE `{dbpre}$table` SET `login`=? WHERE `id`=?");
		
 		$stmt->bind_param("si", $loginname, $this->id);
		$stmt->execute();
		$this->dbCache->setField("loginname", $loginname);
		
		$stmt->close();
	}
	
	/**
	* Setzt das Password für den aktuellen Benutzer neu.
	* Dabei wird auch ein neuer Salt erzeugt
	*
	*/
	public function setPassword($password) {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no user assigned");
		
		$settings = $this->settings;
		$salt = $this->genCode($settings::length_salt);
		$encodedPassword = $this->encodePassword($password, $salt);
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		$table = ($this->getEmailactivated())? "users" : "registrations";
		$stmt = $dbCon->prepare("UPDATE `{dbpre}$table` SET `password`=?, `salt`=? WHERE `id`=?");
		
		$stmt->bind_param("ssi", $encodedPassword, $salt, $this->id);
		$stmt->execute();
		$stmt->close();
		$this->dbCache->setField("checkpassword_$password", true);
		return true;
	}
	
	/**
	* Überprüft ob das angegebene Passwort das Passwort des Benutzers ist.
	*/
	public function checkPassword($password) {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no user assigned");
		
		if ($this->dbCache->inCache("checkpassword_$password"))
			return $this->dbCache->getField("checkpassword_$password");
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		$table = ($this->getEmailactivated())? "users" : "registrations";
		$stmt = $dbCon->prepare("SELECT `salt`, `password` FROM `{dbpre}$table` WHERE id=? LIMIT 1");
		$stmt->bind_param("i", $this->id);
		$stmt->execute();
		$stmt->bind_result($salt, $realPassword);
		$stmt->fetch();
		$stmt->close();
		
		$encodedGivenPassword = self::encodePassword($password, $salt);
		$this->dbCache->setField("checkpassword_$password", $isCorrect = ($encodedGivenPassword === $realPassword));
		return $isCorrect;
	}
	
	/**
	* Gibt ein Array mit den Ids aller Gruppen des Benutzers zurück.
	*
	* @NOCACHING
	*/
	public function getGroups($limit=null, $skip=null) {
		if (! $this->created || $this->deleted || $this->registeredOnly)
			throw new Exception("There is no user assigned");
		
		$limitCommand = "";
		if (is_int($limit))
			$limitCommand = is_int($skip)? " LIMIT $skip, $limit" : " LIMIT $limit";
		elseif (is_int($skip))
			throw new Exception("Cannot skip without limiting");
		
		$groupArray = array();
		$groupIds = array();
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `groupid` FROM `{dbpre}user_groups` WHERE `userid`=?$limitCommand");
		$stmt->bind_param("i", $this->id);
		$stmt->execute();
		$stmt->bind_result($groupId);
		while ($stmt->fetch())
			$groupIds[] = $groupId;
		$stmt->close();
		
		foreach ($groupIds as $groupId) {
			$group = new Group();
			$group->openWithId($groupId);
			$groupArray[] = $group;
		}
		
		return $groupArray;
	}
	
	/**
	* Gibt true zurück, wenn der Benutzer Mitglied der Gruppe mit der Id $groupId, ansonsten false.
	*
	*/
	public function inGroup($groupId) {
		if (! $this->created || $this->deleted || $this->registeredOnly)
			throw new Exception("There is no user assigned");
		
		if ($this->dbCache->inCache("ingroup_$groupId"))
			return $this->dbCache->getField("ingroup_$groupId");

		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `level` FROM `{dbpre}user_groups` WHERE `userid`=? AND `groupid`=?");
		$stmt->bind_param("ii", $this->id, $groupId);
		$stmt->execute();
		$stmt->bind_result($level);
		if ($stmt->fetch())
			$inGroup = true;
		else
			$inGroup = false;
		
		$stmt->close();
		$this->dbCache->setField("ingroup_$groupId", $inGroup);
		return $inGroup;
	}
	
	/**
	* Fügt den aktuellen Benutzer als Mitglied zu der Gruppe mit der Id $groupId hinzu.
	* Wenn der Benutzer bereits in der Gruppe $groupId ist, wird false zurückgegeben.
	* Zusätzlich kann das Level angegeben werden, das der Benutzer in der Gruppe hat.
	* Wenn kein Level angegeben wird, wird es standardmäßig auf 50 gesetzt.
	*
	*/
	public function addGroup($groupId, $level=50) {
		if (! $this->created || $this->deleted || $this->registeredOnly)
			throw new Exception("There is no user assigned");
		
		if (!( $level >= 0 && $level <= 100 ))
			return false;
		
		if ($this->inGroup($groupId))
			return false;
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("INSERT INTO `{dbpre}user_groups` (`userid`, `groupid`, `level`) VALUES (?, ?, ?)");
		
		$stmt->bind_param("iii", $this->id, $groupId, $level);
		$stmt->execute();
		$stmt->close();
		$this->dbCache->setField("ingroup_$groupId", true);
		$this->dbCache->setField("ingrouplevel_$groupId", $level);

		return true;
	}
	
	/**
	* Entfernt den aktuellen Benutzer aus der Gruppe mit der Id $groupId.
	* Wenn der Benutzer nicht in der Gruppe $groupId ist, wird false zurückgegeben.
	*
	*/
	public function removeGroup($groupId) {
		if (! $this->created || $this->deleted || $this->registeredOnly)
			throw new Exception("There is no user assigned");
		
		if (! $this->inGroup($groupId))
			return false;
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("DELETE FROM `{dbpre}user_groups` WHERE `userid`=? AND `groupid`=? LIMIT 1");
		
		$stmt->bind_param("ii", $this->id, $groupId);
		$stmt->execute();
		$stmt->close();
		$this->dbCache->setField("ingroup_$groupId", false);
		$this->dbCache->unsetField("ingrouplevel_$groupId");
		return true;
	}
	
	/**
	* Gibt das Level des aktuellen Benutzers in derGroppe mit der ID $groupId zurück.
	*/
	public function getInGroupLevel($groupId) {
		if (! $this->created || $this->deleted || $this->registeredOnly)
			throw new Exception("There is no user assigned");
		
		if ($this->dbCache->inCache("ingrouplevel_$groupId"))
			return $this->dbCache->getField("ingrouplevel_$groupId");
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `level` FROM `{dbpre}user_groups` WHERE `userid`=? AND `groupid`=? LIMIT 1");
		
		$stmt->bind_param("ii", $this->id, $groupId);
		$stmt->execute();
		$stmt->bind_result($level);
		if ($stmt->fetch())
			;
		else
			$level = false;
		$stmt->close();
		$this->dbCache->setField("ingrouplevel_$groupId", $level);
		return $level;
	}
	
	/**
	* Setzt das Level des aktuellen Benutzers in der Gruppe mit der ID $groupId.
	*
	*/
	public function setInGroupLevel($groupId, $level) {
		if (! $this->created || $this->deleted || $this->registeredOnly)
			throw new Exception("There is no user assigned");
		
		if (!( $level >= 0 && $level <= 100 ))
			return false;
		
		if (!$this->inGroup($groupId))
			return false;
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("UPDATE `{dbpre}user_groups` SET `level`=? WHERE `userid`=? AND `groupid`=? LIMIT 1");
		
		$stmt->bind_param("iii", $level, $this->id, $groupId);
		$stmt->execute();
		$stmt->close();
		$this->dbCache->setField("ingrouplevel_$groupId", $level);
		return true;
	}
	
	/**
	* Gibt true zurück, wenn der Benutzer die Permission $name hat, ansonsten false.
	* Die Gruppen des aktuellen Benutzers werden dabei nicht berücksichtigt.
	*
	*/
	public function hasOwnPermission($name) {
		if (! $this->created || $this->deleted || $this->registeredOnly)
			throw new Exception("There is no user assigned");
		
		if ($this->dbCache->inCache("hasownpermission_$name"))
			return $this->dbCache->getField("hasownpermission_$name");
		
		$permissionId = $this->convertPermissionTitleToId($name);
		if ($permissionId === null)
			return false;
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}user_permissions` WHERE `userid`=? AND `permissionid`=? LIMIT 1");
		
		$stmt->bind_param("ii", $this->id, $permissionId);
		$stmt->execute();
		$stmt->bind_result($mappingId);
		if ($stmt->fetch())
			$hasPermission = true;
		else
			$hasPermission = false;
		$stmt->close();
		$this->dbCache->setField("hasownpermission_$name", $hasPermission);
		return $hasPermission;
	}
	
	/**
	* Fügt dem Nutzer die Permission $name hinzu.
	* Wenn der Nutzer die Permission mit dem Titel $name bereits hat, wird false zurückgegeben.
	*
	*/
	public function addPermission($name) {
		if (! $this->created || $this->deleted || $this->registeredOnly)
			throw new Exception("There is no user assigned");
		
		if ($this->hasOwnPermission($name))
			return false;
		
		$permissionId = $this->convertPermissionTitleToId($name);
		if ($permissionId === null)
			return false;
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("INSERT INTO `{dbpre}user_permissions` (`userid`, `permissionid`) VALUES (?, ?)");
		
		$stmt->bind_param("ii", $this->id, $permissionId);
		$stmt->execute();
		$stmt->close();
		$this->dbCache->setField("hasownpermission_$name", true);
		$this->dbCache->setField("haspermission_$name", true);
		return true;
	}
	
	/**
	* Entfernt die Permission $title vom aktuellen Benutzer.
	* Wenn der Nutzer die Permission mit dem Titel $name nicht hat, wird false zurückgegeben.
	*
	*/
	public function removePermission($name) {
		if (! $this->created || $this->deleted || $this->registeredOnly)
			throw new Exception("There is no user assigned");
		
		if (! $this->hasOwnPermission($name))
			return false;
		
		$permissionId = $this->convertPermissionTitleToId($name);
		if ($permissionId === null)
			return false;
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("DELETE FROM `{dbpre}user_permissions` WHERE `userid`=? AND `permissionid`=? LIMIT 1");
		
		$stmt->bind_param("ii", $this->id, $permissionId);
		$stmt->execute();
		$stmt->close();
		$this->dbCache->setField("hasownpermission_$name", false);
		$this->dbCache->unsetField("haspermission_$name");
		return true;
	}
	
	/**
	* Gibt true zurück, wenn der Benutzer oder eine Gruppe des Benutzers die Permission $name hat, ansonsten false.
	*
	* @NOCACHING
	*/
	public function hasPermission($name) {
		if (! $this->created || $this->deleted || $this->registeredOnly)
			throw new Exception("There is no user assigned");
		
		if ($this->hasOwnPermission($name))
			return true;
		
		$permissionId = $this->convertPermissionTitleToId($name);
		if ($permissionId === null)
			return false;
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		$returnValue = false;
		
		$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}group_permissions` WHERE `groupid`=? AND `permissionid`=? LIMIT 1");
		
		$groups = $this->getGroups();
		foreach ($groups as $group) {
			$groupId = $group->getId();
			$stmt->bind_param("ii", $groupId, $permissionId);
			$stmt->execute();
			$stmt->bind_result($mappingId);
			if ($stmt->fetch()) {
				$returnValue = true;
				break;
			}
		}
		
		$stmt->close();
		return $returnValue;
	}
	
	/**
	* Speichert die übergebene Variable unter dem Titel $title für die aktuelle Session ab.
	* Ist bereits eine Variable mit dem Titel $title vorhanden, wird deren Inhalt überschrieben.
	*
	*/
	public function saveSessionVar($title, $value) {
		if (! $this->created || $this->deleted || ! $this->userLoggedIn)
			throw new Exception("There is no user assigned");
		
		$onlineId = $this->getOnlineId();
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$searchStmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}sessionsvars` WHERE `title`=? AND `onlineid`=? LIMIT 1");
		
		$searchStmt->bind_param("si", $title, $onlineId);
		$searchStmt->execute();
		$searchStmt->store_result();
		$searchStmt->bind_result($sessionVarid);
		if ($searchStmt->num_rows > 0) {
			$searchStmt->fetch();
			$searchStmt->close();
			$updateStmt = $dbCon->prepare("UPDATE `{dbpre}sessionsvars` SET `value`=? WHERE `id`=? LIMIT 1");
			
			$serialziedVar = serialize($value);
			$updateStmt->bind_param("si", $serialziedVar, $sessionVarid);
			$updateStmt->execute();
			$updateStmt->close();
		} else {
			$searchStmt->close();
			$insertStmt = $dbCon->prepare("INSERT INTO `{dbpre}sessionsvars` (`onlineid`, `title`, `value`) VALUES (?, ?, ?)");
			
			$serialziedVar = serialize($value);
			$insertStmt->bind_param("iss", $onlineId, $title, $serialziedVar);
			$insertStmt->execute();
			$insertStmt->close();
		}
		
		$this->dbCache->setField("sessionvar_$title", $value);
	}
	
	/**
	* Gibt den Inhalt der zuvor gespeicherten Variable unter dem Titel $title zurück.
	* Wenn es keine Variable mit dem Titel $title gibt, wird NULL zurückgegeben.
	*
	*/
	public function getSessionVar($title) {
		if (! $this->created || $this->deleted || ! $this->userLoggedIn)
			throw new Exception("There is no user assigned");
		
		if ($this->dbCache->inCache("sessionvar_$title"))
			return $this->dbCache->getField("sessionvar_$title");
		
		$onlineId = $this->getOnlineId();
		$returnValue = null;
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `value` FROM `{dbpre}sessionsvars` WHERE `title`=? AND `onlineid`=? LIMIT 1");
		
		$stmt->bind_param("si", $title, $onlineId);
		$stmt->execute();
		$stmt->store_result();
		$stmt->bind_result($value);
		if ($stmt->num_rows > 0) {
			$stmt->fetch();
			$returnValue = unserialize($value);
		}
		
		$stmt->close();
		$this->dbCache->setField("sessionvar_$title", $returnValue);
		return $returnValue;
	}
	
	/**
	* Löscht den aktuellen Benutzer.
	*
	*/
	public function deleteUser() {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no user assigned");
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		
		$table = ($this->getEmailactivated())? "users" : "registrations";
		$delUserStmt = $dbCon->prepare("DELETE FROM `{dbpre}$table` WHERE `id`=? LIMIT 1");
		$delUserStmt->bind_param("i", $this->id);
		$delUserStmt->execute();
		$delUserStmt->close();
		
		$delGroupConnectionsStmt = $dbCon->prepare("DELETE FROM `{dbpre}user_groups` WHERE `userid`=? LIMIT 1");
		$delGroupConnectionsStmt->bind_param("i", $this->id);
		$delGroupConnectionsStmt->execute();
		$delGroupConnectionsStmt->close();
		
		$delPermissionConnectionsStmt = $dbCon->prepare("DELETE FROM `{dbpre}user_permissions` WHERE `userid`=? LIMIT 1");
		$delPermissionConnectionsStmt->bind_param("i", $this->id);
		$delPermissionConnectionsStmt->execute();
		$delPermissionConnectionsStmt->close();
		
		$this->deleted = true;
		
		return true;
	}
	
	/**
	* Versucht die Email Adresse mit den übergebenen Daten zu aktivieren. Gibt eine ACTIVATEEMAIL_ Konstante zurück.
	*
	*/
	public function activateEmail($activationCode) {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no user assigned");
		
		if ($this->getEmailactivated())
			return self::ACTIVATEEMAIL_ALREADYACTIVATED;
		
		$settings = new UserLibrarySettings();
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$checkStmt = $dbCon->prepare("SELECT `login`, `username`, `password`, `salt`, `email`, `status`, `activationcode`, `secure_cookie_string`, `registerdate` FROM `{dbpre}registrations` WHERE `id`=? LIMIT 1");
		
		$checkStmt->bind_param("i", $this->id);
		$checkStmt->execute();
		$checkStmt->bind_result($db_loginname, $db_username, $db_password, $db_salt, $db_email, $db_status, $db_activationCode, $db_cookieString, $db_registerDate);
		$checkStmt->fetch();
		$checkStmt->close();
		if ($activationCode != $db_activationCode)
			return self::ACTIVATEEMAIL_ACTIVATIONCODEWRONG;
		else {
			$activateStmt = $dbCon->prepare("INSERT INTO `{dbpre}users` (`id`, `login`, `username`, `password`, `salt`, `email`, `status`, `secure_cookie_string`, `registerdate`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
			
			$activateStmt->bind_param("isssssisi", $this->id, $db_loginname, $db_username, $db_password, $db_salt, $db_email, $db_status, $db_cookieString, $db_registerDate);
			$activateStmt->execute();
			$activateStmt->close();
			
			$this->registeredOnly = false;
			return self::ACTIVATEEMAIL_OK;
		}
	}
	
	/**
	* Gibt zurück, ob die Email Adresse des Benutzers aktiviert ist.
	*/
	public function getEmailactivated($echo = false) {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no user assigned");
		return ! $this->registeredOnly;
	}
	
	/**
	* Erstellt einen neuen Benutzer mit den übergebenen Werten.
	* Dabei wird keine Mail verschickt.
	* Wenn $approved nicht angegeben wird, werden die Einstellungen verwendet.
	* Wenn $check true ist, werden Login-, Username und Emailadresse überprüft bevor der Benutzer in die Datenbank geschrieben wird, es wird hingegen nicht überprüft ob das Registrieren deaktiviert ist.
	* Die Methode kann deswegen auch eine der REGISTER_ Konstanten zurückgeben.
	*
	* @static
	*/
	public static function create($loginname, $username, $password, $email, $emailActivated=true, $approved="stan", &$userid="unset", $check=false) {
		$settings = new UserLibrarySettings();
		if ($check) {
			if (! self::checkLoginname($loginname))
				return self::REGISTER_LOGINNAMEEXISTSALREADY;
			elseif (! self::checkUsername($username))
				return self::REGISTER_USERNAMEEXISTSALREADY;
			elseif (! self::checkEmail($email))
				return self::REGISTER_EMAILEXISTSALREADY;
		}
		
		$activationCode = self::genCode($settings::length_activationcode);
		if ($approved == "stan") {
			if ($settings::need_approval)
				$finalStatus = 1;
			else
				$finalStatus = 100;
		} else {
			if ($approved)
				$finalStatus = 100;
			else
				$finalStatus = 1;
		}
		
		$userid = self::writeUserIntoDatabase($loginname, $username, $password, $email, $finalStatus, $emailActivated);
	}
	
	/**
	* Legt einen neuen Benutzer an.
	* Im $emailtext wird [%id%] durch die ID des Benutzers, [%actcode%] durch den Aktivierungslink, [%username%] durch den Benutzername, [%loginname%] durch den Loginnamen und [%password%] durch das Passwort ersetzt.
	* Gibt eine der REGISTER_ Konstanten zurück.
	*
	* @static
	*/
	public static function register($loginname, $username, $password, $email, $emailtext, $emailsubject, &$userid="unset") {
		$settings = new UserLibrarySettings();
		if (! $settings::register_enabled)
			return self::REGISTER_REGISTERDISABLED;
		elseif (! self::checkLoginname($loginname))
			return self::REGISTER_LOGINNAMEEXISTSALREADY;
		elseif (! self::checkUsername($username))
			return self::REGISTER_USERNAMEEXISTSALREADY;
		elseif (! self::checkEmail($email))
			return self::REGISTER_EMAILEXISTSALREADY;
		
		$activationCode = self::genCode($settings::length_activationcode);
		$status = ($settings::need_approval)? 1 : 100;
		
		$userid = self::writeUserIntoDatabase($loginname, $username, $password, $email, $status, false, $activationCode);
		
		$emailtext = str_replace("[%actcode%]", $activationCode, $emailtext);
		$emailtext = str_replace("[%username%]", $username, $emailtext);
		$emailtext = str_replace("[%loginname%]", $loginname, $emailtext);
		$emailtext = str_replace("[%password%]", $password, $emailtext);
		$emailtext = str_replace("[%id%]", $userid, $emailtext);
		
		self::sendMail($settings::send_mailaddress, $email, $emailsubject, $emailtext);
		
		return self::REGISTER_OK;
	}
	
	/**
	* Gibt alle existierenden Benutzer zurück.
	* Benutzer werden in Form eines Array von User Objekten zurückgegeben.
	*
	* @static
	*/
	public static function getAllUsers($limit=null, $skip=null) {
		$limitCommand = "";
		if (is_int($limit))
			$limitCommand = is_int($skip)? " LIMIT $skip, $limit" : " LIMIT $limit";
		elseif (is_int($skip))
			throw new Exception("Cannot skip without limiting");
		
		$users = array();
		$userIds = array();
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}users`$limitCommand");
		
		$stmt->execute();
		$stmt->bind_result($userId);
		while ($stmt->fetch())
			$userIds[] = $userId;
		$stmt->close();
		
		foreach ($userIds as $userId) {
			$user = new User();
			$user->openWithId($userId);
			$users[] = $user;
		}
		
		return $users;
	}
	
	/**
	* Gibt alle Benutzer zurück die aktuell online sind.
	* Benutzer werden in Form eines Array von User Objekten zurückgegeben.
	*
	* @static
	*/
	public static function getAllOnlineUsers($limit=null, $skip=null) {
		$limitCommand = "";
		if (is_int($limit))
			$limitCommand = is_int($skip)? " LIMIT $skip, $limit" : " LIMIT $limit";
		elseif (is_int($skip))
			throw new Exception("Cannot skip without limiting");
		
		$users = array();
		$userIds = array();
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `userid` FROM `{dbpre}onlineusers` WHERE `userid`!='0'$limitCommand");
		
		$stmt->execute();
		$stmt->bind_result($userId);
		while ($stmt->fetch())
			$userIds[] = $userId;
		$stmt->close();
		
		foreach ($userIds as $userId) {
			$user = new User();
			$user->openWithId($userId);
			$users[] = $user;
		}
		
		return $users;
	}
	
	/**
	* Fügt einen Hook hinzu.
	* Die $hookClass muss von {@link UserHooks} abgeleitet werden.
	* Es können mehre Hook Klassen hinzugefügt werden.
	*
	*/
	public function appendHook(UserHooks $hookClass) {
		$this->hookClasses[] = $hookClass;
	}
	
	/**
	* Fügt ein benutzerdefiniertes Feld mit dem Namen $name und dem $type zu der _users Tabelle hinzu.
	* $type ist dabei reines SQL.
	*
	*/
	public function addCustomField($name, $type) {
		if (! $this->created || $this->deleted || $this->registeredOnly)
			throw new Exception("There is no user assigned");
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("ALTER TABLE `{dbpre}users` ADD `custom_$name` $type");
		$stmt->execute();
		
		$stmt->close();
	}
	
	/**
	* Entfernt ein benuterdefiniertes Feld wieder.
	*
	*/
	public function removeCustomField($name) {
		if (! $this->created || $this->deleted || $this->registeredOnly)
			throw new Exception("There is no user assigned");
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("ALTER TABLE `{dbpre}users` DROP `custom_$name`");
		$stmt->execute();
		
		$stmt->close();
	}
	
	/**
	* Speichert den Wert $value in dem benutzerdefinierten Feld $name des aktuellen Benutzers.
	*
	*/
	public function saveCustomField($name, $value) {
		if (! $this->created || $this->deleted || $this->registeredOnly)
			throw new Exception("There is no user assigned");
		
		if (is_integer($value))
			$typeAbb ='i';
		elseif (is_double($value))
			$typeAbb = 'd';
		elseif (is_string($value))
			$typeAbb = 's';
		else
			$typeAbb = 'b';
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("UPDATE `{dbpre}users` SET `custom_$name`=? WHERE `id`=?");
		$stmt->bind_param($typeAbb . "i", $value, $this->id);
		$stmt->execute();
		$stmt->close();
		$this->dbCache->setField("custom_$name", $value);
		return true;
	}
	
	/**
	* Liest den Wert aus dem benutzerdefinierten Feld $name des aktuellen Benutzers.
	*
	*/
	public function getCustomField($name) {
		if (! $this->created || $this->deleted || $this->registeredOnly)
			throw new Exception("There is no user assigned");
		
		if ($this->dbCache->inCache("custom_$name"))
			return $this->dbCache->getField("custom_$name");
		
		$returnValue = false;
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `custom_$name` FROM `{dbpre}users` WHERE `id`=?");
		$stmt->bind_param("i", $this->id);
		$stmt->execute();
		$stmt->bind_result($result);
		if ($stmt->fetch())
			$returnValue = $result;
		else
			$returnValue = false;
		$stmt->close();
		$this->dbCache->setField("custom_$name", $returnValue);
		return $returnValue;
	}
	
	//##################################################################
	//######################    Private methods   ######################
	//##################################################################
	private static function encodePassword($password, $salt) {
		$finalpassword = $password;
		
		for ($i = 0; $i < 10; $i++) {
			$finalpassword = md5($finalpassword . $salt);
		}
		
		return $finalpassword;
	}
	
	private static function genCode($charNum) {
		$letters = array("a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z", "0", "1", "2", "3", "4", "5", "6", "7", "8", "9");
		$code = "";
		
		for ($i = 0; $i < $charNum; $i++) {
			$rand = mt_rand(0, 35);
			$code .= $letters[$rand];
		}
		
		return $code;
	}
	
	private static function checkUsername($username) {
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}users` WHERE `username`=?");
		$stmt->bind_param("s", $username);
		$stmt->execute();
		if ($stmt->fetch()) {
			$stmt->close();
			return false;
		}
		$stmt->close();
		
		$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}registrations` WHERE `username`=?");
		$stmt->bind_param("s", $username);
		$stmt->execute();
		if ($stmt->fetch()) {
			$stmt->close();
			return false;
		}
		$stmt->close();
		return true;
	}
	
	private static function checkLoginname($loginname) {
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}users` WHERE `login`=?");
		$stmt->bind_param("s", $loginname);
		$stmt->execute();
		if ($stmt->fetch()) {
			$stmt->close();
			return false;
		}
		$stmt->close();
		
		$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}registrations` WHERE `login`=?");
		$stmt->bind_param("s", $loginname);
		$stmt->execute();
		if ($stmt->fetch()) {
			$stmt->close();
			return false;
		}
		$stmt->close();
		return true;
	}
	
	private static function checkEmail($email) {
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}users` WHERE `email`=?");
		$stmt->bind_param("s", $email);
		$stmt->execute();
		if ($stmt->fetch()) {
			$stmt->close();
			return false;
		}
		$stmt->close();
		
		$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}registrations` WHERE `email`=?");
		$stmt->bind_param("s", $email);
		$stmt->execute();
		if ($stmt->fetch()) {
			$stmt->close();
			return false;
		}
		$stmt->close();
		return true;
	}
	
	private function preCheck($loginname, &$db_userid, &$db_password, &$db_salt, &$db_status, &$db_loginattempts, &$db_cookieString) {
		$settings = $this->settings;
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `id`, `password`, `salt`, `status`, `loginattempts`, `blockeduntil`, `secure_cookie_string` FROM `{dbpre}users` WHERE `login`=? LIMIT 1");
		$stmt->bind_param("s", $loginname);
		$stmt->execute();
		$stmt->bind_result($db_userid, $db_password, $db_salt, $db_status, $db_loginattempts, $db_blockeduntil, $db_cookieString);
		if ($stmt->fetch()) {
			$stmt->close();
			if ($db_loginattempts >= $settings::maxloginattempts && time() < $db_blockeduntil)
				return self::LOGIN_TOOMANYATTEMPTS;
			return self::LOGIN_OK;
		} else {
			$stmt->close();
			$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}registrations` WHERE `login`=? LIMIT 1");
			$stmt->bind_param("s", $loginname);
			$stmt->execute();
			$stmt->bind_result($db_userid);
			if ($stmt->fetch()) {
				$stmt->close();
				return self::LOGIN_EMAILUNACTIVATED;
			} else {
				$stmt->close();
				return self::LOGIN_USERDOESNOTEXISTS;
			}
		}
	}
	
	private function passwordCheck($password, $db_password, $db_salt) {
		$encodedPassword = self::encodePassword($password, $db_salt);
		if ($encodedPassword == $db_password)
			return self::LOGIN_OK;
		return self::LOGIN_WRONGPASSWORD;
	}
	
	private function finishFaiLogin($db_userid, $db_loginattempts) {
		$settings = $this->settings;
		$dbCon = DatabaseConnection::getDatabaseConnection();
		$loginattempts = $db_loginattempts + 1;
		$blockeduntil = ($loginattempts >= $settings::maxloginattempts)? time() + $settings::loginblocktime : 0;
		$stmt = $dbCon->prepare("UPDATE `{dbpre}users` SET `loginattempts`=?, `blockeduntil`=? WHERE `id`=?");
		$stmt->bind_param("iii", $loginattempts, $blockeduntil, $db_userid);
		$stmt->execute();
		$stmt->close();
	}
	
	private function postCheck($db_status) {
		if ($db_status == 1)
			return self::LOGIN_UNAPPROVED;
		elseif ($db_status == 11 || $db_status == 12)
			return self::LOGIN_BLOCKED;
		elseif ($db_status >= 100 && $db_status < 200)
			return self::LOGIN_OK;
		else
			throw new Exception("Unknown status '$db_status'");
	}
	
	private function finishUnaLogin($db_userid) {
		$dbCon = DatabaseConnection::getDatabaseConnection();
		$stmt = $dbCon->prepare("UPDATE `{dbpre}users` SET `loginattempts`=?, `blockeduntil`=? WHERE `id`=?");
		$stmt->bind_param("iii", $loginattempts = 0, $blockeduntil = 0, $db_userid);
		$stmt->execute();
		$stmt->close();
	}
	
	private function finishSucLogin($db_userid, $db_cookieString) {
		$dbCon = DatabaseConnection::getDatabaseConnection();
		$stmt = $dbCon->prepare("UPDATE `{dbpre}users` SET `loginattempts`=?, `blockeduntil`=? WHERE `id`=?");
		$stmt->bind_param("iii", $loginattempts = 0, $blockeduntil = 0, $db_userid);
		$stmt->execute();
		$stmt->close();
		
		$sessionId = self::genCode(100);
		setcookie("USER_sessionid", $sessionId, 0, "/");
		$_COOKIE["USER_sessionid"] = $sessionId;
		setcookie("USER_cookie_string", $db_cookieString, 0, "/");
		$this->dbCache->unsetField("onlineid");
		$this->userLoggedIn = true;
		$this->created = true;
		$this->id = $db_userid;
		
		$this->insertInOnlineTable();
		$this->callLoginHooks($this->id);
	}
	
	private function getIp() {
		if(getenv("HTTP_X_FORWARDED_FOR")) 
			$ip = getenv("HTTP_X_FORWARDED_FOR");
		else
			$ip = getenv("REMOTE_ADDR");
		return $ip;
	}
	
	private function getRawStatus() {
		if ($this->dbCache->inCache("rawstatus"))
			return $this->dbCache->getField("rawstatus");
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `status` FROM `{dbpre}users` WHERE `id`=? LIMIT 1");
		$stmt->bind_param("i", $this->id);
		$stmt->execute();
		$stmt->bind_result($status);
		$stmt->fetch();
		
		$stmt->close();
		$this->dbCache->setField("rawstatus", $status);
		return $status;
	}
	
	private function convertPermissionTitleToId($title) {
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}permissions` WHERE `name`=? LIMIT 1");
		
		$stmt->bind_param("s", $title);
		$stmt->execute();
		$stmt->bind_result($id);
		if ($stmt->fetch()) {
			$stmt->close();
			return $id;
		} else {
			$stmt->close();
			$insertStmt = $dbCon->prepare("INSERT INTO `{dbpre}permissions` (`name`) VALUES (?)");
			$insertStmt->bind_param("s", $title);
			$insertStmt->execute();
			$id = $dbCon->insert_id();
			$insertStmt->close();
			return $id;
		}
	}
	
	private function getOnlineId() {
		if (! $this->created || $this->deleted )
			return false;
		
		if ($this->dbCache->inCache("onlineid"))
			return $this->dbCache->getField("onlineid");
		
		$settings = $this->settings;
		$session = $_COOKIE['USER_sessionid'];
		$ipaddress = $this->getIp();
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		if ($settings::securesessions)
			$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}onlineusers` WHERE `userid`=? AND `session`=? AND `ipaddress`=? LIMIT 1");
		else
			$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}onlineusers` WHERE `userid`=? AND `session`=? LIMIT 1");
		
		if ($settings::securesessions)
			$stmt->bind_param("iss", $this->id, $session, $ipaddress);
		else
			$stmt->bind_param("is", $this->id, $session);
		$stmt->execute();
		$stmt->bind_result($onlineid);
		$stmt->fetch();
		
		$stmt->close();
		$this->dbCache->setField("onlineid", $onlineid);
		return $onlineid;
	}
	
	private function checkCookieString($id, $cookieString) {
		if ($this->dbCache->inCache("checkcookie_$cookieString"))
			return $this->dbCache->getField("checkcookie_$cookieString");
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		$returnValue = false;
		$stmt = $dbCon->prepare("SELECT `secure_cookie_string` FROM `{dbpre}users` WHERE `id`=? LIMIT 1");
		$stmt->bind_param("i", $id);
		$stmt->execute();
		$stmt->bind_result($db_cookieString);
		if ($stmt->fetch())
			if ($cookieString == $db_cookieString)
				$returnValue = true;
		
		$stmt->close();
		$this->dbCache->setField("checkcookie_$cookieString", $returnValue);
		return $returnValue;
	}
	
	private static function writeUserIntoDatabase($loginname, $username, $password, $email, $status, $emailActivated=false, $activationCode=null) {
		$settings = new UserLibrarySettings();
		$dbCon = DatabaseConnection::getDatabaseConnection();
		$cookieString = self::genCode(100);
		$id = self::getLatestId() + 1;
		$registerDate = time();
		$salt = self::genCode($settings::length_salt);
		$encodedPassword = self::encodePassword($password, $salt);
		
		if ($emailActivated) {
			$insertStmt = $dbCon->prepare("INSERT INTO `{dbpre}users` (`id`, `login`, `username`, `password`, `salt`, `email`, `status`, `secure_cookie_string`, `registerdate`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
			
			$insertStmt->bind_param("isssssisi", $id, $loginname, $username, $encodedPassword, $salt, $email, $status, $cookieString, $registerDate);
		} else {
			$insertStmt = $dbCon->prepare("INSERT INTO `{dbpre}registrations` (`id`, `login`, `username`, `password`, `salt`, `email`, `status`, `activationcode`, `secure_cookie_string`, `registerdate`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
			if ($activationCode === null)
				$activationCode = "";
			
			$insertStmt->bind_param("isssssissi", $id, $loginname, $username, $encodedPassword, $salt, $email, $status, $activationCode, $cookieString, $registerDate);
		}
		$insertStmt->execute();
		$insertStmt->close();
		return $id;
	}
	
	private static function sendMail($FROM, $TO, $SUBJECT, $TEXT) {
		return mail($TO, $SUBJECT, $TEXT, "FROM: " . $FROM);
	}
	
	private static function getLatestId() {
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}users` ORDER BY `id` DESC LIMIT 1");
		$stmt->execute();
		$stmt->bind_result($latestUserId);
		$stmt->fetch();
		$stmt->close();
		$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}registrations` ORDER BY `id` DESC LIMIT 1");
		$stmt->execute();
		$stmt->bind_result($latestRegistrationId);
		$stmt->fetch();
		$stmt->close();
		return max($latestUserId, $latestRegistrationId);
	}
	
	private function callLoginHooks($userid) {
		foreach ($this->hookClasses as $hookClass) {
			$hookClass->login($userid);
		}
	}
	
	private function callLogoutHooks($userid) {
		foreach ($this->hookClasses as $hookClass) {
			$hookClass->logout($userid);
		}
	}
	
	//##################################################################
	//######################     Online table     ######################
	//##################################################################
	private function insertInOnlineTable($anon=false) {
		$dbCon = DatabaseConnection::getDatabaseConnection();
		$stmt = $dbCon->prepare("INSERT INTO `{dbpre}onlineusers` (`userid`, `session`, `ipaddress`, `lastact`) VALUES (?, ?, ?, ?)");
		
		if (! $anon)
			$userid = $this->id;
		else
			$userid = 0;
		$sessionId = $_COOKIE["USER_sessionid"];
		$ipAddress = $this->getIp();
		$actTime = time();
		
		$stmt->bind_param("issi", $userid, $sessionId, $ipAddress, $actTime);
		$stmt->execute();
		
		$stmt->close();
	}
	
	private function updateOnlineTable($anon=false) {
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("UPDATE `{dbpre}onlineusers` SET `lastact`=? WHERE `userid`=? AND `session`=? LIMIT 1");
		
		if (! $anon)
			$userid = $this->id;
		else
			$userid = 0;
		$sessionId = $_COOKIE["USER_sessionid"];
		$actTime = time();
		
		$stmt->bind_param("iis", $actTime, $userid, $sessionId);
		$stmt->execute();
		
		$stmt->close();
	}
	
	private function deleteFromOnlineTable() {
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$userid = $this->id;
		$sessionId = $_COOKIE["USER_sessionid"];
		
		$searchStmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}onlineusers` WHERE `userid`=? AND `session`=? LIMIT 1");
		
		$searchStmt->bind_param("is", $userid, $sessionId);
		$searchStmt->execute();
		$searchStmt->bind_result($onlineId);
		
		if ($searchStmt->fetch()) {
			$searchStmt->close();
			$this->deleteAllUserDataFromOnlineTable($onlineId, $userid);
		}
	}
	
	private function cleanOnlineTable() {
		$dbCon = DatabaseConnection::getDatabaseConnection();
		$settings = $this->settings;
		$delIds = array();
		$actTime = time();
		$minLastTime = $actTime - $settings::autologouttime;
		
		$searchStmt = $dbCon->prepare("SELECT `id`, `userid` FROM `{dbpre}onlineusers` WHERE `lastact`<?");
		$searchStmt->bind_param("i", $minLastTime);
		$searchStmt->execute();
		$searchStmt->bind_result($delOnlineId, $delUserId);
		
		while ($searchStmt->fetch())
			$delIds[] = array($delOnlineId, $delUserId);
		$searchStmt->close();
		
		foreach ($delIds as $delId)
			$this->deleteAllUserDataFromOnlineTable($delId[0], $delId[1]);
	}
	
	private function deleteAllUserDataFromOnlineTable($onlineid, $userid) {
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$delVarstmt = $dbCon->prepare("DELETE FROM `{dbpre}sessionsvars` WHERE `onlineid`=? LIMIT 1");
		$delStmt = $dbCon->prepare("DELETE FROM `{dbpre}onlineusers` WHERE `id`=?");
		
		$delVarstmt->bind_param("i", $onlineid);
		$delStmt->bind_param("i", $onlineid);
		
		$delVarstmt->execute();
		$delVarstmt->close();
		$delStmt->execute();
		$delStmt->close();
		$this->callLogoutHooks($userid);
	}
	
	private function isUserInDataBase($session, $anon=false, &$userid=null) {
		$dbCon = DatabaseConnection::getDatabaseConnection();
		$settings = $this->settings;
		
		if ($settings::securesessions)
			$searchStmt = $dbCon->prepare("SELECT `id`, `userid` FROM `{dbpre}onlineusers` WHERE `session`=? AND `ipaddress`=? LIMIT 1");
		else
			$searchStmt = $dbCon->prepare("SELECT `id`, `userid` FROM `{dbpre}onlineusers` WHERE `session`=? LIMIT 1");
		
		$ipaddress = $this->getIp();
		if ($settings::securesessions)
			$searchStmt->bind_param("ss", $session, $ipaddress);
		else
			$searchStmt->bind_param("s", $session);
		
		$searchStmt->execute();
		$searchStmt->bind_result($onlineid, $userid);
		
		if ($searchStmt->fetch()) {
			$returnValue = true;
		} else
			$returnValue = false;
		
		$searchStmt->close();
		return $returnValue;
	}
}

abstract class UserHooks {
	abstract public function login($userid);
	
	abstract public function logout($userid);
}
?>