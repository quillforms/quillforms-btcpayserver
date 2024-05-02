/**
 * WordPress Dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

/**
 * Internal Dependencies
 */
import Settings from '../components/settings';

addFilter(
	'QuillForms.PaymentGateways.PaymentGatewayModule',
	'QuillForms/BTCPayServer/ImplementPaymentGatewayModuleAdmin',
	( gateway, slug: string ) => {
		if ( slug === 'btcpayserver' ) {
			gateway.active = true;
			gateway.settings = Settings;

			const localize = window[ 'quillforms_btcpayserver_localize' ] ?? {};
			if ( localize.configured ) {
				gateway.methods.checkout.configured = true;
			}
		}
		return gateway;
	}
);
