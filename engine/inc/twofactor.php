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
 Назначение: Восстановление забытого пароля
=====================================================
*/

if( ! defined( 'DATALIFEENGINE' ) ) {
	die( "Hacking attempt!" );
}

if( $is_loged_in ) {
	msg( "error", $lang['index_denied'], $lang['index_denied'] );
}

if( !$_SESSION['twofactor_auth'] ) {
	msg( "error", $lang['index_denied'], $lang['index_denied'] );
}

$skin_login = <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="{$config['charset']}">
  <meta name="viewport" content="width=device-width, maximum-scale=1, initial-scale=1, user-scalable=0">
  <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
  <title>DataLife Engine - {$lang['skin_title']}</title>
  <link href="engine/skins/stylesheets/application.css" rel="stylesheet" type="text/css" />
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
		{$lang['twofactor_alert']}
		<br /><br /><input type="text" name="pin" id="pin" style="width:100%" placeholder="{$lang['twofactor_title']}">
		<div id="twofactor_response" style="color:red"></div>
		<br /><br /><div class="input-group addon-left">
			<button id="send" type="submit" class="btn btn-blue btn-block">{$lang['login_button']} <i class="icon-signin"></i></button>
          </div>
      </div>
    </div>
	
<script language="javascript" type="text/javascript">  
<!--
$(function(){

	$('#send').click(function(){
	
		if ( $("#pin").val().length < 1) {
			 $("#pin").addClass('ui-state-error');
		} else {
			var pin = $("#pin").val();
			$.post("engine/ajax/twofactor.php", { pin: pin }, function(data){
			
				if ( data.success ) {
				
					window.location = window.location;
					
				} else if (data.error) {
					
					$("#twofactor_response").html(data.errorinfo);
					
				}
				
			}, "json");
		
		}
		
		return false;
	
	});
});
//-->
</script>
	<div class="text-center">Copyright 2004-2017 &copy; <a href="http://dle-news.ru" target="_blank">SoftNews Media Group</a>. All rights reserved.</div>
  </div>
</div>
</div>

</body>
</html>
HTML;
	
	echo $skin_login;

?>