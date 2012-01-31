<?php
$error = "";
$info = "";
require_once "data/settings.php";
require_once "data/database.php";
if (isset($_POST['upgrade']) && $_POST['upgrade'] == "yes") {
	$info = "Einrichtung erfolgreich";
}
function formEvaluation() {
}

function upgradeDatabaseTables($dbServer, $dbName, $dbUser, $dbPassword, $dbPrefix) {
}

function convertMissingFields() {
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
  <h1>User Library Upgradeassistent (0.6 => 0.61)</h1>

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