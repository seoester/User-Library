<?php
class UserLibrarySettings {
	const DB_server = "localhost";
	const DB_database = "userlib";
	const DB_user = "root";
	const DB_password = "root";
	const DB_prefix = "userlib_";
	const login_enabled = true;
	const register_enabled = true;
	const need_approval = false;
	const length_salt = 20;
	const length_activationcode = 20;
	const send_mailaddress = "noreply@localhost";
	const autologouttime = 50000;
	const maxloginattempts = 5;
	const loginblocktime = 3600;
	const securesessions = false;
}
?>