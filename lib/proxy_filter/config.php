<?php

$config = array(
	// MySQL settings:
	"mysql_user" => "db_username",
	"mysql_password" => "db_password",
	"mysql_host" => "localhost",
	"mysql_database" => "db_name", // ban list database
	"mysql_table_prefix" => "pf_", // table prefix to use

	// filter settings:
	"check_for_proxy_headers" => true // filter typical proxy headers
	);


/*
CREATE TABLE `pf_ban_list` (
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `reason` varchar(150) NOT NULL DEFAULT '',
  UNIQUE KEY `ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
*/
?>