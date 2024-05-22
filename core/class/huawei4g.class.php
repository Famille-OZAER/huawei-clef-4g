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
  require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
require_once dirname(__FILE__) . '/router.class.php';

class huawei4g extends eqLogic {
  /*     * *************************Attributs****************************** */
  public static $_widgetPossibility = array('custom' => true);
  const ERROR_SYSTEM_UNKNOWN = '100001';
  const ERROR_SYSTEM_NO_SUPPORT = '100002';
  const ERROR_SYSTEM_NO_RIGHTS = '100003';
  const ERROR_SYSTEM_BUSY = '100004';
  const ERROR_SYSTEM_PARAMETER = '100006';
  const ERROR_SYSTEM_CSRF = '125002';

  /*     * ***********************Methode static*************************** */
  public static function dependancy_info() {
    $return = array();
    $return['progress_file'] = jeedom::getTmpFolder('huawei4g') . '/dependance';
    //if (exec(system::getCmdSudo() . system::get('cmd_check') . ' -E "python3\-huawei\-lte\-api" | wc -l') >= 1) {
    if (exec(system::getCmdSudo() . ' python3 -c "import huawei_lte_api"; echo $?') == 0) {
      $return['state'] = 'ok';
    } else {
      $return['state'] = 'nok';
    }

    return $return;
  }
  public static function dependancy_install() {
    log::remove(__CLASS__ . '_update');
    return array('script' => dirname(__FILE__) . '/../../resources/install.sh ' . jeedom::getTmpFolder('huawei4g') . '/dependance', 'log' => log::getPathToLog(__CLASS__ . '_update'));
  }

  public static function update($_eqLogic_id = null) {
    if ($_eqLogic_id == null) {
      $eqLogics = eqLogic::byType('huawei4g');
    } else {
      $eqLogics = array(eqLogic::byId($_eqLogic_id));
    }
    foreach ($eqLogics as $rtr) {
      try {
        $rtr->postSave();
        $rtr->getRouteurInfo();
        $rtr->getSMSInfo();
      } catch (Exception $e) {
        log::add('huawei4g', 'error', $e->getMessage());
      }
    }
  }

  public static function cron5() {
    foreach (self::byType('huawei4g') as $rtr) {
      if ($rtr->getIsEnable() == 1) {
        $cmd = $rtr->getCmd(null, 'refresh');
        if (!is_object($cmd)) {
          continue; 
        }
        if($rtr->getConfiguration('frequence') == '5') {
          $cmd->execCmd();
        }
      }
    }
  }

  public static function cron15() {
    foreach (self::byType('huawei4g') as $rtr) {
      if ($rtr->getIsEnable() == 1) {
        $cmd = $rtr->getCmd(null, 'refresh');
        if (!is_object($cmd)) {
          continue; 
        }
        if($rtr->getConfiguration('frequence') == '15') {
          $cmd->execCmd();
        }
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
    if ($this->getConfiguration('frequence') == '') {
      throw new Exception(__('Le champs fréquence ne peut pas être vide', __FILE__));
    }
  }

  public function getRouteurInfo() {
    // getting configuration
    $IPaddress = $this->getConfiguration('ip');
    $login = $this->getConfiguration('username');
    $pwd = $this->getConfiguration('password');
    $this->infos = array();

    // setting the router session
    $Router = new Router();
    $Router->setIP($IPaddress);

    // calling API
    try {
      $Router->setSession($login, $pwd, "get");
      $this->infos['status'] = $Router->getStatus();

      if($this->infos['status'] == "Up") {
        $this->setInfo($Router->getPublicLandMobileNetwork());
        $this->setInfo($Router->getCellInfo());

      }
    } catch (Exception $e) {
      log::add('huawei4g', 'error', $e);
    }

    $this->updateInfo();
  }

  public function getSMSInfo() {
    // getting configuration
    $IPaddress = $this->getConfiguration('ip');
    $login = $this->getConfiguration('username');
    $pwd = $this->getConfiguration('password');
    $RtrName = $this->getName();

    $this->infos = array();

    // setting the router session
    $Router = new Router();
    $Router->setIP($IPaddress);

    // calling API
    try {
      $Router->setSession($login, $pwd, "sms");
      $this->infos['status'] = $Router->getStatus();

      if($this->infos['status'] == "Up") {
        $this->setInfo($Router->getSMS());
        $this->setInfo($Router->getSMSCount());
      }
    } catch (Exception $e) {
      log::add('huawei4g', 'error', $e);
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
      log::add('huawei4g', 'debug', 'function  has a NULL parameter');
    }
  }

  public function reboot() {
    // getting configuration
    $IPaddress = $this->getConfiguration('ip');
    $login = $this->getConfiguration('username');
    $pwd = $this->getConfiguration('password');

    // setting the router session
    $Router->setIP($IPaddress);
    try {
      $Router->setSession($login, $pwd, "");
      $res = $Router->setReboot();
    } catch (Exception $e) {
      log::add('huawei4g', 'error', $e);
    }

    log::add('huawei4g', 'debug', 'Rebooting: '.$res);
  }

  public function sendSMS($arr) {
    // getting configuration
    $IPaddress = $this->getConfiguration('ip');
    $login = $this->getConfiguration('username');
    $pwd = $this->getConfiguration('password');
    $texteMode = $this->getConfiguration('texteMode');

    // setting the router session
    $Router = new Router();
    $Router->setIP($IPaddress);
    try {
      if($texteMode == 1) {
        $messageSMS = $this->cleanSMS($arr['message']);
      } else {
        $messageSMS = $arr['message'];
      }
      $Router->setSession($login, $pwd, "");
      $numero_tel="Vide";
      if(isset($arr['numerotel'])) {
        $numero_tel=$arr['numerotel'];
      }
      if(isset($arr['title'])) {
        $numero_tel=$arr['title'];

      }
      log::add('huawei4g', 'debug', 'numerotel: '. $numero_tel);
      log::add('huawei4g', 'debug', 'message: '.$messageSMS);
      if(empty($arr['numerotel'])) {
        $res = json_decode($Router->sendSMS($arr['title'], $messageSMS));
      } else {
        $res = json_decode($Router->sendSMS($arr['numerotel'], $messageSMS));
      }
    } catch (Exception $e) {
      log::add('huawei4g', 'error', $e);
    }
    //log::add('huawei4g', 'debug', 'Message: '.$res->message);
    log::add('huawei4g', 'debug', 'Retour: '.$res->retour);
    return $res->retour;
  }

  public function delSMS($arr) {
    // getting configuration
    $IPaddress = $this->getConfiguration('ip');
    $login = $this->getConfiguration('username');
    $pwd = $this->getConfiguration('password');

    // setting the router session
    $Router = new Router();
    $Router->setIP($IPaddress);
    try {
      $Router->setSession($login, $pwd, "");
      log::add('huawei4g', 'debug', 'smsid: '.$arr['smsid']);
      if(empty($arr['smsid'])) {
        log::add('huawei4g', 'debug', 'smsid empty');
      } else {
        $res = $Router->delSMS($arr['smsid']);
      }
    } catch (Exception $e) {
      log::add('huawei4g', 'error', $e);
    }

    log::add('huawei4g', 'debug', 'Sending: '.$res);
  }

  // manage API errors
  private function errorInfo($code) {
    switch($code) {
      case ERROR_SYSTEM_BUSY: 
        $e = "System busy";
        break;
      case ERROR_SYSTEM_CSRF: 
        $e = "Token error";
        break;
      case ERROR_SYSTEM_NO_RIGHTS: 
        $e = "You don't have rights";
        break;
      case ERROR_SYSTEM_NO_SUPPORT: 
        $e = "API not supported";
        break;
      case ERROR_SYSTEM_PARAMETER: 
        $e = "Wrong parameter";
        break;
      case ERROR_SYSTEM_UNKNOWN: 
        $e = "Unknown API error";
        break;	
      default:
        $e = "UNKNOWN ERROR";
    }

    return $e;
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
        //log::add('huawei4g', 'debug', $cle . ': valeur '.$valeur);

        $value = $this->infos[$cle];
        if($cle == "Messages"){

          if(strpos($value, '[')=== FALSE) {
            $value = "[" .  $value. "]";
          }
        }


        $cmd=cmd::byEqLogicIdAndLogicalId($eqLogic->getId(), $cle);
        if (is_object($cmd)){

          $this->checkAndUpdateCmd($cmd, $value);
          log::add('huawei4g', 'debug', 'MAJ '.$cle . ': '.$value);
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
          log::add('huawei4g', 'debug', 'MAJ RestLocalInbox: '.$restants);
        }

      }
    } catch (Exception $e) {
      log::add('huawei4g', 'error', 'Impossible de mettre à jour le champs '.$key);
    }
    //}
  }


  /*     * *********************Methode d'instance************************* */
  public function preSave() {

  }

  public function postSave() {
    $RouteurCmd = $this->getCmd(null, 'refresh');
    if (!is_object($RouteurCmd)) {
      log::add('huawei4g', 'debug', 'refresh');
      $RouteurCmd = new huawei4gCmd();
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
      log::add('huawei4g', 'debug', 'FullName');
      $RouteurCmd = new huawei4gCmd();
      $RouteurCmd->setName(__('Réseau mobile', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('FullName');
      $RouteurCmd->setType('info');
      $RouteurCmd->setTemplate('dashboard','huawei4g-antenna');
      $RouteurCmd->setSubType('string');
      $RouteurCmd->setOrder('2');
      $RouteurCmd->save();
    }

    $RouteurCmd = $this->getCmd(null, 'refreshsms');
    if (!is_object($RouteurCmd)) {
      log::add('huawei4g', 'debug', 'refreshsms');
      $RouteurCmd = new huawei4gCmd();
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
      log::add('huawei4g', 'debug', 'status');
      $RouteurCmd = new huawei4gCmd();
      $RouteurCmd->setName(__('Statut', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('status');
      $RouteurCmd->setType('info');
      $RouteurCmd->setTemplate('dashboard','huawei4g-power');
      $RouteurCmd->setSubType('string');
      $RouteurCmd->setOrder('4');
      $RouteurCmd->save();
    }

    $RouteurCmd = $this->getCmd(null, 'devicename');
    if (!is_object($RouteurCmd)) {
      log::add('huawei4g', 'debug', 'devicename');
      $RouteurCmd = new huawei4gCmd();
      $RouteurCmd->setName(__('Modèle', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('devicename');
      $RouteurCmd->setType('info');
      $RouteurCmd->setTemplate('dashboard','huawei4g-linux');
      $RouteurCmd->setSubType('string');
      $RouteurCmd->setOrder('5');
      $RouteurCmd->save();
    }

    $RouteurCmd = $this->getCmd(null, 'workmode');
    if (!is_object($RouteurCmd)) {
      log::add('huawei4g', 'debug', 'workmode');
      $RouteurCmd = new huawei4gCmd();
      $RouteurCmd->setName(__('Mode', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('workmode');
      $RouteurCmd->setType('info');
      $RouteurCmd->setTemplate('dashboard','huawei4g-antenna');
      $RouteurCmd->setSubType('string');
      $RouteurCmd->setOrder('9');
      $RouteurCmd->save();
    }

    $RouteurCmd = $this->getCmd(null, 'Msisdn');
    if (!is_object($RouteurCmd)) {
      log::add('huawei4g', 'debug', 'Msisdn');
      $RouteurCmd = new huawei4gCmd();
      $RouteurCmd->setName(__('Numéro', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('Msisdn');
      $RouteurCmd->setType('info');
      $RouteurCmd->setTemplate('dashboard','huawei4g-antenna');
      $RouteurCmd->setSubType('string');
      $RouteurCmd->setOrder('11');
      $RouteurCmd->save();
    }


    $RouteurCmd = $this->getCmd(null, 'LocalInbox');
    if (!is_object($RouteurCmd)) {
      log::add('huawei4g', 'debug', 'LocalInbox');
      $RouteurCmd = new huawei4gCmd();
      $RouteurCmd->setName(__('SMS Reçus', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('LocalInbox');
      $RouteurCmd->setType('info');
      $RouteurCmd->setTemplate('dashboard','huawei4g-sms');
      $RouteurCmd->setSubType('numeric');
      $RouteurCmd->setOrder('14');
      $RouteurCmd->save();
    }


    $RouteurCmd = $this->getCmd(null, 'LocalOutbox');
    if (!is_object($RouteurCmd)) {
      log::add('huawei4g', 'debug', 'LocalOutbox');
      $RouteurCmd = new huawei4gCmd();
      $RouteurCmd->setName(__('SMS Envoyés', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('LocalOutbox');
      $RouteurCmd->setType('info');
      $RouteurCmd->setTemplate('dashboard','huawei4g-sms');
      $RouteurCmd->setSubType('numeric');
      $RouteurCmd->setOrder('15');
      $RouteurCmd->save();
    }
    $RouteurCmd = $this->getCmd(null, 'RestLocalInbox');
    if (!is_object($RouteurCmd)) {
      log::add('huawei4g', 'debug', 'LocalInbox');
      $RouteurCmd = new huawei4gCmd();
      $RouteurCmd->setName(__('SMS restants', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('RestLocalInbox');
      $RouteurCmd->setType('info');
      $RouteurCmd->setTemplate('dashboard','huawei4g-sms');
      $RouteurCmd->setSubType('numeric');
      $RouteurCmd->setOrder('16');
      $RouteurCmd->save();
    }
    $RouteurCmd = $this->getCmd(null, 'Messages');
    if (!is_object($RouteurCmd)) {
      log::add('huawei4g', 'debug', 'Messages');
      $RouteurCmd = new huawei4gCmd();
      $RouteurCmd->setName(__('SMS', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('Messages');
      $RouteurCmd->setType('info');
      $RouteurCmd->setTemplate('dashboard','huawei4g-smstxt');
      $RouteurCmd->setSubType('string');
      $RouteurCmd->setOrder('17');
      $RouteurCmd->save();
    }

    $RouteurCmd = $this->getCmd(null, 'reboot');
    if (!is_object($RouteurCmd)) {
      log::add('huawei4g', 'debug', 'reboot');
      $RouteurCmd = new huawei4gCmd();
      $RouteurCmd->setName(__('Redémarrer', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('reboot');
      $RouteurCmd->setType('action');
      //$RouteurCmd->setTemplate('dashboard','huawei4g-btn');
      $RouteurCmd->setSubType('other');
      $RouteurCmd->setOrder('18');
      $RouteurCmd->save();
    }


    $RouteurCmd = $this->getCmd(null, 'sendsms');
    if (!is_object($RouteurCmd)) {
      log::add('huawei4g', 'debug', 'sendsms');
      $RouteurCmd = new huawei4gCmd();
      $RouteurCmd->setName(__('Envoyer SMS', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('sendsms');
      $RouteurCmd->setType('action');
      $RouteurCmd->setTemplate('dashboard','huawei4g-sendsms');
      $RouteurCmd->setSubType('message');
      $RouteurCmd->setOrder('21');
      $RouteurCmd->save();
    }

    $RouteurCmd = $this->getCmd(null, 'delsms');
    if (!is_object($RouteurCmd)) {
      log::add('huawei4g', 'debug', 'delsms');
      $RouteurCmd = new huawei4gCmd();
      $RouteurCmd->setName(__('Supprimer SMS', __FILE__));
      $RouteurCmd->setEqLogic_id($this->getId());
      $RouteurCmd->setLogicalId('delsms');
      $RouteurCmd->setType('action');
      $RouteurCmd->setTemplate('dashboard','huawei4g-delsms');
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

class huawei4gCmd extends cmd {
  /*     * *************************Attributs****************************** */


  /*     * ***********************Methode static*************************** */


  /*     * *********************Methode d'instance************************* */


  public function execute($_options = null) {
    $eqLogic = $this->getEqLogic();
    switch ($this->getLogicalId()) {
      case "reboot":
        log::add('huawei4g','debug','reboot ' . $this->getHumanName());
        $eqLogic->reboot();

        break;

      case "sendsms":
        log::add('huawei4g','debug','sendsms ' . $this->getHumanName());
        $return=$eqLogic->sendSMS($_options);
        $eqLogic->getSMSInfo();
        $eqLogic->refreshWidget();
        return $return;

        break;

      case "refresh":
        log::add('huawei4g','debug','refresh ' . $this->getHumanName());
        $eqLogic->getRouteurInfo();
        $eqLogic->getSMSInfo();
        $eqLogic->refreshWidget();
        break;

      case "refreshsms":
        log::add('huawei4g','debug','refreshsms ' . $this->getHumanName());
        $eqLogic->getSMSInfo();
        $eqLogic->refreshWidget();
        break;

      case "delsms":
        log::add('huawei4g','debug','delsms ' . $this->getHumanName());
        $eqLogic->delSMS($_options);
        //$eqLogic->getRouteurInfo();
        $eqLogic->getSMSInfo();
        $eqLogic->refreshWidget();
        break;


    }
    ;
    return true;
  }

  /*     * **********************Getteur Setteur*************************** */
}

?>