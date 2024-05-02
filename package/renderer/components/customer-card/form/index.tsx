/**
 * Quill Forms Dependencies
 */
import { useTheme } from '@quillforms/renderer-core';

/**
 * WordPress Dependencies
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * External Dependencies
 */
import { css } from 'emotion';
import tinyColor from 'tinycolor2';
import classnames from 'classnames';

const currentYear = new Date().getFullYear();
const monthsArr = Array.from( { length: 12 }, ( x, i ) => {
	const month = i + 1;
	return month <= 9 ? '0' + month : month;
} );
const yearsArr = Array.from( { length: 9 }, ( _x, i ) => currentYear + i );

export default function CForm( {
	cardMonth,
	cardYear,
	onUpdateState,
	cardNumberRef,
	cardDateRef,
	onCardInputFocus,
	onCardInputBlur,
	cardCvv,
	children,
} ) {
	const [ cardNumber, setCardNumber ] = useState( '' );

	const handleFormChange = ( event ) => {
		const { name, value } = event.target;

		onUpdateState( name, value );
	};
	const generalTheme = useTheme();
	const answersColor = tinyColor( generalTheme.answersColor );
	// TODO: We can improve the regex check with a better approach like in the card component.
	const onCardNumberChange = ( event ) => {
		let { value, name } = event.target;
		let cardNumber = value;
		value = value.replace( /\D/g, '' );
		if ( /^3[47]\d{0,13}$/.test( value ) ) {
			cardNumber = value
				.replace( /(\d{4})/, '$1 ' )
				.replace( /(\d{4}) (\d{6})/, '$1 $2 ' );
		} else if ( /^3(?:0[0-5]|[68]\d)\d{0,11}$/.test( value ) ) {
			// diner's club, 14 digits
			cardNumber = value
				.replace( /(\d{4})/, '$1 ' )
				.replace( /(\d{4}) (\d{6})/, '$1 $2 ' );
		} else if ( /^\d{0,16}$/.test( value ) ) {
			// regular cc number, 16 digits
			cardNumber = value
				.replace( /(\d{4})/, '$1 ' )
				.replace( /(\d{4}) (\d{4})/, '$1 $2 ' )
				.replace( /(\d{4}) (\d{4}) (\d{4})/, '$1 $2 $3 ' );
		}

		setCardNumber( cardNumber.trimRight() );
		onUpdateState( name, cardNumber );
	};

	const onCvvFocus = () => {
		onUpdateState( 'isCardFlipped', true );
	};

	const onCvvBlur = () => {
		onUpdateState( 'isCardFlipped', false );
	};

	return (
		<div
			className={ classnames(
				'card-form',
				css`
					label {
						color: ${ generalTheme.questionsColor };
						text-align: left;
					}
					.card-input__input {
						color: ${ generalTheme.answersColor };
						box-shadow: ${ answersColor.setAlpha( 0.3 ).toString() }
							0px 1px !important;
						&::placeholder {
							color: ${ answersColor.setAlpha( 0.3 ).toString() };
						}
					}
					.card-input__input.-select option {
						color: #000;
						background: #fff;
					}
					.select-box::after {
						border-top-color: ${ generalTheme.answersColor };
					}
				`
			) }
		>
			<div className="card-list">{ children }</div>
			<div className="card-form__inner">
				<div className="card-input">
					<label htmlFor="cardNumber" className="card-input__label">
						{ __( 'Card Number', 'quillforms-btcpayserver' ) }
					</label>
					<input
						type="tel"
						name="cardNumber"
						className="card-input__input"
						autoComplete="off"
						onChange={ onCardNumberChange }
						maxLength="19"
						ref={ cardNumberRef }
						onFocus={ ( e ) => onCardInputFocus( e, 'cardNumber' ) }
						onBlur={ onCardInputBlur }
						value={ cardNumber }
						placeholder="#### **** **** ####"
					/>
				</div>
				<div className="card-form__row">
					<div className="card-form__col">
						<div className="card-form__group">
							<label
								htmlFor="cardMonth"
								className="card-input__label"
							>
								{ __(
									'Expiration Date',
									'quillforms-btcpayserver'
								) }
							</label>
							<div className="select-box">
								<select
									className="card-input__input -select"
									value={ cardMonth }
									name="cardMonth"
									onChange={ handleFormChange }
									ref={ cardDateRef }
									onFocus={ ( e ) =>
										onCardInputFocus( e, 'cardDate' )
									}
									onBlur={ onCardInputBlur }
								>
									<option value="" disabled>
										{ __(
											'Month',
											'quillforms-btcpayserver'
										) }
									</option>

									{ monthsArr.map( ( val, index ) => (
										<option key={ index } value={ val }>
											{ val }
										</option>
									) ) }
								</select>
							</div>
							<div className="select-box">
								<select
									name="cardYear"
									className="card-input__input -select"
									value={ cardYear }
									onChange={ handleFormChange }
									onFocus={ ( e ) =>
										onCardInputFocus( e, 'cardDate' )
									}
									onBlur={ onCardInputBlur }
								>
									<option value="" disabled>
										{ __(
											'Year',
											'quillforms-btcpayserver'
										) }
									</option>

									{ yearsArr.map( ( val, index ) => (
										<option key={ index } value={ val }>
											{ val }
										</option>
									) ) }
								</select>
							</div>
						</div>
					</div>
					<div className="card-form__col -cvv">
						<div className="card-input">
							<label
								htmlFor="cardCvv"
								className="card-input__label"
							>
								{ __( 'CVV', 'quillforms-btcpayserver' ) }
							</label>
							<input
								type="tel"
								className="card-input__input"
								maxLength="4"
								autoComplete="off"
								name="cardCvv"
								onChange={ handleFormChange }
								onFocus={ onCvvFocus }
								onBlur={ onCvvBlur }
								ref={ cardCvv }
								placeholder="***"
							/>
						</div>
					</div>
				</div>
			</div>
		</div>
	);
}
