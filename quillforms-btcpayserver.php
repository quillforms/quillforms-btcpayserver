<?php
/**
 * Plugin Name:       Quill Forms BTCPayServer
 * Plugin URI:        https://quillforms.com
 * Description:       BTCPayServer extension for Quill Forms
 * Version:           1.1.2
 * Author:            quillforms.com
 * Author URI:        https://quillforms.com
 * Text Domain:       quillforms-btcpayserver
 * Domain Path:       /languages
 * Requires at least: 5.4
 * Requires PHP:      7.1
 *
 * @package QuillForms_BTCPayServer
 */

defined( 'ABSPATH' ) || exit;

// Plugin file.
if ( ! defined( 'QUILLFORMS_BTCPAYSERVER_PLUGIN_FILE' ) ) {
	define( 'QUILLFORMS_BTCPAYSERVER_PLUGIN_FILE', __FILE__ );
}

// Plugin version.
if ( ! defined( 'QUILLFORMS_BTCPAYSERVER_VERSION' ) ) {
	define( 'QUILLFORMS_BTCPAYSERVER_VERSION', '1.1.2' );
}

// Plugin Folder Path.
if ( ! defined( 'QUILLFORMS_BTCPAYSERVER_PLUGIN_DIR' ) ) {
	define( 'QUILLFORMS_BTCPAYSERVER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Plugin Folder URL.
if ( ! defined( 'QUILLFORMS_BTCPAYSERVER_PLUGIN_URL' ) ) {
	define( 'QUILLFORMS_BTCPAYSERVER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Require dependencies.
require_once QUILLFORMS_BTCPAYSERVER_PLUGIN_DIR . 'dependencies/vendor/autoload.php';

// Require autoload.
require_once QUILLFORMS_BTCPAYSERVER_PLUGIN_DIR . 'includes/autoload.php';

// Init the plugin after main plugin.
add_action(
	'quillforms_loaded',
	function() {
		QuillForms_BTCPayServer\BTCPayServer::instance();
	}
);
