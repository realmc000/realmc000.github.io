<?php
/*
=====================================================
 DataLife Engine - by SoftNews Media Group 
-----------------------------------------------------
 http://dle-news.ru/
-----------------------------------------------------
 Copyright (c) 2004-2017 SoftNews Media Group
=====================================================
 Данный код защищен авторскими правами
=====================================================
 Файл: lostpassword.php
-----------------------------------------------------
 Назначение: Восстановление забытого пароля
=====================================================
*/

if( ! defined( 'DATALIFEENGINE' ) ) {
	die( "Hacking attempt!" );
}

function GetRandInt($max){

	if(function_exists('openssl_random_pseudo_bytes')) {
	     do{
	         $result = floor($max*(hexdec(bin2hex(openssl_random_pseudo_bytes(4)))/0xffffffff));
	     }while($result == $max);
	} else {

		$result = mt_rand( 0, $max );
	}

    return $result;
}

if( $is_loged_in ) {
	msg( "error", $lang['index_denied'], $lang['index_denied'] );
}

$skin_login = $skin_not_logged_header = <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="{$config['charset']}">
  <meta name="viewport" content="width=device-width, maximum-scale=1, initial-scale=1, user-scalable=0">
  <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
  <title>DataLife Engine - {$lang['skin_title']}</title>
  <link href="engine/skins/stylesheets/application.css" rel="stylesheet" type="text/css" />
  {$custom_css}
  <script type="text/javascript" src="engine/skins/javascripts/application.js"></script>
<style type="text/css">
div.selector {
  width: 100%;
  height: 38px;
  margin-left: 2px;
}
div.selector:after {
    top: 6px;
}
div.selector span {
    padding: 0;	
    padding-left: 40px;
    height: 36px;
    line-height: 36px;
}
body {
	background: url("engine/skins/images/bg.png");

}
.box {
	margin-bottom: 5px;
}
label {
    margin-bottom:0px;
}

</style>
</head>
<body>
<script language="javascript" type="text/javascript">
<!--
var dle_act_lang   = [];
var cal_language   = {en:{months:[],dayOfWeek:[]}};
//-->
</script>

<div class="container">
  <div class="col-md-4 col-md-offset-4">
    <div class="padded">
<!--MAIN area-->


	<div class="login box" style="margin-top: 100px;">

      <div class="box-header">
        <span class="title">{$lang['skin_title']} DataLife Engine</span>
      </div>
	  
      <div class="row box-section">
		{text}
      </div>
		<div class="text-right row box-section">
			<a href="?mod=main" class="status-info text-right">{$lang['lost_pass_3']}</a>
		</div>
    </div>
	<div class="text-center">Copyright 2004-2017 &copy; <a href="http://dle-news.ru" target="_blank">SoftNews Media Group</a>. All rights reserved.</div>

	 <!--MAIN area-->
  </div>
</div>
</div>

</body>
</html>
HTML;

$skin_footer = "";

if( intval( $_GET['douser'] ) AND $_GET['lostid'] ) {
	
	$douser = intval( $_GET['douser'] );
	$lostid = $_GET['lostid'];
	
	$row = $db->super_query( "SELECT lostid FROM " . USERPREFIX . "_lostdb WHERE lostname='{$douser}'" );
	
	if( $row['lostid'] != "" AND $lostid != "" AND $row['lostid'] == $lostid ) {

		$row = $db->super_query( "SELECT email, name FROM " . USERPREFIX . "_users WHERE user_id='{$douser}' LIMIT 0,1" );
			
		$username = $row['name'];
		$lostmail = $row['email'];
		
		if ($_GET['action'] == "ip") {

			$db->query( "UPDATE " . USERPREFIX . "_users SET allowed_ip = '' WHERE user_id='{$douser}'" );
			$db->query( "DELETE FROM " . USERPREFIX . "_lostdb WHERE lostname='{$douser}'" );

			$lang['lost_pass_12'] = str_replace("{username}", $username, $lang['lost_pass_12']);

			$skin_login = str_replace ("{text}", $lang['lost_pass_12'], $skin_login);
			echo $skin_login;
			die();


		} else {

			if(function_exists('openssl_random_pseudo_bytes')) {
			
				$stronghash = openssl_random_pseudo_bytes(15);
			
			} else $stronghash = md5(uniqid( mt_rand(), TRUE ));

			$salt = str_shuffle("abchefghjkmnpqrstuvwxyz0123456789".sha1($stronghash. microtime()));

			$new_pass = "";

			for($i = 0; $i < 11; $i ++) {
				$new_pass .= $salt{GetRandInt(72)};
			}
			
			$new_pass_hash = password_hash($new_pass, PASSWORD_DEFAULT);
			
			if( !$new_pass_hash ) {
				die("PHP extension Crypt must be loaded for password_hash to function");
			}
		
			$db->query( "UPDATE " . USERPREFIX . "_users SET password='" . $db->safesql($new_pass_hash) . "', allowed_ip = '' WHERE user_id='{$douser}'" );
			$db->query( "DELETE FROM " . USERPREFIX . "_lostdb WHERE lostname='$douser'" );

			include_once ENGINE_DIR . '/classes/mail.class.php';
			$mail = new dle_mail( $config );

			if ($config['auth_metod']) $username = $lostmail;

			$message = $lang['lost_pass_13']."\n\n{$lang['lost_pass_14']} {$username}\n{$lang['lost_pass_15']} {$new_pass}\n\n{$lang['lost_pass_16']}\n\n{$lang['lost_pass_19']} ".$config['http_home_url'];
			$mail->send( $lostmail, $lang['lost_pass_11'], $message );
			
			$skin_login = str_replace ("{text}", $lang['lost_pass_20']." <b>{$lostmail}</b>. ".$lang['lost_pass_16'], $skin_login);
			echo $skin_login;
			die();
			
		}	

	} else {

		if( $row['lostid'] ) {
			$db->query( "DELETE FROM " . USERPREFIX . "_lostdb WHERE lostname='{$douser}'" );	
		}
		
		$skin_login = str_replace ("{text}", $lang['lost_pass_18'], $skin_login);
		echo $skin_login;
		die();
			

	}
	

} elseif( isset( $_POST['submit_lost'] ) ) {

	if ($config['allow_recaptcha']) {

		require_once ENGINE_DIR . '/classes/recaptcha.php';

		if ( $_POST['g-recaptcha-response'] ) {
			
			$reCaptcha = new ReCaptcha($config['recaptcha_private_key']);
		
			$resp = $reCaptcha->verifyResponse(get_ip(), $_POST['g-recaptcha-response'] );
			
		    if ($resp != null && $resp->success) {

				$_POST['sec_code'] = 1;
				$_SESSION['sec_code_session'] = 1;

		    } else $_SESSION['sec_code_session'] = false;
	
		} else $_SESSION['sec_code_session'] = false;

	}

	if( preg_match( "/[\||\'|\<|\>|\[|\]|\"|\!|\?|\$|\/|\\\|\&\~\*\{\+]/", $_POST['lostname'] ) OR !trim($_POST['lostname'])) {
		
		$skin_login = str_replace ("{text}", $lang['lost_pass_4'], $skin_login);
		echo $skin_login;
		die();
	
	} elseif( $_POST['sec_code'] != $_SESSION['sec_code_session'] OR !$_SESSION['sec_code_session'] ) {
		
		$skin_login = str_replace ("{text}", $lang['lost_pass_5'], $skin_login);
		echo $skin_login;
		die();
	
	} else {
		
		$_SESSION['sec_code_session'] = false;
		$lostname = $db->safesql( $_POST['lostname'] );
		
		if( @count(explode("@", $lostname)) == 2 ) $search = "email = '" . $lostname . "'";
		else $search = "name = '" . $lostname . "'";
		
		$row = $db->super_query( "SELECT email, password, name, user_id, user_group FROM " . USERPREFIX . "_users WHERE {$search}" );
		
		if( $row['user_id'] AND $user_group[$row['user_group']]['allow_admin'] ) {
			
			include_once ENGINE_DIR . '/classes/mail.class.php';
			
			$lostmail = $row['email'];
			$userid = $row['user_id'];
			$lostname = $row['name'];
			$lostpass = $row['password'];
			
			$row = $db->super_query( "SELECT * FROM " . PREFIX . "_email where name='lost_mail' LIMIT 0,1" );
			$mail = new dle_mail( $config, $row['use_html'] );
			
			$row['template'] = stripslashes( $row['template'] );

			if(function_exists('openssl_random_pseudo_bytes')) {
			
				$stronghash = openssl_random_pseudo_bytes(15);
			
			} else $stronghash = md5(uniqid( mt_rand(), TRUE ));
		
			$salt = str_shuffle("abchefghjkmnpqrstuvwxyz0123456789".sha1($lostpass.$stronghash. microtime()) );
			$rand_lost = '';
			
			for($i = 0; $i < 15; $i ++) {
				$rand_lost .= $salt{GetRandInt(72)};
			}
			
			$lostid = sha1( md5( $lostname . $lostmail ) . microtime() . $rand_lost );

			if ( strlen($lostid) != 40 ) die ("US Secure Hash Algorithm 1 (SHA1) disabled by Hosting");
			
			if (strpos($config['http_home_url'], "//") === 0) $slink = "http:".$config['http_home_url'];
			elseif (strpos($config['http_home_url'], "/") === 0) $slink = "http://".$_SERVER['HTTP_HOST'].$config['http_home_url'];
			else $slink = $config['http_home_url'];
					
			$lostlink = $slink . $config['admin_path']."?mod=lostpassword&action=password&douser=" . $userid . "&lostid=" . $lostid;
			$iplink = $slink . $config['admin_path']."?mod=lostpassword&action=ip&douser=" . $userid . "&lostid=" . $lostid;

			if( $row['use_html'] ) {
				$link = $lang['lost_pass_8']."<br />".$lostlink."<br /><br />".$lang['lost_pass_9']."<br />".$iplink;
			} else {
				$link = $lang['lost_pass_8']."\n".$lostlink."\n\n".$lang['lost_pass_9']."\n".$iplink;
			}
			
			$db->query( "DELETE FROM " . USERPREFIX . "_lostdb WHERE lostname='$userid'" );
			
			$db->query( "INSERT INTO " . USERPREFIX . "_lostdb (lostname, lostid) values ('$userid', '$lostid')" );
			
			$row['template'] = str_replace( "{%username%}", $lostname, $row['template'] );
			$row['template'] = str_replace( "{%lostlink%}", $link, $row['template'] );
			$row['template'] = str_replace( "{%ip%}", get_ip(), $row['template'] );
			
			$mail->send( $lostmail, $lang['lost_pass_11'], $row['template'] );
			
			if( $mail->send_error ) $skin_login = str_replace ("{text}", $mail->smtp_msg, $skin_login);
			else $skin_login = str_replace ("{text}", $lang['lost_pass_10'], $skin_login);

			echo $skin_login;
			die();
		
		} elseif( !$row['user_id'] ) {

			$skin_login = str_replace ("{text}", $lang['lost_pass_6'], $skin_login);
			echo $skin_login;
			die();

		} else {

			$skin_login = str_replace ("{text}", $lang['lost_pass_7'], $skin_login);
			echo $skin_login;
			die();

		}
	}

} else {

	$text = "";

    $text .= "<div class=\"form-group\">
            <br /><input type=\"text\" name=\"lostname\" style=\"width:100%\" placeholder=\"{$lang['lost_pass_1']}\" required>
          </div>";	

	if ( $config['allow_recaptcha'] ) {

		$text .= "<div class=\"g-recaptcha\" data-sitekey=\"{$config['recaptcha_public_key']}\" data-theme=\"{$config['recaptcha_theme']}\"></div><script src='https://www.google.com/recaptcha/api.js?hl={$lang['wysiwyg_language']}' async defer></script>";

	} else {

		$text .= "<a onclick=\"reload(); return false;\" href=\"#\" title=\"{$lang['reload_code']}\"><span id=\"dle-captcha\"><img src=\"engine/modules/antibot/antibot.php\" alt=\"{$lang['reload_code']}\" border=\"0\" style=\"width: 130px;height: 46px;\" /></span></a>&nbsp;<input placeholder=\"Повторите код\" type=\"text\" name=\"sec_code\" id=\"sec_code\" style=\"height: 46px;\" required>";

	}

	$text .= "<br /><br /><div class=\"input-group addon-left\">
			<button type=\"submit\" class=\"btn btn-blue btn-block\">{$lang['lost_pass_2']} <i class=\"icon-signin\"></i></button>
          </div>";
	
	$text = "<form  method=\"post\" name=\"registration\" action=\"?mod=lostpassword\">\n" . $text . "
<input name=\"submit_lost\" type=\"hidden\" id=\"submit_lost\" value=\"submit_lost\" />
</form>";
	
	$skin_login = str_replace ("{text}", $text, $skin_login);
	
	echo $skin_login;
	
}
?>