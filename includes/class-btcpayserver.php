<?php
/**
 * Main class: class BTCPayServer
 *
 * @since 1.0.0
 * @package QuillForms_BTCPayServer
 */

namespace QuillForms_BTCPayServer;

use DateInterval;
use DateTime;
use QuillForms\Addon\Form_Data;
use QuillForms\Addon\Payment_Gateway\Payment_Gateway;
use QuillForms\Addon\Settings;

/**
 * BTCPayServer Class.
 *
 * The main class that's responsible for loading all dependencies
 */
final class BTCPayServer extends Payment_Gateway {

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
	 * Name
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $name = 'BTCPayServer';

	/**
	 * Slug
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $slug = 'btcpayserver';

	/**
	 * Version
	 *
	 * @since 1.3.0
	 *
	 * @var string
	 */
	public $version = QUILLFORMS_BTCPAYSERVER_VERSION;

	/**
	 * Text domain
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $textdomain = 'quillforms-btcpayserver';

	/**
	 * Plugin file
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $plugin_file = QUILLFORMS_BTCPAYSERVER_PLUGIN_FILE;

	/**
	 * Plugin dir
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $plugin_dir = QUILLFORMS_BTCPAYSERVER_PLUGIN_DIR;

	/**
	 * Plugin url
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $plugin_url = QUILLFORMS_BTCPAYSERVER_PLUGIN_URL;

	/**
	 * Checkout method
	 *
	 * @since 1.0.0
	 *
	 * @var Checkout_Method
	 */
	public $checkout_method;

	/**
	 * Webhook
	 *
	 * @since 1.0.0
	 *
	 * @var Webhook
	 */
	public $webhook;

	/**
	 * Dependencies
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public $dependencies = [
		'quillforms' => [
			'version' => '1.16.0',
		],
		'ssl'        => [],
		'curl'       => [],
	];

	/**
	 * Class names
	 *
	 * @var array
	 */
	protected static $classes = array(
		'scripts'   => Scripts::class,
		'settings'  => Settings::class,
		'form_data' => Form_Data::class,
		'rest'      => REST\REST::class,
	);

	/**
	 * Initialize
	 *
	 * @since 1.8.0
	 *
	 * @return void
	 */
	protected function init() {
		parent::init();

		new Renderer_Ajax( $this );
		new Admin_Ajax( $this );
		new BTCPayServer_Helper( $this );
		$this->webhook = new Webhook( $this );
	}

	/**
	 * Get mode settings
	 *
	 * @since 1.0.0
	 *
	 * @param string $mode Mode. Current mode will be used if not specified.
	 * @return array|false
	 */
	public function get_mode_settings( $mode = null ) {
		$settings = $this->settings->get();
		$mode     = $mode ?? $settings['mode'] ?? null;
		if ( ! in_array( $mode, [ 'sandbox', 'live' ], true ) ) {
			return false;
		}

		$mode_settings = [
			'mode'                    => $mode,
			'customer_checkout_label' => $settings['customer_checkout_label'] ?? '',
		];
		$keys          = [ 'api_key', 'store_id', 'site_url', 'webhook' ];
		foreach ( $keys as $key ) {
			if ( empty( $settings[ "{$mode}_$key" ] ) ) {
				return false;
			}
			$mode_settings[ $key ] = $settings[ "{$mode}_$key" ];
		}

		return $mode_settings;
	}

	/**
	 * Is gateway and method configured
	 *
	 * @since 1.0.0
	 *
	 * @param string $method Method.
	 * @return boolean
	 */
	public function is_configured( $method ) { // phpcs:ignore
		return (bool) $this->get_mode_settings();
	}

	/**
	 * Is currency supported by the gateway
	 *
	 * @since 1.0.0
	 *
	 * @param string $currency Currency.
	 * @return boolean
	 */
	public function is_currency_supported( $currency ) {
		$supported_currencies = [ 'USD', 'AUD', 'EUR', 'GBP', 'CAD' ];

		return in_array( strtoupper( $currency ), $supported_currencies, true );
	}

	/**
	 * Is recurring supported by method
	 *
	 * @since 1.0.0
	 *
	 * @param string $method Method.
	 * @return boolean
	 */
	public function is_recurring_supported( $method ) {
		return false;
	}

	/**
	 * Is recurring interval supported
	 *
	 * @since 1.0.0
	 *
	 * @param string  $unit Interval unit.
	 * @param integer $count Interval count.
	 * @return boolean
	 */
	public function is_recurring_interval_supported( $unit, $count ) {
		return false;
	}

	/**
	 * Is transaction status ok
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Transaction status.
	 * @return boolean
	 */
	public function is_transaction_status_ok( $status ) {
		return $status === 'Ok';
	}

	/**
	 * Is subscription status ok
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Subscription status.
	 * @return boolean
	 */
	public function is_subscription_status_ok( $status ) {
		return false;
	}

}
