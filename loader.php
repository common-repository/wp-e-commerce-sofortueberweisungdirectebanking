<?php 
/*
Plugin Name:WP e-Commerce sofortueberweisung.de
Plugin URI: http://www.payment-network.com
Description: Mit sofortüberweisung.de stellen Sie noch während Ihrer Bestellung eine Überweisung über den jeweiligen Betrag in Ihr Online-Banking-Konto ein. 
Version: 1.0
Author: Payment Network AG
Author URI: http://www.payment-network.com
*/

if (!class_exists('pnLoader')) {
	class pnLoader {
	
		function pnLoader() {
			// Init options & tables during activation & deregister init option
			register_activation_hook( __file__, array(&$this, 'activate' ));
			register_deactivation_hook( __file__, array(&$this, 'deactivate' ));	
			if(get_option('pn_su_msg')) {
				add_action( 'admin_notices', create_function('', 'echo \'<div id="message" class="error"><p><strong>'.get_option('pn_su_msg').'</strong></p></div>\';') );
				delete_option('pn_su_msg');
			}
		}
		
		/**
		 * activate the plugin
		 */		
		function activate() {
			$suDir = dirname(__file__);
			$pluginDir = dirname(dirname(__file__)).'/wp-e-commerce/merchants';
			$sourceFile = $suDir.'/inc_pn_sofortueberweisung.php';
			$destinationFile = $pluginDir.'/inc_pn_sofortueberweisung.php';
		
			//copy to wpsc-dir
			if(file_exists($pluginDir)) {
				copy($sourceFile, $destinationFile);
				if(!file_exists($destinationFile))
					update_option('pn_su_msg', 'bitte inc_pn_sofortueberweisung.php manuell nach wp-e-commerce/merchants kopieren');
			} 
			else
				update_option('pn_su_msg', 'wp-e-commerce nicht gefunden, bitte installieren.');
			
			//default settings
			require('library/classPnSofortueberweisung.php');
			$obj = new classPnSofortueberweisung();
			update_option('pn_su_reason1', '-TRANSACTION-');
			update_option('pn_su_reason2', 'Nr {{order_id}} '.get_option('blogname'));
			update_option('pn_su_hash_algorithm', $obj->getSupportedHashAlgorithm());
			update_option('pn_su_confirmed_status_id', 2);
			update_option('pn_su_tocheck_status_id', 1);
			update_option('pn_su_temp_status_id', 1);
			update_option('pn_su_user_id', 0);
			update_option('pn_su_project_id', 0);
			$user_defined_name[PN_SU_INTERNALNAME] = PN_SU_DEFAULTDESCR;
			$payment_gateway_names = get_option('payment_gateway_names');
			if(!is_array($payment_gateway_names)) {
				$payment_gateway_names = array();
			}
		  	$payment_gateway_names = array_merge($payment_gateway_names, $user_defined_name);
			update_option('payment_gateway_names', $payment_gateway_names);				
		}

		/**
		 * deactivate the plugin
		 */			
		function deactivate() {
			$wpsc_plugin_dir = dirname(dirname(__file__)).'/wp-e-commerce/merchants';
			
			//delete wpsc-plugin
			if(file_exists($wpsc_plugin_dir.'/inc_pn_sofortueberweisung.php')) {
				unlink($wpsc_plugin_dir.'/inc_pn_sofortueberweisung.php');
			}
			
			//delete config
			foreach (keys() as $key) {
		    	delete_option($key);
			}
		}
		
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
	
	}
	
	$pnLoad = new pnLoader();
}


?>