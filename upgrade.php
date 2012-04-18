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
	
	$error = updateSettingFile();
	if (strlen($error) > 0)
		return $error;
	$error = upgradeDatabaseTables($dbServer, $dbName, $dbUser, $dbPassword, $dbPrefix);
	return $error;
}





function upgradeDatabaseTables($dbServer, $dbName, $dbUser, $dbPassword, $dbPrefix) {
	$error = "";
	
	$mysqli = new mysqli($dbServer, $dbUser, $dbPassword, $dbName);
	if ($mysqli->connect_error)
		return "Fehler bei der Datenbankverbindung:<br />" . mysqli_connect_error(). "<br />Bitte &uuml;berpr&uuml;fen Sie Ihre Angaben";
	
	$createArray = array('CREATE TABLE IF NOT EXISTS `' . $dbPrefix . 'registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `login` varchar(50) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(300) NOT NULL,
  `salt` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `status` int(11) NOT NULL,
  `activationcode` varchar(50) NOT NULL,
  `secure_cookie_string` varchar(100) NOT NULL,
  `registerdate` int(11) NOT NULL,
  PRIMARY KEY (`id`)
 ) DEFAULT CHARSET=latin1 ;',
 'ALTER TABLE `' . $dbPrefix . 'users` ADD `registerdate` INT NOT NULL',
 'ALTER TABLE  `' . $dbPrefix . 'users` ADD INDEX (  `login` )');
	
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
  DROP `activationcode`,
  DROP `email_activated`;');
	
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
	
	$readUsers = $dbCon1->prepare("SELECT `id`, `login`, `username`, `password`, `salt`, `email`, `status`, `activationcode`, `secure_cookie_string` FROM `{dbpre}users` WHERE `email_activated`='0';");
	$writeUsers = $dbCon2->prepare("INSERT INTO `{dbpre}registrations` (`id`, `login`, `username`, `password`, `salt`, `email`, `status`, `activationcode`, `secure_cookie_string`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
	$readUsers->execute();
	$readUsers->bind_result($userid, $loginname, $username, $password, $salt, $email, $status, $activationCode, $cookieString);
	while ($readUsers->fetch()) {
		$writeUsers->bind_param("isssssiss", $userid, $loginname, $username, $password, $salt, $email, $status, $activationCode, $cookieString);
		$writeUsers->execute();
	}
	$readUsers->close();
	$writeUsers->close();
	
	$dbCon1->close();
	$dbCon2->close();
}


function createSettingFile($dbServer, $dbName, $dbUser, $dbPassword, $dbPrefix, $loginEnabled, $registerEnabled, $needApproval, $lengthSalt, $lengthActivationcode, $sendEmail, $autologouttime, $maxLoginAttempts, $loginBlockTime, $securesessions) {
	$loginEnabled = convStringToBoolString($loginEnabled);
	$registerEnabled = convStringToBoolString($registerEnabled);
	$needApproval = convStringToBoolString($needApproval);
	$securesessions = convStringToBoolString($securesessions);
	
	$settingString = "<?php\nclass UserLibrarySettings {\n\tconst DB_server = \"" . $dbServer . "\";\n\tconst DB_database = \"" . $dbName . "\";\n\tconst DB_user = \"" . $dbUser . "\";\n\tconst DB_password = \"" . $dbPassword . "\";\n\tconst DB_prefix = \"" . $dbPrefix . "\";\n\n\tconst login_enabled = " . $loginEnabled . ";\n\tconst register_enabled = " . $registerEnabled . ";\n\tconst need_approval = " . $needApproval . ";\n\tconst length_salt = " . $lengthSalt . ";\n\tconst length_activationcode = " . $lengthActivationcode . ";\n\tconst send_mailaddress = \"" . $sendEmail . "\";\n\n\t#After how many seconds will a user be kicked?\n\tconst autologouttime = " . $autologouttime . ";\n\tconst maxloginattempts = " . $maxLoginAttempts . ";\n\tconst loginblocktime = " . $loginBlockTime . ";\n\tconst securesessions = " . $securesessions . ";\n}\n?>";
	$file = "data/settings.php";
	
	$numberOfBytes = file_put_contents($file, $settingString);
	
	if ($numberOfBytes === false) {
		return "Fehler beim Schreiben der settings.php Datei. &Uuml;berpr&uuml;fen Sie die Zugriffsrechte";
	}
}

function updateSettingFile() {
	$dbServer = settings\DB_server;
	$dbName = settings\DB_database;
	$dbUser = settings\DB_user;
	$dbPassword = settings\DB_password;
	$dbPrefix = settings\DB_prefix;
	$loginEnabled = convStringToBoolString(settings\login_enabled);
	$registerEnabled = convStringToBoolString(settings\register_enabled);
	$needApproval = convStringToBoolString(settings\need_approval);
	$lengthSalt = settings\length_salt;
	$lengthActivationcode = settings\length_activationcode;
	$sendEmail = settings\send_mailaddress;
	$autologouttime = settings\autologouttime;
	$maxLoginAttempts = settings\maxloginattempts;
	$loginBlockTime = settings\loginblocktime;
	$securesessions = convStringToBoolString(settings\securesessions);
	
	
	$settingString = "<?php\nclass UserLibrarySettings {\n\tconst DB_server = \"" . $dbServer . "\";\n\tconst DB_database = \"" . $dbName . "\";\n\tconst DB_user = \"" . $dbUser . "\";\n\tconst DB_password = \"" . $dbPassword . "\";\n\tconst DB_prefix = \"" . $dbPrefix . "\";\n\n\tconst login_enabled = " . $loginEnabled . ";\n\tconst register_enabled = " . $registerEnabled . ";\n\tconst need_approval = " . $needApproval . ";\n\tconst length_salt = " . $lengthSalt . ";\n\tconst length_activationcode = " . $lengthActivationcode . ";\n\tconst send_mailaddress = \"" . $sendEmail . "\";\n\n\t#After how many seconds will a user be kicked?\n\tconst autologouttime = " . $autologouttime . ";\n\tconst maxloginattempts = " . $maxLoginAttempts . ";\n\tconst loginblocktime = " . $loginBlockTime . ";\n\tconst securesessions = " . $securesessions . ";\n}\n?>";
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
  <h1>User Library Upgradeassistent (0.61 => 0.7)</h1>

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