<?php
/**
 * Class: Webhook
 *
 * @since 1.0.0
 * @package QuillForms_BTCPayServer
 */

namespace QuillForms_BTCPayServer;

use Exception;
use QuillForms\Form_Submission;
use QuillForms_Entries\Entries;
use QuillForms_Entries\Entry;
use BTCPayServer\Client\Webhook as BTCPayServerWebhook;
use BTCPayServer\Client\Invoice;
use Throwable;

/**
 * Webhook Class
 *
 * @since 1.0.0
 */
class Webhook {

	/**
	 * Addon
	 *
	 * @since 1.0.0
	 *
	 * @var BTCPayServer
	 */
	private $addon;

	/**
	 * Mode
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private $mode;

	/**
	 * Mode settings
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private $mode_settings;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 *
	 * @param BTCPayServer $addon BTCPayServer addon.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;

		add_action( 'quillforms_loaded', [ $this, 'maybe_handle_webhook' ], 100 );
	}

	/**
	 * Handle webhook request if exists
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_handle_webhook() {
		// check webhook.
		$webhook_mode = $_GET['quillforms_btcpayserver_webhook'] ?? null;
		if ( empty( $webhook_mode ) ) {
			return;
		}

		// check if configured.
		$mode_settings = $this->addon->get_mode_settings();
		if ( ! $mode_settings ) {
			$this->respond( 200, "BTCPayServer isn't configured!" );
		}

		// check current mode match.
		if ( $mode_settings['mode'] !== $webhook_mode ) {
			$this->respond( 200, 'Unmatched current mode!' );
		}

		// set mode.
		$this->mode          = $mode_settings['mode'];
		$this->mode_settings = $mode_settings;
		// webhook event.
		$webhook_event = json_decode( file_get_contents( 'php://input' ) );
		// verify it.
		$verification = $this->get_webhook_verification( file_get_contents( 'php://input' ) );
		if ( ! $verification ) {
			return $this->respond( 200, 'Webhook verification failed!' );
		}

		// handle event.
		switch ( $webhook_event->type ?? null ) {
			case 'InvoicePaymentSettled':
			case 'InvoiceProcessing':
			case 'InvoiceExpired':
			case 'InvoiceSettled':
			case 'InvoiceInvalid':
				$this->handle_invoice( $webhook_event );
				break;
			case 'InvoiceReceivedPayment':
				$this->handle_received_payment( $webhook_event );
				break;
		}

		return;
	}

	/**
	 * Handle received payment
	 *
	 * @since 1.0.0
	 *
	 * @param object $webhook_event Webhook event.
	 *
	 * @return void
	 */
	public function handle_received_payment( $webhook_event ) {
		// check quillforms metadata.
		$submission_id = $webhook_event->metadata->submission_id ?? null;
		if ( ! $submission_id ) {
			$this->respond( 200 );
		}

		$form_submission = Form_Submission::instance();
		$restore         = $form_submission->restore_pending_submission( $submission_id );
		if ( ! $restore ) {
			$this->respond( 200 );
		}

		$client = new Invoice( $this->mode_settings['site_url'], $this->mode_settings['api_key'] );
		try {
			$invoice      = $client->getInvoice( $this->mode_settings['store_id'], $webhook_event->invoiceId );
			$invoice_data = $invoice->getData();
			$invoice_id   = $invoice_data['id'];

			// ensure amount.
			if ( (float) $invoice_data['amount'] !== (float) $form_submission->entry->meta['payments']['value']['products']['total'] ) {
				quillforms_get_logger()->error(
					esc_html__( 'Payment with incorrect amount has been made', 'quillforms-btcpayserver' ),
					[
						'code'          => 'unmatched_payment_amount',
						'submission_id' => $submission_id,
						'invoice_id'    => $invoice_id,
						'amount'        => $invoice_data['amount'],
					]
				);
				$this->respond( 200 );
			}

			// ensure currency.
			if ( strtolower( $invoice_data['currency'] ) !== strtolower( $form_submission->entry->meta['payments']['value']['currency']['code'] ) ) {
				quillforms_get_logger()->error(
					esc_html__( 'Payment with incorrect currency has been made', 'quillforms-btcpayserver' ),
					[
						'code'          => 'unmatched_payment_currency',
						'submission_id' => $submission_id,
						'invoice_id'    => $invoice_id,
						'currency'      => $invoice_data['currency'],
					]
				);
				$this->respond( 200 );
			}

			// save method.
			$form_submission->entry->meta['payments']['value']['gateway'] = 'btcpayserver';
			$form_submission->entry->meta['payments']['value']['method']  = 'checkout';

			// save payment intent.
			$form_submission->entry->meta['payments']['value']['transactions'][ $invoice_id ] = [
				'amount'   => $invoice_data['amount'],
				'currency' => $invoice_data['currency'],
				'status'   => $invoice_data['status'],
				'mode'     => $this->mode,
			];
			// save payment intent lookup meta.
			$form_submission->entry->meta[ "btcpayserver_$invoice_id" ]['value'] = '1';

			// save to notes.
			$form_submission->entry->meta['notes']['value'][] = [
				'source'  => 'btcpayserver',
				/* translators: %s for payment id */
				'message' => sprintf( esc_html__( 'Payment with invoice %s has been made', 'quillforms-btcpayserver' ), $invoice_id ),
				'date'    => gmdate( 'Y-m-d H:i:s' ),
			];

			$form_submission->continue_pending_submission();
			$this->respond( 200 );
		} catch ( Throwable $e ) {
			quillforms_get_logger()->error(
				esc_html__( 'Exception on getting invoice', 'quillforms-btcpayserver' ),
				[
					'code'      => 'webhook_invoice_get_exception',
					'exception' => [
						'message' => $e->getMessage(),
						'trace'   => $e->getTrace(),
					],
				]
			);
			$this->respond( 200 );
		}
	}

	/**
	 * Handle invoice event
	 *
	 * @since 1.0.0
	 *
	 * @param object $webhook_event Webhook event.
	 *
	 * @return void
	 */
	public function handle_invoice( $webhook_event ) {
		if ( ! class_exists( Entries::class ) ) {
			$this->respond( 200 );
		}

		$entry = Entry::get_by_meta( "btcpayserver_$webhook_event->invoiceId", '1' );
		if ( ! $entry ) {
			$this->respond( 200 );
		}

		$client = new Invoice( $this->mode_settings['site_url'], $this->mode_settings['api_key'] );
		try {
			$invoice      = $client->getInvoice( $this->mode_settings['store_id'], $webhook_event->invoiceId );
			$invoice_data = $invoice->getData();
			$invoice_id   = $invoice_data['id'];
			$entry->load_meta();
			$payments_meta = $entry->meta['payments'];
			$notes_meta    = $entry->meta['notes'];

			// check if transaction already saved.
			if ( ! isset( $payments_meta['value']['transactions'][ $invoice_id ] ) ) {
				$this->respond( 200 );
			}

			// add transaction.
			$payments_meta['value']['transactions'][ $invoice_id ]['status'] = $invoice_data['status'];
			$entry->update_meta( 'payments', $payments_meta );

			// add note.
			$notes_meta['value'][] = [
				'source'  => 'btcpayserver',
				/* translators: %s for payment id */
				'message' => sprintf( esc_html__( 'Invoice %1$s status changed to %2$s', 'quillforms-btcpayserver' ), $invoice_id, $invoice_data['status'] ),
				'date'    => gmdate( 'Y-m-d H:i:s' ),
			];
			$entry->update_meta( 'notes', $notes_meta );

			$this->respond( 200 );
		} catch ( Throwable $e ) {
			quillforms_get_logger()->error(
				esc_html__( 'Exception on getting invoice', 'quillforms-btcpayserver' ),
				[
					'code'      => 'webhook_invoice_get_exception',
					'exception' => [
						'message' => $e->getMessage(),
						'trace'   => $e->getTrace(),
					],
				]
			);
			$this->respond( 200 );
		}
	}

	/**
	 * Validate webhook request
	 *
	 * @return boolean
	 */
	private function get_webhook_verification( $body ) {
		try {
			// Get the auth hash from the header.
			$headers = getallheaders();
			foreach ( $headers as $key => $value ) {
				if ( strtolower( $key ) === 'btcpay-sig' ) {
					$signature = $value;
				}
			}

			$webhook_data = $this->mode_settings['webhook'];
			return BTCPayServerWebhook::isIncomingWebhookRequestValid( $body, $signature, $webhook_data['secret'] );
		} catch ( Exception $e ) {
			quillforms_get_logger()->error(
				esc_html__( 'Exception on webhook verification', 'quillforms-btcpayserver' ),
				[
					'code'      => 'webhook_verification_exception',
					'exception' => [
						'message' => __( 'Webhook verification failed', 'quillforms-btcpayserver' ),
						'trace'   => $_SERVER,
					],
				]
			);
			return false;
		}
	}

	/**
	 * Respond to a webhook request
	 *
	 * @since 1.0.0
	 *
	 * @param integer $status
	 * @param mixed   $content
	 * @return void
	 */
	private function respond( $status, $content = null ) {
		http_response_code( $status );
		echo $content;
		exit;
	}
}
