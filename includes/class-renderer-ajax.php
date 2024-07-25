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
use QuillForms\Merge_Tags;
use BTCPayServer\Client\Invoice;
use BTCPayServer\Client\InvoiceCheckoutOptions;
use BTCPayServer\Util\PreciseNumber;

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
		$this->ajax_ensure_availability( [ 'checkout' ] );
		$this->ajax_init_btcpayserver_client();

		$payments      = $this->form_submission->entry->get_meta_value( 'payments' );
		$products      = $payments['products'];
		$amount        = $products['total'];
		$submission_id = absint( $_POST['submission_id'] );
		$currency      = $payments['currency']['code'];
		$form_id       = $this->form_submission->entry->form_id;

		$client          = new Invoice( $this->mode_settings['site_url'], $this->mode_settings['api_key'] );
		$checkoutOptions = new InvoiceCheckoutOptions();
		$return_url      = add_query_arg(
			[
				'submission_id'      => $submission_id,
				'step'               => 'payment',
				'method'             => 'btcpayserver:checkout',
				'thankyou_screen_id' => $this->form_submission->get_thankyou_screen_id(),
			],
			get_post_permalink( $form_id )
		);

		$checkoutOptions->setRedirectURL( $return_url );
		$metadata = array_merge(
			[
				'submission_id' => $submission_id,
			],
			$this->get_customer()
		);
		try {
			$invoice = $client->createInvoice(
				$this->mode_settings['store_id'],
				$currency,
				PreciseNumber::parseString( $amount ),
				$submission_id,
				null, // this is null here as we handle it in the metadata.
				$metadata,
				$checkoutOptions
			);

			wp_send_json_success(
				array(
					'url' => $invoice->getData()['checkoutLink'],
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

	/**
	 * Get customer
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function get_customer() {
		$options = $this->form_submission->form_data['payments']['gateways_options']['btcpayserver'] ?? [];

		$name  = Merge_Tags::instance()->process( $options['customer_name'] ?? null, $this->form_submission->entry, $this->form_submission->form_data, 'raw' );
		$email = Merge_Tags::instance()->process( $options['customer_email'] ?? null, $this->form_submission->entry, $this->form_submission->form_data, 'raw' );
		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			$email = '';
		}
		$address1 = Merge_Tags::instance()->process( $options['customer_address1'] ?? null, $this->form_submission->entry, $this->form_submission->form_data, 'raw' );
		$address2 = Merge_Tags::instance()->process( $options['customer_address2'] ?? null, $this->form_submission->entry, $this->form_submission->form_data, 'raw' );
		$city     = Merge_Tags::instance()->process( $options['customer_city'] ?? null, $this->form_submission->entry, $this->form_submission->form_data, 'raw' );
		$state    = Merge_Tags::instance()->process( $options['customer_state'] ?? null, $this->form_submission->entry, $this->form_submission->form_data, 'raw' );
		$zip      = Merge_Tags::instance()->process( $options['customer_zip'] ?? null, $this->form_submission->entry, $this->form_submission->form_data, 'raw' );
		$country  = Merge_Tags::instance()->process( $options['customer_country'] ?? null, $this->form_submission->entry, $this->form_submission->form_data, 'raw' );

		$data = [
			'buyerEmail'    => $email,
			'buyerName'     => $name,
			'buyerAddress1' => $address1,
			'buyerAddress2' => $address2,
			'buyerCity'     => $city,
			'buyerState'    => $state,
			'buyerZip'      => $zip,
			'buyerCountry'  => $country,
		];

		// remove empty values.
		$data = array_filter(
			$data,
			function( $value ) {
				return ! empty( $value );
			}
		);

		return $data;
	}

}
