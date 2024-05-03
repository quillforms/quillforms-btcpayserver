/**
 * QuillForms Dependencies.
 */
import {
	ToggleControl,
	TextControl,
	Button,
} from '@quillforms/admin-components';
import { setForceReload } from '@quillforms/navigation';
import ConfigApi from '@quillforms/config';

/**
 * WordPress Dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * External Dependencies
 */
import { css } from 'emotion';
import { ThreeDots as Loader } from 'react-loader-spinner';

/**
 * Internal Dependencies
 */
import './style.scss';

type SettingsType = {
	mode: string;
	sandbox_site_url: string;
	sandbox_store_id: string;
	sandbox_api_key: string;
	live_site_url: string;
	live_store_id: string;
	live_api_key: string;
	customer_checkout_label: string;
};

const Settings: React.FC = () => {
	const { ajax_nonce } = window[ 'quillforms_btcpayserver_localize' ] ?? {};
	const [ settings, setSettings ] = useState< null | false | SettingsType >(
		null
	);
	const [ isGenerating, setIsGenerating ] = useState( false );
	const { createErrorNotice, createSuccessNotice } =
		useDispatch( 'core/notices' );

	const setSetting = ( key, value ) => {
		if ( settings ) {
			setSettings( {
				...settings,
				[ key ]: value,
			} );
		}
	};

	useEffect( () => {
		apiFetch( {
			path: '/qf/v1/addons/btcpayserver/settings',
			method: 'GET',
		} )
			.then( ( res: any ) => {
				if ( ! isObject( res ) ) {
					res = {};
				}
				if ( ! res.mode ) {
					res.mode = 'live';
				}
				setSettings( res as SettingsType );
			} )
			.catch( () => {
				setSettings( false );
			} );
	}, [] );

	const generateApiKey = async () => {
		if ( ! settings ) return;
		if ( ! settings.mode ) return;
		if ( ! settings[ `${ settings.mode }_site_url` ] ) {
			createErrorNotice(
				`⛔ ${ __(
					'Site URL is required',
					'quillforms-btcpayserver'
				) }`,
				{
					type: 'snackbar',
					isDismissible: true,
				}
			);
			return;
		}
		setIsGenerating( true );

		try {
			// @ts-ignore
			const res = await fetch(
				ConfigApi.getAdminUrl() + 'admin-ajax.php',
				{
					method: 'POST',
					body: new URLSearchParams( {
						action: 'quillforms_btcpayserver_get_api_key',
						mode: settings.mode,
						host: settings[ `${ settings.mode }_site_url` ],
						nonce: ajax_nonce,
					} ),
				}
			);
			const data = await res.json();

			if ( data.data.url ) {
				// Redirect to the generated api key
				window.location.href = data.data.url;
			} else {
				createErrorNotice(
					`⛔ ${
						data.message ??
						__(
							'Error on generating API Key',
							'quillforms-btcpayserver'
						)
					}`,
					{
						type: 'snackbar',
						isDismissible: true,
					}
				);
			}
		} catch ( error ) {}
	};

	const save = () => {
		apiFetch( {
			path: '/qf/v1/addons/btcpayserver/settings',
			method: 'POST',
			data: settings,
		} )
			.then( ( res: any ) => {
				createSuccessNotice(
					`✅ ${
						res.message ??
						__( 'Settings saved', 'quillforms-btcpayserver' )
					}`,
					{
						type: 'snackbar',
						isDismissible: true,
					}
				);
				setForceReload( true );
			} )
			.catch( ( err ) => {
				createErrorNotice(
					`⛔ ${
						err.message ??
						__(
							'Error on saving settings',
							'quillforms-btcpayserver'
						)
					}`,
					{
						type: 'snackbar',
						isDismissible: true,
					}
				);
			} );
	};

	return (
		<div className="quillforms-settings-payments-btcpayserver">
			{ settings === null ? (
				<div
					className={ css`
						display: flex;
						flex-wrap: wrap;
						width: 100%;
						height: 100px;
						justify-content: center;
						align-items: center;
					` }
				>
					<Loader color="#8640e3" height={ 50 } width={ 50 } />
				</div>
			) : ! settings ? (
				__( 'Error on loading settings', 'quillforms-btcpayserver' )
			) : (
				<>
					<div
						style={ {
							marginBottom: 20,
							borderBottom: '1px solid #ccc',
						} }
					>
						<h4 style={ { margin: '0 0 5px' } }>
							Setup Instructions:
						</h4>
						<ol style={ { marginBottom: 20 } }></ol>
					</div>
					<div className="quillforms-settings-payments-btcpayserver-row quillforms-settings-payments-btcpayserver-row-mode">
						<div className="quillforms-settings-payments-btcpayserver-row-label">
							{ __( 'Sandbox mode', 'quillforms-btcpayserver' ) }
						</div>
						<ToggleControl
							checked={ settings.mode === 'sandbox' }
							onChange={ () =>
								setSetting(
									'mode',
									settings.mode === 'sandbox'
										? 'live'
										: 'sandbox'
								)
							}
						/>
					</div>
					{ settings.mode === 'sandbox' ? (
						<>
							<div className="quillforms-settings-payments-btcpayserver-row">
								<div className="quillforms-settings-payments-btcpayserver-row-label">
									{ __(
										'Sandbox Site URL',
										'quillforms-btcpayserver'
									) }
								</div>
								<TextControl
									className=""
									value={ settings.sandbox_site_url ?? '' }
									onChange={ ( value ) =>
										setSetting( 'sandbox_site_url', value )
									}
								/>
							</div>
							<div className="quillforms-settings-payments-btcpayserver-row">
								<Button isPrimary onClick={ generateApiKey }>
									{ isGenerating ? (
										<Loader
											color="#fff"
											height={ 20 }
											width={ 20 }
										/>
									) : (
										__(
											'Generate API Key',
											'quillforms-btcpayserver'
										)
									) }
								</Button>
							</div>
							<div className="quillforms-settings-payments-btcpayserver-row">
								<div className="quillforms-settings-payments-btcpayserver-row-label">
									{ __(
										'Sandbox Store ID',
										'quillforms-btcpayserver'
									) }
								</div>
								<TextControl
									className=""
									value={ settings.sandbox_store_id ?? '' }
									onChange={ ( value ) =>
										setSetting( 'sandbox_store_id', value )
									}
								/>
							</div>
							<div className="quillforms-settings-payments-btcpayserver-row">
								<div className="quillforms-settings-payments-btcpayserver-row-label">
									{ __(
										'Sandbox API Key',
										'quillforms-btcpayserver'
									) }
								</div>
								<TextControl
									className=""
									value={ settings.sandbox_api_key ?? '' }
									onChange={ ( value ) =>
										setSetting( 'sandbox_api_key', value )
									}
								/>
							</div>
						</>
					) : (
						<>
							<div className="quillforms-settings-payments-btcpayserver-row">
								<div className="quillforms-settings-payments-btcpayserver-row-label">
									{ __(
										'Live Site URL',
										'quillforms-btcpayserver'
									) }
								</div>
								<TextControl
									className=""
									value={ settings.live_site_url ?? '' }
									onChange={ ( value ) =>
										setSetting( 'live_site_url', value )
									}
								/>
							</div>
							<div className="quillforms-settings-payments-btcpayserver-row">
								<Button isPrimary onClick={ generateApiKey }>
									{ isGenerating ? (
										<Loader
											color="#fff"
											height={ 20 }
											width={ 20 }
										/>
									) : (
										__(
											'Generate API Key',
											'quillforms-btcpayserver'
										)
									) }
								</Button>
							</div>
							<div className="quillforms-settings-payments-btcpayserver-row">
								<div className="quillforms-settings-payments-btcpayserver-row-label">
									{ __(
										'Live Store ID',
										'quillforms-btcpayserver'
									) }
								</div>
								<TextControl
									className=""
									value={ settings.live_store_id ?? '' }
									onChange={ ( value ) =>
										setSetting( 'live_store_id', value )
									}
								/>
							</div>
							<div className="quillforms-settings-payments-btcpayserver-row">
								<div className="quillforms-settings-payments-btcpayserver-row-label">
									{ __(
										'Live API Key',
										'quillforms-btcpayserver'
									) }
								</div>
								<TextControl
									className=""
									value={ settings.live_api_key ?? '' }
									onChange={ ( value ) =>
										setSetting( 'live_api_key', value )
									}
								/>
							</div>
						</>
					) }
					<div
						className={ css`
							margin-top: 15px;
							margin-bottom: 10px;
						` }
					>
						{ __(
							'Method labels for customer:',
							'quillforms-btcpayserver'
						) }
					</div>
					<div className="quillforms-settings-payments-btcpayserver-row">
						<div className="quillforms-settings-payments-btcpayserver-row-label">
							{ __(
								'Checkout Label',
								'quillforms-btcpayserver'
							) }
						</div>
						<TextControl
							className=""
							value={ settings.customer_checkout_label ?? '' }
							onChange={ ( value ) =>
								setSetting( 'customer_checkout_label', value )
							}
						/>
					</div>
					<Button isPrimary onClick={ save }>
						{ __( 'Save', 'quillforms-btcpayserver' ) }
					</Button>
				</>
			) }
		</div>
	);
};

const isObject = ( variable: unknown ): boolean => {
	return (
		typeof variable === 'object' &&
		variable !== null &&
		! Array.isArray( variable )
	);
};

export default Settings;
