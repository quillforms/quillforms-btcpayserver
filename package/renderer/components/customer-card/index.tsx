/**
 * Quill Forms Dependencies
 */
import { Button, useTheme } from '@quillforms/renderer-core';

/**
 * WordPress Dependencies
 */
import { useState, useRef, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * External Dependencies
 */
import classnames from 'classnames';
import { TailSpin as Loader } from 'react-loader-spinner';
import { css } from 'emotion';

/**
 * Internal Dependencies
 */
import CForm from './form';
import Card from './card';
import './style.scss';
import { createOrder, createSubscription } from '../../utils';

interface Props {
	slug: string;
	data: any;
	onComplete: () => void;
}

const initialState = {
	cardNumber: '#### #### #### ####',
	cardMonth: '',
	cardYear: '',
	cardCvv: '',
	isCardFlipped: false,
};

const CustomerCard: React.FC< Props > = ( { data, onComplete } ) => {
	const [ isPaying, setIsPaying ] = useState( false );
	const generalTheme = useTheme();
	const [ state, setState ] = useState( initialState );
	const [ error, setError ] = useState( '' );
	const [ currentFocusedElm, setCurrentFocusedElm ] = useState( null );
	const isSubscription = !! data.payments.recurring;

	const updateStateValues = useCallback(
		( keyName, value ) => {
			setState( {
				...state,
				[ keyName ]: value || initialState[ keyName ],
			} );
		},
		[ state ]
	);

	const handlePayment = async () => {
		if ( isPaying ) return;
		setIsPaying( true );
		setError( '' );
		const result = await createOrder(
			state.cardNumber,
			state.cardMonth,
			state.cardYear,
			state.cardCvv,
			data.submission_id
		);
		if ( result.success ) {
			onComplete();
		} else {
			setIsPaying( false );
			setError( result.message ); // TODO: replace with dom message?
			throw Error( result.message );
		}
	};

	const handleSubscription = async () => {
		if ( isPaying ) return;
		setIsPaying( true );
		setError( '' );
		const result = await createSubscription(
			state.cardNumber,
			state.cardMonth,
			state.cardYear,
			state.cardCvv,
			data.submission_id
		);
		if ( result.success ) {
			onComplete();
		} else {
			setIsPaying( false );
			setError( result.message ); // TODO: replace with dom message?
			throw Error( result.message );
		}
	};

	// References for the Form Inputs used to focus corresponding inputs.
	let formFieldsRefObj = {
		cardNumber: useRef( null ),
		cardDate: useRef( null ),
		cardCvv: useRef( null ),
	};

	let focusFormFieldByKey = useCallback( ( key ) => {
		formFieldsRefObj[ key ].current.focus();
	} );

	// This are the references for the Card DIV elements.
	let cardElementsRef = {
		cardNumber: useRef(),
		cardHolder: useRef(),
		cardDate: useRef(),
	};

	let onCardFormInputFocus = ( _event, inputName ) => {
		const refByName = cardElementsRef[ inputName ];
		setCurrentFocusedElm( refByName );
	};

	let onCardInputBlur = useCallback( () => {
		setCurrentFocusedElm( null );
	}, [] );

	return (
		<div className="quillforms-btcpayserver-renderer-card">
			<div className="wrapper">
				<CForm
					cardMonth={ state.cardMonth }
					cardYear={ state.cardYear }
					onUpdateState={ updateStateValues }
					cardNumberRef={ formFieldsRefObj.cardNumber }
					cardDateRef={ formFieldsRefObj.cardDate }
					onCardInputFocus={ onCardFormInputFocus }
					onCardInputBlur={ onCardInputBlur }
				>
					<Card
						cardNumber={ state.cardNumber }
						cardMonth={ state.cardMonth }
						cardYear={ state.cardYear }
						cardCvv={ state.cardCvv }
						isCardFlipped={ state.isCardFlipped }
						currentFocusedElm={ currentFocusedElm }
						onCardElementClick={ focusFormFieldByKey }
						cardNumberRef={ cardElementsRef.cardNumber }
						cardDateRef={ cardElementsRef.cardDate }
					></Card>
				</CForm>
			</div>
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
				onClick={ isSubscription ? handleSubscription : handlePayment }
			>
				<span id="button-text">
					{ data?.labels?.pay ??
						__( 'Pay Now', 'quillforms-btcpayserver' ) }
					{ isPaying && (
						<span
							style={ {
								display: 'inline-flex',
								height: '100%',
								alignItems: 'center',
							} }
						>
							<Loader color="#fff" height={ 20 } width={ 20 } />
						</span>
					) }
				</span>
			</Button>
			{ error && <div className="error">{ error }</div> }
		</div>
	);
};

export default CustomerCard;
