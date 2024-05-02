<?php
/**
 * Class: Webhook
 *
 * @since 1.0.0
 * @package QuillForms_BTCPayServer
 */

namespace QuillForms_BTCPayServer;

use QuillForms\Form_Submission;
use QuillForms_Entries\Entries;
use QuillForms_Entries\Entry;
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
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
	 * BTCPayServer client
	 *
	 * @since 1.0.0
	 *
	 * @var AnetAPI\MerchantAuthenticationType
	 */
	private $merchant_authentication;

	/**
	 * BTCPayServer client
	 *
	 * @since 1.0.0
	 *
	 * @var ANetEnvironment
	 */
	private $environment;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 *
	 * @param BTCPayServer $addon BTCPayServer addon.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;

		add_action( 'rewrite_rules_array', [ $this, 'register_btcpayserver_webhook_endpoint' ] );
		add_action( 'init', [ $this, 'btcpayserver_webhook_rewrite_tags' ] );
		add_action( 'wp', [ $this, 'maybe_handle_webhook' ], 100 );
		add_action( 'init', array( $this, 'flush_rewrite_rules' ), 9999999 );
	}

	/**
	 * Flush rewrite rules
	 *
	 * @since 1.1.1
	 *
	 * @return boolean
	 */
	public function flush_rewrite_rules() {

		if ( ! $option = get_option( 'quillforms-btcpayserver-flush-rewrite-rules' ) ) {
			return false;
		}

		if ( $option == 1 ) {

			flush_rewrite_rules();
			update_option( 'quillforms-btcpayserver-flush-rewrite-rules', 0 );

		}

		return true;

	}

	/**
	 * Registers webhook endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @param array $rules Rewrite rules.
	 * @return array
	 */
	public function register_btcpayserver_webhook_endpoint( $rules ) {
		$new_rules = array(
			'quillforms_btcpayserver_webhook_sandbox/' => 'index.php?quillforms_btcpayserver_webhook_sandbox',
			'quillforms_btcpayserver_webhook_sandbox'  => 'index.php?quillforms_btcpayserver_webhook_sandbox',
			'quillforms_btcpayserver_webhook_live/'    => 'index.php?quillforms_btcpayserver_webhook_live',
			'quillforms_btcpayserver_webhook_live'     => 'index.php?quillforms_btcpayserver_webhook_live',
		);
		$new_rules = array_merge( $new_rules, $rules );
		return $new_rules;
	}

	/**
	 * Registers webhook rewrite tags.
	 *
	 * @since 1.0.0
	 */
	public function btcpayserver_webhook_rewrite_tags() {
		add_rewrite_tag( '%quillforms_btcpayserver_webhook_sandbox%', '([^/]*)' );
		add_rewrite_tag( '%quillforms_btcpayserver_webhook_live%', '([^/]*)' );
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
		global $wp_query;

		// check if configured.
		$mode_settings = $this->addon->get_mode_settings();
		if ( ! $mode_settings ) {
			return false;
		}

		if ( ! isset( $wp_query->query_vars[ "quillforms_btcpayserver_webhook_{$mode_settings['mode']}" ] ) ) {
			return false;
		}
		$this->merchant_authentication = $this->addon->get_btcpayserver_merchant_authentication( $mode_settings );
		$this->environment             = $this->addon->get_btcpayserver_environment( $mode_settings );

		$this->mode = $mode_settings['mode'];
		// webhook event.
		$webhook_event = json_decode( file_get_contents( 'php://input' ) );
		// verify it.
		$verification = $this->get_webhook_verification( file_get_contents( 'php://input' ), $mode_settings['signature_key'] );
		if ( ! $verification ) {
			return;
		}
		// handle event.
		switch ( $webhook_event->eventType ?? null ) {
			case 'net.authorize.customer.subscription.cancelled':
			case 'net.authorize.customer.subscription.created':
			case 'net.authorize.customer.subscription.expired':
			case 'net.authorize.customer.subscription.expiring':
			case 'net.authorize.customer.subscription.failed':
			case 'net.authorize.customer.subscription.suspended':
			case 'net.authorize.customer.subscription.terminated':
			case 'net.authorize.customer.subscription.updated':
				$this->handle_subscription_updated( $webhook_event );
				break;
			case 'net.authorize.payment.refund.created':
				$this->handle_capture_refunded( $webhook_event );
				break;
			case 'net.authorize.payment.authcapture.created':
				$this->handle_capture_created( $webhook_event );
				break;
		}

		return;
	}

	/**
	 * Handle subscription created/updated/deleted
	 *
	 * @since 1.0.0
	 *
	 * @param object $subscription
	 * @return void
	 */
	private function handle_subscription_updated( $subscription ) {
		// check quillforms metadata.
		$btcpayserver_subscriptions = get_option( 'qf_btcpayserver_subscriptions', [] );
		$submission_id              = $btcpayserver_subscriptions[ $subscription->payload->id ] ?? null;
		if ( ! $submission_id ) {
			return;
		}

		$form_submission = Form_Submission::instance();
		$restore         = $form_submission->restore_pending_submission( $submission_id );
		if ( $restore ) {
			// for submissions that still pending, we wait for active status to continue it.
			if ( ! in_array( $subscription->payload->status, [ 'active' ], true ) ) {
				return;
			}

			// save gateway and method.
			$form_submission->entry->meta['payments']['value']['gateway'] = 'btcpayserver';
			$form_submission->entry->meta['payments']['value']['method']  = 'card';

			// save subscription.
			$form_submission->entry->meta['payments']['value']['subscription'] = [
				'id'     => $subscription->payload->id,
				'status' => $subscription->payload->status,
				'mode'   => $this->mode,
			];

			// add meta lookup key.
			$form_submission->entry->meta[ "btcpayserver_{$subscription->payload->id}" ]['value'] = '1';

			$message = sprintf(
				'<p>Subscription ID: %s</p>
				<p>Subscription Status: %s</p>
				<p>Customer Profile ID: %s</p>
				<p>Customer Payment Profile ID: %s</p>
				<p>Description: %s</p>',
				$subscription->payload->id,
				$subscription->payload->status,
				$subscription->payload->profile->customerProfileId,
				$subscription->payload->profile->paymentProfile->customerPaymentProfileId ?? '',
				$subscription->payload->profile->description ?? ''
			);

			// add note.
			$form_submission->entry->meta['notes']['value'][] = [
				'source'  => 'btcpayserver',
				/* translators: %s for the subscription status */
				'message' => $message,
				'date'    => gmdate( 'Y-m-d H:i:s' ),
			];

			// get all paid payments for this subscription, typically it is one.
			try {
				$request = new AnetAPI\GetTransactionListForCustomerRequest();
				$request->setMerchantAuthentication( $this->merchant_authentication );
				$request->setCustomerProfileId( $subscription->payload->profile->customerProfileId );

				$controller = new AnetController\GetTransactionListForCustomerController( $request );

				$response = $controller->executeWithApiResponse( $this->environment );

				if ( ( $response != null ) && ( $response->getMessages()->getResultCode() == 'Ok' ) ) {
					if ( null != $response->getTransactions() ) {
						foreach ( $response->getTransactions() as $tx ) {
							$transaction_id = $tx->getTransId();
							// add transaction.
							$form_submission->entry->meta['payments']['value']['transactions'][ $transaction_id ] = [
								'amount' => $tx->getSettleAmount(),
								'mode'   => $this->mode,
							];
							// add lookup key.
							$form_submission->entry->meta[ "btcpayserver_{$transaction_id}" ]['value'] = '1';
							// add note.
							$form_submission->entry->meta['notes']['value'][] = [
								'source'  => 'btcpayserver',
								/* translators: %s for payment id */
								'message' => sprintf( esc_html__( 'Payment %s done', 'quillforms-btcpayserver' ), $transaction_id ),
								'date'    => gmdate( 'Y-m-d H:i:s' ),
							];
						}
					}
				} else {
					$errorMessages = $response->getMessages()->getMessage();
					return new WP_Error( 'quillforms_btcpayserver', $errorMessages[0]->getCode() . '  ' . $errorMessages[0]->getText() );
				}
			} catch ( Throwable $e ) {
				// just skip adding the transactions.
			}
			$form_submission->continue_pending_submission();
			return;
		} else {
			if ( ! class_exists( Entries::class ) ) {
				return;
			}

			$entry = Entry::get_by_meta( 'submission_id', $submission_id );
			if ( ! $entry ) {
				return;
			}

			$entry->load_meta();
			$payments_meta = $entry->meta['payments'];
			$notes_meta    = $entry->meta['notes'];

			// check subscription id.
			if ( $subscription->payload->id !== $payments_meta['value']['subscription']['id'] ) {
				return;
			}

			// update payments meta.
			$payments_meta['value']['subscription']['status'] = $subscription->payload->status;
			$entry->update_meta( 'payments', $payments_meta );

			// add note.
			$notes_meta['value'][] = [
				'source'  => 'btcpayserver',
				/* translators: %s for the subscription status */
				'message' => sprintf( esc_html__( 'Subscription status changed to %s.', 'quillforms-btcpayserver' ), $subscription->payload->status ),
				'date'    => gmdate( 'Y-m-d H:i:s' ),
			];
			$entry->update_meta( 'notes', $notes_meta );
			return;
		}
	}

	/**
	 * Handle charge refunded
	 *
	 * @since 1.0.0
	 *
	 * @param object $charge
	 * @return void
	 */
	private function handle_capture_refunded( $charge ) {
		if ( empty( $charge->payload->id ) ) {
			return;
		}

		if ( ! class_exists( Entries::class ) ) {
			return;
		}

		$entry = Entry::get_by_meta( "btcpayserver_{$charge->payload->id}", '1' );
		if ( ! $entry ) {
			return;
		}

		$entry->load_meta();
		$payments_meta = $entry->meta['payments'];
		$notes_meta    = $entry->meta['notes'];

		// check if transaction doesn't exist.
		if ( ! isset( $payments_meta['value']['transactions'][ $charge->payload->id ] ) ) {
			return;
		}

		// update transaction status.
		$payments_meta['value']['transactions'][ $charge->payload->id ]['status'] = 'refunded';
		$entry->update_meta( 'payments', $payments_meta );

		// latest refund.
		$latest_refund = $charge->payload->authAmount;

		// add note.
		$notes_meta['value'][] = [
			'source'  => 'btcpayserver',
			/* translators: %s for payment id */
			'message' => sprintf( esc_html__( 'Amount %1$s is refunded from payment %2$s', 'quillforms-btcpayserver' ), $latest_refund, $charge->payload->id ),
			'date'    => gmdate( 'Y-m-d H:i:s' ),
		];
		$entry->update_meta( 'notes', $notes_meta );

		return;
	}

	/**
	 * Handle transaction captured
	 *
	 * @since 1.0.0
	 *
	 * @param object $transaction
	 * @return void
	 */
	public function handle_capture_created( $transaction ) {
		if ( empty( $transaction->payload->id ) ) {
			return;
		}

		if ( ! class_exists( Entries::class ) ) {
			return;
		}

		$entry = Entry::get_by_meta( "btcpayserver_{$transaction->payload->id}", '1' );
		if ( ! $entry ) {
			return;
		}

		$entry->load_meta();
		$payments_meta = $entry->meta['payments'];
		$notes_meta    = $entry->meta['notes'];

		// check if transaction doesn't exist.
		if ( ! isset( $payments_meta['value']['transactions'][ $transaction->payload->id ] ) ) {
			return;
		}

		// update transaction status.
		$payments_meta['value']['transactions'][ $transaction->payload->id ]['status'] = 'captured';
		$entry->update_meta( 'payments', $payments_meta );

		// latest payment.
		$latest_payment = $transaction->payload->authAmount;

		// add note.
		$notes_meta['value'][] = [
			'source'  => 'btcpayserver',
			/* translators: %s for payment id */
			'message' => sprintf( esc_html__( 'Amount %1$s is captured from payment %2$s', 'quillforms-btcpayserver' ), $latest_payment, $transaction->payload->id ),
			'date'    => gmdate( 'Y-m-d H:i:s' ),
		];
		$entry->update_meta( 'notes', $notes_meta );

		return;
	}

	/**
	 * Validate webhook request
	 *
	 * @return boolean
	 */
	private function get_webhook_verification( $body, $btcpayserver_signature ) {
		// Get the auth hash from the header.
		$auth_hash = isset( $_SERVER['HTTP_X_ANET_SIGNATURE'] ) ? strtoupper( explode( '=', $_SERVER['HTTP_X_ANET_SIGNATURE'] )[1] ) : '';
		if ( empty( $auth_hash ) ) {
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

		$generated_hash = strtoupper( hash_hmac( 'sha512', $body, $btcpayserver_signature ) );
		return hash_equals( $auth_hash, $generated_hash );
	}

}
