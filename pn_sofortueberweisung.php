<?php

/**
 * @version sofortüberweisung.de 1.0 - $Date: 2010-04-13 17:00:21 +0200 (Di, 13 Apr 2010) $
 * @author Payment Network AG (integration@payment-network.com)
 * @link http://www.payment-network.com/
 *
 * Payment Network AG
 * Copyright (c) 2010 Payment Network AG
 *
 *
 * $Id: pn_sofortueberweisung.php 126 2010-04-13 15:00:21Z thoma $
 *
 */
 
define('PN_SU_INTERNALNAME', 'pn_sofortueberweisung');
define('PN_SU_DEFAULTDESCR', 'Sofortueberweisung.de <br />Online-&Uuml;berweisung mit T&Uuml;V gepr&uuml;ftem Datenschutz ohne Registrierung. Bitte halten Sie Ihre Online-Banking-Daten (PIN/TAN) bereit. Dienstleistungen/Waren werden bei Verf&uuml;gbarkeit SOFORT geliefert bzw. versendet!');
define('PN_SU_VERSION', 'pn_wpc_1.0');


$nzshpcrt_gateways[$num]['name'] = 'Sofortueberweisung.de';
$nzshpcrt_gateways[$num]['admin_name'] = 'Sofortueberweisung.de';
$nzshpcrt_gateways[$num]['internalname'] = PN_SU_INTERNALNAME;
$nzshpcrt_gateways[$num]['function'] = 'gateway_sofortueberweisung';
$nzshpcrt_gateways[$num]['form'] = "form_sofortueberweisung";
$nzshpcrt_gateways[$num]['submit_function'] = "submit_sofortueberweisung";
$nzshpcrt_gateways[$num]['payment_type'] = "manual_payment";

require(dirname(__FILE__).'/library/classPnSofortueberweisung.php');

function gateway_sofortueberweisung($seperator, $sessionid) {

	global $wpdb, $wpsc_cart;

	$sql = "SELECT `id` FROM `".WPSC_TABLE_PURCHASE_LOGS."` WHERE sessionid='$sessionid'";
	$orderId = $wpdb->get_var($sql);
	$wpdb->query("UPDATE `".WPSC_TABLE_PURCHASE_LOGS."` SET processed = '".get_option('pn_su_temp_status_id')."' WHERE id='".$orderId."' LIMIT 1");
	
	//setup bank-transaction data
	$total = $wpsc_cart->calculate_total_price();
	$total = number_format($total, 2, '.', '');

	$userId = get_option('pn_su_user_id');
	$projectId = get_option('pn_su_project_id');
	$reason1 = get_option('pn_su_reason1');
	$reason1 = substr($reason1, 0, 27);
	
	$reason2 = get_option('pn_su_reason2');
	$reason2 = preg_replace("#\{\{([0-9]+)\}\}#e", '$_POST[\'collected_data\'][$1]', $reason2);
	$reason2 = str_replace('{{order_date}}', strftime("%Y-%m-%d"), $reason2);
	$reason2 = str_replace('{{order_id}}', $orderId, $reason2);
	$reason2 = substr($reason2, 0, 27);	

	$user_variable_0 = $orderId; //order

	// success return url:
	$user_variable_2 = get_option('transact_url').$seperator."sessionid=".$sessionid.'&pn_su_result=success';
	$user_variable_2 = str_replace('http://', '', $user_variable_2);
	$user_variable_2 = str_replace('https://', '', $user_variable_2);
	
	// cancel return url:
	$user_variable_3 = get_option('transact_url').$seperator."sessionid=".$sessionid.'&pn_su_result=cancel';
	$user_variable_3 = str_replace('http://', '', $user_variable_3);
	$user_variable_3 = str_replace('https://', '', $user_variable_3);
	
    $currency_code = $wpdb->get_var("SELECT `code` FROM `".WPSC_TABLE_CURRENCY_LIST."` WHERE `id`='".get_option('currency_type')."' LIMIT 1");

    //build url and redirect to SU
	$obj = new classPnSofortueberweisung(get_option('pn_su_project_password'), get_option('pn_su_hash_algorithm'));
	$url= $obj->getPaymentUrl($userId, $projectId, $total, $currency_code, $reason1, $reason2, $user_variable_0, $user_variable_1, $user_variable_2, $user_variable_3, $user_variable_4);
	header('Location: '.$url);
	exit();
	
}


add_action('init', 'result_sofortueberweisung');
/**
 * responses from server after transaction
 * @param $_GET['pn_su_response'] state of transaction success|cancel|notification
 */
function result_sofortueberweisung(){
	if(empty($_GET['pn_su_result']))
		return false;
		

	global $wpsc_cart;
		
	switch($_GET['pn_su_result']) {
		case 'success':
			break;
		case 'cancel':
			break;
		case 'notification':
			$error = callback_sofortueberweisung();
			if($error != 'SUCCESS') {
				header("x-status: ".$error);
				print_r($_POST); 
			}
			echo $error;
			exit();
			break;
		default:
			echo 'unknown state';
			break;
	}
	return true;
}

/**
 * notification subfunction
 * this is where we update the db after a successful transaction
 * @return $data errorcode
 */
function callback_sofortueberweisung() {
	global $wpdb;
	
	$notificationPassword = get_option('pn_su_notification_password');
	$hashAlgorithm = get_option('pn_su_hash_algorithm');
	
	$obj = new classPnSofortueberweisung($notificationPassword, $hashAlgorithm);
	
	$data = $obj->getNotification();
	//we got an error
	if(!is_array($data))
		return $data;
	
	$order_id = preg_replace('/[^0-9]+/', '', $data['user_variable_0']); 
	$transaction = $data['transaction'];
		
	if (empty($order_id)) {
		return "ERROR_NO_ORDER_ID";
	}
	

	//check database for purchase/order
	$sql = "SELECT `totalprice` FROM `".WPSC_TABLE_PURCHASE_LOGS."` WHERE id='".$order_id."'";
	$amount = $wpdb->get_var($sql);
		
	if(is_null($amount)) {
		return 'ERROR_ORDER_NOT_FOUND';
	}
	if($amount != $data['amount']) {
		$wpdb->query("UPDATE `".WPSC_TABLE_PURCHASE_LOGS."` SET processed = '".get_option('pn_su_tocheck_status_id')."', track_id='".$transaction."'  WHERE id='".$order_id."' LIMIT 1");
		return 'ERROR_WRONG_TOTAL';
	}	

	$wpdb->query("UPDATE `".WPSC_TABLE_PURCHASE_LOGS."` SET processed = '".get_option('pn_su_confirmed_status_id')."', track_id='".$transaction."' WHERE id='".$order_id."' LIMIT 1");
	return 'SUCCESS';	
}


/**
 * names of configuration variables
 * @return multitype:string 
 */
function keys() {
	return array('pn_su_user_id', 
				'pn_su_project_id', 
				'pn_su_project_password', 
				'pn_su_notification_password',
				'pn_su_hash_algorithm', 
				'pn_su_reason1',
				'pn_su_reason2',
				'pn_su_confirmed_status_id',
				'pn_su_tocheck_status_id',
				'pn_su_temp_status_id'
	);
}


/**
 * submit configuration values
 */
function submit_sofortueberweisung() {
	
	foreach (keys() as $key) {
		if($_POST[$key] != null){
	    	update_option($key, $_POST[$key]);
	    }
	}
	return true;
}


add_action('init', 'install');
/**
 * setup db after install or autoinstall 
 */
function install(){
	global $wpdb;
	$obj = new classPnSofortueberweisung();
	
	if(!empty($_POST['autoinstall'])){
		$cancelLink = 'http://-USER_VARIABLE_3-';
		$successLink = 'http://-USER_VARIABLE_2-';
		$notificationLink = get_option('siteurl').'/index.php?pn_su_result=notification&transaction=-TRANSACTION-';
		$backLink = get_option('siteurl').'/wp-admin/admin.php?page=wpsc-settings&tab=gateway&postinstall=1';
		$currency_code = $wpdb->get_var("SELECT `code` FROM `".WPSC_TABLE_CURRENCY_LIST."` WHERE `id`='".get_option('currency_type')."' LIMIT 1");
		
		$out = $obj->getAutoInstallPage(get_option('blogname'),get_option('home'), get_option('admin_email'), get_option('base_country'), $currency_code,	
								$cancelLink, $successLink, $notificationLink, $backLink, 244);
		update_option('pn_su_project_password', $obj->password);
		update_option('pn_su_notification_password', $obj->password2);

		echo $out;
		exit();
	}
	if(!empty($_GET['postinstall'])){
			if(!empty($_GET['user_id']) && !empty($_GET['project_id'])) {
			update_option('pn_su_user_id', $_GET['user_id']);
			update_option('pn_su_project_id', $_GET['project_id']);
		}
		
	}
}


/**
 * config menue
 * @return htmlcode
 */
function form_sofortueberweisung() {
	global $wpdb;
	
	$purchaseStatuses = $wpdb->get_results("SELECT id as 'value',name as 'text' FROM `".WPSC_TABLE_PURCHASE_STATUSES."` WHERE `active`='1'",ARRAY_A);


$options = "
	<tr>
		<td>Kundennummer:</td>
		<td><input type='text' size='20' value='".get_option('pn_su_user_id')."' name='pn_su_user_id' /></td>
	</tr>
	<tr>
		<td>Projektnummer:</td>
		<td><input type='text' size='20' value='".get_option('pn_su_project_id')."' name='pn_su_project_id' /></td>
	</tr>	
	<tr>
		<td>Projektpasswort:</td>
		<td><input type='text' size='60' value='".get_option('pn_su_project_password')."' name='pn_su_project_password' /></td>
	</tr>	
	<tr>
		<td>Benachrichtigungspasswort:</td>
		<td><input type='text' size='60' value='".get_option('pn_su_notification_password')."' name='pn_su_notification_password' /></td>
	</tr>
	<tr>
		<td>Verwendungszweck Zeile 1:</td>
		<td>".get_option('pn_su_reason1')."
		</td>
	</tr>							
	<tr>
		<td>Verwendungszweck Zeile 2:</td>
		<td><input type='text' size='60' value='".get_option('pn_su_reason2')."' name='pn_su_reason2'/><br/>
		M&ouml;gliche Ersetzungsvariablen: {{order_date}}, {{order_id}}</td>
	</tr>	
	<tr>
		<td>best&auml;tigter Bestellstatus</td>
		<td><select name='pn_su_confirmed_status_id'>
				".drawDropdown($purchaseStatuses, get_option('pn_su_confirmed_status_id'))."
			</select> 
		</td>
	</tr>	
	<tr>
		<td>Zu &uuml;berpr&uuml;fender Bestellstatus</td>
		<td><select name='pn_su_tocheck_status_id'>
				".drawDropdown($purchaseStatuses, get_option('pn_su_tocheck_status_id'))."
			</select> 
		</td>
	</tr>	
	<tr>
		<td>Tempor&auml;rer Bestellstatus</td>
		<td><select name='pn_su_temp_status_id'>
				".drawDropdown($purchaseStatuses, get_option('pn_su_temp_status_id'))."
			</select> 
		</td>
	</tr>
	<tr>
		<td>Hash-Algorithmus:</td>
		<td>".get_option('pn_su_hash_algorithm')."
		</td>
	</tr>		
	<tr>
		<td colspan='2'>Benachrichtigungslink: 
		<small>".get_option('siteurl')."/index.php?pn_su_result=notification</strong></td>
	</tr>	
	<tr>
	<td colspan='2' align='right'><input value='autoinstall' name='autoinstall' type='submit'></td>
	</tr>
	";	

	//display regular form
	return $options;
}

/**
 * @param array $data array with all possible options array[] = bla or array[][value] = bla,array[][text] = blub
 * @param unknown_type $selected [optional] value of preselected option
 * @return string <option .. lines
 */
function drawDropdown($data, $selected = null){
	$output = '';
	foreach ($data as $option) {
		$output .= "<option ";
		if(!is_array($option)) {
			$text = $value = $option;
		}
		else {
			$text =	$option['text'];
			$value = $option['value'];
		}
		
		if($value == $selected)
			$output .= "selected='selected' ";
		
		$output .= "value='$value'>$text</option>\n";
	}
	
	return $output;
}	
?>