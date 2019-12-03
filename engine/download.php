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
 Файл: download.php
-----------------------------------------------------
 Назначение: Скачивание файлов
=====================================================
*/
define ( 'DATALIFEENGINE', true );
define ( 'FILE_DIR', '../uploads/files/' );
define ( 'ROOT_DIR', '..' );
define ( 'ENGINE_DIR', ROOT_DIR . '/engine' );

@error_reporting ( E_ALL ^ E_WARNING ^ E_NOTICE );
@ini_set ( 'display_errors', true );
@ini_set ( 'html_errors', false );
@ini_set ( 'error_reporting', E_ALL ^ E_WARNING ^ E_NOTICE );

function reset_url($url) {
	$url = (string)$url;
	
	$value = str_replace ( "http://", "", $url );
	$value = str_replace ( "https://", "", $value );
	$value = str_replace ( "www.", "", $value );
	$value = explode ( "/", $value );
	$value = reset ( $value );
	return $value;
}

function clear_url_dir($var) {
	$var = (string)$var;
	
	$var = str_ireplace( ".php", "", $var );
	$var = str_ireplace( ".php", ".ppp", $var );
	$var = trim( strip_tags( $var ) );
	$var = str_replace( "\\", "/", $var );
	$var = preg_replace( "/[^a-z0-9\/\_\-]+/mi", "", $var );
	return $var;
	
}

require ENGINE_DIR . '/data/config.php';

date_default_timezone_set ( $config['date_adjust'] );

if ($config['http_home_url'] == "") {
	
	$config['http_home_url'] = explode ( "engine/download.php", $_SERVER['PHP_SELF'] );
	$config['http_home_url'] = reset ( $config['http_home_url'] );
	$config['http_home_url'] = "http://" . $_SERVER['HTTP_HOST'] . $config['http_home_url'];

}

require_once ENGINE_DIR . '/classes/mysql.php';
require_once ENGINE_DIR . '/data/dbconfig.php';
require_once ENGINE_DIR . '/modules/functions.php';

dle_session();
$_TIME = time();

//################# Определение групп пользователей
$user_group = get_vars ( "usergroup" );

if ( !$user_group ) {
	
	$user_group = array ();
	
	$db->query ( "SELECT * FROM " . USERPREFIX . "_usergroups ORDER BY id ASC" );
	
	while ( $row = $db->get_row () ) {
		
		$user_group[$row['id']] = array ();
		
		foreach ( $row as $key => $value ) {
			$user_group[$row['id']][$key] = $value;
		}
	
	}
	
	set_vars ( "usergroup", $user_group );
	$db->free ();

}

require_once ENGINE_DIR . '/modules/sitelogin.php';
require_once ENGINE_DIR . '/classes/download.class.php';

if ( !$is_logged ) {
	$member_id['user_group'] = 5;
}

$id = intval( $_REQUEST['id'] );
$viewonline = intval( $_REQUEST['viewonline'] );
$perm = true;
$onlineview_ext = array('doc', 'docx','odt','pdf','xls','xlsx');

if ($_REQUEST['area'] == "static") {
	
	$row = $db->super_query ( "SELECT name, onserver FROM " . PREFIX . "_static_files WHERE id ='{$id}'" );
	
} else {
	
	$row = $db->super_query ( "SELECT news_id, name, onserver FROM " . PREFIX . "_files WHERE id ='{$id}'" );

	if ( $row['news_id'] AND !$viewonline ) {
		
		$row_news = $db->super_query ( "SELECT id, autor, date, category, approve, access FROM " . PREFIX . "_post LEFT JOIN " . PREFIX . "_post_extras ON (" . PREFIX . "_post.id=" . PREFIX . "_post_extras.news_id) WHERE id ='{$row['news_id']}'" );
		
		if( $row_news['id'] ) {
			$options = news_permission( $row_news['access'] );
			if( $options[$member_id['user_group']] AND $options[$member_id['user_group']] != 3 ) $perm = true;
			if( $options[$member_id['user_group']] == 3 ) $perm = false;
	
			if ($config['no_date'] AND !$config['news_future'] AND !$user_group[$member_id['user_group']]['allow_all_edit']) {
		
				if( strtotime($row_news['date']) > $_TIME ) {
					$perm = false;		
				}
		
			}
			
			$cat_list = explode( ',', $row_news['category'] );
			
			if( count($cat_list) ) {
				
				$allow_list = explode( ',', $user_group[$member_id['user_group']]['allow_cats'] );
				$not_allow_cats = explode ( ',', $user_group[$member_id['user_group']]['not_allow_cats'] );
		
				foreach ( $cat_list as $element ) {
						
					if( $allow_list[0] != "all" AND !in_array( $element, $allow_list ) ) $perm = false;
					
					if( $not_allow_cats[0] != "" AND in_array( $element, $not_allow_cats ) ) $perm = false;
					
				}
				
			}
			
			if( !$row_news['approve'] AND $member_id['name'] != $row_news['autor'] AND !$user_group[$member_id['user_group']]['allow_all_edit'] ) $perm = false;
			
		} else $perm = false;

	} elseif ( !$row['news_id'] ) {
		$perm = false;
	}

}

if ( !$row['name'] ) {
	die ( "Access denied" );
}

if ( !$perm ) {
	die ( "You don't have access to download this file" );
}

$url = @parse_url ( $row['onserver'] );

$file_path = dirname (clear_url_dir($url['path']));
$file_name = pathinfo($url['path']);
$file_ext  = totranslit($file_name['extension'], true, false);
$file_name = totranslit($file_name['basename'], false);

if ($file_path AND $file_path != ".") $file_name = $file_path."/".$file_name;

if( stripos ( $file_name, ".php" ) !== false ) {
	die ( "Access denied" );
}

if ($viewonline == 1 AND in_array($file_ext, $onlineview_ext) ) {
	
    $config['files_antileech'] = false;
	$user_group[$member_id['user_group']]['files_max_speed'] = 0;
	$user_group[$member_id['user_group']]['allow_files'] = true;
	
}

if ( !$user_group[$member_id['user_group']]['allow_files'] ) {
	die ( "Access denied" );
}

if ($config['files_antileech']) {
	
	$_SERVER['HTTP_REFERER'] = reset_url ( $_SERVER['HTTP_REFERER'] );
	$_SERVER['HTTP_HOST'] = reset_url ( $_SERVER['HTTP_HOST'] );

	if ($_SERVER['HTTP_HOST'] != $_SERVER['HTTP_REFERER']) {
		@header ( 'Location: ' . $config['http_home_url'] );
		die ( "Access denied!!!<br /><br />Please visit <a href=\"{$config['http_home_url']}\">{$config['http_home_url']}</a>" );
	}

}

$file = new download ( FILE_DIR . $file_name, $row['name'], $config['files_force'], intval($user_group[$member_id['user_group']]['files_max_speed']) );

if ($_REQUEST['area'] == "static") {
	
	if ($config['files_count'] AND !$file->range) {
		$db->query ( "UPDATE " . PREFIX . "_static_files SET dcount=dcount+1 WHERE id ='$id'" );
	}

} else {
	
	if ($config['files_count'] AND !$file->range) {
		$db->query ( "UPDATE " . PREFIX . "_files SET dcount=dcount+1 WHERE id ='$id'" );
	}

}

$db->close ();
session_write_close();

$file->download_file();
?>