<?php
/*PrestaShop SMS Notifier
این ماژول به شماره رسمی سند
005/2289/453/-8965
در سازمان اختراعات و ثبت نرم افزار جمهوری اسلامی ایران ثبت گرده است
استفاده از کدهای ماژول و قرار گیری در پنل مربوطه خود به هر نحو جرم محسوب می شود و پیگرد قانونی دارد

این ماژول صرفا با حق کپی برای پنل نوین پیامک ساخت و تهیه گردیده است
برای گرد آوری این ماژول برای پنل خود می توانید با ما ارتباط برقرار نمائید
* PrestaTools.Ir*/

if (!defined('_PS_VERSION_'))
    exit;

require_once (_PS_MODULE_DIR_ . 'smsnotifier/class.php');

class SmsNotifier extends Module
{
    private $_html = '';
    private $_postErrors = array();
    private $_hooks = array();
    private $_version = '';
    private $_ids = array();

    function __construct(){
        $this->name = 'smsnotifier';
        $this->version = '1.0';
        $this->author = 'Www.PrestaTools.Ir';
        $this->need_instance = 1;
        $this->ps_versions_compliancy['min'] = '1.4.6';
        $this->_version = (version_compare(_PS_VERSION_, '1.5.0') >= 0) ? '1.5' : '1.4';
        $this->_hooks = array(
                            '1.5' => array(
                                    array('name' => 'actionCustomerAccountAdd'), 
                                    array('name' => 'actionValidateOrder'), 
                                    array('name' => 'actionOrderStatusPostUpdate'),
                                    array('name' => 'smsNotifierOrderMessage', 'add' => true , 'title' => 'Send message in order page')),
                             '1.4' => array(
                                    array('name' => 'createAccount'), 
                                    array('name' => 'newOrder'), 
                                    array('name' => 'postUpdateOrderStatus'),
                                    array('name' => 'smsNotifierOrderMessage', 'add' => true , 'title' => 'Send message in order page')) 
                             );

        $this->initContext();
        parent::__construct();

        $this->displayName = $this->l('ارسال پیامک');
        $this->description = $this->l('ارسال پیامک در وضعیت های گوناگون برای مدیر و مشتری');
        $this->confirmUninstall = $this->l('ماژول ارسال پیامک حذف می شود، ادامه می دهید؟');

        /*$config = Configuration::getMultiple(array( 'SMSNOTIFIER_SERVICE',
                                                    'SMSNOTIFIER_PASSWORD', 
                                                    'SMSNOTIFIER_USERNAME', 
                                                    'SMSNOTIFIER_SENDER',
                                                    'SMSNOTIFIER_ADMIN_MOB', 
                                                    'SMSNOTIFIER_IS_FLASH'));
        foreach ($config as $con)
            if (!isset($conf)) {
                $this->warning = $this->l('ماژول ارسال پیامک پیکربندی نشده است');
                break;
            }*/
    }

    private function initContext(){
        if (class_exists('Context'))
            $this->context = Context::getContext();
        else {
            global $smarty, $cookie, $language;
            $this->context = new StdClass();
            $this->context->smarty = $smarty;
            $this->context->cookie = $cookie;
            $this->context->language = $language;
        }
    }

    public function install(){
        if (!parent::install() || 
            !$this->_installDatabase() || 
            !$this->_installConfig() ||
            !$this->_installHooks() ||
            !$this->_installFiles())
            return false;

        return true;
    }

    public function uninstall(){
        if (!parent::uninstall() || 
            !$this->_uninstallDatabase() || 
            !$this->_uninstallConfig() || 
            !$this->_uninstallHooks() ||
            !$this->_uninstallFiles())
            return false;
        return true;
    }

    private function _installDatabase(){
        // Add log table to database
        Db::getInstance()->Execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ .
            'smsnotifier_logs` (
			  `id_smsnotifier_logs` int(10) unsigned NOT NULL auto_increment,
			  `id_customer` int(10) unsigned default NULL,
			  `recipient` varchar(100) NOT NULL,
			  `phone` varchar(16) NOT NULL,
			  `event` varchar(64) NOT NULL,
			  `message` text NOT NULL,
			  `status` tinyint(1) NOT NULL default \'0\',
			  `unique` varchar(255) default NULL,
              `error` varchar(255) default NULL,
			  `date_add` datetime NOT NULL,
			  PRIMARY KEY  (`id_smsnotifier_logs`)
			) ENGINE=InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;');
        Db::getInstance()->Execute(
			'ALTER TABLE `' . _DB_PREFIX_ . 'smsnotifier_logs` ENGINE=InnoDB;'
		);

        return true;
    }

    private function _uninstallDatabase(){
        Db::getInstance()->Execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ .'smsnotifier_logs`');
        return true;
    }

    private function _installConfig(){
        Configuration::updateValue('SMSNOTIFIER_IS_FLASH', '0');
        return true;
    }

    private function _uninstallConfig(){
        Db::getInstance()->Execute('
			DELETE FROM `' . _DB_PREFIX_ . 'configuration`
			WHERE `name` like \'SMSNOTIFIER_%\'');
        Db::getInstance()->Execute('
			DELETE FROM `' . _DB_PREFIX_ . 'configuration`
			WHERE `name` like \'SMS_TXT_%\'');
        Db::getInstance()->Execute('
			DELETE FROM `' . _DB_PREFIX_ . 'configuration`
			WHERE `name` like \'SMS_ISACTIVE_%\'');
        Db::getInstance()->Execute('
			DELETE FROM `' . _DB_PREFIX_ . 'configuration_lang`
			WHERE `id_configuration` NOT IN (SELECT `id_configuration` from `' .
            _DB_PREFIX_ . 'configuration`)');
        return true;
    }

    private function _installHooks(){
        foreach ($this->_hooks[$this->_version] as $hook) {
            if (isset($hook['add']) and version_compare(_PS_VERSION_, '1.6.0') < 0 )
				Db::getInstance()->Execute('INSERT INTO `'._DB_PREFIX_.'hook` (name,title,description,position) VALUES ("'.$hook['name'].'", "'.$hook['title'].'", "", "0")');


            if (!$this->registerHook($hook['name']))
                return false;
        }
        return true;
    }

    private function _uninstallHooks(){
        
        foreach ($this->_hooks[$this->_version] as $hook) {
            if (!$this->unregisterHook($hook['name']))
                return false;
                
            if (isset($hook['add']))
				Db::getInstance()->Execute('DELETE FROM `'._DB_PREFIX_.'hook` WHERE `name` like \''.$hook['name'].'%\'');
        }
        return true;
    }
    
    private function _installFiles() {
        if($this->_version == '1.4')
		  $this->_modifyFile(_PS_ADMIN_DIR_.'/tabs/AdminOrders.php', 'smsNotifierOrderMessage', 
                            "if (@Mail::Send((int)(\$order->id_lang), 'order_merchant_comment'", 
                            "Module::hookExec('smsNotifierOrderMessage', array('customer' => '', 'order' => \$message->id_order, 'message' => \$message->message));\nif (@Mail::Send((int)(\$order->id_lang), 'order_merchant_comment'");
		
        elseif($this->_version == '1.5')
            $this->_modifyFile('../controllers/admin/AdminOrdersController.php', 'smsNotifierOrderMessage', 
                            "if (@Mail::Send((int)\$order->id_lang, 'order_merchant_comment'",
                            "Module::hookExec('smsNotifierOrderMessage', array('customer' => '', 'order' => \$order->id, 'message' => \$message));\nif (@Mail::Send((int)\$order->id_lang, 'order_merchant_comment'");

		return true;
	}

	private function _uninstallFiles() {
	   if($this->_version == '1.4')
            $this->_restoreFile(_PS_ADMIN_DIR_.'/tabs/AdminOrders.php', 'smsNotifierOrderMessage');
        
       elseif($this->_version == '1.5')
             $this->_restoreFile('../controllers/admin/AdminOrdersController.php', 'smsNotifierOrderMessage');    
		
		return true;
	}

	private function _modifyFile($path, $search, $replace1, $replace2) {
		if (file_exists($path)) {
			$fd = fopen($path, 'r');
			$contents = fread($fd, filesize($path));
			if (strpos($contents, $search) === false) {
			    copy($path, $path . '-savedbysmsnotifier');
				$content2 = $contents;
				if (is_array($replace1) && is_array($replace2)) {
					foreach($replace1 as $key => $val1) {
						$contents = str_replace($val1, $replace2[$key], $contents);
					}
				} else
					$contents = str_replace($replace1, $replace2, $contents);
				fclose($fd);
				//copy($path, $path . '-savedbysmsnotifier');
				$fd = fopen($path, 'w+');
				fwrite($fd, $contents);
				fclose($fd);
			} else {
				fclose($fd);
			}
		}
	}

	private function _restoreFile($path, $search) {
		if (file_exists($path. '-savedbysmsnotifier')) {
		    @unlink($path);
            copy($path . '-savedbysmsnotifier', $path);
            @unlink($path. '-savedbysmsnotifier');
			/*$fd = fopen($path, 'r');
			$contents = fread($fd, filesize($path));
			if (is_array($search)) {
				foreach($search as $val) {
					$contents = str_replace($val, "", $contents);
				}
			} else
				$contents = str_replace($search, "", $contents);

			fclose($fd);
			$fd = fopen($path, 'w+');
			fwrite($fd, $contents);
			fclose($fd);
			@unlink($path . '-savedbysmsnotifier');*/
		}
	}

    public function hookActionCustomerAccountAdd($params){
        $sms = new SmsClass();
        $sms->send('actionCustomerAccountAdd', $params);
    }
    
    public function hookCreateAccount($params){
        $this->hookActionCustomerAccountAdd($params);
        return;
    }

    public function hookActionValidateOrder($params){
        $sms = new SmsClass();
        $sms->send('actionValidateOrder', $params);
    }
    
    public function hookNewOrder($params){
        $this->hookActionValidateOrder($params);
        return;
    }

    public function hookActionOrderStatusPostUpdate($params){
        $sms = new SmsClass();
        $sms->send('actionOrderStatusPostUpdate', $params);
    }
    
    public function hookPostUpdateOrderStatus($params){
        $this->hookActionOrderStatusPostUpdate($params);
        return;
    }
    
    public function hookSmsNotifierOrderMessage($params){
        $sms = new SmsClass();
        $sms->send('smsNotifierOrderMessage', $params);
    }
    
    public function getContent(){
        if($this->_version == '1.5' or $this->_version == '1.6')
            foreach($this->_hooks[$this->_version] as $hook)
                $this->_ids[$hook['name']] = Hook::getIdByName($hook['name']);
        else
            foreach($this->_hooks[$this->_version] as $hook)
                $this->_ids[$hook['name']] = Hook::get($hook['name']);

		$this->_html .= $this->headerHTML();
		$this->_html .= '<h2>'.$this->displayName.'.</h2>';

		/* Validate & process */
		if (Tools::isSubmit('submitSmsnotifier') || Tools::isSubmit('submitSmsnotifierText'))
		{
			if ($this->_postValidation())
                $this->_postProcess();
			$this->_displayForm();
		}elseif(Tools::isSubmit('submitSmsnotifierSend')){
			if( Tools::getValue('SMSNOTIFIER_MESSAGE_CUSTOMER') != ''){
			
				$text = Tools::getValue('SMSNOTIFIER_MESSAGE_CUSTOMER');
				$numbers = $this->_getNumbers();
				$this->_postProcessSend($text ,$numbers);
			
			}else $this->_html .= '<div class="alert error">متن پیام ارسال را وارد کنید</div>';
			$this->_displayForm();
		}elseif(Tools::isSubmit('submitSmsnotifierSendONE')){
			if( Tools::getValue('SMSNOTIFIER_MESSAGE_MOBILE') != '' 
				or Tools::getValue('SMSNOTIFIER_MOBILE') != ''){
				
				$text = Tools::getValue('SMSNOTIFIER_MESSAGE_MOBILE');
				$numbers = Tools::getValue('SMSNOTIFIER_MOBILE');
				
				$this->_postProcessSend($text ,$numbers);	
				
			}else $this->_html .= '<div class="alert error">متن پیام و شماره همراه را وارد نمایید.</div>';
			$this->_displayForm();
		}else
			$this->_displayForm();

		return $this->_html;
	}
    
    private function  services_list() {
        $path = _PS_MODULE_DIR_.$this->name.'/services';
        $m = '<div class="margin-form" style="min-height:20px;">';
        if ($handle = opendir($path)) {
            $selected = Tools::getValue('SMSNOTIFIER_SERVICE', Configuration::get('SMSNOTIFIER_SERVICE'));
            $m .= '<select name="SMSNOTIFIER_SERVICE" style="min-width:155px; padding: 2px 2px">';
            $m .= '<option value="0">انتخاب کنید </option>';
            while (false !== ($file = readdir($handle))) {
                if ('.' === $file) continue;
                if ('..' === $file) continue;
                    include_once($path.'/'.$file);
                    $class = 'class_'.$file;
                    $class = str_replace( array('.php', '.PHP','.Php'), '', $class);
                    
                    $instance = new $class;
                    $name = $instance->name;
                    
                    $s = '';
                    if($class == $selected){
                        $s = 'selected="selected"';
                    }
                    
                    $m .= '<option value="'.$class.'" '.$s.'>'. $name .'</option>';
        
            }
            closedir($handle);
           
           $m .= '</select>'; 
        }//end if
        $m .= '</div>';
        
        return $m;
    }
    
    private function _displayForm(){
        foreach($this->_ids as $name => $id){    
            $check[$name] = (Tools::getValue('SMS_ISACTIVE_'.$id, Configuration::get('SMS_ISACTIVE_'.$id)) == 1) ? 'checked="checked"' : "";
            $check_admin[$name] = (Tools::getValue('SMS_ISACTIVE_'.$id.'_ADMIN', Configuration::get('SMS_ISACTIVE_'.$id.'_ADMIN')) == 1) ? 'checked="checked"' : "";
        }
          
   
        $this->_html .= '
		<fieldset>
			<legend><img src="'._PS_BASE_URL_.__PS_BASE_URI__.'modules/'.$this->name.'/logo.gif" alt="" /> تنظیمات پنل </legend>';

        $this->_html .= '<form action="'.Tools::safeOutput($_SERVER['REQUEST_URI']).'" method="post">';
        
        
        //service
        $this->_html .= '<label>سرویس دهنده : </label>';
        $this->_html .= $this->services_list();
        /*$_parto = Tools::getValue('SMSNOTIFIER_SERVICE', Configuration::get('SMSNOTIFIER_SERVICE')) == '1' ? 'checked="checked"' : '';
        $_iran = Tools::getValue('SMSNOTIFIER_SERVICE', Configuration::get('SMSNOTIFIER_SERVICE')) == '2' ? 'checked="checked" ' : '';
        $this->_html .= '
		<label for="service">سرویس دهنده : </label>
		<div class="margin-form">
			<input type="radio" name="SMSNOTIFIER_SERVICE" id="parto" '.$_parto.' value="1" />
			<label class="t" for="parto">پرتو اس ام اس</label>

			<input type="radio" name="SMSNOTIFIER_SERVICE" id="2972" '.$_iran.' value="2" />
			<label class="t" for="2972">2972.ir</label>
		</div>';*/
        
        //username
       	$this->_html .= '
		<label>نام کاربری : </label>
		<div class="margin-form">
			<input type="text" name="SMSNOTIFIER_USERNAME" id="username" size="25" value="'.htmlentities(Tools::getValue('SMSNOTIFIER_USERNAME', Configuration::get('SMSNOTIFIER_USERNAME'))).'" /> 
		</div>';
        
        //password
        $this->_html .= '
		<label>کلمه عبور : </label>
		<div class="margin-form">
			<input type="text" name="SMSNOTIFIER_PASSWORD" id="password" size="25" value="'.htmlentities(Tools::getValue('SMSNOTIFIER_PASSWORD', Configuration::get('SMSNOTIFIER_PASSWORD'))).'" /> 
		</div>';
        
        //sender
        $this->_html .= '
		<label>شماره اختصاصی : </label>
		<div class="margin-form">
			<input type="text" name="SMSNOTIFIER_SENDER" id="sender" size="25" value="'.htmlentities(Tools::getValue('SMSNOTIFIER_SENDER', Configuration::get('SMSNOTIFIER_SENDER'))).'" /> 
		</div>';
        
        //admin phone
        $this->_html .= '
		<label>شماره مدیر فروشگاه : </label>
		<div class="margin-form">
			<input type="text" name="SMSNOTIFIER_ADMIN_MOB" id="sender" size="25" value="'.htmlentities(Tools::getValue('SMSNOTIFIER_ADMIN_MOB', Configuration::get('SMSNOTIFIER_ADMIN_MOB'))).'" /> 
		</div>';
        
        // flash sms
        $_val_1 = (Tools::getValue('SMSNOTIFIER_IS_FLASH', Configuration::get('SMSNOTIFIER_IS_FLASH')) == 1) ? 'checked="checked"' : '';
        $_val_0 = (Tools::getValue('SMSNOTIFIER_IS_FLASH', Configuration::get('SMSNOTIFIER_IS_FLASH')) == 2) ? 'checked="checked"' : '';
        $this->_html .= '
		<label for="service">ارسال پیامک بصورت فلش : </label>
		<div class="margin-form">
			<input type="radio" name="SMSNOTIFIER_IS_FLASH" id="yes" '.$_val_1.' value="1" />
			<label class="t" for="yes">بله</label>

			<input type="radio" name="SMSNOTIFIER_IS_FLASH" id="no" '.$_val_0.' value="2" />
			<label class="t" for="no">خیر</label>
		</div>';
        
        // save
        $this->_html .= '
		<div class="margin-form">
			<input type="submit" class="button" name="submitSmsnotifier" value="ذخیره" />
		</div>';       
		$this->_html .= '</fieldset>';

		$this->_html .= '<br /><br />';
		$this->_html .= '
			<fieldset>
				<legend><img src="'._PS_BASE_URL_.__PS_BASE_URI__.'modules/'.$this->name.'/logo.gif" alt="" />ارسال پیام به مشتریان</legend>';   
        $this->_html .= '
		<label>متن پیام: </label>
		<div class="margin-form">
			<textarea style="overflow: auto" name="SMSNOTIFIER_MESSAGE_CUSTOMER" rows="4" cols="50"></textarea>	
		</div>'; 				
		$numbers = $this->_getNumbers();
        $this->_html .= '
		<div class="margin-form">تعداد <b>'. count($numbers) .'</b> شماره موبایل از مشتریان پیدا شد.</div>';		
        $this->_html .= '
		<div class="margin-form">
			<input type="submit" class="button" name="submitSmsnotifierSend" value="ارسال پیام" />
		</div>';	
		
		$this->_html .= '</fieldset>';    
		$this->_html .= '<br /><br />';
		$this->_html .= '
			<fieldset>
				<legend><img src="'._PS_BASE_URL_.__PS_BASE_URI__.'modules/'.$this->name.'/logo.gif" alt="" />ارسال پیام به مشتری خاص</legend>';   
        $this->_html .= '
		<label>شماره همراه: </label>
		<div class="margin-form">
			<input type="text" name="SMSNOTIFIER_MOBILE"  size="25" /> 
		</div>'; 	
		$this->_html .= '
		<label>متن پیام: </label>
		<div class="margin-form">
			<textarea style="overflow: auto" name="SMSNOTIFIER_MESSAGE_MOBILE" rows="4" cols="50"></textarea>	
		</div>'; 				
				
        $this->_html .= '
		<div class="margin-form">
			<input type="submit" class="button" name="submitSmsnotifierSendONE" value="ارسال پیام" />
		</div>';	
		
		$this->_html .= '</fieldset>';  		
        $this->_html .= '<br /><br />';
		$this->_html .= '
		<fieldset>
			<legend><img src="'._PS_BASE_URL_.__PS_BASE_URI__.'modules/'.$this->name.'/logo.gif" alt="" /> پیامک ها </legend>';
        
        //
        $this->_html .= '
			<div id="smstext" style="width: 400px; margin-top: 30px;">
				<ul id="boxes">';
        
        $helper = array(
                            '1.5' => array(
                                    'actionCustomerAccountAdd' => array('name' =>'ایجاد حساب کاربری جدید', 'admin' => true, 'tags' => ' {firstname}, {lastname}, {email}, {passwd}, {shopname}, {shopurl}'), 
                                    'actionValidateOrder' => array('name' =>'ثبت سفارش جدید', 'admin' => true, 'tags' => ' {firstname}, {lastname}, {order_id}, {payment}, {total_paid}, {currency}, {shopname}, {shopurl}'), 
                                    'actionOrderStatusPostUpdate' => array('name' =>'تغییر وضعیت سفارش', 'admin' => false, 'tags' => ' {firstname}, {lastname}, {order_id}, {order_state}, {shopname}, {shopurl}'), 
                                    'smsNotifierOrderMessage' => array('name' =>'ارسال پیام در صفحه سفارش', 'admin' => false, 'tags' => ' {firstname}, {lastname}, {order_id}, {message}, {shopname}, {shopurl}')),
                             '1.4' => array(
                                    'createAccount' => array('name' =>'ایجاد حساب کاربری جدید', 'admin' => true, 'tags' => ' {firstname}, {lastname}, {email}, {passwd}, {shopname}, {shopurl}'), 
                                    'newOrder' => array('name' =>'ثبت سفارش جدید', 'admin' => true, 'tags' => ' {firstname}, {lastname}, {order_id}, {payment}, {total_paid}, {currency}, {shopname}, {shopurl}'),  
                                    'postUpdateOrderStatus' => array('name' =>'تغییر وضعیت سفارش', 'admin' => false, 'tags' => ' {firstname}, {lastname}, {order_id}, {order_state}, {shopname}, {shopurl}'), 
                                    'smsNotifierOrderMessage' => array('name' =>'ارسال پیام در صفحه سفارش', 'admin' => false, 'tags' => ' {firstname}, {lastname}, {order_id}, {message}, {shopname}, {shopurl}'))
                             );
             
        foreach ($this->_ids as $name => $id)
        {
            if($helper[$this->_version][$name]['admin'])
                $this->_html .='
			     <li id="box_admin_'.$id.'">
			         <p style="float: right">
                        <label>'.$helper[$this->_version][$name]['name'].'</label>
                        <div class="margin-form">	
                            <div style="padding-bottom: 4px">
                                <input type="checkbox" name="SMS_ISACTIVE_'.$id.'_ADMIN" value="1" '.$check_admin[$name].'>فعال؟
                            </div>
                                <label for="text_admin_'.$id.'"><strong style="color:#C55;">برای مدیر</strong></label>
                                <textarea id="text_admin_'.$id.'" style="overflow: auto" name="SMS_TXT_'.$id.'_ADMIN" rows="4" cols="50">'.htmlentities(Tools::getValue('SMS_TXT_'.$id.'_ADMIN', Configuration::get('SMS_TXT_'.$id.'_ADMIN')), ENT_COMPAT, 'UTF-8').'</textarea>
                                <div id="help_admin_'.$id.'" class="clear" style="padding-top: 4px; padding-bottom: 10px;direction: ltr;">تگ های مجاز </br>'.$helper[$this->_version][$name]['tags'].' </div>
                        </div>
                    </p>
				</li>';
            
            
			$this->_html .= '
			     <li id="box_'.$id.'">
			         <p style="float: right">
                        <label>'.$helper[$this->_version][$name]['name'].'</label>
                        <div class="margin-form">	
                            <div style="padding-bottom: 4px">
                                <input type="checkbox" name="SMS_ISACTIVE_'.$id.'" value="1" '.$check[$name].'>فعال؟
                            </div>
                                <label for="text_'.$id.'"><strong style="color:#5A5;">برای کاربر</strong></label>
                                <textarea id="text_'.$id.'" style="overflow: auto" name="SMS_TXT_'.$id.'" rows="4" cols="50">'.htmlentities(Tools::getValue('SMS_TXT_'.$id, Configuration::get('SMS_TXT_'.$id)), ENT_COMPAT, 'UTF-8').'</textarea>
                                <div id="help_'.$id.'" class="clear" style="padding-top: 4px; padding-bottom: 10px;direction: ltr;">تگ های مجاز </br>'.$helper[$this->_version][$name]['tags'].' </div>
                        </div>
                    </p>
				</li>';
		}
		$this->_html .= '</ul></div>';
    
     // save
     $this->_html .= '
		<div class="margin-form">
			<input type="submit" class="button" name="submitSmsnotifier" value="ذخیره" />
		</div>';
  
   $this->_html .= '</form>';
    //
    $this->_html .= '<div style="padding:10px; margin:5px; text-align: center; font: 11px tahoma;">سایت منتشر کننده
		<a href="http://prestatools.ir" target="_blank"><img src="http://prestatools.ir/themes/ot_jewelry/images/logo.png" alt="" width="274" height="86" /></a>
	     <br><br>
		   <a href="http://novinpayamak.com/">مختص به سامانه نوین پیامک</a></div>';

	}
    
    private function headerHTML(){
		return ;
	}

    private function _postValidation(){
        
        if (!Tools::getValue('SMSNOTIFIER_SERVICE'))
            $this->_postErrors[] = 'سرویس دهنده انتخاب نشده است';
            
        if (!Tools::getValue('SMSNOTIFIER_USERNAME') or !Validate::isString(Tools::getValue('SMSNOTIFIER_USERNAME')))
            $this->_postErrors[] = 'نام کاربری وارد شده نامعتبر است';
            
        if (!Tools::getValue('SMSNOTIFIER_PASSWORD') or !Validate::isString(Tools::getValue('SMSNOTIFIER_PASSWORD')))
            $this->_postErrors[] = 'رمز عبور وارد شده نامعتبر است';
            
        if (!Tools::getValue('SMSNOTIFIER_SENDER') or !Validate::isString(Tools::getValue('SMSNOTIFIER_SENDER')))
            $this->_postErrors[] = 'شماره اختصاصی وارد شده نامعتبر است';
        
        if (!Tools::getValue('SMSNOTIFIER_ADMIN_MOB') or !Validate::isString(Tools::getValue('SMSNOTIFIER_ADMIN_MOB')))
            $this->_postErrors[] = 'شماره ای که برای مدیر وارد شده نامعتبر است';
            
        if (!Tools::getValue('SMSNOTIFIER_IS_FLASH') or !Validate::isInt(Tools::getValue('SMSNOTIFIER_IS_FLASH')))
            $this->_postErrors[] = 'تنظیمات ارسال پیامک بصورت فلش انجام نشده';
            
        foreach ($this->_ids as $name => $id)
        {
            if(!Validate::isCleanHtml(Tools::getValue('SMS_TXT_'.$id.'_ADMIN')))
                $this->_postErrors[] = 'داده های وارد شده به عنوان متن پیامک نامعتبر هستند، لطفن دوباره آن ها را بررسی کنید';
        
            if(!Validate::isCleanHtml(Tools::getValue('SMS_TXT_'.$id)))
                $this->_postErrors[] = 'داده های وارد شده به عنوان متن پیامک نامعتبر هستند، لطفن دوباره آن ها را بررسی کنید';
        }

        if(!count($this->_postErrors))
            return true;
            
        foreach ($this->_postErrors as $err)
					$this->_html .= '<div class="alert error">'.$err.'</div>';
                    
        return false;
    }

    private function _postProcess(){
        $helper = array(
                            '1.5' => array(
                                    'actionCustomerAccountAdd' => array('name' =>'ایجاد حساب کاربری جدید', 'admin' => true, 'tags' => ' {firstname}, {lastname}, {shopname}, {shopurl}'), 
                                    'actionValidateOrder' => array('name' =>'ثبت سفارش جدید', 'admin' => true, 'tags' => ' {firstname}, {lastname}, {order_id}, {payment}, {total_paid}, {currency}, {shopname}, {shopurl}'), 
                                    'actionOrderStatusPostUpdate' => array('name' =>'تغییر وضعیت سفارش به ارسال شده', 'admin' => false, 'tags' => ' {firstname}, {lastname}, {order_id}, {order_state}, {shopname}, {shopurl}'), 
                                    'smsNotifierOrderMessage' => array('name' =>'ارسال پیام در صفحه سفارش', 'admin' => false, 'tags' => ' {firstname}, {lastname}, {order_id}, {message}, {shopname}, {shopurl}')),
                             '1.4' => array(
                                    'createAccount' => array('name' =>'ایجاد حساب کاربری جدید', 'admin' => true, 'tags' => ' {firstname}, {lastname}, {shopname}, {shopurl}'), 
                                    'newOrder' => array('name' =>'ثبت سفارش جدید', 'admin' => true, 'tags' => ' {firstname}, {lastname}, {order_id}, {payment}, {total_paid}, {currency}, {shopname}, {shopurl}'),  
                                    'postUpdateOrderStatus' => array('name' =>'تغییر وضعیت سفارش به ارسال شده', 'admin' => false, 'tags' => ' {firstname}, {lastname}, {order_id}, {order_state}, {shopname}, {shopurl}'), 
                                    'smsNotifierOrderMessage' => array('name' =>'ارسال پیام در صفحه سفارش', 'admin' => false, 'tags' => ' {firstname}, {lastname}, {order_id}, {message}, {shopname}, {shopurl}'))
                             );

        if (Configuration::updateValue('SMSNOTIFIER_SERVICE', Tools::getValue('SMSNOTIFIER_SERVICE')) AND
            Configuration::updateValue('SMSNOTIFIER_USERNAME', Tools::getValue('SMSNOTIFIER_USERNAME')) AND
            Configuration::updateValue('SMSNOTIFIER_PASSWORD', Tools::getValue('SMSNOTIFIER_PASSWORD')) AND
            Configuration::updateValue('SMSNOTIFIER_SENDER', Tools::getValue('SMSNOTIFIER_SENDER')) AND
            Configuration::updateValue('SMSNOTIFIER_ADMIN_MOB', Tools::getValue('SMSNOTIFIER_ADMIN_MOB')) AND
            Configuration::updateValue('SMSNOTIFIER_IS_FLASH', Tools::getValue('SMSNOTIFIER_IS_FLASH'))
            ) {
                $res = true;
                foreach ($this->_ids as $name => $id)
                {
                    if($helper[$this->_version][$name]['admin']){
                        if(!Configuration::updateValue('SMS_TXT_'.$id.'_ADMIN', Tools::getValue('SMS_TXT_'.$id.'_ADMIN')) OR
                           !Configuration::updateValue('SMS_ISACTIVE_'.$id.'_ADMIN', Tools::getValue('SMS_ISACTIVE_'.$id.'_ADMIN')))
                           $res = false;
                    }
                    
                    if(!Configuration::updateValue('SMS_TXT_'.$id, Tools::getValue('SMS_TXT_'.$id)) OR
                       !Configuration::updateValue('SMS_ISACTIVE_'.$id, Tools::getValue('SMS_ISACTIVE_'.$id)))
                           $res = false;
                }
                if($res)
                    $this->_html .= $this->displayConfirmation('تنظیمات بروز شد');
                else
                    $this->_html .= $this->displayErrors('خطایی رخ داده است، لطفن دوباره تلاش کنید'); 
        } else
            $this->_html .= $this->displayErrors('خطایی رخ داده است، لطفن دوباره تلاش کنید'); // an Error occured


    }
    
    private function _postProcessSend($text,$numbers){
		if($text != ''){
			$sms = new SmsClass();
			$res = $sms->sendMessageAllCustomer(array('numbers'=>$numbers,'text'=>$text));
			if($res  ){
				$this->_html .= $this->displayConfirmation('عمليات با موفقيت انجام شده است.');					
			}else
				$this->_html .= $this->displayConfirmation('اشکال در ارسال پیامک');			
		}
    }
	private function _getNumbers(){
		$numbers = array();
		$result =  Db::getInstance()->executeS('SELECT phone_mobile FROM '._DB_PREFIX_.'address');
		foreach($result as $res){
			if( $res['phone_mobile'] != '' and 
					!in_array($res['phone_mobile'],$numbers)){
					$numbers[] = $res['phone_mobile'];
			}
		}	
		return $numbers;
	}
  

 
}
?>