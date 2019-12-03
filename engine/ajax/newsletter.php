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
 Файл: newsletter.php
-----------------------------------------------------
 Назначение: AJAX для рассылки сообщений
=====================================================
*/

@error_reporting ( E_ALL ^ E_WARNING ^ E_NOTICE );
@ini_set ( 'display_errors', true );
@ini_set ( 'html_errors', false );
@ini_set ( 'error_reporting', E_ALL ^ E_WARNING ^ E_NOTICE );

define('DATALIFEENGINE', true);
define( 'ROOT_DIR', substr( dirname(  __FILE__ ), 0, -12 ) );
define( 'ENGINE_DIR', ROOT_DIR . '/engine' );

include ENGINE_DIR.'/data/config.php';

date_default_timezone_set ( $config['date_adjust'] );

if ($config['http_home_url'] == "") {

	$config['http_home_url'] = explode("engine/ajax/newsletter.php", $_SERVER['PHP_SELF']);
	$config['http_home_url'] = reset($config['http_home_url']);
	$config['http_home_url'] = "http://".$_SERVER['HTTP_HOST'].$config['http_home_url'];

}

require_once ENGINE_DIR.'/classes/mysql.php';
require_once ENGINE_DIR.'/data/dbconfig.php';
require_once ENGINE_DIR.'/modules/functions.php';

dle_session();

require_once ROOT_DIR.'/language/'.$config['langs'].'/website.lng';

$config['charset'] = ($lang['charset'] != '') ? $lang['charset'] : $config['charset'];

$user_group = get_vars ( "usergroup" );

if (! $user_group) {
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

require_once ENGINE_DIR.'/modules/sitelogin.php';

if (!$is_logged OR !$user_group[$member_id['user_group']]['admin_newsletter']) die ("error");

include_once ENGINE_DIR . '/classes/parse.class.php';
$parse = new ParseFilter();
$parse->allow_code = false;

$startfrom = intval($_POST['startfrom']);

if ($_POST['empfanger']) {

	$empfanger = array(); 

	$temp = explode(",", $_POST['empfanger']); 

	foreach ( $temp as $value ) {
		$empfanger[] = intval($value);
	}

	$empfanger = implode( "','", $empfanger );

	$empfanger = "user_group IN ('" . $empfanger . "')";

} else $empfanger = false;

$type = $_POST['type'];
$a_mail = intval($_POST['a_mail']);
$limit = intval($_POST['limit']);
$fromregdate = intval($_POST['fromregdate']);
$toregdate = intval($_POST['toregdate']);
$fromentdate = intval($_POST['fromentdate']);
$toentdate = intval($_POST['toentdate']);
$step = 0;

$title = convert_unicode($_POST['title'], $config['charset']);
$message = convert_unicode($_POST['message'], $config['charset']);

$title = stripslashes($parse->process(strip_tags($title)));
$message = stripslashes($parse->process($message));

if (!$title OR !$message OR !$limit) die ("error");

$where = array();

$where[] = "banned != 'yes'";

if ($empfanger) $where[] = $empfanger;
if ($a_mail AND $type == "email") $where[] = "allow_mail = '1'";

if( $fromregdate ) {
	$where[] = "reg_date>='" . $fromregdate . "'";
}

if( $toregdate ) {
	$where[] = "reg_date<='" . $toregdate . "'";
}

if( $fromentdate ) {
	$where[] = "lastdate>='" . $fromentdate . "'";
}

if( $toentdate ) {
	$where[] = "lastdate<='" . $toentdate . "'";
}

$where = " WHERE ".implode (" AND ", $where);

if ($type == "pm") {

	$time = time();
	$title = $db->safesql($title);
	$message = $db->safesql($message);

	$result = $db->query("SELECT user_id, name, fullname FROM " . USERPREFIX . "_users".$where." LIMIT ".$startfrom.",".$limit);



	while($row = $db->get_row($result)) {
	
		if ( $row['fullname'] )
			$message_send = str_replace("{%user%}", $row['fullname'], $message);
		else
			$message_send = str_replace("{%user%}", $row['name'], $message);
	
		$db->query("INSERT INTO " . USERPREFIX . "_pm (subj, text, user, user_from, date, pm_read, folder) values ('$title', '$message_send', '$row[user_id]', '$member_id[name]', '$time', 'no', 'inbox')");
		$db->query("UPDATE " . USERPREFIX . "_users set pm_all=pm_all+1, pm_unread=pm_unread+1  where user_id='$row[user_id]'");
	    $step++;
	}

	$db->free($result);

} elseif ($type == "email") {

	$row = $db->super_query( "SELECT template FROM " . PREFIX . "_email WHERE name='newsletter' LIMIT 0,1" );

	$row['template'] = str_replace( "{%charset%}", $config['charset'], $row['template'] );
	$row['template'] = str_replace( "{%title%}", $title, $row['template'] );
	$row['template'] = str_replace( "{%content%}", $message, $row['template'] );
			
	$title = str_replace( '&amp;', '&', $title );
	$message = stripslashes( $row['template'] );


	include_once ENGINE_DIR.'/classes/mail.class.php';
	$mail = new dle_mail ($config, true);
	$mail->keepalive = true;

	if ($config['mail_bcc']) {
		$limit = $limit * 6;
		$i = 0;
		$t = 0;
		$h_mail = array();
		$bcc = array();

		$db->query("SELECT email FROM " . USERPREFIX . "_users".$where." ORDER BY user_id DESC LIMIT ".$startfrom.",".$limit);

		$db->close();

		while($row = $db->get_row()) {
			
			if ($i == 0) { $h_mail[$t] = $row['email'];}
			else {$bcc[$t][] = $row['email'];}

			$i++;

			if ($i == 6) {
				$i=0;
				$t++;
			}

			$step++;
          }

		$db->free();

		foreach ($h_mail as $key => $email) {
			$mail->bcc = $bcc[$key];
			$message_send = str_replace("{%user%}", $lang['nl_info_2'], $message);
			
			$message_send = str_replace("{%unsubscribe%}", "--", $message_send);

			$mail->send ($email, $title, $message_send);
		}

	} else {

		$db->query("SELECT email, password, name, user_id, fullname FROM " . USERPREFIX . "_users".$where." ORDER BY user_id DESC LIMIT ".$startfrom.",".$limit);

		$db->close();
		
		if (strpos($config['http_home_url'], "//") === 0) $slink = "http:".$config['http_home_url'];
		elseif (strpos($config['http_home_url'], "/") === 0) $slink = "http://".$_SERVER['HTTP_HOST'].$config['http_home_url'];
		else $slink = $config['http_home_url'];
			
		  while( $row = $db->get_row() ) {

			if ( $row['fullname'] ) {
				$message_send = str_replace("{%user%}", $row['fullname'], $message);
			} else {
				$message_send = str_replace("{%user%}", $row['name'], $message);
			}

			$hash = md5( SECURE_AUTH_KEY . $_SERVER['HTTP_HOST'] . $row['user_id'] . sha1( substr($row['password'], 0, 6) ) . $config['key'] );
			
		    $message_send = str_replace("{%unsubscribe%}", $slink . "index.php?do=newsletterunsubscribe&user_id=" . $row['user_id'] . "&hash=" . $hash, $message_send);
		   
		    $mail->send ($row['email'], $title, $message_send);
	
		    $step++;
		  }

		$db->free();
	}

} else die ("error");

$count = $startfrom + $step;

if($step < $limit) $complete = 1; else $complete = 0;

$buffer = "{\"status\": \"ok\",\"count\": {$count},\"complete\": {$complete}}";

@header("Content-type: text/html; charset=".$config['charset']);
echo $buffer;
?>