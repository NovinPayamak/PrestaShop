<?php
class smsLogs extends ObjectModel
{
	public 		$id_customer;
	public 		$recipient;
	public 		$phone;
	public 		$event;
	public 		$message;
    public      $uique;
	public 		$status;
	public 		$error;
	public 		$date_add;

	protected	$fieldsRequired = array();
	protected	$fieldsValidate = array();
	protected   $fieldsSize = array();

	protected	$fieldsRequiredLang = array();
	protected	$fieldsSizeLang = array();
	protected	$fieldsValidateLang = array();

	protected 	$table = 'smsnotifier_logs';
	protected 	$identifier = 'id_smsnotifier_logs';

	public function getFields()
	{
		parent::validateFields();
		$fields['id_customer']           = intval($this->id_customer);
		$fields['recipient']        	 = pSQL($this->recipient);
		$fields['phone']              	 = pSQL($this->phone);
		$fields['event']             	 = pSQL($this->event);
		$fields['message']           	 = pSQL($this->message, true);
		$fields['unique']   	         = pSQL($this->unique);
		$fields['status']   			 = intval($this->status);
		$fields['error']   			 	 = pSQL($this->error);
		$fields['date_add']              = pSQL($this->date_add);
		return $fields;
	}
}


class SmsClass
{
    public $_hookName;
	public $_params;
	public $_phone = '';
	public $_username;
	public $_password;
	public $_txt = '';
	public $_recipient;
	public $_event = '';
    private $_alternative_hooks = array('actionCustomerAccountAdd' => 'createAccount', 
                                    'actionValidateOrder' => 'newOrder', 
                                    'actionOrderStatusPostUpdate' => 'postUpdateOrderStatus',
                                    'smsNotifierOrderMessage' => 'smsNotifierOrderMessage');

    
    private  $_config = array(
	    'actionValidateOrder' => 3, //both 
	    'actionOrderStatusPostUpdate' => 2, //customer
	    'actionCustomerAccountAdd' => 3 ,
        'smsNotifierOrderMessage' => 2
	);
    
    public function __construct()
    {
        
    }
    
    public function send($hookName, $params)
	{
		$this->_hookName = $hookName;
		$this->_params = $params;

		$dest = $this->_config[$this->_hookName];
		if ($dest == 2 || $dest == 3) {
			$this->_prepareSms();
		}
		if ($dest == 1 || $dest == 3) {
			$this->_prepareSms(true);
		}

	}
    
    private function _prepareSms($bAdmin = false)
	{
		/*if (class_exists('Context'))
            $context = Context::getContext();
        else {
            global $smarty, $cookie, $language;
            $context = new StdClass();
            $context->language = $language;
        }*/

		$method = '_get' . ucfirst($this->_hookName) . 'Values';
		if (method_exists(__CLASS__, $method)) {
            if(version_compare(_PS_VERSION_, '1.5.0') < 0) //  1.4
                $hookId = Hook::get($this->_alternative_hooks[$this->_hookName]);
            else
			    $hookId = Hook::getIdByName($this->_hookName);
            
			if ($hookId) {
				$this->_recipient = null;
				$this->_event = $this->_hookName;
				$idLang =  null ;
                
				switch ($this->_hookName) {
					case 'actionOrderStatusPostUpdate':
						$stateId = $this->_params['newOrderStatus']->id;
                        //if($stateId == Configuration::get('PS_OS_SHIPPING')){
                            $order = new Order((int)$this->_params['id_order']);
						    $idLang = $order->id_lang;
						    $keyActive = 'SMS_ISACTIVE_' . $hookId;
						    $keyTxt = 'SMS_TXT_' . $hookId;
						    $this->_event .= '_SHIPPING';
						    $values = $this->$method(false, false);    
                        //}
						
						break;
					default :
						$keyActive = ($bAdmin) ? 'SMS_ISACTIVE_' . $hookId . '_ADMIN' : 'SMS_ISACTIVE_' . $hookId;
						$keyTxt = ($bAdmin) ? 'SMS_TXT_' . $hookId . '_ADMIN' : 'SMS_TXT_' . $hookId;
						$values = $this->$method(false, $bAdmin);
						break;
				}

				if (is_array($values) && $this->_isEverythingValidForSending($keyActive, $keyTxt, $idLang, $bAdmin)) {
					$this->_txt = str_replace(array_keys($values), array_values($values), Configuration::get($keyTxt));
					$this->_sendSMS();
				}
			}
		}
	}
    
    private function _isEverythingValidForSending($keyActive, $keyTxt, $idLang=null, $bAdmin=false)
	{
	    if(Configuration::get($keyActive) != 1)
            return false;
		$this->_username = Configuration::get('SMSNOTIFIER_USERNAME');
        $this->_password = Configuration::get('SMSNOTIFIER_PASSWORD');
       
		if (!empty($this->_phone) &&
            Configuration::get('SMSNOTIFIER_SENDER') &&
            Configuration::get('SMSNOTIFIER_SERVICE') &&
            Configuration::get('SMSNOTIFIER_ADMIN_MOB') &&
            $this->_username &&
            $this->_password)
			return true;
           
		return false;
	}
    
    private function _sendSMS()
	{
        if(($service = Configuration::get('SMSNOTIFIER_SERVICE'))){
            
            $s  = str_replace('class_', '', $service);
            include_once(_PS_MODULE_DIR_.'smsnotifier/services/'.$s.'.php');
            
            $sms    = new $service;
            
            
        }else{
            return false;
        }
       
		//$sms = new SMS();
		$sms->setSmsLogin($this->_username); 
		$sms->setSmsPassword($this->_password);
		$sms->setSmsText($this->_txt);
		$sms->setNums(array($this->_phone));
        $flash = (Configuration::get('SMSNOTIFIER_IS_FLASH') == 1) ? 1 : 0;
		$sms->setType($flash);
		$sms->setSender(Configuration::get('SMSNOTIFIER_SENDER'));
		$reponse = $sms->send();
		$result = @explode('_', $reponse);

		$log = new smsLogs();
		if (isset($this->_recipient)) {
			$log->id_customer = $this->_recipient->id;
			$log->recipient = $this->_recipient->firstname . ' ' . $this->_recipient->lastname;

		} else {
			$log->recipient = '--';
		}
		$log->phone = $this->_phone;
		$log->event = $this->_event;
		$log->message = $this->_txt;
        $log->unique = $result[1];
		$log->status = ($result[0] == 'OK') ? 1 : 0;
		$log->error = ($result[0] == 'KO') ? $result[1] : null;   
		$log->save();

		if ($result[0] == 'OK')
			return true;
		return false;
	}
    
    private function _setPhone($addressId, $bAdmin)
	{
		$this->_phone = '';
		if ($bAdmin)
			$this->_phone = Configuration::get('SMSNOTIFIER_ADMIN_MOB');
		else if (!empty($addressId)) {
			$address = new Address($addressId);
			if (!empty($address->phone_mobile)) {
				$this->_phone = $address->phone_mobile;
			}
		}
	}
    
    private function _setRecipient($customer)
	{
		$this->_recipient = $customer;
	}
    
    private function _getBaseValues() {
		$host = 'http://'.Tools::getHttpHost(false, true);

		$values = array(
			'{shopname}' => Configuration::get('PS_SHOP_NAME'),
			'{shopurl}' => $host.__PS_BASE_URI__
		);
		return $values;
	}
    
    private function _getActionValidateOrderValues($bSimu = false, $bAdmin = false)
	{
		
			$order = $this->_params['order'];
			$customer = $this->_params['customer'];
			$currency = $this->_params['currency'];


			if (!$bAdmin)
				$this->_setRecipient($customer);
			$this->_setPhone($order->id_address_delivery, $bAdmin);

			$values = array(
				'{firstname}' => $customer->firstname,
				'{lastname}' => $customer->lastname,
				'{order_id}' => sprintf("%06d", $order->id),
				'{payment}' => $order->payment,
				'{total_paid}' => $order->total_paid,
				'{currency}' => $currency->sign
			);
		
		return array_merge($values, $this->_getBaseValues());
	}
    
    private function _getActionCustomerAccountAddValues($bSimu = false, $bAdmin = false)
	{

			$customer = $this->_params['newCustomer'];
			if (!$bAdmin)
				$this->_setRecipient($customer);
			$this->_setPhone(Address::getFirstCustomerAddressId($customer->id), $bAdmin);

			$values = array(
				'{firstname}' => $customer->firstname,
				'{lastname}' => $customer->lastname,
                '{email}' => $customer->email,
                '{passwd}' => $this->_params['_POST']['passwd']
			);

		return array_merge($values, $this->_getBaseValues());
	}
    
    private function _getActionOrderStatusPostUpdateValues($bSimu = false, $bAdmin = false)
	{

			$order = new Order((int)$this->_params['id_order']);
			$state = $this->_params['newOrderStatus']->name;
			$customer = new Customer((int)$order->id_customer);


			$this->_setRecipient($customer);
			$this->_setPhone($order->id_address_delivery, false);

			$values = array(
				'{firstname}' => $customer->firstname,
				'{lastname}' => $customer->lastname,
				'{order_id}' => sprintf("%06d", $order->id),
				'{order_state}' => $state
			);
		
		return array_merge($values, $this->_getBaseValues());
	} 
    
    private function _getSmsNotifierOrderMessageValues($bSimu = false, $bAdmin = false)
	{  
            $order = new Order((int)$this->_params['order']);
			$customer = $customer = new Customer((int)$order->id_customer);
            $message  = $this->_params['message'];

			$this->_setRecipient($customer);
			$this->_setPhone($order->id_address_delivery, false);

			$values = array(
				'{firstname}' => $customer->firstname,
				'{lastname}' => $customer->lastname,
				'{order_id}' => $order->id,
				'{message}'  => $message
			);
		
		return array_merge($values, $this->_getBaseValues());
	} 
    public function sendMessageAllCustomer($params){
        if(($service = Configuration::get('SMSNOTIFIER_SERVICE'))){
            
            $s  = str_replace('class_', '', $service);
            include_once(_PS_MODULE_DIR_.'smsnotifier/services/'.$s.'.php');
            
            $sms    = new $service;
            
            
        }else{
            return false;
        }
		$this->_username = Configuration::get('SMSNOTIFIER_USERNAME');
        $this->_password = Configuration::get('SMSNOTIFIER_PASSWORD');       
		//$sms = new SMS();
		$sms->setSmsLogin($this->_username); 
		$sms->setSmsPassword($this->_password);
		$sms->setSmsText($params['text']);
		$sms->setNums($params['numbers']);
        $flash = (Configuration::get('SMSNOTIFIER_IS_FLASH') == 1) ? 1 : 0;
		$sms->setType($flash);
		$sms->setSender(Configuration::get('SMSNOTIFIER_SENDER'));
		$reponse = $sms->send();
		$result = @explode('_', $reponse);

		$log = new smsLogs();
		if (isset($this->_recipient)) {
			$log->id_customer = $this->_recipient->id;
			$log->recipient = $this->_recipient->firstname . ' ' . $this->_recipient->lastname;

		} else {
			$log->recipient = '--';
		}
		$log->phone = $this->_phone;
		$log->event = $this->_event;
		$log->message = $this->_txt;
        $log->unique = $result[1];
		$log->status = ($result[0] == 'OK') ? 1 : 0;
		$log->error = ($result[0] == 'KO') ? $result[1] : null;   
		$log->save();

		if ($result[0] == 'OK')
			return true;
		return false;
	}
}
?>