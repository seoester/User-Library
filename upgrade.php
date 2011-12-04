<?php
$error = "";
$info = "";
require_once "data/settings.php";
require_once "data/database.php";
if (isset($_POST['upgrade']) && $_POST['upgrade'] == "yes") {
	$error = formEvaluation();
	
	if (empty($error))
		$info = "Einrichtung erfolgreich";
}
function formEvaluation() {
	$error = "";
	
	
	$dbServer = settings\DB_server;
	$dbName = settings\DB_database;
	$dbUser = settings\DB_user;
	$dbPassword = settings\DB_password;
	$dbPrefix = settings\DB_prefix;
	
    //###################################
    //##################################

	$error = upgradeDatabaseTables($dbServer, $dbName, $dbUser, $dbPassword, $dbPrefix);
	return $error;
}





function upgradeDatabaseTables($dbServer, $dbName, $dbUser, $dbPassword, $dbPrefix) {
	$error = "";
	
	$mysqli = new mysqli($dbServer, $dbUser, $dbPassword, $dbName);
	if ($mysqli->connect_error)
		return "Fehler bei der Datenbankverbindung:<br />" . mysqli_connect_error(). "<br />Bitte &uuml;berpr&uuml;fen Sie Ihre Angaben";
	
	$createArray = array( 'CREATE TABLE IF NOT EXISTS `' . $dbPrefix . 'user_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userid` int(11) NOT NULL,
  `groupid` int(11) NOT NULL,
  `level` int(11) NOT NULL,
  PRIMARY KEY (`id`)
 ) DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;',
 'CREATE TABLE IF NOT EXISTS `' . $dbPrefix . 'user_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userid` int(11) NOT NULL,
  `permissionid` int(11) NOT NULL,
  PRIMARY KEY (`id`)
 ) DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;',
 'CREATE TABLE IF NOT EXISTS `' . $dbPrefix . 'group_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `groupid` int(11) NOT NULL,
  `permissionid` int(11) NOT NULL,
  PRIMARY KEY (`id`)
 ) DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;',
 'ALTER TABLE  `' . $dbPrefix . 'users`
  ADD `secure_cookie_string` VARCHAR(100) NOT NULL;', );
	
	foreach ($createArray as $value) {
		$result = $mysqli->query($value);
		if (! $result) {
			$error = "Fehler beim Anlegen der Tabellen:<br />" . $mysqli->error;
			break;
		}
	}
	
	$mysqli->close();
	
	convertMissingFields();
	
	$mysqli = new mysqli($dbServer, $dbUser, $dbPassword, $dbName);
	if ($mysqli->connect_error)
		return "Fehler bei der Datenbankverbindung:<br />" . mysqli_connect_error(). "<br />Bitte &uuml;berpr&uuml;fen Sie Ihre Angaben";
	
	$createArray = array( 'ALTER TABLE  `' . $dbPrefix . 'users`
  DROP `groups`,
  DROP `permissions`;',
  'ALTER TABLE  `' . $dbPrefix . 'groups`
  DROP `permissions`;' );
	
	foreach ($createArray as $value) {
		$result = $mysqli->query($value);
		if (! $result) {
			$error = "Fehler beim Anlegen der Tabellen:<br />" . $mysqli->error;
			break;
		}
	}
	
	$mysqli->close();
	return $error;
}

function convertMissingFields() {
	$dbCon1 = new DatabaseConnection();
	$dbCon2 = new DatabaseConnection();
	$dbCon3 = new DatabaseConnection();
	$dbCon4 = new DatabaseConnection();
	$dbCon5 = new DatabaseConnection();
	$dbCon6 = new DatabaseConnection();
	$level = 50;
	
	$readUsers = $dbCon1->prepare("SELECT `id`, `groups`, `permissions` FROM {dbpre}users;");
	$writeUserPermissions = $dbCon2->prepare("INSERT INTO {dbpre}user_permissions (`userid`, `permissionid`) VALUES (?, ?);");
	$writeUserGroups = $dbCon3->prepare("INSERT INTO {dbpre}user_groups (`userid`, `groupid`, `level`) VALUES (?, ?, ?);");
	$writeCookieString = $dbCon6->prepare("UPDATE `{dbpre}users` SET `secure_cookie_string`=?  WHERE `id`=? LIMIT 1;");
	$readGroups = $dbCon4->prepare("SELECT `id`, `permissions` FROM {dbpre}groups;");
	$writeGroupPermissions = $dbCon5->prepare("INSERT INTO {dbpre}group_permissions (`groupid`, `permissionid`) VALUES (?, ?);");
	
	$readUsers->execute();
	$readUsers->bind_result($userid, $userGroups, $userPermissions);
	while ($readUsers->fetch()) {
		$permissionIds = preg_split("/;/", $userPermissions);
		$groupIds = preg_split("/;/", $userGroups);
		$code = genCode(100);
		$writeCookieString->bind_param("si", $code, $userid);
		$writeCookieString->execute();
		foreach ($permissionIds as $pid) {
			if (! empty($pid)) {
				$writeUserPermissions->bind_param("ii", $userid, $pid);
				$writeUserPermissions->execute();
			}
		}
		
		foreach ($groupIds as $gid) {
			if (! empty($gid)) {
				$writeUserGroups->bind_param("iii", $userid, $gid, $level);
				$writeUserGroups->execute();
			}
		}
	}
	
	$readGroups->execute();
	$readGroups->bind_result($groupid, $groupPermissions);
	while ($readGroups->fetch()) {
		$permissionIds = preg_split("/;/", $groupPermissions);
		foreach ($permissionIds as $pid) {
			if (! empty($pid)) {
				$pid = intval($pid);
				$writeGroupPermissions->bind_param("ii", $groupid, $pid);
				$writeGroupPermissions->execute();
			}
		}
	}
	
	$dbCon1->close();
	$dbCon2->close();
	$dbCon3->close();
	$dbCon4->close();
	$dbCon5->close();
	$dbCon6->close();
}

function genCode($charNum) {
	$letters = array("a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z", "0", "1", "2", "3", "4", "5", "6", "7", "8", "9");
	$code = "";
	
	for ($i = 0; $i < $charNum; $i++) {
		$rand = mt_rand(0, 35);
		$code .= $letters[$rand];
	}
	
	return $code;
}

function createSettingFile($dbServer, $dbName, $dbUser, $dbPassword, $dbPrefix, $loginEnabled, $registerEnabled, $needApproval, $lengthSalt, $lengthActivationcode, $sendEmail, $autologouttime, $maxLoginAttempts, $loginBlockTime, $securesessions) {
	$loginEnabled = convStringToBoolString($loginEnabled);
	$registerEnabled = convStringToBoolString($registerEnabled);
	$needApproval = convStringToBoolString($needApproval);
	$securesessions = convStringToBoolString($securesessions);
	
	$settingString = "<?php\nnamespace settings;\n\nconst DB_server = \"" . $dbServer . "\";\nconst DB_database = \"" . $dbName . "\";\nconst DB_user = \"" . $dbUser . "\";\nconst DB_password = \"" . $dbPassword . "\";\nconst DB_prefix = \"" . $dbPrefix . "\";\n\nconst login_enabled = " . $loginEnabled . ";\nconst register_enabled = " . $registerEnabled . ";\nconst need_approval = " . $needApproval . ";\nconst length_salt = " . $lengthSalt . ";\nconst length_activationcode = " . $lengthActivationcode . ";\nconst send_mailaddress = \"" . $sendEmail . "\";\n\n#After how many seconds will a user be kicked?\nconst autologouttime = " . $autologouttime . ";\nconst maxloginattempts = " . $maxLoginAttempts . ";\nconst loginblocktime = " . $loginBlockTime . ";\nconst securesessions = " . $securesessions . ";\n?>";
	$file = "data/settings.php";
	
	$numberOfBytes = file_put_contents($file, $settingString);
	
	if ($numberOfBytes === false) {
		return "Fehler beim Schreiben der settings.php Datei. &Uuml;berpr&uuml;fen Sie die Zugriffsrechte";
	}
}

function checkFormVar(&$var) {
	if (! isset($var) || empty($var))
		return false;
	else
		return true;
}

function convStringToBoolString($bool) {
	if ($bool)
		return "true";
	elseif (! $bool)
		return "false";
	
}

function convStringToBool($string) {
    if ($string == "Ja")
        return true;
    elseif ($string == "Nein")
        return false;
    else
        return null;
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>

<head>
  <title>User Library Upgradeassistent</title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>
<body>
  <h1>User Library Upgradeassistent (0.5 => 0.6)</h1>

  <?php
if (! empty($error))
        echo "\n<span style=\"color:red;\">Fehler: " . $error . "</span>\n";
if (! empty($info))
        echo "\n<span style=\"color:green;\">" . $info . "</span>\n";
?>
<FORM action="./upgrade.php" method="POST">
<INPUT type="hidden" name="upgrade" value="yes" />
<INPUT type="submit" value="Daten absenden" />
</FORM>

</body>
</html>