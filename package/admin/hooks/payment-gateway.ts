/**
 * WordPress Dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

/**
 * Internal Dependencies
 */
import Settings from '../components/settings';
import Options from '../components/options';

addFilter(
	'QuillForms.PaymentGateways.PaymentGatewayModule',
	'QuillForms/BTCPayServer/ImplementPaymentGatewayModuleAdmin',
	( gateway, slug: string ) => {
		if ( slug === 'btcpayserver' ) {
			gateway.active = true;
			gateway.settings = Settings;
			gateway.options = {
				component: Options,
				has: ( settings: any ) => true,
				validate: ( settings: any ) => {
					return {
						valid: true,
					};
				},
			};

			const localize = window[ 'quillforms_btcpayserver_localize' ] ?? {};
			if ( localize.configured ) {
				gateway.methods.checkout.configured = true;
			}
		}
		return gateway;
	}
);
