<?php
  require_once dirname(__FILE__).'/../../../core/php/core.inc.php';
function deamon1(){
    
    $pid_file = jeedom::getTmpFolder('huawei_dongle') . '/deamon_huawei_dongle1.pid';
    $pid=getmypid();
    file_put_contents($pid_file, $pid);
    huawei_dongle::add_log( 'info', "pid $pid enregistré dans $pid_file");
    huawei_dongle::add_log( 'info', "Listage des eqLogics utilisant le démon");
    $nb_eqLogic=0;
    while (1==1){

        $eqLogics = eqLogic::byType('huawei_dongle',true); 
        if ($nb_eqLogic != count((array)$eqLogics)){
        $nb_eqLogic=count((array)$eqLogics);
        if (count((array)$eqLogics) <= 1){
            huawei_dongle::add_log( 'info', count((array)$eqLogics) . " équipement découvert");
        }else{
            huawei_dongle::add_log( 'info', count((array)$eqLogics) . " équipenments découverts");
        }
        foreach ($eqLogics as $eqLogic){
            if(!is_null($eqLogic->getObject_id())){
            huawei_dongle::add_log( 'info', $eqLogic->getName()." " .  jeeObject::byId($eqLogic->getObject_id())->getName());
            }else{
            huawei_dongle::add_log( 'info', $eqLogic->getName());
            }
        }
        }
        foreach ($eqLogics as $eqLogic){
        if(!$eqLogic->getIsEnable()){
            continue;
        }
        huawei_dongle::getAllInfo();
        sleep(60);
        }
    }   
}
function deamon2(){
    
    $pid_file = jeedom::getTmpFolder('huawei_dongle') . '/deamon_huawei_dongle2.pid';
    $pid=getmypid();
    file_put_contents($pid_file, $pid);
    huawei_dongle::add_log( 'info', "pid $pid enregistré dans $pid_file");
    $nb_eqLogic=0;
    while (1==1){

        $eqLogics = eqLogic::byType('huawei_dongle',true); 
        
        foreach ($eqLogics as $eqLogic){
        if(!$eqLogic->getIsEnable()){
            continue;
        }
        huawei_dongle::getSMSInfo();
        sleep(10);
        }
    }   
}
huawei_dongle::add_log( 'info',"Démarrage du démon");
deamon1();
deamon2();
?>