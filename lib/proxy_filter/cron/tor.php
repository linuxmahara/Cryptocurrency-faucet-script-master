<?php
// Tor list: https://www.dan.me.uk/torlist/
// if you use this cron job, be sure to edit the config.php file for the database settings

chdir("..");
include("./proxy_filter.php");
$pf = new proxy_filter();
$tor_list = @file_get_contents("https://www.dan.me.uk/torlist/");
if (!empty($tor_list))
	{
	$tor_list = str_replace("\r\n","\n",$tor_list);
	$tor_list = explode("\n",$tor_list);
	$pf->add($tor_list,"Tor exit node");
	}
?>