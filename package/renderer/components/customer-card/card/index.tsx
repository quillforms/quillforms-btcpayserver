/**
 * WordPress Dependencies
 */
import { useState, useEffect, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	CSSTransition,
	TransitionGroup,
	SwitchTransition,
} from 'react-transition-group';

const CARDS = {
	visa: '^4',
	amex: '^(34|37)',
	mastercard: '^5[1-5]',
	discover: '^6011',
	unionpay: '^62',
	troy: '^9792',
	diners: '^(30[0-5]|36)',
};

const cardBackgroundName = () => {
	let random = Math.floor( Math.random() * 25 + 1 );
	return `${ random }.jpeg`;
};

const BACKGROUND_IMG = cardBackgroundName();

const Card = ( {
	cardNumber,
	cardMonth,
	cardYear,
	cardCvv,
	isCardFlipped,
	currentFocusedElm,
	onCardElementClick,
	cardNumberRef,
	cardDateRef,
} ) => {
	const { assetsDir } = window[ 'quillforms_btcpayserver_localize' ];
	const [ style, setStyle ] = useState( null );

	const cardType = ( cardNumber ) => {
		const number = cardNumber;
		let re;
		for ( const [ card, pattern ] of Object.entries( CARDS ) ) {
			re = new RegExp( pattern );
			if ( number.match( re ) != null ) {
				return card;
			}
		}

		return 'visa'; // default type
	};

	const useCardType = useMemo( () => {
		return cardType( cardNumber );
	}, [ cardNumber ] );

	const outlineElementStyle = ( element ) => {
		return element
			? {
					width: `${ element.offsetWidth }px`,
					height: `${ element.offsetHeight }px`,
					transform: `translateX(${ element.offsetLeft }px) translateY(${ element.offsetTop }px)`,
			  }
			: null;
	};

	useEffect( () => {
		if ( currentFocusedElm ) {
			const style = outlineElementStyle( currentFocusedElm.current );
			setStyle( style );
		}
	}, [ currentFocusedElm ] );

	const maskCardNumber = ( cardNumber ) => {
		let cardNumberArr = cardNumber.split( '' );
		cardNumberArr.forEach( ( val, index ) => {
			if ( index > 4 && index < 14 ) {
				if ( cardNumberArr[ index ] !== ' ' ) {
					cardNumberArr[ index ] = '*';
				}
			}
		} );

		return cardNumberArr;
	};

	return (
		<div className={ 'card-item ' + ( isCardFlipped ? '-active' : '' ) }>
			<div className="card-item__side -front">
				<div
					className={ `card-item__focus ${
						currentFocusedElm ? `-active` : ``
					}` }
					style={ style }
				/>
				<div className="card-item__cover">
					<img
						alt=""
						src={ `${ assetsDir }/card-background/${ BACKGROUND_IMG }` }
						className="card-item__bg"
					/>
				</div>

				<div className="card-item__wrapper">
					<div className="card-item__top">
						<img
							src={ `${ assetsDir }/chip.png` }
							alt=""
							className="card-item__chip"
						/>
						<div className="card-item__type">
							<img
								alt={ useCardType }
								src={ `${ assetsDir }/card-type/${ useCardType }.png` }
								className="card-item__typeImg"
							/>
						</div>
					</div>

					<label
						className="card-item__number"
						ref={ cardNumberRef }
						onClick={ () => onCardElementClick( 'cardNumber' ) }
					>
						<TransitionGroup
							className="slide-fade-up"
							component="div"
						>
							{ cardNumber ? (
								maskCardNumber( cardNumber ).map(
									( val, index ) => (
										<CSSTransition
											classNames="slide-fade-up"
											timeout={ 250 }
											key={ index }
										>
											<div className="card-item__numberItem">
												{ val }
											</div>
										</CSSTransition>
									)
								)
							) : (
								<CSSTransition
									classNames="slide-fade-up"
									timeout={ 250 }
								>
									<div className="card-item__numberItem">
										#
									</div>
								</CSSTransition>
							) }
						</TransitionGroup>
					</label>
					<div className="card-item__content">
						<div
							className="card-item__date"
							onClick={ () => onCardElementClick( 'cardDate' ) }
							ref={ cardDateRef }
						>
							<label className="card-item__dateTitle">
								{ __( 'Expires', 'quillforms-btcpayserver' ) }
							</label>
							<label className="card-item__dateItem">
								<SwitchTransition in-out>
									<CSSTransition
										classNames="slide-fade-up"
										timeout={ 200 }
										key={ cardMonth }
									>
										<span>
											{ ! cardMonth ? 'MM' : cardMonth }{ ' ' }
										</span>
									</CSSTransition>
								</SwitchTransition>
							</label>
							/
							<label
								htmlFor="cardYear"
								className="card-item__dateItem"
							>
								<SwitchTransition out-in>
									<CSSTransition
										classNames="slide-fade-up"
										timeout={ 250 }
										key={ cardYear }
									>
										<span>
											{ ! cardYear
												? 'YY'
												: cardYear
														.toString()
														.substr( -2 ) }
										</span>
									</CSSTransition>
								</SwitchTransition>
							</label>
						</div>
					</div>
				</div>
			</div>

			<div className="card-item__side -back">
				<div className="card-item__cover">
					<img
						alt=""
						src={ `${ assetsDir }/card-background/${ BACKGROUND_IMG }` }
						className="card-item__bg"
					/>
				</div>
				<div className="card-item__band" />
				<div className="card-item__cvv">
					<div className="card-item__cvvTitle">
						{ __( 'CVV', 'quillforms-btcpayserver' ) }
					</div>
					<div className="card-item__cvvBand">
						<TransitionGroup>
							{ cardCvv.split( '' ).map( ( val, index ) => (
								<CSSTransition
									classNames="zoom-in-out"
									key={ index }
									timeout={ 250 }
								>
									<span>*</span>
								</CSSTransition>
							) ) }
						</TransitionGroup>
					</div>
					<div className="card-item__type">
						<img
							alt="card-type"
							src={ `${ assetsDir }/card-type/visa.png` }
							className="card-item__typeImg"
						/>
					</div>
				</div>
			</div>
		</div>
	);
};

export default Card;
