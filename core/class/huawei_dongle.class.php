<?php

  /* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

  /* * ***************************Includes********************************* */
  require_once '/var/www/html/core/php/core.inc.php';
 // require_once dirname(__FILE__) . '/huawei_dongleRouter.class.php';


class huawei_dongle extends eqLogic {
 
  /*     * *************************Attributs****************************** */
  public static $_widgetPossibility = array('custom' => true);


  /*     * ***********************Methode static*************************** */
  public static function dependancy_info() {
    $return = array();
    $return['progress_file'] = jeedom::getTmpFolder('huawei_dongle') . '/dependance';
    if (exec(system::getCmdSudo() . ' python3 -c "import huawei_lte_api"; echo $?') == 0) {
      $return['state'] = 'ok';
    } else {
      $return['state'] = 'nok';
    }

    return $return;
  }
  public static function dependancy_install() {
    log::remove(__CLASS__ . '_update');
    return array('script' => dirname(__FILE__) . '/../../resources/install.sh ' . jeedom::getTmpFolder('huawei_dongle') . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_update'));
  }
  public static function deamon_info() {
    $return = array();
    $return['log'] = 'huawei_dongle';
    $return['state'] = 'nok';
    $pid_file = jeedom::getTmpFolder('huawei_dongle') . '/deamon_huawei_dongle.pid';
    if (file_exists($pid_file)) {

      if (@posix_getsid(trim(file_get_contents($pid_file)))) {
        $return['state'] = 'ok';
      } else {
        shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null;rm -rf ' . $pid_file . ' 2>&1 > /dev/null;');
      }
    }
    
    $return['launchable'] = 'ok';
    return $return;
  }

  public static function deamon_start() {
    self::deamon_stop();
    self::deamon_info();
    $cmd = 'sudo /usr/bin/php ' . realpath(dirname(__FILE__) . '/../..') . '/deamon/deamon_huawei_dongle.php start';
    exec($cmd . ' >> ' . log::getPathToLog('huawei_dongle') . ' 2>&1 &');
    return true;
  }

  public static function deamon_stop() {
    $pid_file = jeedom::getTmpFolder('huawei_dongle') . '/deamon_huawei_dongle.pid';
    if (file_exists($pid_file)) {
      $pid = intval(trim(file_get_contents($pid_file)));
      system::kill($pid);
    }
    system::kill('deamon_huawei_dongle.php');
  }
  static function add_log($level = 'debug',$Log,$eqLogic=null){
    if (is_array($Log)) $Log = json_encode($Log);
    if(count(debug_backtrace(false, 2)) == 1){
      $function_name = debug_backtrace(false, 2)[0]['function'];
      $ligne = debug_backtrace(false, 2)[0]['line'];
    }else{
      $function_name = debug_backtrace(false, 2)[1]['function'];
      $ligne = debug_backtrace(false, 2)[0]['line'];
    }
    $msg =  $function_name .' (' . $ligne . '): '.$Log;
   
     log::add(__CLASS__  , $level,$msg);
    }
  public static function update($_eqLogic_id = null) {
    if ($_eqLogic_id == null) {
      $eqLogics = eqLogic::byType('huawei_dongle');
    } else {
      $eqLogics = array(eqLogic::byId($_eqLogic_id));
    }
    foreach ($eqLogics as $rtr) {
      try {
        $rtr->postSave();
        $rtr->getRouteurInfo();
        $rtr->getSMSInfo();
      } catch (Exception $e) {
        huawei_dongle::add_log( 'error', $e->getMessage());
      }
    }
  }

  public function preUpdate() {
    if ($this->getConfiguration('ip') == '') {
      throw new Exception(__('Le champs IP ne peut pas être vide', __FILE__));
    }
    if ($this->getConfiguration('username') == '') {
      throw new Exception(__("Le champs Nom d'utilisateur ne peut pas être vide", __FILE__));
    }
    if ($this->getConfiguration('password') == '') {
      throw new Exception(__('Le champs Mot de passe ne peut pas être vide', __FILE__));
    }
  }
  private function ping($ip) {
		$ping = "NOK";
		$exec_string = 'sudo ping -n -c 1 -t 255 ' . $ip;
		exec($exec_string, $output, $return);
		$output = array_values(array_filter($output));
	
		if (!empty($output[1])) {
			if (count($output) >= 5) {
			$response = preg_match("/time(?:=|<)(?<time>[\.0-9]+)(?:|\s)ms/", $output[count($output)-4], $matches);
			if ($response > 0 && isset($matches['time'])) {
	
				$ping = "OK";
			}				
			}			
		}	
		return $ping;
		}
  
  public static function getAllInfo($eqlogic){
    if ($eqlogic->ping($eqlogic->getConfiguration('ip'))=="NOK"){
      $eqlogic->infos["status"]="Down";
      $eqlogic->updateInfo();
      return;
   }
  
   $IPaddress = $eqlogic->getConfiguration('ip');
   $login = $eqlogic->getConfiguration('username');
   $pwd = $eqlogic->getConfiguration('password');

   $eqlogic->infos = array();

    // setting the huawei_dongleRouter session
    $huawei_dongleRouter = new huawei_dongleRouter();
    $huawei_dongleRouter->setIP($IPaddress);
    

    // calling API
    try {
      $huawei_dongleRouter->setSession($login, $pwd, "all");
      $eqlogic->infos['status'] = $huawei_dongleRouter->getStatus();

      if($eqlogic->infos['status'] == "Up") {
        $eqlogic->setInfo($huawei_dongleRouter->getPublicLandMobileNetwork());
        $eqlogic->setInfo($huawei_dongleRouter->getCellInfo());
        $eqlogic->setInfo($huawei_dongleRouter->getSMS());
        $eqlogic->setInfo($huawei_dongleRouter->getSMSCount());
      }
    } catch (Exception $e) {
       huawei_dongle::add_log( 'error', $e);
    }

    $eqlogic->updateInfo();
  }

  public function getRouteurIngetSMSInfofo() {
    // getting configuration
    if ($this->ping($this->getConfiguration('ip'))=="NOK"){
       $this->infos["status"]="Down";
       $this->updateInfo();
       return;
    }
   
    $IPaddress = $this->getConfiguration('ip');
    $login = $this->getConfiguration('username');
    $pwd = $this->getConfiguration('password');
    $this->infos = array();
    // setting the huawei_dongleRouter session
    $huawei_dongleRouter = new huawei_dongleRouter();
    $huawei_dongleRouter->setIP($IPaddress);

    // calling API
    try {
      $huawei_dongleRouter->setSession($login, $pwd, "get");
      $this->infos['status'] = $huawei_dongleRouter->getStatus();

      if($this->infos['status'] == "Up") {
        $this->setInfo($huawei_dongleRouter->getPublicLandMobileNetwork());
        $this->setInfo($huawei_dongleRouter->getCellInfo());

      }
    } catch (Exception $e) {
       huawei_dongle::add_log( 'error', $e);
    }

    $this->updateInfo();
  }

  public function getSMSInfo() {
    if ($this->ping($this->getConfiguration('ip'))=="NOK"){
      $this->infos["status"]="Down";
      $this->updateInfo();
      return;
   }
    // getting configuration
    $IPaddress = $this->getConfiguration('ip');
    $login = $this->getConfiguration('username');
    $pwd = $this->getConfiguration('password');
    $RtrName = $this->getName();

    $this->infos = array();

    // setting the huawei_dongleRouter session
    $huawei_dongleRouter = new huawei_dongleRouter();
    $huawei_dongleRouter->setIP($IPaddress);
    

    // calling API
    try {
      $huawei_dongleRouter->setSession($login, $pwd, "all");
      $this->infos['status'] = $huawei_dongleRouter->getStatus();

      if($this->infos['status'] == "Up") {
        $this->setInfo($huawei_dongleRouter->getSMS());
        $this->setInfo($huawei_dongleRouter->getSMSCount());
      }
    } catch (Exception $e) {
       huawei_dongle::add_log( 'error', $e);
    }

    $this->updateInfo();
  }



  // fill the info array
  private function setInfo($infoTab) {

    if(isset($infoTab)) {
      // workaround PHP < 7
      if (!function_exists('array_key_first')) {
        function array_key_first(array $arr) {
          foreach($arr as $key => $unused) {
            return $key;
          }
          return NULL;
        }
      }


      foreach($infoTab as $key => $value) {
        switch($key) {
          case "Messages": 

            $this->infos[$key] = json_encode($value['Message']);

            break;
          case "DeviceName": 
            $this->infos['devicename'] = $value;
            break;
          default:
            $this->infos[$key] = $value;
        }

      }

    } else {
       huawei_dongle::add_log( 'debug', 'function  has a NULL parameter');
    }
  }

  public function reboot() {
    // getting configuration
    $IPaddress = $this->getConfiguration('ip');
    $login = $this->getConfiguration('username');
    $pwd = $this->getConfiguration('password');
    $RouteurCmd = $this->getCmd(null, 'status');
    $RouteurCmd->event("Down");
    // setting the huawei_dongleRouter session
    $huawei_dongleRouter = new huawei_dongleRouter();
    $huawei_dongleRouter->setIP($IPaddress);
    try {
      $huawei_dongleRouter->setSession($login, $pwd, "");
      $res = $huawei_dongleRouter->setReboot();
    } catch (Exception $e) {
       huawei_dongle::add_log( 'error', $e);
    }

     huawei_dongle::add_log( 'debug', 'Rebooting: '.$res);
  }

  public function sendSMS($arr) {
    // getting configuration
    $IPaddress = $this->getConfiguration('ip');
    $login = $this->getConfiguration('username');
    $pwd = $this->getConfiguration('password');
   

    // setting the huawei_dongleRouter session
    $huawei_dongleRouter = new huawei_dongleRouter();
    $huawei_dongleRouter->setIP($IPaddress);
    try {
      
       
      $messageSMS = $arr['message'];
     
      $huawei_dongleRouter->setSession($login, $pwd, "");
      $numero_tel="Vide";
      if(isset($arr['numerotel'])) {
        $numero_tel=$arr['numerotel'];
      }
      if(isset($arr['title'])) {
        $numero_tel=$arr['title'];

      }
       huawei_dongle::add_log( 'debug', 'numerotel: '. $numero_tel);
       huawei_dongle::add_log( 'debug', 'message: '.$messageSMS);
      if(empty($arr['numerotel'])) {
        $res = json_decode($huawei_dongleRouter->sendSMS($arr['title'], $messageSMS));
      } else {
        $res = json_decode($huawei_dongleRouter->sendSMS($arr['numerotel'], $messageSMS));
      }
    } catch (Exception $e) {
       huawei_dongle::add_log( 'error', $e);
    }
    // huawei_dongle::add_log( 'debug', 'Message: '.$res->message);
     huawei_dongle::add_log( 'debug', 'Retour: '.$res->retour);
    return $res->retour;
  }

  public function delSMS($arr) {
    // getting configuration
    $IPaddress = $this->getConfiguration('ip');
    $login = $this->getConfiguration('username');
    $pwd = $this->getConfiguration('password');

    // setting the huawei_dongleRouter session
    $huawei_dongleRouter = new huawei_dongleRouter();
    $huawei_dongleRouter->setIP($IPaddress);
    try {
      $huawei_dongleRouter->setSession($login, $pwd, "");
       huawei_dongle::add_log( 'debug', 'smsid: '.$arr['smsid']);
      if(empty($arr['smsid'])) {
         huawei_dongle::add_log( 'debug', 'smsid empty');
      } else {
        $res = $huawei_dongleRouter->delSMS($arr['smsid']);
      }
    } catch (Exception $e) {
       huawei_dongle::add_log( 'error', $e);
    }

     huawei_dongle::add_log( 'debug', 'Sending: '.$res);
  }

 

  private function cleanSMS($message) {
    $caracteres = array(
      'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ä' => 'a', 'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', '@' => 'a',
      'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', '€' => 'e',
      'Ì' => 'i', 'Í' => 'i', 'Î' => 'i', 'Ï' => 'i', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
      'Ò' => 'o', 'Ó' => 'o', 'Ô' => 'o', 'Ö' => 'o', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o',
      'Ù' => 'u', 'Ú' => 'u', 'Û' => 'u', 'Ü' => 'u', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'µ' => 'u',
      'Œ' => 'oe', 'œ' => 'oe',
      '$' => 's');
    return preg_replace('#[^A-Za-z0-9 \n\.\'=\*:]+#', '', strtr($message, $caracteres));
  }

  // update HTML
  public function updateInfo() {


    //foreach ($this->getCmd('info') as $cmd) {
    $eqLogic=$this;

    try {
      foreach($this->infos as $cle=>$valeur){
        // huawei_dongle::add_log( 'debug', $cle . ': valeur '.$valeur);

        $value = $this->infos[$cle];
        if($cle == "Messages"){
          $cle= "SMS";
          if(strpos($value, '[')=== FALSE) {
            $value = "[" .  $value. "]";
          }
        }

        
        $cmd=cmd::byEqLogicIdAndLogicalId($eqLogic->getId(), $cle);
        if (is_object($cmd)){

          
          if($cmd->execCmd()!=$value && $value !=''){
            if($cle== "Messages") {             
              $value = 'test';
            }

            huawei_dongle::add_log( 'info', 'MAJ '.$cle . ': '.$value);
          }
          $this->checkAndUpdateCmd($cmd, $value);
        }


      }
      if(isset($this->infos["LocalMax"]) && isset($this->infos["LocalOutbox"]) && isset($this->infos["LocalInbox"])){
        $nombre_max=0;
        $nombre_actuel=0;
        $nombre_actuel= $nombre_actuel + $this->infos["LocalOutbox"];
        $nombre_actuel= $nombre_actuel + $this->infos["LocalInbox"];
        $nombre_max= $this->infos["LocalMax"];
		    $restants=intval($nombre_max-$nombre_actuel);
        $cmd=cmd::byEqLogicIdAndLogicalId($eqLogic->getId(), "RestLocalInbox");
        if (is_object($cmd)){
         
          if($cmd->execCmd()!=$restants){
             huawei_dongle::add_log( 'info', 'MAJ RestLocalInbox: '.$restants);
           
          }
           $this->checkAndUpdateCmd($cmd, $restants);
        }

      }
    } catch (Exception $e) {
       huawei_dongle::add_log( 'error', 'Impossible de mettre à jour le champs '.$key);
    }
    //}
  }


  /*     * *********************Methode d'instance************************* */
  public function preSave() {

  }

  public function postSave() {
    $RouteurCmd = $this->getCmd(null, 'refresh');
    if (!is_object($RouteurCmd)) {
       huawei_dongle::add_log( 'debug', 'refresh');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('Rafraîchir', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('refresh');
      $RouteurCmd->setType('action');
      $RouteurCmd->setSubType('other');
      $RouteurCmd->setOrder('1');
      $RouteurCmd->save();
    }
    $RouteurCmd = $this->getCmd(null, 'FullName');
    if (!is_object($RouteurCmd)) {
       huawei_dongle::add_log( 'debug', 'FullName');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('Réseau mobile', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('FullName');
      $RouteurCmd->setType('info');
      $RouteurCmd->setSubType('string');
      $RouteurCmd->setOrder('2');
      $RouteurCmd->save();
    }

    $RouteurCmd = $this->getCmd(null, 'refreshsms');
    if (!is_object($RouteurCmd)) {
       huawei_dongle::add_log( 'debug', 'refreshsms');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('Rafraîchir SMS', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('refreshsms');
      $RouteurCmd->setType('action');
      $RouteurCmd->setSubType('other');
      $RouteurCmd->setOrder('3');
      $RouteurCmd->save();
    }
    $RouteurCmd = $this->getCmd(null, 'status');
    if (!is_object($RouteurCmd)) {
       huawei_dongle::add_log( 'debug', 'status');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('Statut', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('status');
      $RouteurCmd->setType('info');
      $RouteurCmd->setSubType('string');
      $RouteurCmd->setOrder('4');
      $RouteurCmd->save();
    }

    $RouteurCmd = $this->getCmd(null, 'devicename');
    if (!is_object($RouteurCmd)) {
       huawei_dongle::add_log( 'debug', 'devicename');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('Modèle', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('devicename');
      $RouteurCmd->setType('info');
      $RouteurCmd->setSubType('string');
      $RouteurCmd->setOrder('5');
      $RouteurCmd->save();
    }

    $RouteurCmd = $this->getCmd(null, 'workmode');
    if (!is_object($RouteurCmd)) {
       huawei_dongle::add_log( 'debug', 'workmode');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('Mode', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('workmode');
      $RouteurCmd->setType('info');
      $RouteurCmd->setSubType('string');
      $RouteurCmd->setOrder('9');
      $RouteurCmd->save();
    }

    $RouteurCmd = $this->getCmd(null, 'Msisdn');
    if (!is_object($RouteurCmd)) {
       huawei_dongle::add_log( 'debug', 'Msisdn');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('Numéro', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('Msisdn');
      $RouteurCmd->setType('info');
      $RouteurCmd->setSubType('string');
      $RouteurCmd->setOrder('11');
      $RouteurCmd->save();
    }


    $RouteurCmd = $this->getCmd(null, 'LocalInbox');
    if (!is_object($RouteurCmd)) {
       huawei_dongle::add_log( 'debug', 'LocalInbox');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('SMS Reçus', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('LocalInbox');
      $RouteurCmd->setType('info');
      $RouteurCmd->setSubType('numeric');
      $RouteurCmd->setOrder('14');
      $RouteurCmd->save();
    }


    $RouteurCmd = $this->getCmd(null, 'LocalOutbox');
    if (!is_object($RouteurCmd)) {
       huawei_dongle::add_log( 'debug', 'LocalOutbox');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('SMS Envoyés', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('LocalOutbox');
      $RouteurCmd->setType('info');
      $RouteurCmd->setSubType('numeric');
      $RouteurCmd->setOrder('15');
      $RouteurCmd->save();
    }
    $RouteurCmd = $this->getCmd(null, 'RestLocalInbox');
    if (!is_object($RouteurCmd)) {
       huawei_dongle::add_log( 'debug', 'LocalInbox');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('SMS restants', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('RestLocalInbox');
      $RouteurCmd->setType('info');
      $RouteurCmd->setSubType('numeric');
      $RouteurCmd->setOrder('16');
      $RouteurCmd->save();
    }
    $RouteurCmd = $this->getCmd(null, 'SMS');
    if (!is_object($RouteurCmd)) {
       huawei_dongle::add_log( 'debug', 'SMS');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('SMS', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('SMS');
      $RouteurCmd->setType('info');
      $RouteurCmd->setSubType('string');
      $RouteurCmd->setOrder('17');
      $RouteurCmd->save();
    }

    $RouteurCmd = $this->getCmd(null, 'reboot');
    if (!is_object($RouteurCmd)) {
       huawei_dongle::add_log( 'debug', 'reboot');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('Redémarrer', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('reboot');
      $RouteurCmd->setType('action');
      $RouteurCmd->setSubType('other');
      $RouteurCmd->setOrder('18');
      $RouteurCmd->save();
    }


    $RouteurCmd = $this->getCmd(null, 'sendsms');
    if (!is_object($RouteurCmd)) {
       huawei_dongle::add_log( 'debug', 'sendsms');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('Envoyer SMS', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('sendsms');
      $RouteurCmd->setType('action');
      $RouteurCmd->setSubType('message');
      $RouteurCmd->setOrder('21');
      $RouteurCmd->save();
    }

    $RouteurCmd = $this->getCmd(null, 'delsms');
    if (!is_object($RouteurCmd)) {
       huawei_dongle::add_log( 'debug', 'delsms');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('Supprimer SMS', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('delsms');
      $RouteurCmd->setType('action');
      $RouteurCmd->setSubType('other');
      $RouteurCmd->setOrder('22');
      $RouteurCmd->save();
    }




  }

  public function postUpdate() {		
    $cmd = $this->getCmd(null, 'refresh');
    if (is_object($cmd)) { 
      $cmd->execCmd();
    }
  }
  function toHtml($_version = 'dashboard') {
  
    $eqLogic=$this;
    $replace = $eqLogic->preToHtml($_version);
   
		$version = jeedom::versionAlias($_version);
  
    if (!is_array($replace)) {return $replace; }
     
    $version_alias=jeedom::versionAlias($_version);
  
    if ($eqLogic->getDisplay('hideOn' . $version_alias) == 1) { return ''; }
    $replace['#statusid#'] = $eqLogic->getCmd(null, 'status')->getId();
    $replace['#status#'] = $eqLogic->getCmd(null, 'status')->execCmd();
    $replace['#modèleid#'] = $eqLogic->getCmd(null, 'devicename')->getId();
    $replace['#modèle#'] = $eqLogic->getCmd(null, 'devicename')->execCmd();
    $replace['#modeid#'] = $eqLogic->getCmd(null, 'workmode')->getId();
    $replace['#mode#'] = $eqLogic->getCmd(null, 'workmode')->execCmd();
    $replace['#numéroid#'] = $eqLogic->getCmd(null, 'Msisdn')->getId();
    $replace['#numéro#'] = $eqLogic->getCmd(null, 'Msisdn')->execCmd();
    $replace['#reçuid#'] = $eqLogic->getCmd(null, 'LocalInbox')->getId();
    $replace['#reçu#'] = $eqLogic->getCmd(null, 'LocalInbox')->execCmd();
    $replace['#envoyéid#'] = $eqLogic->getCmd(null, 'LocalOutbox')->getId();
    $replace['#envoyé#'] = $eqLogic->getCmd(null, 'LocalOutbox')->execCmd();
    $replace['#restantsid#'] = $eqLogic->getCmd(null, 'RestLocalInbox')->getId();
    $replace['#restants#'] = $eqLogic->getCmd(null, 'RestLocalInbox')->execCmd();
    $replace['#reçu_txtid#'] = $eqLogic->getCmd(null, 'SMS')->getId();
    $replace['#envoiid#'] = $eqLogic->getCmd(null, 'sendsms')->getId();
    $replace['#supprimerid#'] = $eqLogic->getCmd(null, 'delsms')->getId();
    $replace['#refreshsmsid#'] = $eqLogic->getCmd(null, 'refreshsms')->getId();
    $replace['#rebootid#'] = $eqLogic->getCmd(null, 'reboot')->getId();
    $sms_reçu=json_decode( $eqLogic->getCmd(null, 'SMS')->execCmd());
    $sms_reçu_txt='';
    foreach($sms_reçu as $sms){
      
      $StatutSMS;
      if($sms->Smstat == '0') {
          $StatutSMS = "Reçu";
      }else{
          $StatutSMS = "Envoyé";
      }
      $sms_reçu_txt .= '<textarea readonly=true class="input-sm message" style="height: 120px;" sms-id="'.$sms->Index.'">Statut : '.$StatutSMS.'&#10;Tel : '.$sms->Phone.'&#10;Date : '.$sms->Date.'&#10;'.$sms->Content.' </textarea>';
      
              

    }
    if($sms_reçu_txt == ''){
      $sms_reçu_txt = '<textarea 
      class="input-sm messages" 
      style="height: 120px;" 
      sms-id="null" readonly=true></textarea>';
    }
    $replace['#reçu_txt#'] = $sms_reçu_txt;
    $replace['#réseauid#'] = $eqLogic->getCmd(null, 'FullName')->getId();
    $replace['#réseau#'] = $eqLogic->getCmd(null, 'FullName')->execCmd();
   // huawei_dongle::add_log("huawei_dongle","info",$replace);
    $html= $this->postToHtml($_version, template_replace($replace, getTemplate('core', $version, 'huawei_dongle', __CLASS__)));
   
  
   // cache::set('Huawei_dongle' . $_version . $this->getId(), $html, 0);
		return $html;
    
 
  } 
}

class huawei_dongleCmd extends cmd {
  /*     * *************************Attributs****************************** */


  /*     * ***********************Methode static*************************** */


  /*     * *********************Methode d'instance************************* */


  public function execute($_options = null) {
    $eqLogic = $this->getEqLogic();
    switch ($this->getLogicalId()) {
      case "reboot":
         huawei_dongle::add_log('debug','reboot ' . $this->getHumanName());
        $eqLogic->reboot();

        break;

      case "sendsms":
         huawei_dongle::add_log('debug','sendsms ' . $this->getHumanName());
        $return=$eqLogic->sendSMS($_options);
        $eqLogic->getSMSInfo();
        return $return;

        break;

      case "refresh":
         huawei_dongle::add_log('debug','refresh ' . $this->getHumanName());
       
        $eqLogic->getAllInfo($eqLogic);
        break;

      case "refreshsms":
         huawei_dongle::add_log('debug','refreshsms ' . $this->getHumanName());
        $eqLogic->getSMSInfo();
        break;

      case "delsms":
         huawei_dongle::add_log('debug','delsms ' . $this->getHumanName());
        $eqLogic->delSMS($_options);
        //$eqLogic->getSMSInfo();
        break;


    }
    
    return true;
  }

  /*     * **********************Getteur Setteur*************************** */
}
class huawei_dongleRouter {
	private $client;
	private $session;
	private $statut;
	private $login;
	private $password;
	private $ip;
	private $output;
	private $outputSMS;
	const LOGGED_IN = '0';
	const LOGGED_OUT = '-1';

	
	
	public function setIP($ip) {
		$this->ip = $ip;
	}
	
	

    
	
	private function encodeToUtf8($string) {
		return mb_convert_encoding($string, "UTF-8", mb_detect_encoding($string, "UTF-8, ISO-8859-1, ISO-8859-15", true));
	}

	
	public function getStatus() {
		$state = $this->getState();
		
		if(empty($state['State'])) {
			if($state == 'down'){
				$this->statut = "Down";
				 huawei_dongle::add_log( 'debug', 'Down - no data');
			}else if(intval($state['State']) == self::LOGGED_IN) {
				$this->statut = "Up";
			} else {
				$this->statut = "Down";
				 huawei_dongle::add_log( 'debug', 'Down - no data');
			}
		} else {
			$this->statut = "Down";
			 huawei_dongle::add_log( 'debug', 'Down');
		}
		
		return $this->statut;
	}


	/*
	Functions for sessions
	*/
	
	public function setSession($login, $pwd, $action) {
		
		$this->login = "'".str_replace("'", "'\\''", $login)."'";
		$this->password = "'".str_replace("'", "'\\''", $pwd)."'";
		/**/
		
		switch($action) {
			case "get":
				$this->setInfo($this->getInfoPython());
				break;
			
			case "sms":
				$this->setInfoSMS($this->getSMSPython());
				break;
			case "all":	
        $this->setInfo($this->getInfoPython());
        $this->setInfoSMS($this->getSMSPython());
				break;
			default:
				break;
		}
		
	}
	
	private function setInfo($out) {
		// huawei_dongle::add_log( 'debug', 'Output: '.$out);
		
		// removing Python bracket list
		$tmp = substr(trim($out), 2, -2);
		// splitting json outputs
		$this->output = explode('}\', \'{', $tmp);
		
		foreach($this->output as $key => $value) {
			if($value[0] != '{') {
				$this->output[$key] = substr_replace($value,'{',0,0);
			}
			if(substr($this->output[$key], -1) != '}') {
				$this->output[$key] = $this->output[$key].'}';
			}
						
			$this->output[$key] = str_replace("\\'", "'", $this->output[$key]);
			$this->output[$key] = str_replace(array("\r\n", "\n", "\r"), "", $this->output[$key]);
			// huawei_dongle::add_log( 'debug', $key.': '.$this->output[$key]);
			$this->output[$key] = json_decode($this->output[$key], true);
			
			switch (json_last_error()) {
				case JSON_ERROR_NONE:
					// huawei_dongle::add_log( 'debug', ' - Aucune erreur');
				break;
				case JSON_ERROR_DEPTH:
					 huawei_dongle::add_log( 'debug', ' - Profondeur maximale atteinte');
				break;
				case JSON_ERROR_STATE_MISMATCH:
					 huawei_dongle::add_log( 'debug', ' - Inadéquation des modes ou underflow');
				break;
				case JSON_ERROR_CTRL_CHAR:
					 huawei_dongle::add_log( 'debug', ' - Erreur lors du contrôle des caractères');
				break;
				case JSON_ERROR_SYNTAX:
					 huawei_dongle::add_log( 'debug', ' - Erreur de syntaxe ; JSON malformé');
				break;
				case JSON_ERROR_UTF8:
					 huawei_dongle::add_log( 'debug', ' - Caractères UTF-8 malformés, probablement une erreur d\'encodage');
				break;
				default:
					 huawei_dongle::add_log( 'debug', ' - Erreur inconnue');
				break;
			}
		}
	}
  private function cleanJsonString($data) {
    $data = trim($data);
    $data = preg_replace('!\s*//[^"]*\n!U', '\n', $data);
    $data = preg_replace('!/\*[^"]*\*/!U', '', $data);
    $data = !startsWith('{', $data) ? '{'.$data : $data;
    $data = !endsWith('}', $data) ? $data.'}' : $data;
    $data = preg_replace('!,(\s*[}\]])!U', '$1', $data);
    return $data;
  }
	private function startsWith($needle, $haystack) {
    return !strncmp($haystack, $needle, strlen($needle));
  }
 
  private function endsWith($needle, $haystack) {
      $length = strlen($needle);
      if ($length == 0)
          return true;
      return (substr($haystack, -$length) === $needle);
  }
	private function setInfoSMS($out) {
		// huawei_dongle::add_log( 'debug', 'PreOutputSMS: '.$out);
		
		// removing Python bracket list
		$tmp = substr(trim($out), 2, -2);
		// splitting json outputs
		$this->outputSMS = explode('}\', \'{', $tmp);
		
		foreach($this->outputSMS as $key => $value) {
			if($value[0] != '{') {
				$this->outputSMS[$key] = substr_replace($value,'{',0,0);
			}
			if(substr($this->outputSMS[$key], -1) != '}') {
				$this->outputSMS[$key] = $this->outputSMS[$key].'}';
			}
                    
          
          
			$this->outputSMS[$key] = str_replace(array("\r\n",'\\r\n', "\\n",'\\r', "\r"), "", $this->outputSMS[$key]);			
			$this->outputSMS[$key] = str_replace("\\'", "'", $this->outputSMS[$key]);
          $this->outputSMS[$key] = str_replace("\'", "'", $this->outputSMS[$key]);
           $this->outputSMS[$key] = str_replace('\\"', "'", $this->outputSMS[$key]);
          $this->outputSMS[$key] = str_replace("\\'", "", $this->outputSMS[$key]);
			 huawei_dongle::add_log( 'debug', $key.': '.$this->outputSMS[$key]);
            $this->outputSMS[$key] = json_decode($this->outputSMS[$key],true);
          	//var_dump($out);
			switch (json_last_error()) {
				case JSON_ERROR_NONE:
					// huawei_dongle::add_log( 'debug', ' - Aucune erreur');
				break;
				case JSON_ERROR_DEPTH:
					 huawei_dongle::add_log( 'debug', ' - Profondeur maximale atteinte');
				break;
				case JSON_ERROR_STATE_MISMATCH:
					 huawei_dongle::add_log( 'debug', ' - Inadéquation des modes ou underflow');
				break;
				case JSON_ERROR_CTRL_CHAR:
					 huawei_dongle::add_log( 'debug', ' - Erreur lors du contrôle des caractères');
				break;
				case JSON_ERROR_SYNTAX:
					 huawei_dongle::add_log( 'debug', ' - Erreur de syntaxe ; JSON malformé');
                	 huawei_dongle::add_log( 'debug', $key.': '.$this->outputSMS[$key]);
				break;
				case JSON_ERROR_UTF8:
					 huawei_dongle::add_log( 'debug', ' - Caractères UTF-8 malformés, probablement une erreur d\'encodage');
				break;
				default:
					 huawei_dongle::add_log( 'debug', ' - Erreur inconnue');
				break;
			}
		}
	}
	
	// get the info
	private function getInfoPython() {
		$command = dirname(__FILE__) . '/../../resources/scripts/poller.py '.$this->ip.' '.$this->login.' '.$this->password;
		try{
			$json = shell_exec('python3 '.$command);
		} catch (Exception $e){
			 huawei_dongle::add_log( 'debug', $e);
		}
		 huawei_dongle::add_log( 'debug', $json);
		return $json;		
	}
	
	// SMS
	private function setSMSPython($tel, $msg) {
		$escapedArg = "'".str_replace("'", "'\\''", $msg)."'";
		$command = dirname(__FILE__) . '/../../resources/scripts/sender.py '.$this->ip.' '.$this->login.' '.$this->password.' '.$tel.' '.$this->encodeToUtf8($escapedArg);
		try{
			$json = shell_exec('python3 '.$command);
		} catch (Exception $e){
			 huawei_dongle::add_log( 'debug', $e);
		}
		// huawei_dongle::add_log( 'debug', $json);
      return $json;
		//return json_decode($json, true);		
	}
	
	private function getSMSPython() {
		$command = dirname(__FILE__) . '/../../resources/scripts/getsms.py '.$this->ip.' '.$this->login.' '.$this->password;
		try{
         
			$json = shell_exec('python3 '.$command);
		} catch (Exception $e){
			 huawei_dongle::add_log( 'debug', $e);
		}
		 huawei_dongle::add_log( 'debug', $json);
		return $json;		
	}
	
	private function delSMSPython($ind) {
		$command = dirname(__FILE__) . '/../../resources/scripts/delsms.py '.$this->ip.' '.$this->login.' '.$this->password.' '.$ind;
		try{
			$json = shell_exec('python3 '.$command);
		} catch (Exception $e){
			 huawei_dongle::add_log( 'debug', $e);
		}
		 huawei_dongle::add_log( 'debug', $json);
		return json_decode($json, true);		
	}
	
	// Reboot
	private function reboot() {
		$command = dirname(__FILE__) . '/../../resources/scripts/reboot.py '.$this->ip.' '.$this->login.' '.$this->password;
		try{
			$json = shell_exec('python3 '.$command);
		} catch (Exception $e){
			 huawei_dongle::add_log( 'debug', $e);
		}
		 huawei_dongle::add_log( 'debug', $json);
		return json_decode($json, true);		
	}
	
	/*
	Functions w/o login needed
	*/
	
	
	public function getPublicLandMobileNetwork() {
		return $this->output[3];
	}
	
	public function getCellInfo() {
		return $this->output[5];
	}
	
	
	
	/* toujours garder en dernier dans le tableau */
  public function getSMSCount() {
		return $this->outputSMS[1];
	}
	public function getSMS() {
		return $this->outputSMS[2];
	}
	
	public function setReboot() {
		return $this->reboot();
	}
	
	
	public function getState() {
		
		if(empty($this->outputSMS[1])) {
			return $this->output[1];
		} else {
         
			return $this->outputSMS[0];
		}
	}
	
	public function sendSMS($phone, $message) {
		return $this->setSMSPython($phone, $message);
	}
	
	public function delSMS($index) {
		return $this->delSMSPython($index);
	}
}
?>