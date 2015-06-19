<?php

class proxy_filter
	{
	protected $config = false;
	protected $db = false;
	protected $running = false;

	public function __construct($database_connection = false)
		{
		require(__DIR__."/config.php");
		if (isset($config))
			{
			$this->config = $config;
			if ($database_connection instanceof mysqli)
				$this->db = $database_connection;
			else
				{
				$this->db = @new mysqli($config["mysql_host"],$config["mysql_user"],$config["mysql_password"],$config["mysql_database"]);
				if (!mysqli_connect_error())
					$this->running = true;
				}
			}
		else
			die("No config found.");
		}

	public function check($ip_address = false,$return_reason = false)
		{
		if ($this->running)
			{
			if ($this->config["check_for_proxy_headers"] && !$ip_address)
				{
				$proxy_headers = array(
					'HTTP_VIA',
					'HTTP_X_FORWARDED_FOR',
					'HTTP_FORWARDED_FOR',
					'HTTP_X_FORWARDED',
					'HTTP_FORWARDED',
					'HTTP_CLIENT_IP',
					'HTTP_FORWARDED_FOR_IP',
					'VIA',
					'X_FORWARDED_FOR',
					'FORWARDED_FOR',
					'X_FORWARDED',
					'FORWARDED',
					'CLIENT_IP',
					'FORWARDED_FOR_IP',
					'HTTP_PROXY_CONNECTION'
					);
				foreach($proxy_headers as $header)
					{
					if (isset($_SERVER[$header]))
						return $return_reason ? 'Proxy header detected' : true;
					}
				}
			if (!$ip_address)
				$ip_address = $_SERVER["REMOTE_ADDR"];
			$result = $this->db->query("SELECT `reason` FROM `".$this->db->escape_string($this->config["mysql_table_prefix"])."ban_list` WHERE `ip_address` = '".$this->db->escape_string($ip_address)."'");
			return ($row = @$result->fetch_assoc()) ? $return_reason ? $row["reason"] : true : false;
			}
		return null;
		}

	public function add($ip_address,$comment = "")
		{
		if ($this->running)
			{
			$list = array();
			if (is_array($ip_address))
				{
				if (empty($ip_address))
					return false;
				foreach ($ip_address as $key=>$value)
					{
					if ((is_string($key) && empty($key)) || (is_numeric($key) && empty($value)))
						continue;
					if (is_string($key))
						$list[] = "('".$this->db->escape_string($key)."', '".$this->db->escape_string($value)."')";
					else
						$list[] = "('".$this->db->escape_string($value)."', '".$this->db->escape_string($comment)."')";
					}
				}
			else
				$list[] = "('".$this->db->escape_string($ip_address)."', '".$this->db->escape_string($comment)."')";
			return $this->db->query("INSERT IGNORE INTO `".$this->db->escape_string($this->config["mysql_table_prefix"])."ban_list` (`ip_address`,`reason`) VALUES ".implode(", ",$list));
			}
		return false;
		}

	public function remove($ip_address)
		{
		if ($this->running)
			{
			$list = array();
			if (is_array($ip_address))
				{
				if (empty($ip_address))
					return false;
				foreach ($ip_address as $value)
					$list[] = "`ip_address` = '".$this->db->escape_string($value)."'";
				}
			else
				$list[] = "`ip_address` = '".$this->db->escape_string($ip_address)."'";
			return $this->db->query("DELETE FROM `".$this->db->escape_string($this->config["mysql_table_prefix"])."ban_list` WHERE ".implode(" OR ",$list));
			}
		return false;
		}
	}

?>