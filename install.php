<?php
$error = "";
$info = "";
error_reporting(0);

if (checkformVar($_POST['submit'])) {
	if (checkformVar($_POST['databaseserver'])
			&& checkformVar($_POST['databasename'])
			&& checkformVar($_POST['databaseuser'])
			&& checkformVar($_POST['databasepassword'])
			&& checkformVar($_POST['databaseprefix'])
			&& checkformVar($_POST['loginenabled'])
			&& checkformVar($_POST['registerenabled'])
			&& checkformVar($_POST['needapproval'])
			&& checkformVar($_POST['passwordalgorithm'])
			&& checkformVar($_POST['saltlength'])
			&& checkformVar($_POST['cpudifficulty'])
			&& checkformVar($_POST['memdifficulty'])
			&& checkformVar($_POST['parallelDifficulty'])
			&& checkformVar($_POST['keylength'])
			&& checkformVar($_POST['rounds'])
			&& checkformVar($_POST['activationcodelength'])
			&& checkformVar($_POST['sendemail'])
			&& checkformVar($_POST['autologouttime'])
			&& checkformVar($_POST['maxloginattempts'])
			&& checkformVar($_POST['loginblocktime'])
			&& checkformVar($_POST['securesessions'])) {
		$error = formEvaluation();
		
		if (empty($error))
			$info = "Installation successfull";
	} else
		$error = "Missing information, all fields are mandatory";
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
	$passwordAlgorithm = $_POST['passwordalgorithm'];
	$passwordSaltLength = $_POST['saltlength'];
	$passwordCpuDifficulty = $_POST['cpudifficulty'];
	$passwordMemDifficulty = $_POST['memdifficulty'];
	$passwordParallelDifficulty = $_POST['parallelDifficulty'];
	$passwordKeyLength = $_POST['keylength'];
	$passwordRounds = $_POST['rounds'];
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
        return "Login has to be enabled or disabled";
    if ($registerEnabled === null)
        return "Registration has to be enabled or disabled";
    if ($needApproval === null)
        return "The approval of new users has to be enabled or disabled";
	if ($securesessions === null)
        return "Securesessions has to be enabled or disabled";
	
	
    if (! is_numeric($lengthActivationcode))
        return "The length of the activationcode has to be numeric";
    if (! is_numeric($autologouttime))
        return "The auto-logout-time has to be numeric";
    if (! is_numeric($maxLoginAttempts))
        return "The maximum amount of failed logins has to be numeric";
    if (! is_numeric($loginBlockTime))
        return "The blocking time has to be numeric";
	
    if (strlen($lengthActivationcode) < 0 || strlen($lengthActivationcode) > 50)
        return "The length of the activationcode has to be between 0 and 50";
    if (strlen($autologouttime) < 0)
        return "The auto-logout-time has to be bigger than 0";
    if (strlen($maxLoginAttempts) < 0)
        return "The maximum amount of failed logins has to be bigger than 0";
    if (strlen($loginBlockTime) < 0)
        return "The blocking time has to be bigger than 0";
	
	$error = createDatabasetables($dbServer, $dbName, $dbUser, $dbPassword, $dbPrefix);
	if (! empty($error))
		return $error;

	$error = createSettingFile($dbServer,
			$dbName,
			$dbUser,
			$dbPassword,
			$dbPrefix,
			$loginEnabled,
			$registerEnabled,
			$needApproval,
			$passwordAlgorithm,
			$passwordSaltLength,
			$passwordCpuDifficulty,
			$passwordMemDifficulty,
			$passwordParallelDifficulty,
			$passwordKeyLength,
			$passwordRounds,
			$lengthActivationcode,
			$sendEmail,
			$autologouttime,
			$maxLoginAttempts,
			$loginBlockTime,
			$securesessions
		);
	
	return $error;
}

function createDatabasetables($dbServer, $dbName, $dbUser, $dbPassword, $dbPrefix) {
	$error = "";
	
	$mysqli = new mysqli($dbServer, $dbUser, $dbPassword, $dbName);
	if ($mysqli->connect_error)
		return "Error when trying to connect to the database:<br />" . mysqli_connect_error(). "<br />Please check the credentials";
	
	$createArray = array( 'CREATE table IF NOT EXISTS `' . $dbPrefix . 'groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 ;',
'CREATE table IF NOT EXISTS `' . $dbPrefix . 'group_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `groupid` int(11) NOT NULL,
  `permissionid` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 ;',
 'CREATE table IF NOT EXISTS `' . $dbPrefix . 'onlineusers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userid` int(11) NOT NULL,
  `session` varchar(250) NOT NULL,
  `ipaddress` varchar(100) NOT NULL,
  `lastact` int(11) NOT NULL,
  PRIMARY KEY (`id`)
 ) DEFAULT CHARSET=latin1 ;',
 'CREATE table IF NOT EXISTS `' . $dbPrefix . 'permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(250) NOT NULL,
  PRIMARY KEY (`id`)
 ) DEFAULT CHARSET=latin1 ;',
 'CREATE table IF NOT EXISTS `' . $dbPrefix . 'registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `login` varchar(50) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(300) NOT NULL,
  `email` varchar(150) NOT NULL,
  `status` int(11) NOT NULL,
  `activationcode` varchar(50) NOT NULL,
  `secure_cookie_string` varchar(100) NOT NULL,
  `registerdate` int(11) NOT NULL,
  PRIMARY KEY (`id`)
 ) DEFAULT CHARSET=latin1 ;',
 'CREATE table IF NOT EXISTS `' . $dbPrefix . 'sessionsvars` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `onlineid` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `value` varchar(3000) NOT NULL,
  PRIMARY KEY (`id`)
 ) DEFAULT CHARSET=latin1 ;',
 'CREATE table IF NOT EXISTS `' . $dbPrefix . 'users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `login` varchar(50) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(300) NOT NULL,
  `email` varchar(150) NOT NULL,
  `status` int(11) NOT NULL,
  `loginattempts` int(11) NOT NULL,
  `blockeduntil` int(11) NOT NULL,
  `secure_cookie_string` varchar(100) NOT NULL,
  `registerdate` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `login` (`login`)
 ) DEFAULT CHARSET=latin1 ;',
 'CREATE table IF NOT EXISTS `' . $dbPrefix . 'user_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userid` int(11) NOT NULL,
  `groupid` int(11) NOT NULL,
  `level` int(11) NOT NULL,
  PRIMARY KEY (`id`)
 ) DEFAULT CHARSET=latin1 ;',
 'CREATE table IF NOT EXISTS `' . $dbPrefix . 'user_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userid` int(11) NOT NULL,
  `permissionid` int(11) NOT NULL,
  PRIMARY KEY (`id`)
 ) DEFAULT CHARSET=latin1 ;' );
	
	foreach ($createArray as $value) {
		$result = $mysqli->query($value);
		if (! $result) {
			$error = "Error when trying to create the mysql tables:<br />" . $mysqli->error;
			break;
		}
	}
	
	$mysqli->close();
	return $error;
}


function createSettingFile(
		$dbServer,
		$dbName,
		$dbUser,
		$dbPassword,
		$dbPrefix,
		$loginEnabled,
		$registerEnabled,
		$needApproval,
		$passwordAlgorithm,
		$passwordSaltLength,
		$passwordCpuDifficulty,
		$passwordMemDifficulty,
		$passwordParallelDifficulty,
		$passwordKeyLength,
		$passwordRounds,
		$lengthActivationcode,
		$sendEmail,
		$autologouttime,
		$maxLoginAttempts,
		$loginBlockTime,
		$securesessions) {
	$loginEnabled = convStringToBoolString($loginEnabled);
	$registerEnabled = convStringToBoolString($registerEnabled);
	$needApproval = convStringToBoolString($needApproval);
	$securesessions = convStringToBoolString($securesessions);
	
	$settingsString = <<<EOF
<?php
class UserLibrarySettings {
	const DB_server = "$dbServer";
	const DB_database = "$dbName";
	const DB_user = "$dbUser";
	const DB_password = "$dbPassword";
	const DB_prefix = "$dbPrefix";
	const login_enabled = $loginEnabled;
	const register_enabled = $registerEnabled;
	const need_approval = $needApproval;
	const password_algorithm = '$passwordAlgorithm';
	const password_salt_length = $passwordSaltLength;
	const password_cpu_difficulty = $passwordCpuDifficulty;
	const password_mem_difficulty = $passwordMemDifficulty;
	const password_parallel_difficulty = $passwordParallelDifficulty;
	const password_key_length = $passwordKeyLength;
	const password_rounds = $passwordRounds;
	const length_activationcode = $lengthActivationcode;
	const send_mailaddress = "$sendEmail";
	const autologouttime = $autologouttime;
	const maxloginattempts = $maxLoginAttempts;
	const loginblocktime = $loginBlockTime;
	const securesessions = $securesessions;
}
?>
EOF;
	$file = "data/settings.php";
	
	$numberOfBytes = file_put_contents($file, $settingsString);
	
	if ($numberOfBytes === false) {
		return "Error when trying to write settings.php. Please check the file permissions";
	}
}

function checkformVar(&$var) {
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
    if ($string == "Yes")
        return true;
    elseif ($string == "No")
        return false;
    else
        return null;
}

?>
<!DOCTYPE html>
<html>
<head>
  <title>User Library Installation Assistent</title>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>
<body>
  <h1>User Library Installation Assistent (0.73)</h1>

  <?php
if (! empty($error))
        echo "\n<span style=\"color:red;\">Error: $error</span>\n";
if (! empty($info))
        echo "\n<span style=\"color:green;\">$info</span>\n";
?>
<form action="./install.php" method="POST">
	<fieldset>
		<legend>Database</legend>
		<table>
			<tbody>
				<tr><td style="text-align:right">Database Server:</td><td><input type="text" name="databaseserver" /></td></tr>
				<tr><td style="text-align:right">Database Name:</td><td><input type="text" name="databasename" /></td></tr>
				<tr><td style="text-align:right">Database User:</td><td><input type="text" name="databaseuser" /></td></tr>
				<tr><td style="text-align:right">Database Password:</td><td><input type="password" name="databasepassword" /></td></tr>
				<tr><td style="text-align:right">Table Prefix:</td><td><input type="text" name="databaseprefix" /></td></tr>
			</tbody>
		</table>
	</fieldset>
	
	<fieldset>
		<legend>Registration/Login/Session</legend>
		<table>
			<tbody>
				<tr><td style="text-align:right">Login enabled:</td><td><select name="loginenabled" size="1"><option>Yes</option><option>No</option></select></td></tr>
				<tr><td style="text-align:right">Registration enabled:</td><td><select name="registerenabled" size="1"><option>Yes</option><option>No</option></select></td></tr>
				<tr><td style="text-align:right">Users need approval:</td><td><select name="needapproval" size="1"><option>Yes</option><option>No</option></select></td></tr>
				<tr><td style="text-align:right">Length of the activationcode:</td><td><input type="text" name="activationcodelength" /></td></tr>
				<tr><td style="text-align:right">Mail address for sending:</td><td><input type="text" name="sendemail" /></td></tr>
				<tr><td style="text-align:right">Auto-Logout-Time:</td><td><input type="text" name="autologouttime" /></td></tr>
				<tr><td style="text-align:right">Maximum amount of failed logins:</td><td><input type="text" name="maxloginattempts" /></td></tr>
				<tr><td style="text-align:right">Blocking time after too many failed logins:</td><td><input type="text" name="loginblocktime" /></td></tr>
				<tr><td style="text-align:right">Securesessions (The IP address must not change during a session):</td><td><select name="securesessions" size="1"><option>Yes</option><option selected="selected">No</option></select></td></tr>
			</tbody>
		</table>
	</fieldset>
	<fieldset>
		<legend>Password Encryption</legend>
		<table>
			<tbody>
				<tr><td style="text-align:right">Password algorithm:</td><td><select name="passwordalgorithm"><option>scrypt<?php if (! extension_loaded("scrypt")) echo "(You have to install the php-scrypt extension before using it)"; ?></option><option>bcrypt</option></select></td></tr>
				<tr><td style="text-align:right">scrypt: Salt length:</td><td><input type="text" name="saltlength" value="20" /></td></tr>
				<tr><td style="text-align:right">scrypt: CPU Difficulty:</td><td><input type="text" name="cpudifficulty" value="16384" /></td></tr>
				<tr><td style="text-align:right">scrypt: Mem Difficulty:</td><td><input type="text" name="memdifficulty" value="8" /></td></tr>
				<tr><td style="text-align:right">scrypt: Parallel Difficulty:</td><td><input type="text" name="parallelDifficulty" value="1" /></td></tr>
				<tr><td style="text-align:right">scrypt: Key Length:</td><td><input type="text" name="keylength" value="32" /></td></tr>
				<tr><td style="text-align:right">bcrypt: Rounds:</td><td><input type="text" name="rounds" value="10" /></td></tr>
			</tbody>
		</table>
	</fieldset>

<input type="submit" name="submit" value="Send" />
</form>

</body>
</html>