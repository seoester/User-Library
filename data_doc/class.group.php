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

/**
* In group.php sind alle Elemente zu finden, die mit Gruppen in der User Library zu tun haben.
*
* @package userlib
*/

require_once "class.databaseconnection.php";
require_once "class.user.php";
require_once "settings.php";

class Group {
	
	protected $created = false;
	protected $id;
	protected $deleted = false;
	protected $dbCache;
	
	
	//##################################################################
	//######################   Initial methods    ######################
	//##################################################################
	public function __construct() {
		$this->dbCache = new Cache();
	}
	
	/**
	* Öffnet eine Gruppe mithilfe ihrer ID in der Datenbank. Gibt es keine Gruppe mit der angegebenen ID, wird false zurückgegeben.
	*
	*/
	public function openWithId($groupId) {
		if ($this->created)
			throw new Exception("There is already a group assigned");
		
		$returnValue = false;
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}groups` WHERE `id`=?");
		$stmt->bind_param("i", $groupId);
		$stmt->execute();
		$stmt->store_result();
		if ($stmt->num_rows > 0) {
			$this->id = $groupId;
			$this->created = true;
			$returnValue = true;
		}
		$stmt->close();
		return $returnValue;
	}
	
	
	//##################################################################
	//######################    Public methods    ######################
	//##################################################################
	/**
	* Gibt die ID der aktuellen Gruppe zurück.
	* 
	*/
	public function getId() {
		return $this->id;
	}
	
	/**
	* Gibt true zurück, wenn die Gruppe die Permission $name hat, ansonsten false.
	* 
	*/
	public function hasPermission($name) {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no group assigned");
		
		if ($this->dbCache->inCache("haspermission_$name"))
			return $this->dbCache->getField("haspermission_$name");
		
		$permissionId = $this->convertPermissionTitleToId($name);
		if ($permissionId === null)
			return false;
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}group_permissions` WHERE `groupid`=? AND `permissionid`=? LIMIT 1");
		
		$stmt->bind_param("ii", $this->id, $permissionId);
		$stmt->execute();
		$stmt->bind_result($mappingId);
		if ($stmt->fetch())
			$hasPermission = true;
		else
			$hasPermission = false;
		$stmt->close();
		
		$this->dbCache->setField("haspermission_$name", $hasPermission);
		return $hasPermission;
	}
	
	/**
	* Fügt der Gruppe die Permission $name hinzu.
	* Wenn die Gruppe die Permission mit dem Titel $name bereits hat, wird false zurückgegeben.
	*
	*/
	public function addPermission($name) {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no group assigned");
		
		if ($this->hasPermission($name))
			return false;
		
		$permissionId = $this->convertPermissionTitleToId($name);
		if ($permissionId === null)
			return false;
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("INSERT INTO `{dbpre}group_permissions` (`groupid`, `permissionid`) VALUES (?, ?)");
		
		$stmt->bind_param("ii", $this->id, $permissionId);
		$stmt->execute();
		$stmt->close();
		$this->dbCache->setField("haspermission_$name", true);
		return true;
	}
	
	/**
	* Entzieht der Gruppe die Permission $name wieder.
	* Wenn die Gruppe die Permission mit dem Titel $name nicht hat, wird false zurückgegeben.
	*
	*/
	public function removePermission($name) {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no group assigned");
		
		if (! $this->hasPermission($name))
			return false;
		
		$permissionId = $this->convertPermissionTitleToId($name);
		if ($permissionId === null)
			return false;
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("DELETE FROM `{dbpre}group_permissions` WHERE `groupid`=? AND `permissionid`=? LIMIT 1");
		
		$stmt->bind_param("ii", $this->id, $permissionId);
		$stmt->execute();
		$stmt->close();
		$this->dbCache->setField("haspermission_$name", false);
		return true;
	}
	
	/**
	* Gibt den Namen der Gruppe zurück.
	*
	*/
	public function getName() {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no group assigned");
		
		if ($this->dbCache->inCache("name"))
			return $this->dbCache->getField("name");
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		$returnValue;
		
		$stmt = $dbCon->prepare("SELECT `name` FROM `{dbpre}groups` WHERE `id`=?");
		$stmt->bind_param("i", $this->id);
		$stmt->execute();
		$stmt->bind_result($name);
		if ($stmt->fetch())
			$returnValue = $name;
		else
			$returnValue = false;
		
		$stmt->close();
		$this->dbCache->setField("name", $returnValue);
		return $returnValue;
	}
	
	/**
	* Setzt den Namen der Gruppe.
	*
	*/
	public function setName($name) {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no group assigned");
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("UPDATE `{dbpre}groups` SET `name`=? WHERE `id`=?");
		$stmt->bind_param("si", $name, $this->id);
		$stmt->execute();
		
		$stmt->close();
		$this->dbCache->setField("name", $name);
	}
	
	/**
	* Gibt alle Benutzer zurück, die in der aktuellen Gruppe sind.
	* Diese werden als ein Array von User Objekten zurückgegeben.
	*
	* @NOCACHING
	*/
	public function getAllUsersInGroup($limit=null, $skip=null) {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no group assigned");
		
		$limitCommand = "";
		if (is_int($limit))
			$limitCommand = is_int($skip)? " LIMIT $skip, $limit" : " LIMIT $limit";
		elseif (is_int($skip))
			throw new Exception("Cannot skip without limiting");
		
		$users = array();
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `userid` FROM `{dbpre}user_groups` WHERE `groupid`=?$limitCommand");
		$stmt->bind_param("i", $this->id);
		$stmt->execute();
		$stmt->bind_result($userId);
		while ($stmt->fetch()) {
			$user = new User();
			$user->openWithId($userId);
			$users[] = $user;
		}
		$stmt->close();
		
		return $users;
	}
	
	/**
	* Löscht die Gruppe.
	*
	*/
	public function deleteGroup() {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no group assigned");
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$delGroupStmt = $dbCon->prepare("DELETE FROM `{dbpre}groups` WHERE `id`=? LIMIT 1");
		$delGroupStmt->bind_param("i", $this->id);
		$delGroupStmt->execute();
		$delGroupStmt->close();
		
		$delUserConnectionsStmt = $dbCon->prepare("DELETE FROM `{dbpre}user_groups` WHERE `groupid`=? LIMIT 1");
		$delUserConnectionsStmt->bind_param("i", $this->id);
		$delUserConnectionsStmt->execute();
		$delUserConnectionsStmt->close();
		
		$delPermissionConnectionsStmt = $dbCon->prepare("DELETE FROM `{dbpre}group_permissions` WHERE `groupid`=? LIMIT 1");
		$delPermissionConnectionsStmt->bind_param("i", $this->id);
		$delPermissionConnectionsStmt->execute();
		$delPermissionConnectionsStmt->close();
		
		$this->deleted = true;
		
		return true;
	}
	
	/**
	* Erstellt eine neue Gruppe mit dem Namen $groupname
	*
	* @static
	*/
	public static function create($groupname, &$groupId="unset") {
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("INSERT INTO `{dbpre}groups` (`name`) VALUES (?)");
		$stmt->bind_param("s", $groupname);
		$stmt->execute();
		$groupId = $dbCon->insert_id();
		
		$stmt->close();
	}
	
	/**
	* Gibt alle existierenden Gruppen zurück.
	* Diese werden als ein Array von Group Objekten zurückgegeben.
	*
	* @static
	*/
	public static function getAllGroups($limit=null, $skip=null) {
		$limitCommand = "";
		$groupIds = array();
		if (is_int($limit))
			$limitCommand = is_int($skip)? " LIMIT $skip, $limit" : " LIMIT $limit";
		elseif (is_int($skip))
			throw new Exception("Cannot skip without limiting");
		
		$groups = array();
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}groups`");
		
		$stmt->execute();
		$stmt->bind_result($groupId);
		while ($stmt->fetch())
			$groupIds[] = $groupId;
		
		$stmt->close();
		
		foreach ($groupIds as $groupId) {
			$group = new Group();
			$group->openWithId($groupId);
			$groups[] = $group;
		}
		
		return $groups;
	}
	
	/**
	* Fügt ein benutzerdefiniertes Feld mit dem Namen $name und dem $type zu der _groups Tabelle hinzu.
	* $type ist dabei reines SQL.
	*
	*/
	public function addCustomField($name, $type) {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no group assigned");
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("ALTER TABLE `{dbpre}groups` ADD `custom_$name` $type");
		$stmt->execute();
		
		$stmt->close();
	}
	
	/**
	* Entfernt ein benuterdefiniertes Feld wieder.
	*
	*/
	public function removeCustomField($name) {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no group assigned");
		
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("ALTER TABLE `{dbpre}groups` DROP `custom_$name`");
		$stmt->execute();
		
		$stmt->close();
	}
	
	/**
	* Speichert den Wert $value in dem benutzerdefinierten Feld $name der aktuellen Gruppe.
	*
	*
	*/
	public function saveCustomField($name, $value) {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no group assigned");
		
		if (is_integer($value))
			$typeAbb ='i';
		elseif (is_double($value))
			$typeAbb = 'd';
		elseif (is_string($value))
			$typeAbb = 's';
		else
			$typeAbb = 'b';
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("UPDATE `{dbpre}groups` SET `custom_$name`=? WHERE `id`=?");
		$stmt->bind_param($typeAbb . "i", $value, $this->id);
		$stmt->execute();
		$stmt->close();
		$this->dbCache->setField("custom_$name", $value);
		return true;
	}
	
	/**
	* Liest den Wert aus dem benutzerdefinierten Feld $name der aktuellen Gruppe.
	*
	*/
	public function getCustomField($name) {
		if (! $this->created || $this->deleted)
			throw new Exception("There is no group assigned");
		
		if ($this->dbCache->inCache("custom_$name"))
			return $this->dbCache->getField("custom_$name");
		
		$returnValue = false;
		$dbCon = DatabaseConnection::getDatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `custom_$name` FROM `{dbpre}groups` WHERE `id`=?");
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
}
?>