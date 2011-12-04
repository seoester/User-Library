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

require_once "database.php";
require_once "settings.php";

/**
* In group.php sind alle Elemente zu finden, die mit Gruppen in der User Library zu tun haben.
*
* @package userlib
*/
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
			return false;
		
		$returnValue = false;
		$dbCon = new DatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}groups` WHERE `id`=?");
		$stmt->bind_param("i", $groupId);
		$stmt->execute();
		$stmt->store_result();
		if ($stmt->num_rows > 0) {
			$this->id = $groupId;
			$this->created = true;
			$returnValue = true;
		}
		$dbCon->close();
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
			return false;
		
		$permissionId = $this->convertPermissionTitleToId($name);
		if ($permissionId === null)
			return false;
		
		$dbCon = new DatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}group_permissions` WHERE `groupid`=? AND `permissionid`=? LIMIT 1;");
		
		$stmt->bind_param("ii", $this->id, $permissionId);
		$stmt->execute();
		$stmt->bind_result($mappingId);
		if ($stmt->fetch())
			$hasPermission = true;
		else
			$hasPermission = false;
		$dbCon->close();
		
		return $hasPermission;
	}
	
	/**
	* Fügt der Gruppe die Permission $name hinzu.
	* Wenn die Gruppe die Permission mit dem Titel $name bereits hat, wird false zurückgegeben.
	*
	*/
	public function addPermission($name) {
		if (! $this->created || $this->deleted)
			return false;
		
		if ($this->hasPermission($name))
			return false;
		
		$permissionId = $this->convertPermissionTitleToId($name);
		if ($permissionId === null)
			return false;
		
		$dbCon = new DatabaseConnection();
		
		$stmt = $dbCon->prepare("INSERT INTO `{dbpre}group_permissions` (`groupid`, `permissionid`) VALUES (?, ?);");
		
		$stmt->bind_param("ii", $this->id, $permissionId);
		$stmt->execute();
		$dbCon->close();
		return true;
	}
	
	/**
	* Entzieht der Gruppe die Permission $name wieder.
	* Wenn die Gruppe die Permission mit dem Titel $name nicht hat, wird false zurückgegeben.
	*
	*/
	public function removePermission($name) {
		if (! $this->created || $this->deleted)
			return false;
		
		if (! $this->hasPermission($name))
			return false;
		
		$permissionId = $this->convertPermissionTitleToId($name);
		if ($permissionId === null)
			return false;
		
		$dbCon = new DatabaseConnection();
		
		$stmt = $dbCon->prepare("DELETE FROM `{dbpre}group_permissions` WHERE `groupid`=? AND `permissionid`=? LIMIT 1;");
		
		$stmt->bind_param("ii", $this->id, $permissionId);
		$stmt->execute();
		$dbCon->close();
		return true;
	}
	
	/**
	* Gibt den Namen der Gruppe zurück.
	*
	*/
	public function getName() {
		if (! $this->created || $this->deleted)
			return false;
		
		if ($this->dbCache->inCache("name"))
			return $this->dbCache->getField("name");
		
		$dbCon = new DatabaseConnection();
		$returnValue;
		
		$stmt = $dbCon->prepare("SELECT `name` FROM `{dbpre}groups` WHERE `id`=?");
		$stmt->bind_param("i", $this->id);
		$stmt->execute();
		$stmt->bind_result($name);
		if ($stmt->fetch())
			$returnValue = $name;
		else
			$returnValue = false;
		
		$dbCon->close();
		$this->dbCache->setField("name", $returnValue);
		return $returnValue;
	}
	
	/**
	* Setzt den Namen der Gruppe.
	*
	*/
	public function setName($name) {
		if (! $this->created || $this->deleted)
			return false;
		
		$dbCon = new DatabaseConnection();
		
		$stmt = $dbCon->prepare("UPDATE `{dbpre}groups` SET `name`=? WHERE `id`=?");
		$stmt->bind_param("si", $name, $this->id);
		$stmt->execute();
		
		$dbCon->close();
		$this->dbCache->setField("name", $name);
	}
	
	/**
	* Gibt alle Benutzer zurück, die in der aktuellen Gruppe sind.
	* Diese werden als ein Array von User Objekten zurückgegeben.
	*
	*/
	public function getAllUsersInGroup() {
		if (! $this->created || $this->deleted)
			return false;
		
		$users = array();
		$dbCon = new DatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `userid` FROM `{dbpre}user_groups` WHERE `groupid`=?;");
		$stmt->bind_param("i", $this->id);
		$stmt->execute();
		$stmt->bind_result($userId);
		while ($stmt->fetch()) {
			$user = new User();
			$user->openWithId($userId);
			$users[] = $user;
		}
		$dbCon->close();
		
		return $users;
	}
	
	/**
	* Löscht die Gruppe.
	*
	*/
	public function deleteGroup() {
		if (! $this->created || $this->deleted)
			return false;
		
		$dbCon = new DatabaseConnection();
		
		$stmt = $dbCon->prepare("DELETE FROM `{dbpre}groups` WHERE `id`=? LIMIT 1;");
		$stmt->bind_param("i", $this->id);
		$stmt->execute();
		
		$dbCon->close();
		$this->deleted = true;
		
		return true;
	}
	
	/**
	* Erstellt eine neue Gruppe mit dem Namen $groupname
	*
	* @static
	*/
	public static function create($groupname) {
		$dbCon = new DatabaseConnection();
		
		$stmt = $dbCon->prepare("INSERT INTO `{dbpre}groups` (`name`) VALUES (?);");
		$stmt->bind_param("s", $groupname);
		$stmt->execute();
		
		$dbCon->close();
	}
	
	/**
	* Gibt alle existierenden Gruppen zurück.
	* Diese werden als ein Array von Group Objekten zurückgegeben.
	*
	* @static
	*/
	public static function getAllGroups() {
		$groups = array();
		$dbCon = new DatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}groups`;");
		
		$stmt->execute();
		$stmt->bind_result($groupId);
		while ($stmt->fetch()) {
			$group = new Group();
			$group->openWithId($groupId);
			$groups[] = $group;
		}
		$dbCon->close();
		
		return $groups;
	}
	
	/**
	* Fügt ein benutzerdefiniertes Feld mit dem Namen $name und dem $type zu der _groups Tabelle hinzu.
	* $type ist dabei reines SQL.
	*
	*/
	public function addCustomField($name, $type) {
		if (! $this->created || $this->deleted)
			return false;
		
		$dbCon = new DatabaseConnection();
		
		$stmt = $dbCon->prepare("ALTER TABLE `{dbpre}groups` ADD `custom_$name` $type;");
		$stmt->execute();
		
		$dbCon->close();
	}
	
	/**
	* Entfernt ein benuterdefiniertes Feld wieder.
	*
	*/
	public function removeCustomField($name) {
		if (! $this->created || $this->deleted)
			return false;
		
		$dbCon = new DatabaseConnection();
		
		$stmt = $dbCon->prepare("ALTER TABLE `{dbpre}groups` DROP `custom_$name`;");
		$stmt->execute();
		
		$dbCon->close();
	}
	
	/**
	* Speichert den Wert $value in dem benutzerdefinierten Feld $name der aktuellen Gruppe.
	*
	*
	*/
	public function saveCustomField($name, $value) {
		if (! $this->created || $this->deleted)
			return false;
		
		if (is_integer($value))
			$typeAbb ='i';
		elseif (is_double($value))
			$typeAbb = 'd';
		elseif (is_string($value))
			$typeAbb = 's';
		else
			$typeAbb = 'b';
		$dbCon = new DatabaseConnection();
		
		$stmt = $dbCon->prepare("UPDATE `{dbpre}groups` SET `custom_$name`=? WHERE `id`=?;");
		$stmt->bind_param($typeAbb . "i", $value, $this->id);
		$stmt->execute();
		$dbCon->close();
		$this->dbCache->setField("custom_$name", $value);
		return true;
	}
	
	/**
	* Liest den Wert aus dem benutzerdefinierten Feld $name der aktuellen Gruppe.
	*
	*/
	public function getCustomField($name) {
		if (! $this->created || $this->deleted)
			return false;
		
		if ($this->dbCache->inCache("custom_$name"))
			return $this->dbCache->getField("custom_$name");
		
		$returnValue = false;
		$dbCon = new DatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `custom_$name` FROM `{dbpre}groups` WHERE `id`=?;");
		$stmt->bind_param("i", $this->id);
		$stmt->execute();
		$stmt->bind_result($result);
		if ($stmt->fetch())
			$returnValue = $result;
		else
			$returnValue = false;
		$dbCon->close();
		$this->dbCache->setField("custom_$name", $returnValue);
		return $returnValue;
	}
	
	//##################################################################
	//######################    Private methods   ######################
	//##################################################################
	private function removeFromArray($value, array $array) {
		$newArray = array();
		
		foreach ($array as $ar) {
			if ($ar != $value)
				$newArray[] = $ar;
		}
		
		return $newArray;
	}
	
	private function convertPermissionTitleToId($title) {
		$dbCon = new DatabaseConnection();
		
		$stmt = $dbCon->prepare("SELECT `id` FROM `{dbpre}permissions` WHERE `name`=? LIMIT 1;");
		$stmt->bind_param("s", $title);
		$stmt->execute();
		$stmt->bind_result($id);
		$stmt->fetch();
		
		$dbCon->close();
		return $id;
	}
}
?>