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
        log::add('huawei_dongle', 'error', $e->getMessage());
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
  public  function ping($ip) {
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
  
  public function getAllInfo(){
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
      $huawei_dongleRouter->setSession($login, $pwd, "all");
      $this->infos['status'] = $huawei_dongleRouter->getStatus();

      if($this->infos['status'] == "Up") {
        $this->setInfo($huawei_dongleRouter->getPublicLandMobileNetwork());
        $this->setInfo($huawei_dongleRouter->getCellInfo());
        $this->setInfo($huawei_dongleRouter->getSMS());
        $this->setInfo($huawei_dongleRouter->getSMSCount());
      }
    } catch (Exception $e) {
      log::add('huawei_dongle', 'error', $e);
    }

    $this->updateInfo();
  }

  public function getRouteurInfo() {
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
      log::add('huawei_dongle', 'error', $e);
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
      log::add('huawei_dongle', 'error', $e);
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
      log::add('huawei_dongle', 'debug', 'function  has a NULL parameter');
    }
  }

  public function reboot() {
    // getting configuration
    $IPaddress = $this->getConfiguration('ip');
    $login = $this->getConfiguration('username');
    $pwd = $this->getConfiguration('password');

    // setting the huawei_dongleRouter session
    $huawei_dongleRouter->setIP($IPaddress);
    try {
      $huawei_dongleRouter->setSession($login, $pwd, "");
      $res = $huawei_dongleRouter->setReboot();
    } catch (Exception $e) {
      log::add('huawei_dongle', 'error', $e);
    }

    log::add('huawei_dongle', 'debug', 'Rebooting: '.$res);
  }

  public function sendSMS($arr) {
    // getting configuration
    $IPaddress = $this->getConfiguration('ip');
    $login = $this->getConfiguration('username');
    $pwd = $this->getConfiguration('password');
    $texteMode = $this->getConfiguration('texteMode');

    // setting the huawei_dongleRouter session
    $huawei_dongleRouter = new huawei_dongleRouter();
    $huawei_dongleRouter->setIP($IPaddress);
    try {
      if($texteMode == 1) {
        $messageSMS = $this->cleanSMS($arr['message']);
      } else {
        $messageSMS = $arr['message'];
      }
      $huawei_dongleRouter->setSession($login, $pwd, "");
      $numero_tel="Vide";
      if(isset($arr['numerotel'])) {
        $numero_tel=$arr['numerotel'];
      }
      if(isset($arr['title'])) {
        $numero_tel=$arr['title'];

      }
      log::add('huawei_dongle', 'debug', 'numerotel: '. $numero_tel);
      log::add('huawei_dongle', 'debug', 'message: '.$messageSMS);
      if(empty($arr['numerotel'])) {
        $res = json_decode($huawei_dongleRouter->sendSMS($arr['title'], $messageSMS));
      } else {
        $res = json_decode($huawei_dongleRouter->sendSMS($arr['numerotel'], $messageSMS));
      }
    } catch (Exception $e) {
      log::add('huawei_dongle', 'error', $e);
    }
    //log::add('huawei_dongle', 'debug', 'Message: '.$res->message);
    log::add('huawei_dongle', 'debug', 'Retour: '.$res->retour);
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
      log::add('huawei_dongle', 'debug', 'smsid: '.$arr['smsid']);
      if(empty($arr['smsid'])) {
        log::add('huawei_dongle', 'debug', 'smsid empty');
      } else {
        $res = $huawei_dongleRouter->delSMS($arr['smsid']);
      }
    } catch (Exception $e) {
      log::add('huawei_dongle', 'error', $e);
    }

    log::add('huawei_dongle', 'debug', 'Sending: '.$res);
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
        //log::add('huawei_dongle', 'debug', $cle . ': valeur '.$valeur);

        $value = $this->infos[$cle];
        if($cle == "Messages"){

          if(strpos($value, '[')=== FALSE) {
            $value = "[" .  $value. "]";
          }
        }


        $cmd=cmd::byEqLogicIdAndLogicalId($eqLogic->getId(), $cle);
        if (is_object($cmd)){

          $this->checkAndUpdateCmd($cmd, $value);
          log::add('huawei_dongle', 'debug', 'MAJ '.$cle . ': '.$value);
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
          $this->checkAndUpdateCmd($cmd, $restants);
          log::add('huawei_dongle', 'debug', 'MAJ RestLocalInbox: '.$restants);
        }

      }
    } catch (Exception $e) {
      log::add('huawei_dongle', 'error', 'Impossible de mettre à jour le champs '.$key);
    }
    //}
  }


  /*     * *********************Methode d'instance************************* */
  public function preSave() {

  }

  public function postSave() {
    $RouteurCmd = $this->getCmd(null, 'refresh');
    if (!is_object($RouteurCmd)) {
      log::add('huawei_dongle', 'debug', 'refresh');
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
      log::add('huawei_dongle', 'debug', 'FullName');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('Réseau mobile', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('FullName');
      $RouteurCmd->setType('info');
      $RouteurCmd->setTemplate('dashboard','huawei_dongle-antenna');
      $RouteurCmd->setSubType('string');
      $RouteurCmd->setOrder('2');
      $RouteurCmd->save();
    }

    $RouteurCmd = $this->getCmd(null, 'refreshsms');
    if (!is_object($RouteurCmd)) {
      log::add('huawei_dongle', 'debug', 'refreshsms');
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
      log::add('huawei_dongle', 'debug', 'status');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('Statut', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('status');
      $RouteurCmd->setType('info');
      $RouteurCmd->setTemplate('dashboard','huawei_dongle-power');
      $RouteurCmd->setSubType('string');
      $RouteurCmd->setOrder('4');
      $RouteurCmd->save();
    }

    $RouteurCmd = $this->getCmd(null, 'devicename');
    if (!is_object($RouteurCmd)) {
      log::add('huawei_dongle', 'debug', 'devicename');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('Modèle', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('devicename');
      $RouteurCmd->setType('info');
      $RouteurCmd->setTemplate('dashboard','huawei_dongle-linux');
      $RouteurCmd->setSubType('string');
      $RouteurCmd->setOrder('5');
      $RouteurCmd->save();
    }

    $RouteurCmd = $this->getCmd(null, 'workmode');
    if (!is_object($RouteurCmd)) {
      log::add('huawei_dongle', 'debug', 'workmode');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('Mode', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('workmode');
      $RouteurCmd->setType('info');
      $RouteurCmd->setTemplate('dashboard','huawei_dongle-antenna');
      $RouteurCmd->setSubType('string');
      $RouteurCmd->setOrder('9');
      $RouteurCmd->save();
    }

    $RouteurCmd = $this->getCmd(null, 'Msisdn');
    if (!is_object($RouteurCmd)) {
      log::add('huawei_dongle', 'debug', 'Msisdn');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('Numéro', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('Msisdn');
      $RouteurCmd->setType('info');
      $RouteurCmd->setTemplate('dashboard','huawei_dongle-antenna');
      $RouteurCmd->setSubType('string');
      $RouteurCmd->setOrder('11');
      $RouteurCmd->save();
    }


    $RouteurCmd = $this->getCmd(null, 'LocalInbox');
    if (!is_object($RouteurCmd)) {
      log::add('huawei_dongle', 'debug', 'LocalInbox');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('SMS Reçus', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('LocalInbox');
      $RouteurCmd->setType('info');
      $RouteurCmd->setTemplate('dashboard','huawei_dongle-sms');
      $RouteurCmd->setSubType('numeric');
      $RouteurCmd->setOrder('14');
      $RouteurCmd->save();
    }


    $RouteurCmd = $this->getCmd(null, 'LocalOutbox');
    if (!is_object($RouteurCmd)) {
      log::add('huawei_dongle', 'debug', 'LocalOutbox');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('SMS Envoyés', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('LocalOutbox');
      $RouteurCmd->setType('info');
      $RouteurCmd->setTemplate('dashboard','huawei_dongle-sms');
      $RouteurCmd->setSubType('numeric');
      $RouteurCmd->setOrder('15');
      $RouteurCmd->save();
    }
    $RouteurCmd = $this->getCmd(null, 'RestLocalInbox');
    if (!is_object($RouteurCmd)) {
      log::add('huawei_dongle', 'debug', 'LocalInbox');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('SMS restants', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('RestLocalInbox');
      $RouteurCmd->setType('info');
      $RouteurCmd->setTemplate('dashboard','huawei_dongle-sms');
      $RouteurCmd->setSubType('numeric');
      $RouteurCmd->setOrder('16');
      $RouteurCmd->save();
    }
    $RouteurCmd = $this->getCmd(null, 'Messages');
    if (!is_object($RouteurCmd)) {
      log::add('huawei_dongle', 'debug', 'Messages');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('SMS', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('Messages');
      $RouteurCmd->setType('info');
      $RouteurCmd->setTemplate('dashboard','huawei_dongle-smstxt');
      $RouteurCmd->setSubType('string');
      $RouteurCmd->setOrder('17');
      $RouteurCmd->save();
    }

    $RouteurCmd = $this->getCmd(null, 'reboot');
    if (!is_object($RouteurCmd)) {
      log::add('huawei_dongle', 'debug', 'reboot');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('Redémarrer', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('reboot');
      $RouteurCmd->setType('action');
      $RouteurCmd->setTemplate('dashboard','huawei_dongle-btn');
      $RouteurCmd->setSubType('other');
      $RouteurCmd->setOrder('18');
      $RouteurCmd->save();
    }


    $RouteurCmd = $this->getCmd(null, 'sendsms');
    if (!is_object($RouteurCmd)) {
      log::add('huawei_dongle', 'debug', 'sendsms');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('Envoyer SMS', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('sendsms');
      $RouteurCmd->setType('action');
      $RouteurCmd->setTemplate('dashboard','huawei_dongle-sendsms');
      $RouteurCmd->setSubType('message');
      $RouteurCmd->setOrder('21');
      $RouteurCmd->save();
    }

    $RouteurCmd = $this->getCmd(null, 'delsms');
    if (!is_object($RouteurCmd)) {
      log::add('huawei_dongle', 'debug', 'delsms');
      $RouteurCmd = new cmd();
      $RouteurCmd->setName(__('Supprimer SMS', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('delsms');
      $RouteurCmd->setType('action');
      $RouteurCmd->setTemplate('dashboard','huawei_dongle-delsms');
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

}

class huawei_dongleCmd extends cmd {
  /*     * *************************Attributs****************************** */


  /*     * ***********************Methode static*************************** */


  /*     * *********************Methode d'instance************************* */


  public function execute($_options = null) {
    $eqLogic = $this->getEqLogic();
    switch ($this->getLogicalId()) {
      case "reboot":
        log::add('huawei_dongle','debug','reboot ' . $this->getHumanName());
        $eqLogic->reboot();

        break;

      case "sendsms":
        log::add('huawei_dongle','debug','sendsms ' . $this->getHumanName());
        $return=$eqLogic->sendSMS($_options);
        $eqLogic->getSMSInfo();
        return $return;

        break;

      case "refresh":
        log::add('huawei_dongle','debug','refresh ' . $this->getHumanName());
       
        $eqLogic->getAllInfo();
        break;

      case "refreshsms":
        log::add('huawei_dongle','debug','refreshsms ' . $this->getHumanName());
        $eqLogic->getSMSInfo();
        break;

      case "delsms":
        log::add('huawei_dongle','debug','delsms ' . $this->getHumanName());
        $eqLogic->delSMS($_options);
        $eqLogic->getSMSInfo();
        break;


    }
    ;
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
				log::add('huawei_dongle', 'debug', 'Down - no data');
			}else if(intval($state['State']) == self::LOGGED_IN) {
				$this->statut = "Up";
			} else {
				$this->statut = "Down";
				log::add('huawei_dongle', 'debug', 'Down - no data');
			}
		} else {
			$this->statut = "Down";
			log::add('huawei_dongle', 'debug', 'Down');
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
		//log::add('huawei_dongle', 'debug', 'Output: '.$out);
		
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
			//log::add('huawei_dongle', 'debug', $key.': '.$this->output[$key]);
			$this->output[$key] = json_decode($this->output[$key], true);
			
			switch (json_last_error()) {
				case JSON_ERROR_NONE:
					//log::add('huawei_dongle', 'debug', ' - Aucune erreur');
				break;
				case JSON_ERROR_DEPTH:
					log::add('huawei_dongle', 'debug', ' - Profondeur maximale atteinte');
				break;
				case JSON_ERROR_STATE_MISMATCH:
					log::add('huawei_dongle', 'debug', ' - Inadéquation des modes ou underflow');
				break;
				case JSON_ERROR_CTRL_CHAR:
					log::add('huawei_dongle', 'debug', ' - Erreur lors du contrôle des caractères');
				break;
				case JSON_ERROR_SYNTAX:
					log::add('huawei_dongle', 'debug', ' - Erreur de syntaxe ; JSON malformé');
				break;
				case JSON_ERROR_UTF8:
					log::add('huawei_dongle', 'debug', ' - Caractères UTF-8 malformés, probablement une erreur d\'encodage');
				break;
				default:
					log::add('huawei_dongle', 'debug', ' - Erreur inconnue');
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
		//log::add('huawei_dongle', 'debug', 'PreOutputSMS: '.$out);
		
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
			log::add('huawei_dongle', 'debug', $key.': '.$this->outputSMS[$key]);
            $this->outputSMS[$key] = json_decode($this->outputSMS[$key],true);
          	//var_dump($out);
			switch (json_last_error()) {
				case JSON_ERROR_NONE:
					//log::add('huawei_dongle', 'debug', ' - Aucune erreur');
				break;
				case JSON_ERROR_DEPTH:
					log::add('huawei_dongle', 'debug', ' - Profondeur maximale atteinte');
				break;
				case JSON_ERROR_STATE_MISMATCH:
					log::add('huawei_dongle', 'debug', ' - Inadéquation des modes ou underflow');
				break;
				case JSON_ERROR_CTRL_CHAR:
					log::add('huawei_dongle', 'debug', ' - Erreur lors du contrôle des caractères');
				break;
				case JSON_ERROR_SYNTAX:
					log::add('huawei_dongle', 'debug', ' - Erreur de syntaxe ; JSON malformé');
                	log::add('huawei_dongle', 'debug', $key.': '.$this->outputSMS[$key]);
				break;
				case JSON_ERROR_UTF8:
					log::add('huawei_dongle', 'debug', ' - Caractères UTF-8 malformés, probablement une erreur d\'encodage');
				break;
				default:
					log::add('huawei_dongle', 'debug', ' - Erreur inconnue');
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
			log::add('huawei_dongle', 'debug', $e);
		}
		log::add('huawei_dongle', 'debug', $json);
		return $json;		
	}
	
	// SMS
	private function setSMSPython($tel, $msg) {
		$escapedArg = "'".str_replace("'", "'\\''", $msg)."'";
		$command = dirname(__FILE__) . '/../../resources/scripts/sender.py '.$this->ip.' '.$this->login.' '.$this->password.' '.$tel.' '.$this->encodeToUtf8($escapedArg);
		try{
			$json = shell_exec('python3 '.$command);
		} catch (Exception $e){
			log::add('huawei_dongle', 'debug', $e);
		}
		//log::add('huawei_dongle', 'debug', $json);
      return $json;
		//return json_decode($json, true);		
	}
	
	private function getSMSPython() {
		$command = dirname(__FILE__) . '/../../resources/scripts/getsms.py '.$this->ip.' '.$this->login.' '.$this->password;
		try{
         
			$json = shell_exec('python3 '.$command);
		} catch (Exception $e){
			log::add('huawei_dongle', 'debug', $e);
		}
		log::add('huawei_dongle', 'debug', $json);
		return $json;		
	}
	
	private function delSMSPython($ind) {
		$command = dirname(__FILE__) . '/../../resources/scripts/delsms.py '.$this->ip.' '.$this->login.' '.$this->password.' '.$ind;
		try{
			$json = shell_exec('python3 '.$command);
		} catch (Exception $e){
			log::add('huawei_dongle', 'debug', $e);
		}
		log::add('huawei_dongle', 'debug', $json);
		return json_decode($json, true);		
	}
	
	// Reboot
	private function reboot() {
		$command = dirname(__FILE__) . '/../../resources/scripts/reboot.py '.$this->ip.' '.$this->login.' '.$this->password;
		try{
			$json = shell_exec('python3 '.$command);
		} catch (Exception $e){
			log::add('huawei_dongle', 'debug', $e);
		}
		log::add('huawei_dongle', 'debug', $json);
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