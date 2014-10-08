<?php
require_once(_PS_MODULE_DIR_.'smsnotifier/lib/nusoap/nusoap.php');
@date_default_timezone_set('Asia/Tehran');

class class_novinpayamak{
     public  $name  =   'نوین پیامک';
	 private $smsLogin; 	// string
	 private $smsPassword; 	// string
	 private $prestaKey; 	// string

	 private $sms_text; // string

 	 private $t_nums; 	// array
 	 private $t_fields_1; 	// array
 	 private $t_fields_2; 	// array
 	 private $t_fields_3; 	// array

	 private $type; 	// int
	 private $d; 	// int
 	 private $m; 	// int
 	 private $h; 	// int
 	 private $i; 	// int
 	 private $y; 	// int

	 private $sender;  	// string
	 private $simulation; 	// int

	 private $list_name; // string
    
	 public function __construct()
	 {
		$this->smsLogin = '';
		$this->smsPassword = '';
		$this->prestaKey = '';

		$this->sms_text = '';

		$this->t_nums = array();
		$this->t_fields_1 = array();
		$this->t_fields_2 = array();
		$this->t_fields_3 = array();

		$this->type = '';
 		$this->d = 0;
 		$this->m = 0;
 		$this->h = 0;
 		$this->i = 0;
 		$this->y = 0;

 		$this->sender 	= '';
 		$this->list_name = '';
 		$this->simulation 	= 0;
	}
    

	public function send(){
		$domain   = 'http://www.novinpayamak.com';  
		$path     = '/services/SMSBox/wsdl'; 

		$data = array(
			'username'     	=> $this->smsLogin, // نام کاربری که در تنظیمات وارد شده
			'password'	    => $this->smsPassword, // رمز عبور که در تنظیمات وارد شده
			'text'		    => $this->sms_text,  // متن پیامک
			'to'		    => $this->t_nums,    //شماره هایی که پیامک به آنها ارسل می شود
			'isflash'		=> $this->type,   // ارسال پیامک بصورت فلش که در تنظیمات انتخاب شده
			'from'		    => $this->sender,    // شماره اختصاصی که در تنظیمات وارد شده
			'domain'		=> $domain,    // در بالا تعریف شد
			'path'			=> $path    // در بالا تعریف شد
		);
		$result =  $this->soapSend($data);
        
        switch ($result[0]){ 
	       case '1': $res = 'OK_'.$result[1];
	       break;

	       default : $res = 'KO_Error number:'.$result[0];
        }
        
        return $res;
      
	}
	public function soapSend($data)
	{
		$this->client_object = new nusoap_client($data['domain'] . $data['path'] ,true);
		$this->client_object->soap_defencoding = 'utf-8';
        
		$args = array(
			array(
					'Auth' 	=> array('number' => $data['from'], 'pass' => $data['password']),
					'Recipients'=> array('string' => $data['to']),
					'Message' 	=> array('string' => $data['text']),
					'Flash' 	=> $data['isflash']
				)
		);
		
		$result = $this->client_object->call('Send', $args);
		if($result['Status']){
			return array('1', $result['Status']);
		}else{
			return array('0', $result['Status']);
		}
	}
    
	public function setSmsLogin($login)
	{
   		$this->smsLogin = $login;
 	}

	public function setSmsPassword($password)
	{
   		$this->smsPassword = $password;
 	}

	public function setSmsText($text)
	{
   		$this->sms_text = $text;
 	}

 	public function setType($type)
 	{
 		$this->type = $type;
 	}

	public function setNums($nums)
	{
		$this->t_nums = $nums;
	}

	public function setSender($sender)
	{
		$this->sender = $sender;
	}

}
?>