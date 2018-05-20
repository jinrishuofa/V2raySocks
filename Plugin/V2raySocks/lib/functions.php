<?php

function V2raySocks_multi_language_support(){
	global $_VLANG;
	$dir = realpath(dirname(dirname(__FILE__)) . "/lang");
	if(isset($GLOBALS['CONFIG']['Language']) ){
		$language = $GLOBALS['CONFIG']['Language'];
	}
	if(isset($_SESSION['adminid'])){
		$language = _getUserLanguage('tbladmins', 'adminid');
	}elseif( $_SESSION['uid'] ){
		$language = _getUserLanguage('tblclients', 'uid');
	}
	if(!$language){
		$language = Default_Lang;
	}
	$file = $dir.'/'.$language.".php";
	if(file_exists($file)){
		include($file);
	}else{
        $file = $dir.'/'.Default_Lang.'.php';
        include($file);
    }
	return $file;
}

function V2raySocks_getUserLanguage($table, $field){
    try{
        $sqlresult = select_query($table, 'language', array( 'id' => mysql_real_escape_string($_SESSION[$field])));
        if($data = mysql_fetch_row($sqlresult)){
            return reset($data);
        }
        return false;
    }catch(Exception $e){
        logModuleCall('V2raySocks', 'V2raySocks_MultiLanguageSupport', $field, $e->getMessage(), $e->getTraceAsString());
        return false;
    }
}

function V2raySocks_get_lang($var){
	global $_VLANG;
	return isset($_VLANG[$var]) ? $_VLANG[$var] : $var . '(Missing Language)' ;
}
