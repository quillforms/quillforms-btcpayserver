<?php
/**
 * BTCPayServer_Helper class.
 *
 * @since 1.0.0
 *
 * @package QuillForms_BTCPayServer
 */

namespace QuillForms_BTCPayServer;

use Exception;
use BTCPayServer\Client\Webhook;
use BTCPayServer\Result\Webhook as WebhookResult;
use BTCPayServer\Client\Store;

/**
 * Helper Class
 *
 * @since 1.0.0
 */
class BTCPayServer_Helper {

	const WEBHOOK_EVENTS = [
		'InvoiceReceivedPayment',
		'InvoicePaymentSettled',
		'InvoiceProcessing',
		'InvoiceExpired',
		'InvoiceSettled',
		'InvoiceInvalid',
	];

	public const REQUIRED_PERMISSIONS = [
		'btcpay.store.canviewinvoices',
		'btcpay.store.cancreateinvoice',
		'btcpay.store.canviewstoresettings',
		'btcpay.store.canmodifyinvoices',
	];
	public const OPTIONAL_PERMISSIONS = [
		'btcpay.store.cancreatenonapprovedpullpayments',
		'btcpay.store.webhooks.canmodifywebhooks',
	];

	/**
	 * Addon
	 *
	 * @since 1.0.0
	 *
	 * @var BTCPayServer
	 */
	private $addon;

	/**
	 * API key
	 *
	 * @var string|null
	 */
	private $api_key;

	/**
	 * Permissions
	 *
	 * @var array
	 */
	private $permissions;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 *
	 * @param BTCPayServer $addon BTCPayServer addon.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;

		// Handle auth callback.
		add_action( 'template_redirect', array( $this, 'handle_auth_callback' ) );
	}


	/**
	 * Handle auth callback.
	 *
	 * @return void
	 */
	public function handle_auth_callback() {
		if ( ! isset( $_GET['quillforms_btcpayserver_auth'] ) ) {
			return;
		}

		$api_key = isset( $_POST['apiKey'] ) ? sanitize_text_field( wp_unslash( $_POST['apiKey'] ) ) : null;
		if ( ! $api_key ) {
			wp_redirect( admin_url( 'admin.php?page=quillforms&path=settings' ) );
			exit;
		}
		$mode     = ! empty( $_GET['quillforms_btcpayserver_auth'] ) ? sanitize_text_field( $_GET['quillforms_btcpayserver_auth'] ) : 'live';
		$site_url = $this->addon->settings->get( "{$mode}_site_url" );
		$client   = new Store( $site_url, $api_key );
		if ( empty( $client->getStores() ) ) {
			wp_redirect( admin_url( 'admin.php?page=quillforms&path=settings' ) );
			exit;
		}

		$this->api_key = $api_key;
		if ( is_array( $_POST['permissions'] ) ) {
			foreach ( $_POST['permissions'] as $key => $value ) {
				$this->permissions[ $key ] = sanitize_text_field( $_POST['permissions'][ $key ] ?? null );
			}
		}

		if ( ! $this->has_single_store() || ! $this->has_required_permissions() ) {
			wp_redirect( admin_url( 'admin.php?page=quillforms&path=settings' ) );
			exit;
		}

		$webhook = $this->create_webhook( $api_key, $site_url, $this->get_store_id(), $mode );
		if ( ! $webhook ) {
			wp_redirect( admin_url( 'admin.php?page=quillforms&path=settings' ) );
			exit;
		}
		$api_key_name  = "{$mode}_api_key";
		$site_url_name = "{$mode}_site_url";
		$store_id_name = "{$mode}_store_id";
		$webhook_name  = "{$mode}_webhook";
		$this->addon->settings->update(
			[
				'mode'         => $mode,
				$api_key_name  => $api_key,
				$site_url_name => $site_url,
				$store_id_name => $this->get_store_id(),
				$webhook_name  => $webhook,
			]
		);

		// redirect to settings page.
		wp_redirect( admin_url( 'admin.php?page=quillforms&path=settings' ) );
	}

	/**
	 * Creates webhook.
	 *
	 * @since 1.0.0
	 *
	 * @param string $api_key API key.
	 * @param string $site_url Site URL.
	 * @param string $store_id Store ID.
	 * @param string $mode Mode.
	 * @return WebhookResult
	 */
	public function create_webhook( $api_key, $site_url, $store_id, $mode ) {
		$webhook_url    = $this->get_webhook_url( $mode );
		$stored_webhook = $this->addon->settings->get( "{$mode}_webhook" );

		try {
			$client = new Webhook( $site_url, $api_key );
			if ( $stored_webhook['id'] ?? false ) {
				$existing = $client->getWebhook( $store_id, $stored_webhook['id'] );
				if ( $existing->getData()['id'] === $stored_webhook['id'] ) {
					return $stored_webhook;
				}
			}

			$webhook = $client->createWebhook( $store_id, $webhook_url, self::WEBHOOK_EVENTS, null );

			return $webhook->getData();
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Get webhook url
	 *
	 * @since 1.0.0
	 *
	 * @param string $mode Mode.
	 * @return string
	 */
	private function get_webhook_url( $mode ) {
		return site_url( "?quillforms_btcpayserver_webhook=$mode", 'https' );
	}

	/**
	 * Get API key
	 *
	 * @return string|null
	 */
	public function get_api_key(): ?string {
		return $this->api_key;
	}

	/**
	 * Get store ID
	 *
	 * @return string
	 */
	public function get_store_id(): string {
		return explode( ':', $this->permissions[0] )[1];
	}

	/**
	 * Check if has required permissions
	 *
	 * @return bool
	 */
	public function has_required_permissions(): bool {
		$permissions = array_reduce(
			$this->permissions,
			static function ( array $carry, string $permission ) {
				return array_merge( $carry, [ explode( ':', $permission )[0] ] );
			},
			[]
		);

		// Remove optional permissions so that only required ones are left.
		$permissions = array_diff( $permissions, self::OPTIONAL_PERMISSIONS );

		return empty(
			array_merge(
				array_diff( self::REQUIRED_PERMISSIONS, $permissions ),
				array_diff( $permissions, self::REQUIRED_PERMISSIONS )
			)
		);
	}

	/**
	 * Check if has single store
	 *
	 * @return bool
	 */
	public function has_single_store(): bool {
		$storeId = null;
		foreach ( $this->permissions as $perms ) {
			if ( 2 !== count( $exploded = explode( ':', $perms ) ) ) {
				return false;
			}

			if ( null === ( $receivedStoreId = $exploded[1] ) ) {
				return false;
			}

			if ( $storeId === $receivedStoreId ) {
				continue;
			}

			if ( null === $storeId ) {
				$storeId = $receivedStoreId;
				continue;
			}

			return false;
		}

		return true;
	}

	/**
	 * Check if has refunds permission
	 *
	 * @return bool
	 */
	public function has_refunds_permission(): bool {
		$permissions = array_reduce(
			$this->permissions,
			static function ( array $carry, string $permission ) {
				return array_merge( $carry, [ explode( ':', $permission )[0] ] );
			},
			[]
		);

		return in_array( 'btcpay.store.cancreatenonapprovedpullpayments', $permissions, true );
	}

	public function has_webhook_permission(): bool {
		$permissions = array_reduce(
			$this->permissions,
			static function ( array $carry, string $permission ) {
				return array_merge( $carry, [ explode( ':', $permission )[0] ] );
			},
			[]
		);

		return in_array( 'btcpay.store.webhooks.canmodifywebhooks', $permissions, true );
	}
}
