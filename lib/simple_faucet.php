<?php

class simple_faucet
	{
	protected $config;

	protected $rpc_client = false;
	protected $db = false;

	protected $status = 0;

	protected $payout_amount = 0;
	protected $payout_address = "";

	protected $promo_payout_amount = 0;

	protected $balance = 0;

	protected $header = '';


	public function __construct($config)
		{
		if (!defined("SF_STATUS_OPERATIONAL"))
			{
			define("SF_STATUS_OPERATIONAL",100);
			define("SF_STATUS_PAYOUT_ACCEPTED",101);
			define("SF_STATUS_PAYOUT_AND_PROMO_ACCEPTED",102);
			//define("SF_STATUS_SUCCESS",102);

			define("SF_STATUS_RPC_CONNECTION_FAILED",200);
			define("SF_STATUS_MYSQL_CONNECTION_FAILED",201);
			define("SF_STATUS_PAYOUT_DENIED",202);
			define("SF_STATUS_INVALID_DOGE_ADDRESS",203);
			define("SF_STATUS_PAYOUT_ERROR",204);
			define("SF_STATUS_CAPTCHA_INCORRECT",205);
			define("SF_STATUS_DRY_FAUCET",206);
			define("SF_STATUS_PROXY_DETECTED",207);

			define("SF_STATUS_FAUCET_INCOMPLETE",300);
			}
		$defaults = array(
			"minimum_payout" => 0.01,
			"maximum_payout" => 10,
			"payout_threshold" => 250,
			"payout_interval" => "7h",
			"user_check" => "both",
			"wallet_passphrase" => "",
			"use_captcha" => true,
			"captcha" => "recaptcha",
			"captcha_config" => array(),
			"use_promo_codes" => true,
			"mysql_table_prefix" => "sf_",
			"donation_address" => "DTiUqjQTXwgZfvcTcdoabp7uLezK47TPkN ",
			"title" => "DOGE Faucet",
			"template" => "default",
			"stage_payments" => false,
			"stage_payment_account_name" => "account",
			"staged_payment_threshold" => 15,
			"staged_payment_cron_only" => false,
			"filter_proxies" => false,
			"proxy_filter_use_faucet_database" => false

			);
		$this->config = array_merge($defaults,$config);
		if ($this->config["user_check"] != "ip_address" && $this->config["user_check"] != "doge_address")
			$this->config["user_check"] = "both";

		if ($this->config["captcha"] == "recaptcha")
			require_once('./lib/recaptchalib.php');
		elseif ($this->config["captcha"] == "recaptcha2")
			require_once('./lib/recaptchalib2.php');

		if (isset($config["rpc_user"],$config["rpc_password"],$config["rpc_host"],$config["rpc_port"],$config["mysql_user"],$config["mysql_password"],$config["mysql_host"],$config["mysql_database"]))
			{
			if (class_exists("jsonRPCClient"))
				{
				$this->rpc_client = new jsonRPCClient('http://'.urlencode($config["rpc_user"]).':'.urlencode($config["rpc_password"]).'@'.urlencode($config["rpc_host"]).':'.urlencode($config["rpc_port"]));
				
				$this->db = @new mysqli($config["mysql_host"],$config["mysql_user"],$config["mysql_password"],$config["mysql_database"]);
				//if (!$this->db->connect_error)
				if (!mysqli_connect_error() && !is_null($this->balance = $this->rpc("getbalance"))) // compatibility with older PHP versions
					{
					if ($this->balance >= $this->config["payout_threshold"])
						{
						$this->status = SF_STATUS_OPERATIONAL;

						if (isset($_POST["dogecoin_address"]) && (($this->config["use_captcha"] && $this->valid_captcha()) || !$this->config["use_captcha"]))
							{
							if ($this->config["filter_proxies"] && class_exists("proxy_filter"))
								{
								$pf = new proxy_filter($this->config["proxy_filter_use_faucet_database"]?$this->db:false);
								if ($pf->check())
									{
									$this->status = SF_STATUS_PROXY_DETECTED;
									return;
									}
								}

							$dogecoin_address = $_POST["dogecoin_address"];
							$validation = $this->rpc("validateaddress",array($dogecoin_address));
							if ($validation["isvalid"])
								{
								$interval = "7 HOUR"; // hardcoded default interval if the custom interval is messed up
								$interval_value = intval(substr($this->config["payout_interval"],0,-1));
								$interval_function = strtoupper(substr($this->config["payout_interval"],-1));
								if ($interval_value >= 0 && ($interval_function == "H" || $interval_function == "M" || $interval_function == "D"))
									{
									$interval = $interval_value." ";
									switch ($interval_function)
										{
										case "M":
											$interval .= "MINUTE";
											break;
										case "H":
											$interval .= "HOUR";
											break;
										case "D":
											$interval .= "DAY";
											break;
										}
									}
								$user_check = " AND (";
								if ($this->config["user_check"] == "ip_address" || $this->config["user_check"] == "both")
									$user_check .= " `ip_address` = '".$this->db->escape_string($_SERVER["REMOTE_ADDR"])."'";
								if ($this->config["user_check"] == "doge_address" || $this->config["user_check"] == "both")
									$user_check .= ($this->config["user_check"] == "both"?" OR":"")." `payout_address` = '".$this->db->escape_string($dogecoin_address)."'";
								$user_check .= ")";
								$result = $this->db->query("SELECT `id` FROM `".$this->db->escape_string($this->config["mysql_table_prefix"])."payouts` WHERE `timestamp` > NOW() - INTERVAL ".$interval.$user_check);
								if ($row = @$result->fetch_assoc())
									$this->status = SF_STATUS_PAYOUT_DENIED; // user already received a payout within the payout interval
								else
									{
									$promo_code = "";
									if ($this->config["use_promo_codes"] && isset($_POST["promo_code"])) // check for valid promo code
										{
										$result2 = $this->db->query("SELECT `minimum_payout`,`maximum_payout`,`uses` FROM `".$this->config["mysql_table_prefix"]."promo_codes` WHERE `code` = '".$this->db->escape_string($_POST["promo_code"])."'"); 
										if ($promo = @$result2->fetch_assoc())
											{
											$promo["uses"] = intval($promo["uses"]); // MySQLi
											if ($promo["uses"] !== 0)
												{
												$promo_code = $_POST["promo_code"];
												$promo["minimum_payout"] = floatval($promo["minimum_payout"]);
												$promo["maximum_payout"] = floatval($promo["maximum_payout"]);
												if ($promo["minimum_payout"] >= $promo["maximum_payout"])
													$this->promo_payout_amount = $promo["maximum_payout"];
												else
													$this->promo_payout_amount = $this->float_rand($promo["minimum_payout"],$promo["maximum_payout"]);
													//$this->promo_payout_amount = mt_rand($promo["minimum_payout"]*10000,$promo["maximum_payout"]*10000)/10000; // calculate a random promo DOGE amount
												if ($promo["uses"] > 0)
													$this->db->query("UPDATE `".$this->config["mysql_table_prefix"]."promo_codes` SET `uses` = `uses`-1 WHERE `code` = '".$this->db->escape_string($promo_code)."'");
												}
											}
										}
									//$this->payout_amount = mt_rand($this->config["minimum_payout"]*10000,$this->config["maximum_payout"]*10000)/10000; // calculate a random DOGE amount
									$this->payout_amount = $this->float_rand($this->config["minimum_payout"],$this->config["maximum_payout"]);
									$this->db->query("INSERT INTO `".$this->db->escape_string($this->config["mysql_table_prefix"])."payouts` (`payout_amount`,`ip_address`,`payout_address`,`promo_code`,`promo_payout_amount`,`timestamp`) VALUES ('".$this->payout_amount."','".$this->db->escape_string($_SERVER["REMOTE_ADDR"])."','".$this->db->escape_string($dogecoin_address)."','".$this->db->escape_string($promo_code)."','".$this->promo_payout_amount."',NOW())"); // insert the transaction into the payout log

									if ($this->config["wallet_passphrase"] != "")
										$this->rpc("walletpassphrase",array($this->config["wallet_passphrase"],5)); // unlock wallet

									if (isset($this->config["_debug_test_mode"]))
										$this->status = true ? $this->promo_payout_amount>0 ? SF_STATUS_PAYOUT_AND_PROMO_ACCEPTED : SF_STATUS_PAYOUT_ACCEPTED : SF_STATUS_PAYOUT_ERROR; // test status
									else
										{
										if ($this->config["stage_payments"])
											$this->status = $this->stage_payment($dogecoin_address,($this->payout_amount+$this->promo_payout_amount)) ? $this->promo_payout_amount>0 ? SF_STATUS_PAYOUT_AND_PROMO_ACCEPTED : SF_STATUS_PAYOUT_ACCEPTED : SF_STATUS_PAYOUT_ERROR; // stage the DOGE;
										else
											$this->status = !is_null($this->rpc("sendtoaddress",array($dogecoin_address, ($this->payout_amount+$this->promo_payout_amount) ))) ? $this->promo_payout_amount>0 ? SF_STATUS_PAYOUT_AND_PROMO_ACCEPTED : SF_STATUS_PAYOUT_ACCEPTED : SF_STATUS_PAYOUT_ERROR; // send the DOGE
										}
									
									if ($this->config["wallet_passphrase"] != "")
										$this->rpc("walletlock"); // lock wallet
									}
								}
							else
								$this->status = SF_STATUS_INVALID_DOGE_ADDRESS;
							}
						else
							{
							if (isset($_POST["dogecoin_address"]))
								$this->status = SF_STATUS_CAPTCHA_INCORRECT;
							}
						}
					else
						$this->status = SF_STATUS_DRY_FAUCET;
					}
				else
					$this->status = SF_STATUS_MYSQL_CONNECTION_FAILED;
				}
			else
				$this->status = SF_STATUS_FAUCET_INCOMPLETE; // missing RPC client
			}
		else
			$this->status = SF_STATUS_FAUCET_INCOMPLETE; // missing some settings
		}

	public function add_head($h)
		{
		$this->header .= $h;
		}

	public function render()
		{
		if (!file_exists("./templates/".$this->config["template"].".template.php"))
			die("Template ".$this->config["template"]."not found.");
		ob_start();
		include("./templates/".$this->config["template"].".template.php");
		$template = ob_get_clean();
		
		$self = $this;
		$db = $this->db;
		$header = $this->header;
		$status = $this->status;
		$config = $this->config;
		$balance = $this->balance;
		$payout_amount = $this->payout_amount;
		$payout_address = $this->payout_address;
		$promo_payout_amount = $this->promo_payout_amount;

		$template = preg_replace_callback("/\{\{([a-zA-Z-0-9\ \_]+?)\}\}/",function($match) use ($self,$db,$header,$status,$config,$balance,$payout_amount,$payout_address,$promo_payout_amount)
			{
			switch (strtolower($match[1]))
				{
				// faucet information:
				case "minimum_payout":
				case "maximum_payout":
				case "payout_threshold":
				case "donation_address":
				case "title":
					return isset($config[strtolower($match[1])]) ? $config[strtolower($match[1])] : $match[1];
                
                case "head":
                	return $header;
                                
                case "coinname":
	               return $config["coinname"];
                
				case "balance":
					return $balance;
				
				// statistics:
				case "average_payout":
					return $self->payout_aggregate("AVG");

				case "total_payout":
				case "total_payouts":
					return $self->payout_aggregate("SUM");

				case "smallest_payout":
					return $self->payout_aggregate("MIN");

				case "largest_payout":
					return $self->payout_aggregate("MAX");

				case "number_of_payouts":
					return $self->payout_aggregate("COUNT");

				// staged payment info:
				case "staged_payment_count":
					if ($status != SF_STATUS_MYSQL_CONNECTION_FAILED)
						{
						if ($result = $db->query("SELECT COUNT(`payout_amount`) FROM `".$db->escape_string($config["mysql_table_prefix"])."staged_payments`"))
							{
							$row = $result->fetch_array(MYSQLI_NUM);
							return $row[0];
							}
						}
					return 0;

				case "staged_payments_left":
					if ($status != SF_STATUS_MYSQL_CONNECTION_FAILED)
						{
						if ($result = $db->query("SELECT COUNT(`payout_amount`) FROM `".$db->escape_string($config["mysql_table_prefix"])."staged_payments`"))
							{
							$row = $result->fetch_array(MYSQLI_NUM);
							return $config["staged_payment_threshold"] - $row[0];
							}
						}
					return 0;

				case "staged_payment_threshold":
					return $config["staged_payment_threshold"];

				// current user information:
				case "payout_amount":
					return $payout_amount;

				case "payout_address":
					return $payout_address;

				case "promo_payout_amount":
					return $promo_payout_amount;

				// CAPTCHA:

				case "captcha":
					if ($config["use_captcha"])
						{
						if ($config["captcha"] == "recaptcha" || $config["captcha"] == "recaptcha2")
							return recaptcha_get_html(@$config["captcha_config"]["public_key"]);
						}
					return '';
                                case "ads":
                                    return $config['ads'];

				default:
					return $match[1];
				}
			},$template);
		echo $template;
		}

	public function status()
		{
		return $this->status;
		}

	public function config($config)
		{
		return isset($this->config[$config]) ? $this->config[$config] : null;
		}

	// Payout aggregate functions, to make things easier.
	// Possible functions are:
	// AVG - average payout
	// SUM - total payout
	// MIN - smallest payout
	// MAX - largest payout
	// COUNT - number of payouts
	// See: http://dev.mysql.com/doc/refman/5.0/en/group-by-functions.html
	public function payout_aggregate($function = "AVG")
		{
		//if ($this->db->ping())
		if ($this->status != SF_STATUS_MYSQL_CONNECTION_FAILED)
			{
			if ($result = $this->db->query("SELECT ".$this->db->escape_string($function)."(`payout_amount`) FROM `".$this->db->escape_string($this->config["mysql_table_prefix"])."payouts`"))
				{
				$row = $result->fetch_array(MYSQLI_NUM);
				return is_float($row[0]) ? number_format($row[0],6) : $row[0];
				}
			}
		return false;
		}

	protected function valid_captcha()
		{
		if (!$this->config["use_captcha"])
			return true;
		if ($this->config["captcha"] == "recaptcha")
			{
			$resp = @recaptcha_check_answer($this->config["captcha_config"]["private_key"],$_SERVER["REMOTE_ADDR"],@$_POST["recaptcha_challenge_field"],@$_POST["recaptcha_response_field"]);
			return $resp->is_valid; // $resp->error;
			}
		else
			return recaptcha_check_answer($this->config["captcha_config"]["private_key"],@$_POST["g-recaptcha-response"],$this->config["captcha_https"]);
		return false;		
		}

	protected function stage_payment($address,$amount)
		{
		if ($this->status != SF_STATUS_MYSQL_CONNECTION_FAILED && $this->config["stage_payments"])
			{
			$this->db->query("INSERT INTO `".$this->db->escape_string($this->config["mysql_table_prefix"])."staged_payments` (`payout_address`,`payout_amount`) VALUES ('".$this->db->escape_string($address)."','".$this->db->escape_string($amount)."')");
			if (!$this->config["staged_payment_cron_only"])
				{
				if ($result = $this->db->query("SELECT COUNT(`payout_amount`) FROM `".$this->db->escape_string($this->config["mysql_table_prefix"])."staged_payments`"))
					{
					$row = $result->fetch_array(MYSQLI_NUM);
					if ($row[0] >= $this->config["staged_payment_threshold"])
						$this->execute_staged_payments();
					}
				}
			return true;
			}
		return false;
		}

	public function execute_staged_payments()
		{
		if ($this->status != SF_STATUS_MYSQL_CONNECTION_FAILED)
			{
			if ($result = $this->db->query("SELECT * FROM `".$this->db->escape_string($this->config["mysql_table_prefix"])."staged_payments`"))
				{
				$stage = array();
				$ids = array();
				while($row = $result->fetch_assoc())
					{
					$ids[] = $row["id"];
					$stage[$row["payout_address"]] = !isset($stage[$row["payout_address"]]) ? floatval($row["payout_amount"]) : $stage[$row["payout_address"]] + floatval($row["payout_amount"]);
					}
				$this->db->query("DELETE FROM `".$this->db->escape_string($this->config["mysql_table_prefix"])."staged_payments` WHERE `id` = '".implode("' OR `id` = '",$ids)."'"); // delete the stage payments that are about to be executed

				if ($this->config["wallet_passphrase"] != "")
					$this->rpc("walletpassphrase",array($this->config["wallet_passphrase"],5)); // unlock wallet

				$this->rpc("sendmany",array($this->config["stage_payment_account_name"],$stage)); // check null?

				if ($this->config["wallet_passphrase"] != "")
					$this->rpc("walletlock"); // lock wallet
				}
			}
		}

	public function float_rand($min,$max,$round=8)
		{
		if ($min > $max)
			{
			$a = $min;
			$min = $max;
			$max = $a;
			}
		$f = $min + mt_rand() / mt_getrandmax() * ($max - $min);
		if($round > 0)
			return round($f,$round);
		return $f;
		}

	protected function rpc($method,$params = array())
		{
		try
			{
			return @$this->rpc_client->__call($method,$params);
			}
		catch (Exception $e)
			{
			$this->status = SF_STATUS_RPC_CONNECTION_FAILED;
			return null;
			}
		}
	}

defined("SIMPLE_FAUCET") || header(".");
?>
