/**
 * WordPress Dependencies
 */
import { addAction } from '@wordpress/hooks';

/**
 * Internal Dependencies
 */

addAction(
	'QuillForms.RendererCore.PaymentStep',
	'QuillForms/BTCPayServer/Implement',
	( urlParams: URLSearchParams, completeForm, showPaymentModal ) => {
		switch ( urlParams.get( 'method' ) ) {
			case 'btcpayserver:checkout':
				completeForm();
				break;
		}
	}
);
