/**
 * WordPress Dependencies
 */
import { addFilter } from '@wordpress/hooks';

/**
 * Internal Dependencies
 */
import NotConfigured from '../components/not-configured';

addFilter(
	'QuillForms.PaymentGateways.PaymentGatewayModule',
	'QuillForms/BTCPayServer/ImplementPaymentGatewayModuleRenderer',
	( gateway, slug: string ) => {
		if ( slug === 'btcpayserver' ) {
			const localize = window[ 'quillforms_btcpayserver_localize' ] ?? {};

			// methods labels.
			if ( localize.customer_checkout_label ) {
				gateway.methods.checkout.customer.label.text =
					localize.customer_checkout_label;
			}

			// configured and render.
			if ( localize.configured ) {
				gateway.methods.checkout.configured = true;
				gateway.methods.checkout.customer.render = () => null;
			} else {
				gateway.methods.checkout.customer.render = NotConfigured;
			}
		}
		return gateway;
	}
);
