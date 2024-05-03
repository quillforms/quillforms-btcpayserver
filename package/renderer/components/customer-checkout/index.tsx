/**
 * Quill Forms Dependencies
 */
import { Button, useTheme } from '@quillforms/renderer-core';
import ConfigApi from '@quillforms/config';

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * External Dependencies
 */
import React from 'react';
import classnames from 'classnames';
import { css } from 'emotion';
import { TailSpin as Loader } from 'react-loader-spinner';

/**
 * Internal Dependencies
 */
import './style.scss';

interface Props {
	slug: string;
	data: any;
	onComplete: () => void;
}

const CustomerCheckout: React.FC< Props > = ( { data } ) => {
	const generalTheme = useTheme();
	const [ isPaying, setIsPaying ] = React.useState( false );
	const handleClick = async () => {
		setIsPaying( true );
		try {
			const response = await await fetch(
				ConfigApi.getAdminUrl() + 'admin-ajax.php',
				{
					method: 'POST',
					body: new URLSearchParams( {
						action: 'quillforms_btcpayserver_create_order',
						submission_id: data.submission_id,
					} ),
				}
			);

			const responseData = await response.json();
			if ( responseData.data?.url ) {
				window.location.href = responseData.data.url;
			} else {
				console.error(
					'Stripe create checkout session error',
					responseData
				);
			}
		} catch ( e ) {}

		setIsPaying( false );
	};

	return (
		<div className="quillforms-btcpayserver-renderer-checkout">
			<Button
				className={ classnames(
					{
						loading: isPaying,
					},
					css`
						&.loading .renderer-core-arrow-icon {
							display: none;
						}
					`,
					'payment-button'
				) }
				onClick={ handleClick }
			>
				<span id="button-text">
					{ isPaying ? (
						<Loader
							color={ generalTheme.buttonsFontColor }
							height={ 50 }
							width={ 50 }
						/>
					) : (
						<>{ data?.labels?.pay ?? 'Pay now' }</>
					) }
				</span>
			</Button>
		</div>
	);
};

export default CustomerCheckout;
