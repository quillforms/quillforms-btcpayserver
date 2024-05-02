/**
 * QuillForms Dependencies.
 */
import ConfigApi from '@quillforms/config';

type CreateOrderResult = Promise<
	{ success: true; transId: string } | { success: false; message: string }
>;

export const createOrder = async (
	card_number: string,
	card_exp_month: string,
	card_exp_year: string,
	card_cvc: string,
	submission_id: string
): CreateOrderResult => {
	try {
		let response = await fetch(
			ConfigApi.getAdminUrl() + 'admin-ajax.php',
			{
				method: 'POST',
				body: new URLSearchParams( {
					action: 'quillforms_btcpayserver_create_order',
					card_number,
					card_exp_month,
					card_exp_year,
					card_cvc,
					submission_id,
				} ),
			}
		);

		let result = await response.json();
		if ( ! result.data?.transId ) {
			throw Error( result.data?.message );
		}

		return {
			success: true,
			transId: result.data.transId,
		};
	} catch ( e ) {
		console.log( 'createOrder: error throwed', e );
		return {
			success: false,
			message:
				e instanceof Error && e.message
					? e.message
					: 'Unexpected error',
		};
	}
};

type CreateSubscriptionResult = Promise<
	{ success: true; transId: string } | { success: false; message: string }
>;

export const createSubscription = async (
	card_number: string,
	card_exp_month: string,
	card_exp_year: string,
	card_cvc: string,
	submission_id: string
): CreateSubscriptionResult => {
	try {
		let response = await fetch(
			ConfigApi.getAdminUrl() + 'admin-ajax.php',
			{
				method: 'POST',
				body: new URLSearchParams( {
					action: 'quillforms_btcpayserver_create_subscription',
					card_number,
					card_exp_month,
					card_exp_year,
					card_cvc,
					submission_id,
				} ),
			}
		);

		let result = await response.json();
		if ( ! result.data?.transId ) {
			throw Error( result.data?.message );
		}

		return {
			success: true,
			transId: result.data.transId,
		};
	} catch ( e ) {
		console.log( 'createSubscription: error throwed', e );
		return {
			success: false,
			message:
				e instanceof Error && e.message
					? e.message
					: 'Unexpected error',
		};
	}
};
