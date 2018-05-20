<?php
/**
 * V2raySocks whmcs module
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once 'lib/functions.php';
require_once 'config.php';

V2raySocks_multi_language_support();

function V2raySocks_initialize(array $params , $date = false){
    $query['CREATE_ACCOUNT'] = 'INSERT INTO `user`(`uuid`,`u`,`d`,`transfer_enable`,`created_at`,`updated_at`,`need_reset`,`sid`) VALUES (:uuid,0,0,:transfer_enable,UNIX_TIMESTAMP(),0,:need_reset,:sid)';
    $query['ALREADY_EXISTS'] = 'SELECT `uuid` FROM `user` WHERE `sid` = :sid';
    $query['ENABLE'] = 'UPDATE `user` SET `enable` = :enable WHERE `sid` = :sid';
    $query['USERINFO'] = 'SELECT `id`,`uuid`,`t`,`u`,`d`,`transfer_enable`,`enable`,`created_at`,`updated_at`,`need_reset`,`sid` FROM `user` WHERE `sid` = :sid';
    $query['DELETE_ACCOUNT'] = 'DELETE FROM `user` WHERE `sid` = :sid';
    $query['CHANGE_PACKAGE'] = 'UPDATE `user` SET `transfer_enable` = :transfer_enable WHERE `sid` = :sid';
    $query['RESETUSERCHART'] = 'delete from `user_usage` where `sid` = :sid';
    $query['UPDATEBALANCE'] = 'UPDATE `user` SET `transfer_enable` = `transfer_enable` + :transfer WHERE `sid` = :sid';
    if($date){
        $query['RESET'] = 'UPDATE `user` SET `u`=0,`d`=0,`updated_at`='.$date.'  WHERE `sid` = :sid';
        $query['CHARTINFO'] = 'SELECT * FROM `user_usage` WHERE `sid` = :sid AND `date` >= '.$date.' ORDER BY `date` DESC';
    }else{
        $query['RESET'] = 'UPDATE `user` SET `u`=0,`d`=0 WHERE `sid` = :sid';
        $query['CHARTINFO'] = 'SELECT * FROM `user_usage` WHERE `sid` = :sid ORDER BY `date` DESC';
    }
    return $query;
}

function V2raySocks_MetaData()
{
    return array(
        'DisplayName' => 'V2raySocks',
        'APIVersion' => '1.0',
        'RequiresServer' => true
    );
}

function V2raySocks_ConfigOptions(){
    return array(
    V2raySocks_get_lang('database') => array('Type' => 'text', 'Size' => '25'),
    V2raySocks_get_lang('resetbandwidth') => array(
        'Type'        => 'dropdown',
        'Options'     => array('3'=> V2raySocks_get_lang('end_of_month'), '2'=> V2raySocks_get_lang('start_of_month'), '1' => V2raySocks_get_lang('by_duedate_day'), '0' => V2raySocks_get_lang('neednot_reset')),
        'Description' => V2raySocks_get_lang('resetbandwidth_description')
        ),
    V2raySocks_get_lang('bandwidth') => array('Type' => 'text', 'Size' => '25', 'Description' => V2raySocks_get_lang('bandwidth_description')),
    V2raySocks_get_lang('routelist') => array('Type' => 'textarea', 'Rows' => '3', 'Cols' => '50', 'Description' => V2raySocks_get_lang('routelist_description')),
    V2raySocks_get_lang('announcements') => array('Type' => 'textarea', 'Rows' => '3', 'Cols' => '50', 'Description' => V2raySocks_get_lang('announcements_description'))
    );
}

function V2raySocks_TestConnection(array $params){
    try {
        $dbhost = $params['serverip'];
        $dbuser = $params['serverusername'];
        $dbpass = $params['serverpassword'];
        $db = new PDO('mysql:host=' . $dbhost, $dbuser, $dbpass);
        $success = true;
        $errorMsg = '';
    }
    catch (Exception $e) {
        logModuleCall('V2raySocks', 'V2raySocks_TestConnection', $params, $e->getMessage(), $e->getTraceAsString());
        $success = false;
        $errorMsg = $e->getMessage();
    }
    return array('success' => $success, 'error' => $errorMsg);
}

function V2raySocks_CreateAccount(array $params){
    $query = V2raySocks_initialize($params);
    try {
        $dbhost = $params['serverip'];
        $dbname = $params['configoption1'];
        $dbuser = $params['serverusername'];
        $dbpass = $params['serverpassword'];
        $db = new PDO('mysql:host=' . $dbhost . ';dbname=' . $dbname, $dbuser, $dbpass);
        $already = $db->prepare($query['ALREADY_EXISTS']);
        $already->bindValue(':sid', $params['serviceid']);
        $already->execute();
        if ($already->fetchColumn()) {
            return V2raySocks_get_lang('User_already_exists');
        }
        $bandwidth = (!empty($params['configoption3']) ? V2raySocks_Convert($params['configoption3'], 'mb', 'bytes') : (!empty($params['configoptions']['traffic']) ? V2raySocks_Convert($params['configoptions']['traffic'], 'gb', 'bytes') : '1099511627776'));

        $create = $db->prepare($query['CREATE_ACCOUNT']);
        $create->bindValue(':uuid', V2raySocks_GenerateUuid());
        $create->bindValue(':transfer_enable', $bandwidth);
        $create->bindValue(':need_reset', $params['configoption2']);
        $create->bindValue(':sid', $params['serviceid']);
        $create = $create->execute();
        
        if ($create) {
            return 'success';
        }else {
            $error = $db->errorInfo();
            return $error;
        }
    }
    catch (Exception $e) {
        logModuleCall('V2raySocks', 'V2raySocks_CreateAccount', $params, $e->getMessage(), $e->getTraceAsString());
        return V2raySocks_get_lang('Model_error').$e->getMessage();
    }
}

function V2raySocks_SuspendAccount(array $params){
    $query = V2raySocks_initialize($params);
    try {
        $dbhost = $params['serverip'];
        $dbname = $params['configoption1'];
        $dbuser = $params['serverusername'];
        $dbpass = $params['serverpassword'];
        $db = new PDO('mysql:host=' . $dbhost . ';dbname=' . $dbname, $dbuser, $dbpass);
        $enable = $db->prepare($query['ENABLE']);
        $enable->bindValue(':enable', '0');
        $enable->bindValue(':sid', $params['serviceid']);
        
        $todo = $enable->execute();
        if (!$todo) {
            $error = $db->errorInfo();
            return $error;
        }
        return 'success';
    }
    catch (Exception $e) {
        logModuleCall('V2raySocks', 'V2raySocks_SuspendAccount', $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

function V2raySocks_UnsuspendAccount(array $params){
    $query = V2raySocks_initialize($params,time());
    try {
        $dbhost = $params['serverip'];
        $dbname = $params['configoption1'];
        $dbuser = $params['serverusername'];
        $dbpass = $params['serverpassword'];
        $db = new PDO('mysql:host=' . $dbhost . ';dbname=' . $dbname, $dbuser, $dbpass);
        $enable = $db->prepare($query['ENABLE']);
        $enable->bindValue(':enable', '1');
        $enable->bindValue(':sid', $params['serviceid']);
    
        $todo = $enable->execute();
        if (!$todo) {
            $error = $db->errorInfo();
            return $error;
        }
        $enable = $db->prepare($query['RESET']);
        $enable->bindValue(':sid', $params['serviceid']);
        $todo = $enable->execute();
        $resetchart = $db->prepare($query['RESETUSERCHART']);
        $resetchart->bindValue(':sid', $params['serviceid']);
        $resetchart->execute();
        if (!$todo) {
            $error = $db->errorInfo();
            return $error;
        }
        return 'success';
    }
    catch (Exception $e) {
        logModuleCall('V2raySocks', 'V2raySocks_UnsuspendAccount', $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

function V2raySocks_TerminateAccount(array $params){
    $query = V2raySocks_initialize($params);
    try {
        $dbhost = $params['serverip'];
        $dbname = $params['configoption1'];
        $dbuser = $params['serverusername'];
        $dbpass = $params['serverpassword'];
        $db = new PDO('mysql:host=' . $dbhost . ';dbname=' . $dbname, $dbuser, $dbpass);
        $enable = $db->prepare($query['DELETE_ACCOUNT']);
        $enable->bindValue(':sid', $params['serviceid']);
        
        $todo = $enable->execute();
        if (!$todo) {
            $error = $db->errorInfo();
            return $error;
        }
        return 'success';
    }
    catch (Exception $e) {
        logModuleCall('V2raySocks', 'V2raySocks_TerminateAccount', $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

function V2raySocks_ChangePackage(array $params){
    $query = V2raySocks_initialize($params);
    try {
        $dbhost = $params['serverip'];
        $dbname = $params['configoption1'];
        $dbuser = $params['serverusername'];
        $dbpass = $params['serverpassword'];
        $db = new PDO('mysql:host=' . $dbhost . ';dbname=' . $dbname, $dbuser, $dbpass);
        $bandwidth = (!empty($params['configoption3']) ? convert($params['configoption3'], 'mb', 'bytes') : (!empty($params['configoptions']['traffic']) ? convert($params['configoptions']['traffic'], 'gb', 'bytes') : '1099511627776'));
        $enable = $db->prepare($query['CHANGE_PACKAGE']);
        $enable->bindValue(':transfer_enable', $bandwidth);
        $enable->bindValue(':sid', $params['serviceid']);
        $todo = $enable->execute();
        if (!$todo) {
            $error = $db->errorInfo();
            return $error;
        }
        return 'success';
    }
    catch (Exception $e) {
        logModuleCall('V2raySocks', 'V2raySocks_ChangePackage', $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

function V2raySocks_AdminCustomButtonArray(){
    return array(V2raySocks_get_lang('resetbandwidth') => 'ResetBandwidth');
}

function V2raySocks_ResetBandwidth(array $params){
    $query = V2raySocks_initialize($params,time());
    try {
        $dbhost = ($params['serverip']);
        $dbname = ($params['configoption1']);
        $dbuser = ($params['serverusername']);
        $dbpass = ($params['serverpassword']);
        $db = new PDO('mysql:host=' . $dbhost . ';dbname=' . $dbname, $dbuser, $dbpass);
        $enable = $db->prepare($query['RESET']);
        $enable->bindValue(':sid', $params['serviceid']);
        $todo = $enable->execute();
        $resetchart = $db->prepare($query['RESETUSERCHART']);
        $resetchart->bindValue(':sid', $params['serviceid']);
        $resetchart->execute();
        if (!$todo) {
            $error = $db->errorInfo();
            return $error;
        }
        return 'success';
    }
    catch (Exception $e) {
        logModuleCall('V2raySocks', 'V2raySocks_ResetBandwidth', $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getMessage();
    }
}

function V2raySocks_ClientArea($params) {
    if($params['status'] == 'Active'){
        require_once 'lib/Mobile_Detect.php';
        $detect = new Mobile_Detect;
        if($detect->isMobile()){
            $date = time() - 60*60*24;
            $datadays = 1;
        }else{
            $date = time() - 60*60*24*3;
            $datadays = 3;
        }
        $query = V2raySocks_initialize($params,$date);
        try {
            $dbhost = $params['serverip'];
            $dbname = $params['configoption1'];
            $dbuser = $params['serverusername'];
            $dbpass = $params['serverpassword'];
            $db = new PDO('mysql:host=' . $dbhost . ';dbname=' . $dbname, $dbuser, $dbpass);
            $usage = $db->prepare($query['USERINFO']);
            $usage->bindValue(':sid', $params['serviceid']);
            $usage->execute();
            $usage = $usage->fetch();
            
            $chartinfo = $db->prepare($query['CHARTINFO']);
            $chartinfo->bindValue(':sid', $params['serviceid']);
            $chartinfo->execute();
            if($chartinfo){
                $exa = array();
                foreach($chartinfo as $chart){
                    $exa[] = $chart;
                }
                $label = "";
                $total = "";
                $upload = "";
                $download = "";
                $chartinfo = array_reverse($exa,true);
                foreach($chartinfo as $chart){
                    $label .= "'',";
                    //$label .= "'".date('m/d  H:i',$chart['date'])."',";
                    $upload .= number_format(V2raySocks_convert($chart['upload'], 'bytes', 'mb'), 2, '.', '').",";
                    $download .= number_format(V2raySocks_convert($chart['download'], 'bytes', 'mb'), 2, '.', '').",";
                    $total .= number_format(V2raySocks_convert($chart['upload']+$chart['download'], 'bytes', 'mb'), 2, '.', '').",";
                }
                $label = substr($label,0,strlen($label)-1);
                $total = substr($total,0,strlen($total)-1);
                $upload = substr($upload,0,strlen($upload)-1);
                $download = substr($download,0,strlen($download)-1);
                $script = V2raySocks_make_script("totalc",$label,$total);
                $script .= V2raySocks_make_script("uploadc",$label,$upload);
                $script .= V2raySocks_make_script("downloadc",$label,$download);
            }

            $nodes = $params['configoption4'];
            $z = 0;
            $results = array();
            
            $noder = explode("\n",$nodes);
            $x = 0;
            foreach($noder as $nodee){
                $nodee = explode('|', $nodee);
                $str = base64_encode($nodee[3] . ":" . $usage['uuid'] . "@" . $nodee[1] . ":" . $nodee[2]);
                $str = "vmess://" . $str . "?remarks=" . $nodee[0] . "&obfs=none";
                $nodee[4] = $str;
                $results[$x] = $nodee;
                $x++;
            }
            $infos = $params['configoption5'] ? $params['configoption5'] : false;
            $user = array('uuid' => $usage['uuid'],
                          'u' => $usage['u'], 
                          'd' => $usage['d'], 
                          't' => $usage['t'], 
                          'sum' => $usage['u'] + $usage['d'], 
                          'transfer_enable' => $usage['transfer_enable'], 
                          'created_at' => $usage['created_at'], 
                          'updated_at' => $usage['updated_at'],
                          'tr_MB_GB' => V2raySocks_MBGB($usage['transfer_enable']/1048576),
                          's_MB_GB' => V2raySocks_MBGB(round(($usage['u'] + $usage['d'])/1048576,2)),
                          'u_MB_GB' => V2raySocks_MBGB(round($usage['u']/1048576,2)),
                          'd_MB_GB' => V2raySocks_MBGB(round($usage['d']/1048576,2)));
            if ($usage && $usage['enable']) {
                return array(
                'tabOverviewReplacementTemplate' => 'details.tpl',
                'templateVariables'              => array(
                                                        'usage' => $user,  
                                                        'params' => $params, 
                                                        'nodes' => $results,
                                                        'script' => $script,
                                                        'datadays' => $datadays,
                                                        'nowdate' => date('m/d  H:i',time()),
                                                        'infos' => $infos,
                                                        'HTTP_HOST' => $_SERVER['HTTP_HOST'])
                );
            }
            return array(
            'tabOverviewReplacementTemplate' => 'error.tpl',
            'templateVariables'              => array('usefulErrorHelper' => V2raySocks_get_lang('error_Service_Disable'))
            );
        }
        catch (Exception $e) {
            logModuleCall('V2raySocks', 'V2raySocks_ClientArea', $params, $e->getMessage(), $e->getTraceAsString());
            return array(
        'tabOverviewReplacementTemplate' => 'error.tpl',
        'templateVariables'              => array('usefulErrorHelper' => V2raySocks_get_lang('Model_error').$e->getMessage())
        );
        }
    }else{
        return array(
            'tabOverviewReplacementTemplate' => 'error.tpl',
            'templateVariables'              => array('usefulErrorHelper' => V2raySocks_get_lang('error_Service_Disable'))
            );
    }
}

function V2raySocks_AdminServicesTabFields(array $params){
    $query = V2raySocks_initialize($params);
    try {
        $dbhost = $params['serverip'];
        $dbname = $params['configoption1'];
        $dbuser = $params['serverusername'];
        $dbpass = $params['serverpassword'];
        $db = new PDO('mysql:host=' . $dbhost . ';dbname=' . $dbname, $dbuser, $dbpass);
        $userinfo = $db->prepare($query['USERINFO']);
        $userinfo->bindValue(':sid', $params['serviceid']);
        $userinfo->execute();
        $userinfo = $userinfo->fetch();
        if ($userinfo) {
            return array(V2raySocks_get_lang('uuid') => $userinfo['uuid'], V2raySocks_get_lang('bandwidth') => V2raySocks_convert($userinfo['transfer_enable'], 'bytes', 'mb') . 'MB', V2raySocks_get_lang('upload') => round(V2raySocks_convert($userinfo['u'], 'bytes', 'mb')) . 'MB', V2raySocks_get_lang('download') => round(V2raySocks_convert($userinfo['d'], 'bytes', 'mb')) . 'MB', V2raySocks_get_lang('used') => round(V2raySocks_convert($userinfo['d'] + $userinfo['u'], 'bytes', 'mb')) . 'MB', V2raySocks_get_lang('last_use_time') => date('Y-m-d H:i:s', $userinfo['t']), V2raySocks_get_lang('last_reset_time') => date('Y-m-d H:i:s', $userinfo['updated_at']));
        }
    }
    catch (Exception $e) {
        logModuleCall('V2raySocks', 'V2raySocks_AdminServicesTabFields', $params, $e->getMessage(), $e->getTraceAsString());
        return $e->getTraceAsString();
    }
}

function V2raySocks_make_script($name,$label,$data){
    if($name and $label and $data){
        $script = "
            var canvas=document.getElementById('".$name."');
            var data = {
                labels : [".$label."],
                datasets : [
                    {
                        fillColor : 'rgba(220,220,220,0.5)',
                        strokeColor : 'rgba(220,220,220,1)',
                        pointColor : 'rgba(220,220,220,1)',
                        pointStrokeColor : '#fff',
                        data : [".$data."]
                    },
                ]
            }
            var ctx = canvas.getContext('2d');
            var myLine = new Chart(ctx).Line(data,{
            responsive: true,
            scaleLabel: '<%=value%>MB'});";
        return $script;
    }
}

function V2raySocks_MBGB($tra){
    if($tra >= 1024){
        $tra = round($tra / 1024,2);
        $tra .= 'GB';
    }else{
        $tra .= 'MB';
    }
    return $tra;
}

function V2raySocks_GenerateUuid(){  
    $chars = md5(uniqid(mt_rand(), true));  
    $uuid  = substr($chars,0,8) . '-';  
    $uuid .= substr($chars,8,4) . '-';  
    $uuid .= substr($chars,12,4) . '-';  
    $uuid .= substr($chars,16,4) . '-';  
    $uuid .= substr($chars,20,12);  
    return $uuid;  
}

function V2raySocks_Convert($number, $from, $to){
    $to = strtolower($to);
    $from = strtolower($from);
    switch ($from) {
    case 'gb':
        switch ($to) {
        case 'mb':
            return $number * 1024;
        case 'bytes':
            return $number * 1073741824;
        default:
        }
        return $number;
        break;
    case 'mb':
        switch ($to) {
        case 'gb':
            return $number / 1024;
        case 'bytes':
            return $number * 1048576;
        default:
        }
        return $number;
        break;
    case 'bytes':
        switch ($to) {
        case 'gb':
            return $number / 1073741824;
        case 'mb':
            return $number / 1048576;
        default:
        }
        return $number;
        break;
    default:
    }
    return $number;
}
