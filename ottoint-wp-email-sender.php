<?php
/**
 * Plugin Name: Otto International WP Email Sender
 * Plugin URI: git
 * Description: This plugin will send email automatically as per the trigger set.
 * Version: 1.0.0
 * Author: Shomen Muhury
 * Author URI: http://122.248.192.24:3000/
 *
 */
 
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'OTTOINT_PLUGIN_FILE' ) ) {
	define( 'OTTOINT_PLUGIN_FILE', __FILE__ );
}

// Include the main class
if ( ! class_exists( 'OttointEmailSender' ) ) {
	require_once(sprintf("%s/include/class-ottoint-email-sender.php", dirname(OTTOINT_PLUGIN_FILE)));		
}

if(class_exists('OttointEmailSender')){
	$ottoint_email_sender = New OttointEmailSender();	// Call main Class
	
}

