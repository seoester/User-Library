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
* Die Klasse DatabaseConnection wird von der User Library benutzt, um Datenbank Verbindungen zu verwalten.
* Die Klasse Cache wird von der User Library benutzt um Datenbank Abfragen cachen zu k�nnen.
*
* @package userlib
*/

require_once "settings.php";

class DatabaseConnection {
	private $mysqliObj;
	private $statements = array();
	private $dbPrefix;
	private static $connections = array();
	
	public function __construct($settings=null) {
		$this->mysqliObj = new mysqli();
		if ($settings === null)
			$settings = new UserLibrarySettings();
		
		$this->mysqliObj->real_connect($settings::DB_server, $settings::DB_user, $settings::DB_password, $settings::DB_database);
		$this->dbPrefix = $settings::DB_prefix;
	}
	
	public function getConnection() {
		if ($this->mysqliObj->connect_error)
			throw new Exception("Database Error:\n" . mysqli_connect_error());
		else
			return $this->mysqliObj;
	}
	
	public function prepare($sql) {
		$correctSQL = str_replace("{dbpre}", $this->dbPrefix, $sql);
		if ($stmt = $this->mysqliObj->prepare($correctSQL)) {
		} else
			throw new Exception("DataBase Error: " . $this->mysqliObj->error);
		
		$this->statements[] = $stmt;
		return $stmt;
	}
	
	public function close($freeResult = false) {
		$this->mysqliObj->close();
	}
	
	public function affected_rows() {
		return $this->mysqliObj->affected_rows;
	}
	
	public function insert_id() {
		return $this->mysqliObj->insert_id;
	}
	
	public static function getDatabaseConnection($settings=null) {
		$connection = null;
		if ($settings === null)
			$settings = new UserLibrarySettings();
		
		foreach (self::$connections as $connectionItem) {
			if ($connectionItem["server"] == $settings::DB_server &&
				$connectionItem["user"] == $settings::DB_user &&
				$connectionItem["password"] == $settings::DB_password &&
				$connectionItem["database"] == $settings::DB_database &&
				$connectionItem["prefix"] == $settings::DB_prefix) {
				$connection = $connectionItem["connection"];
				break;
			}
		}
		
		if ($connection === null) {
			$connection = new DatabaseConnection($settings);
			$connectionItem = array("server" => $settings::DB_server,
			"user" => $settings::DB_user,
			"password" => $settings::DB_password,
			"database" => $settings::DB_database,
			"prefix" => $settings::DB_prefix,
			"connection" => $connection);
			self::$connections[] = $connectionItem;
		}
		return $connection;
	}
}

class Cache {
	private $cache = array();
	
	public function inCache($field) {
		return isset($this->cache[$field]);
	}
	
	public function setField($field, $value) {
		$this->cache[$field] = $value;
	}
	
	public function getField($field, $default = null) {
		if ($this->inCache($field))
			return $this->cache[$field];
		else
			return $default;
	}
	
	public function unsetField($field) {
		unset($this->cache[$field]);
	}
}

class DatabaseSettings {
	const DB_server = null;
	const DB_user = null;
	const DB_password = null;
	const DB_database = null;
	const DB_prefix = null;
}
?>