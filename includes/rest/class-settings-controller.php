<?php
/**
 * Settings_Controller class.
 *
 * @since 1.0.0
 * @package QuillForms
 */

namespace QuillForms_BTCPayServer\REST;

use QuillForms\Addon\REST\Settings_Controller as Abstract_Settings_Controller;
use QuillForms_BTCPayServer\BTCPayServer;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use BTCPayServer\Client\Webhook;
use BTCPayServer\Result\Webhook as WebhookResult;
use QuillForms_BTCPayServer\BTCPayServer_Helper;

/**
 * Settings_Controller abstract class.
 *
 * @since 1.0.0
 *
 * @property BTCPayServer $addon
 */
class Settings_Controller extends Abstract_Settings_Controller {

	/**
	 * Retrieves schema, conforming to JSON Schema.
	 * Should include context for gettable data
	 * Should specify additionalProperties & readonly to specify updatable data
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_schema() {
		$schema = [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'settings',
			'type'       => 'object',
			'context'    => [ 'view' ],
			'properties' => [
				'mode'                    => [
					'type'     => 'string',
					'required' => true,
					'enum'     => [ 'live', 'sandbox' ],
				],
				'sandbox_api_key'         => [
					'type' => 'string',
				],
				'sandbox_site_id'         => [
					'type' => 'string',
				],
				'sandbox_webhook'         => [
					'type'    => 'string',
					'context' => [],
				],
				'sandbox_site_url'        => [
					'type' => 'string',
				],
				'live_api_key'            => [
					'type' => 'string',
				],
				'live_site_id'            => [
					'type' => 'string',
				],
				'live_webhook'            => [
					'type'    => 'string',
					'context' => [],
				],
				'live_site_url'           => [
					'type' => 'string',
				],
				'customer_checkout_label' => [
					'type' => 'string',
				],
			],
		];

		return rest_default_additional_properties_to_false( $schema );
	}

	/**
	 * Updates settings.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update( $request ) {
		$settings = $request->get_json_params();

		// to avoid unexplained errors to user, only connect to current user viewable keys.
		$mode          = $settings['mode'];
		$api_key_name  = "{$mode}_api_key";
		$site_id_name  = "{$mode}_site_id";
		$site_url_name = "{$mode}_site_url";
		$api_key       = trim( $settings[ $api_key_name ] ?? '' );
		$site_id       = trim( $settings[ $site_id_name ] ?? '' );
		$site_url      = trim( $settings[ $site_url_name ] ?? '' );

		// ensure api login id and transaction key are not empty.
		if ( empty( $api_key ) || empty( $site_id ) ) {
			return new WP_Error( 'quillforms_btcpayserver_settings_update', esc_html__( 'Both API Login ID and Transaction Key are required', 'quillforms-btcpayserver' ) );
		}

		// ensure signature key is not empty.
		if ( empty( $site_url ) ) {
			return new WP_Error( 'quillforms_btcpayserver_settings_update', esc_html__( 'Signature Key is required', 'quillforms-btcpayserver' ) );
		}

		// ensure api login id and transaction key are valid.
		$current_settings = $this->addon->settings->get();
		if ( ( $current_settings[ $api_key_name ] ?? null ) === $api_key && ( $current_settings[ $site_id_name ] ?? null ) === $site_id ) {
			$this->addon->settings->update(
				[
					'mode'                    => $mode,
					'customer_checkout_label' => $settings['customer_checkout_label'] ?? '',
				]
			);
			return new WP_REST_Response(
				array(
					'success' => true,
					'updated' => false,
				)
			);
		}

		// create webhook.
		$webhook = $this->create_webhook( $api_key, $site_url, $site_id, $mode );
		if ( ! isset( $webhook['id'] ) ) {
			return new WP_Error( 'quillforms_btcpayserver_create_webhook', esc_html__( 'Cannot create webhook. See log for details.', 'quillforms-btcpayserver' ) );
		}

		// update settings.
		$this->addon->settings->update(
			[
				'mode'                    => $mode,
				"{$mode}_api_key"         => $api_key,
				"{$mode}_site_id"         => $site_id,
				"{$mode}_site_url"        => $site_url,
				"{$mode}_webhook_id"      => $webhook,
				'customer_checkout_label' => $settings['customer_checkout_label'] ?? '',
			]
		);

		// flush rewrite rules.
		update_option( 'quillforms-btcpayserver-flush-rewrite-rules', 1 );

		return new WP_REST_Response(
			array(
				'success' => true,
				/* translators: %s: Mode. */
				'message' => sprintf( esc_html__( 'Account connected successfully at %s mode.', 'quillforms-btcpayserver' ), $mode ),
				'updated' => true,
			)
		);
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
		$stored_webhook = $this->addon->settings->get( "{$mode}_webhook_id" );

		try {
			$client = new Webhook( $site_url, $api_key );
			if ( $stored_webhook ) {
				$existing = $client->getWebhook( $store_id, $stored_webhook );
				if ( $existing->getData()['id'] === $stored_webhook ) {
					return $existing->getData();
				}
			}

			$webhook = $client->createWebhook( $store_id, $webhook_url, BTCPayServer_Helper::WEBHOOK_EVENTS, null );

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

}
