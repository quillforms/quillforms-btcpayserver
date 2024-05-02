<?php
/**
 * Class: Renderer_Ajax
 *
 * @since 1.0.0
 * @package QuillForms_BTCPayServer
 */

namespace QuillForms_BTCPayServer;

use DateTime;
use QuillForms\Form_Submission;
use BTCPayServer\Client\Invoice;

/**
 * Renderer_Ajax Class
 *
 * @since 1.0.0
 */
class Renderer_Ajax {

	/**
	 * Addon
	 *
	 * @since 1.0.0
	 *
	 * @var BTCPayServer
	 */
	private $addon;

	/**
	 * Mode settings
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private $mode_settings;

	/**
	 * Form submission
	 *
	 * @since 1.0.0
	 *
	 * @var Form_Submission
	 */
	private $form_submission;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 *
	 * @param BTCPayServer $addon BTCPayServer addon.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;

		// create_order for both checkout and card methods.
		add_action( 'wp_ajax_quillforms_btcpayserver_create_order', array( $this, 'ajax_create_order' ) );
		add_action( 'wp_ajax_nopriv_quillforms_btcpayserver_create_order', array( $this, 'ajax_create_order' ) );
	}

	/**
	 * Handle create_order ajax action
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function ajax_create_order() {
		$this->ajax_init_form_submission();
		$this->ajax_ensure_availability( [ 'card' ] );
		$this->ajax_init_btcpayserver_client();

		$payments      = $this->form_submission->entry->get_meta_value( 'payments' );
		$products      = $payments['products'];
		$amount        = $products['total'];
		$submission_id = absint( $_POST['submission_id'] );
		$currency      = $payments['currency']['code'];

		$client = new Invoice( $this->mode_settings['site_url'], $this->merchant_authentication['api_key'] );

		try {
			$invoice = $client->createInvoice(
				$this->mode_settings['store_id'],
				$currency,
				$amount,
				$submission_id,
				null, // this is null here as we handle it in the metadata.
				null,
				null
			);

			$invoice->setRedirectURL( site_url( '?quillforms_btcpayserver_redirect=' . $submission_id, 'https' ) );

			wp_send_json_success(
				array(
					'invoice' => $invoice->getData(),
				)
			);

		} catch ( \Exception $e ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Failed to create invoice', 'quillforms-btcpayserver' ) ], 500 );
			exit;
		}
	}

	/**
	 * Initialize mode settings for ajax request
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function ajax_init_btcpayserver_client() {
		$this->mode_settings = $this->addon->get_mode_settings();
		if ( ! $this->mode_settings ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Authorize.Net addon is not configured', 'quillforms-btcpayserver' ) ], 500 );
			exit;
		}

		$this->merchant_authentication = $this->addon->get_btcpayserver_merchant_authentication( $this->mode_settings );
		$this->environment             = $this->addon->get_btcpayserver_environment( $this->mode_settings );
	}

	/**
	 * Initialize form submission for ajax request
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function ajax_init_form_submission() {
		$submission_id         = absint( $_POST['submission_id'] );
		$this->form_submission = Form_Submission::instance();
		$restore               = $this->form_submission->restore_pending_submission( $submission_id );
		if ( ! $restore ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Cannot retrieve from submission', 'quillforms-btcpayserver' ) ], 400 );
			exit;
		}
	}

	/**
	 * Ensure availability of one of the methods
	 * This function ensure that one of the methods is enabled and configured.
	 *
	 * @since 1.0.0
	 *
	 * @param array $methods
	 * @return void
	 */
	private function ajax_ensure_availability( $methods ) {
		foreach ( $methods as $method ) {
			if ( isset( $this->form_submission->form_data['payments']['methods'][ "btcpayserver:$method" ] ) ) {
				if ( $this->addon->is_configured( $method ) ) {
					// this return stop the function from falling to the error.
					return;
				}
			}
		}

		wp_send_json_error( [ 'message' => esc_html__( "This payment method isn't available.", 'quillforms-btcpayserver' ) ], 400 );
		exit;
	}

}
