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
 Файл: twofactor.php
-----------------------------------------------------
 Назначение: Двухфакторная авторизация
=====================================================
*/

@error_reporting ( E_ALL ^ E_WARNING ^ E_NOTICE );
@ini_set ( 'display_errors', true );
@ini_set ( 'html_errors', false );
@ini_set ( 'error_reporting', E_ALL ^ E_WARNING ^ E_NOTICE );

define( 'DATALIFEENGINE', true );
define( 'ROOT_DIR', substr( dirname(  __FILE__ ), 0, -12 ) );
define( 'ENGINE_DIR', ROOT_DIR . '/engine' );

include ENGINE_DIR . '/data/config.php';

date_default_timezone_set ( $config['date_adjust'] );

require_once ENGINE_DIR . '/classes/mysql.php';
require_once ENGINE_DIR . '/data/dbconfig.php';
require_once ENGINE_DIR . '/modules/functions.php';

dle_session();

$_REQUEST['skin'] = totranslit($_REQUEST['skin'], false, false);

if( $_REQUEST['skin'] ) {
	if( @is_dir( ROOT_DIR . '/templates/' . $_REQUEST['skin'] ) ) {
		$config['skin'] = $_REQUEST['skin'];
	} else {
		echo "{\"error\":true, \"errorinfo\":\"Template not found\"}";
		die();
	}
}

if( $config["lang_" . $config['skin']] ) {
	if ( file_exists( ROOT_DIR . '/language/' . $config["lang_" . $config['skin']] . '/website.lng' ) ) {	
		include_once ROOT_DIR . '/language/' . $config["lang_" . $config['skin']] . '/website.lng';
	} else {
		echo "{\"error\":true, \"errorinfo\":\"Language file not found\"}";
		die();
	}
} else {
	
	include_once ROOT_DIR . '/language/' . $config['langs'] . '/website.lng';

}
$config['charset'] = ($lang['charset'] != '') ? $lang['charset'] : $config['charset'];

//################# Определение групп пользователей
$user_group = get_vars( "usergroup" );

if( ! $user_group ) {
	$user_group = array ();
	
	$db->query( "SELECT * FROM " . USERPREFIX . "_usergroups ORDER BY id ASC" );
	
	while ( $row = $db->get_row() ) {
		
		$user_group[$row['id']] = array ();
		
		foreach ( $row as $key => $value ) {
			$user_group[$row['id']][$key] = stripslashes($value);
		}
	
	}
	set_vars( "usergroup", $user_group );
	$db->free();
}

@header( "Content-type: text/html; charset=" . $config['charset'] );

if( !isset($_SESSION['twofactor_id']) OR !isset($_SESSION['twofactor_auth'])) {
	echo "{\"error\":true, \"errorinfo\":\" {$lang['twofactor_err_1']}\"}";
	die();
}

$_POST['pin'] = (string)$_POST['pin'];

if(!$_POST['pin']) {
	echo "{\"error\":true, \"errorinfo\":\" {$lang['twofactor_err_2']}\"}";
	die();
}

$user_id = intval($_SESSION['twofactor_id']);

if( !$user_id OR $user_id < 1 ) {
	echo "{\"error\":true, \"errorinfo\":\" {$lang['twofactor_err_1']}\"}";
	die();
}

$_IP = get_ip();
$_TIME = time ();
$thisdate = $_TIME-900;

$db->query( "DELETE FROM " . USERPREFIX . "_twofactor WHERE date < '$thisdate'" );

$member_id = $db->super_query( "SELECT * FROM " . USERPREFIX . "_users WHERE user_id='{$user_id}'" );

if( $member_id['user_id'] AND $member_id['password'] AND $_SESSION['twofactor_auth'] AND md5($member_id['password']) == $_SESSION['twofactor_auth'] ) {

	$row = $db->super_query( "SELECT * FROM " . USERPREFIX . "_twofactor WHERE user_id='{$user_id}'" );
	
	if(!$row['id']) {
		
		$_SESSION['twofactor_id'] = 0;
		$_SESSION['twofactor_auth'] = "";
		
		unset($_SESSION['twofactor_id']);
		unset($_SESSION['twofactor_auth']);
		echo "{\"error\":true, \"errorinfo\":\" {$lang['twofactor_err_4']}\"}";
		die();
	}
	
	if( $row['pin'] !== $_POST['pin'] ) {
		
		$db->query( "UPDATE " . USERPREFIX . "_twofactor SET attempt=attempt+1 WHERE id='{$row['id']}'" );
		
		if ($user_group[$member_id['user_group']]['allow_admin']) {

			$db->query( "INSERT INTO " . USERPREFIX . "_admin_logs (name, date, ip, action, extras) values ('".$db->safesql($member_id['name'])."', '{$_TIME}', '{$_IP}', '99', '')" );	
			
		}
			
		$attempt = 2-$row['attempt'];
		
		if ($attempt < 1) {
			
			$db->query( "DELETE FROM " . USERPREFIX . "_twofactor WHERE id='{$row['id']}'" );
			
			$_SESSION['twofactor_id'] = 0;
			$_SESSION['twofactor_auth'] = "";
			unset($_SESSION['twofactor_id']);
			unset($_SESSION['twofactor_auth']);
			echo "{\"success\":true}";
			die();
		}
		
		$lang['twofactor_err_5'] = str_replace("{attempt}", $attempt, $lang['twofactor_err_5']);
		echo "{\"error\":true, \"errorinfo\":\" {$lang['twofactor_err_5']}\"}";
		die();
	}

	session_regenerate_id();

	$db->query( "DELETE FROM " . USERPREFIX . "_twofactor WHERE id='{$row['id']}'" );

	if ($user_group[$member_id['user_group']]['allow_admin']) {

		$db->query( "INSERT INTO " . USERPREFIX . "_admin_logs (name, date, ip, action, extras) values ('".$db->safesql($member_id['name'])."', '{$_TIME}', '{$_IP}', '100', '')" );	
			
	}
		
	if ( $_SESSION['no_save_cookie'] ) {
	
		set_cookie( "dle_user_id", "", 0 );
		set_cookie( "dle_password", "", 0 );
	
	} else {			
	
		set_cookie( "dle_user_id", $member_id['user_id'], 365 );
		set_cookie( "dle_password", md5($member_id['password']), 365 );
	
	}

	$_SESSION['dle_user_id'] = $member_id['user_id'];
	$_SESSION['dle_password'] = md5($member_id['password']);
	$_SESSION['member_lasttime'] = $member_id['lastdate'];
	
	$_SESSION['twofactor_id'] = 0;
	$_SESSION['no_save_cookie'] = 0;
	$_SESSION['twofactor_auth'] = "";
	unset($_SESSION['twofactor_id']);
	unset($_SESSION['twofactor_auth']);
	unset($_SESSION['no_save_cookie']);
	echo "{\"success\":true}";
	die();
	
} else {
	
	$_SESSION['twofactor_id'] = 0;
	$_SESSION['twofactor_auth'] = "";
	
	unset($_SESSION['twofactor_id']);
	unset($_SESSION['twofactor_auth']);
	echo "{\"error\":true, \"errorinfo\":\" {$lang['twofactor_err_3']}\"}";
	die();
	
}


?>