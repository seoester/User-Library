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

require_once "settings.php";

class DatabaseConnection {
	private $mysqliObj;
	private $statements = array();
	
	public function __construct () {
		$this->mysqliObj = new mysqli();
		$this->mysqliObj->real_connect(settings\DB_server, settings\DB_user, settings\DB_password, settings\DB_database);
	}
	
	public function getConnection() {
		if ($this->mysqliObj->connect_error)
			throw new Exception("Database Error:\n" . mysqli_connect_error());
		else
			return $this->mysqliObj;
	}
	
	public function prepare($sql) {
		$dbPrefix = settings\DB_prefix;
		
		$correctSQL = str_replace("{dbpre}", $dbPrefix, $sql);
		if ($stmt = $this->mysqliObj->prepare($correctSQL)) {
		} else
			throw new Exception("DataBase Error: " . $this->mysqliObj->error);
		
		$this->statements[] = $stmt;
		return $stmt;
	}
	
	public function close($freeResult = false) {
		foreach ($this->statements as $stmt) {
			if ($freeResult)
				$stmt->free_result();
			$stmt->close();
		}
		
		$this->mysqliObj->close();
	}
	
	public function affected_rows() {
		return $this->mysqliObj->affected_rows;
	}
}

class Cache {
	private $fields = array();
	private $values = array();
	
	public function inCache($field) {
		return in_array($field, $this->fields);
	}
	
	public function setField($field, $value) {
		if ($this->inCache($field)) {
			$this->cleanArrays($field);
			$this->fields[] = $field;
			$this->values[] = $value;
		} else {
			$this->fields[] = $field;
			$this->values[] = $value;
		}
	}
	
	public function getField($field, $default = null) {
		if ($this->inCache($field)) {
			$index = array_search($field, $this->fields);
			return $this->values[$index];
		} else
			return $default;
	}
	
	private function cleanArrays($field) {
		$index = array_search($field, $this->fields);
		unset($this->fields[$index]);
		unset($this->values[$index]);
		$this->fields = array_values($this->fields);
		$this->values = array_values($this->values);
	}
}
?>