<?php
class UserLibrarySettings {
	const DB_server = "localhost";
	const DB_database = "userlib";
	const DB_user = "root";
	const DB_password = "waterford3user4";
	const DB_prefix = "userlib_";
	const login_enabled = true;
	const register_enabled = true;
	const need_approval = false;
	const password_algorithm = 'bcrypt';
	const password_salt_length = 20;
	const password_cpu_difficulty = 16384;
	const password_mem_difficulty = 8;
	const password_parallel_difficulty = 1;
	const password_key_length = 32;
	const password_rounds = 10;
	const length_activationcode = 20;
	const send_mailaddress = "noreply@localhost";
	const autologouttime = 50000;
	const maxloginattempts = 5;
	const loginblocktime = 3600;
	const securesessions = false;
}
?>