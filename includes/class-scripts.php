<?php
/**
 * Scripts class
 *
 * @package QuillForms_BTCPayServer
 * @since 1.0.0
 */

namespace QuillForms_BTCPayServer;

use QuillForms\Addon\Scripts as Abstract_Scripts;

/**
 * Scripts class
 *
 * @property BTCPayServer $addon
 */
class Scripts extends Abstract_Scripts {

	/**
	 * Scripts to register.
	 *
	 * @var array
	 */
	protected $scripts = [
		'quillforms-btcpayserver-admin'    => [
			'path'    => 'build/admin/index.js',
			'enqueue' => [ 'admin' ],
		],
		'quillforms-btcpayserver-renderer' => [
			'path'    => 'build/renderer/index.js',
			'enqueue' => [ 'renderer' ],
		],
	];

	/**
	 * Styles to register.
	 *
	 * @var array
	 */
	protected $styles = [
		'quillforms-btcpayserver-admin'    => [
			'path'         => 'build/admin/style.css',
			'dependencies' => [ 'quillforms-admin-components', 'wp-components' ],
			'enqueue'      => [ 'admin' ],
		],
		'quillforms-btcpayserver-renderer' => [
			'path'         => 'build/renderer/style.css',
			'dependencies' => [],
			'enqueue'      => [ 'renderer' ],
		],
	];

	/**
	 * Localize scripts.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function localize_scripts() {
		$settings = $this->addon->get_mode_settings();

		// localize admin script.
		wp_localize_script(
			'quillforms-btcpayserver-admin',
			'quillforms_btcpayserver_localize',
			[
				'configured' => (bool) $settings,
				'ajax_nonce' => wp_create_nonce( 'quillforms-btcpayserver' ),
			]
		);

		// localize renderer script.
		wp_localize_script(
			'quillforms-btcpayserver-renderer',
			'quillforms_btcpayserver_localize',
			[
				'configured'              => (bool) $settings,
				'mode'                    => $settings['mode'] ?? null,
				'customer_checkout_label' => $settings['customer_checkout_label'] ?? null,
				'assetsDir'               => QUILLFORMS_AUTHORIZENET_PLUGIN_URL . 'assets',
			]
		);
	}

}
