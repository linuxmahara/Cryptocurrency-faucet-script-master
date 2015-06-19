<?php

// this is a re-implementation of recaptchalib.php so it can easily be swapped out.

$this->add_head('<script src="http'.($this->config("captcha_https")?'s':'').'://www.google.com/recaptcha/api.js"></script>');

function recaptcha_check_answer($secret,$response_code,$use_https = false)
	{
	$response = file_get_contents('http'.($use_https?'s':'').'://www.google.com/recaptcha/api/siteverify?secret='.urlencode($secret).'&response='.urlencode($response_code));
	if ($response)
		{
		$response = json_decode($response,true);
		return (@$response["success"] === true);
		}
	return false;
	}

function recaptcha_get_html($sitekey)
	{
	return '<div class="g-recaptcha" data-sitekey="'.htmlentities($sitekey).'"></div>';
	}
?>
