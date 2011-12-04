<?php
$error = "";
$info = "";
error_reporting(0);

if (checkFormVar($_POST['databaseserver']) || checkFormVar($_POST['databasename']) || checkFormVar($_POST['databaseuser']) || checkFormVar($_POST['databasepassword'])) {
	if (checkFormVar($_POST['databaseserver']) && checkFormVar($_POST['databasename']) && checkFormVar($_POST['databaseuser']) && checkFormVar($_POST['databasepassword']) && checkFormVar($_POST['databaseprefix']) && checkFormVar($_POST['loginenabled']) && checkFormVar($_POST['registerenabled']) && checkFormVar($_POST['needapproval']) && checkFormVar($_POST['saltlength']) && checkFormVar($_POST['activationcodelength']) && checkFormVar($_POST['sendemail']) && checkFormVar($_POST['autologouttime']) && checkFormVar($_POST['maxloginattempts']) && checkFormVar($_POST['loginblocktime']) && checkFormVar($_POST['securesessions'])) {
		$error = formEvaluation();
		
		if (empty($error))
			$info = "Einrichtung erfolgreich";
	} else
		$error = "Fehlende Angaben, alle Felder m&uuml;ssen ausgef&uuml;llt werden";
}
function formEvaluation() {
	$error = "";
	
	$dbServer = $_POST['databaseserver'];
	$dbName = $_POST['databasename'];
	$dbUser = $_POST['databaseuser'];
	$dbPassword = $_POST['databasepassword'];
	$dbPrefix = $_POST['databaseprefix'];
	
    $loginEnabled = $_POST['loginenabled'];
    $registerEnabled = $_POST['registerenabled'];
    $needApproval = $_POST['needapproval'];
    $lengthSalt = $_POST['saltlength'];
    $lengthActivationcode = $_POST['activationcodelength'];
    $sendEmail = $_POST['sendemail'];
    $autologouttime = $_POST['autologouttime'];
	$maxLoginAttempts = $_POST['maxloginattempts'];
	$loginBlockTime = $_POST['loginblocktime'];
	$securesessions = $_POST['securesessions'];
	
    $loginEnabled = convStringToBool($loginEnabled);
    $registerEnabled = convStringToBool($registerEnabled);
    $needApproval = convStringToBool($needApproval);
	$securesessions = convStringToBool($securesessions);
	
	
    if ($loginEnabled === null)
        return "Login muss aktiviert oder deaktiviert sein";
    if ($registerEnabled === null)
        return "Registrierung muss aktiviert oder deaktiviert sein";
    if ($needApproval === null)
        return "Die Best&auml;tigung von Benutzer muss aktiviert oder deaktiviert sein";
	if ($securesessions === null)
        return "Securesessions m&uuml;ssen aktiviert oder deaktiviert sein";
	
	
    if (! is_numeric($lengthSalt))
        return "Die L&auml;nge des Salts muss eine Zahl sein";
    if (! is_numeric($lengthActivationcode))
        return "Die L&auml;nge des Aktvierungscode muss eine Zahl sein";
    if (! is_numeric($autologouttime))
        return "Die Auto Logout Zeit muss eine Zahl sein";
    if (! is_numeric($maxLoginAttempts))
        return "Die maximalen Einlogversuche m&uuml;ssen eine Zahl sein";
    if (! is_numeric($loginBlockTime))
        return "Die Sperrzeit muss eine Zahl sein";
	
    if (strlen($lengthSalt) < 0 || strlen($lengthSalt) > 50)
        return "Die L&auml;nge des Salts muss zwischen 0 und 50 liegen";
    if (strlen($lengthActivationcode) < 0 || strlen($lengthActivationcode) > 50)
        return "Die L&auml;nge des Aktivierungscode muss zwischen 0 und 50 liegen";
    if (strlen($autologouttime) < 0)
        return "Die Auto Logout Zeit darf nicht kleiner als 0 sein";
    if (strlen($maxLoginAttempts) < 0)
        return "Die maximalen Einlogversuche d&uuml;rfen nicht kleiner als 0 sein";
    if (strlen($loginBlockTime) < 0)
        return "Die Sperrzeit darf nicht kleiner als 0 sein";
	
	$error = createDatabaseTables($dbServer, $dbName, $dbUser, $dbPassword, $dbPrefix);
	if (! empty($error))
		return $error;
	
	$error = createSettingFile($dbServer, $dbName, $dbUser, $dbPassword, $dbPrefix, $loginEnabled, $registerEnabled, $needApproval, $lengthSalt, $lengthActivationcode, $sendEmail, $autologouttime, $maxLoginAttempts, $loginBlockTime, $securesessions);
	
	return $error;
}

function createDatabaseTables($dbServer, $dbName, $dbUser, $dbPassword, $dbPrefix) {
	$error = "";
	
	$mysqli = new mysqli($dbServer, $dbUser, $dbPassword, $dbName);
	if ($mysqli->connect_error)
		return "Fehler bei der Datenbankverbindung:<br />" . mysqli_connect_error(). "<br />Bitte &uuml;berpr&uuml;fen Sie Ihre Angaben";
	
	$createArray = array( 'CREATE TABLE IF NOT EXISTS `' . $dbPrefix . 'groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;',
'CREATE TABLE IF NOT EXISTS `' . $dbPrefix . 'group_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `groupid` int(11) NOT NULL,
  `permissionid` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;',
 'CREATE TABLE IF NOT EXISTS `' . $dbPrefix . 'onlineusers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userid` int(11) NOT NULL,
  `session` varchar(250) NOT NULL,
  `ipaddress` varchar(100) NOT NULL,
  `lastact` int(11) NOT NULL,
  PRIMARY KEY (`id`)
 ) DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;',
 'CREATE TABLE IF NOT EXISTS `' . $dbPrefix . 'permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(250) NOT NULL,
  PRIMARY KEY (`id`)
 ) DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;',
 'CREATE TABLE IF NOT EXISTS `' . $dbPrefix . 'sessionsvars` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `onlineid` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `value` varchar(3000) NOT NULL,
  PRIMARY KEY (`id`)
 ) DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;',
 'CREATE TABLE IF NOT EXISTS `' . $dbPrefix . 'users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `login` varchar(50) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(300) NOT NULL,
  `salt` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `activationcode` varchar(50) NOT NULL,
  `email_activated` int(11) NOT NULL,
  `status` int(11) NOT NULL,
  `loginattempts` int(11) NOT NULL,
  `blockeduntil` int(11) NOT NULL,
  `secure_cookie_string` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
 ) DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;'
 'CREATE TABLE IF NOT EXISTS `' . $dbPrefix . 'user_groups` (
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
 ) DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;' );
	
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
  <title>User Library Installationsassistent</title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>
<body>
  <h1>User Library Installationsassistent (0.6)</h1>

  <?php
if (! empty($error))
        echo "\n<span style=\"color:red;\">Fehler: " . $error . "</span>\n";
if (! empty($info))
        echo "\n<span style=\"color:green;\">" . $info . "</span>\n";
?>
<FORM action="./install.php" method="POST">
	<fieldset>
		<LEGEND>Datenbank</LEGEND>
		<TABLE>
			<TBODY>
				<TR><TD style="text-align:right">Datenbank Server:</TD><TD><INPUT type="text" name="databaseserver" /></TD></TR>
				<TR><TD style="text-align:right">Datenbank Name:</TD><TD><INPUT type="text" name="databasename" /></TD></TR>
				<TR><TD style="text-align:right">Datenbank Benutzer:</TD><TD><INPUT type="text" name="databaseuser" /></TD></TR>
				<TR><TD style="text-align:right">Datenbank Passwort:</TD><TD><INPUT type="password" name="databasepassword" /></TD></TR>
				<TR><TD style="text-align:right">Tabellen Pr&auml;fix:</TD><TD><INPUT type="text" name="databaseprefix" /></TD></TR>
			</TBODY>
		</TABLE>
	</fieldset>
	
	<fieldset>
		<LEGEND>Registrierung/Login/Session</LEGEND>
		<TABLE>
			<TBODY>
				<TR><TD style="text-align:right">Login aktiviert:</TD><TD><select name="loginenabled" size="1"><option>Ja</option><option>Nein</option></select></TD></TR>
				<TR><TD style="text-align:right">Registrierung aktiviert:</TD><TD><select name="registerenabled" size="1"><option>Ja</option><option>Nein</option></select></TD></TR>
				<TR><TD style="text-align:right">Benutzer ben&ouml;tigen Bestätigung:</TD><TD><select name="needapproval" size="1"><option>Ja</option><option>Nein</option></select></TD></TR>
				<TR><TD style="text-align:right">Salt L&auml;nge:</TD><TD><INPUT type="text" name="saltlength" /></TD></TR>
				<TR><TD style="text-align:right">L&auml;nge des Aktivierungscode:</TD><TD><INPUT type="text" name="activationcodelength" /></TD></TR>
				<TR><TD style="text-align:right">Mail Adresse zum Versenden von Mails:</TD><TD><INPUT type="text" name="sendemail" /></TD></TR>
				<TR><TD style="text-align:right">Auto-Logout-Zeit:</TD><TD><INPUT type="text" name="autologouttime" /></TD></TR>
				<TR><TD style="text-align:right">Maximale Anzahl an fehlerhaften Loginversuchen:</TD><TD><INPUT type="text" name="maxloginattempts" /></TD></TR>
				<TR><TD style="text-align:right">Sperrzeit nach zu vielen fehlerhaften Loginversuchen:</TD><TD><INPUT type="text" name="loginblocktime" /></TD></TR>
				<TR><TD style="text-align:right">Securesessions (Während einer Sitzung darf die IP Adresse nicht wechseln):</TD><TD><select name="securesessions" size="1"><option>Ja</option><option selected="selected">Nein</option></select></TD></TR>
			</TBODY>
		</TABLE>
	</fieldset>

<INPUT type="submit" value="Daten absenden" />
</FORM>

</body>
</html>