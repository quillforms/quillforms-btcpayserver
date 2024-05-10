<?php
/**
 * Class: Admin_Ajax
 *
 * @since 1.0.0
 * @package QuillForms_BTCPayServer
 */

namespace QuillForms_BTCPayServer;

use Exception;
use BTCPayServer\Client\ApiKey;
use QuillForms_BTCPayServer\Helper;
use BTCPayServer\Client\Webhook;
use BTCPayServer\Result\Webhook as WebhookResult;
use BTCPayServer\Client\Store;

/**
 * Admin_Ajax Class
 *
 * @since 1.0.0
 */
class Admin_Ajax {

	/**
	 * Addon
	 *
	 * @since 1.0.0
	 *
	 * @var BTCPayServer
	 */
	private $addon;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 *
	 * @param BTCPayServer $addon BTCPayServer addon.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;
		// Get Api key.
		add_action( 'wp_ajax_quillforms_btcpayserver_get_api_key', array( $this, 'ajax_get_api_key' ) );
	}

	/**
	 * Get btcpayserver Api key.
	 *
	 * @return void
	 */
	public function ajax_get_api_key() {
		// Check nonce.
		check_ajax_referer( 'quillforms-btcpayserver', 'nonce' );

		$host = isset( $_POST['host'] ) ? sanitize_text_field( wp_unslash( $_POST['host'] ) ) : null;
		$mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'sandbox';
		if ( ! $host ) {
			wp_send_json_error( __( 'Invalid host.', 'quillforms-btcpayserver' ) );
		}

		$permissions = array_merge( BTCPayServer::REQUIRED_PERMISSIONS, BTCPayServer::OPTIONAL_PERMISSIONS );

		try {
			// Create the redirect url to BTCPay instance.
			$url = ApiKey::getAuthorizeUrl(
				$host,
				$permissions,
				__( 'QuillForms BTCPayServer', 'quillforms-btcpayserver' ),
				true,
				true,
				site_url( '?quillforms_btcpayserver_auth=' . $mode ),
				null
			);

			$site_option_name = "{$mode}_site_url";
			$this->addon->settings->update(
				[
					'mode'            => $mode,
					$site_option_name => $host,
				]
			);

			// Return the redirect url.
			wp_send_json_success( [ 'url' => $url ] );
		} catch ( \Throwable $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}
}
