/**
 * QuillForms Dependencies.
 */
import { ComboboxControl } from '@quillforms/admin-components';
import type { CustomizeFunction } from '@quillforms/admin-components/build-types/combobox-control';

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal Dependencies
 */
import './style.scss';

interface Props {
	settings: any;
	onChange: ( options: any ) => void;
}

const Options: React.FC< Props > = ( { settings, onChange } ) => {
	const options = settings.gateways_options?.btcpayserver ?? {};

	const customize: CustomizeFunction = ( value ) => {
		let { sections, options } = value;

		sections = sections.filter( ( section ) => {
			return [ 'fields', 'hidden_fields' ].includes( section.key );
		} );

		options = options.filter( ( option ) => {
			return [ 'field', 'hidden_field' ].includes( option.type );
		} );

		return { sections, options };
	};

	return (
		<div className="btcpayserver-gateway-options">
			<div className="btcpayserver-customer-name">
				<label>
					{ __( 'Customer Name', 'quillforms-btcpayserver' ) }
				</label>
				<ComboboxControl
					value={ options.customer_name ?? {} }
					onChange={ ( value ) => {
						onChange( {
							...options,
							customer_name: value,
						} );
					} }
					customize={ customize }
					isToggleEnabled={ false }
				/>
			</div>
			<div className="btcpayserver-customer-email">
				<label>
					{ __( 'Customer Email', 'quillforms-btcpayserver' ) }
				</label>
				<ComboboxControl
					value={ options.customer_email ?? {} }
					onChange={ ( value ) => {
						onChange( {
							...options,
							customer_email: value,
						} );
					} }
					customize={ customize }
					isToggleEnabled={ false }
				/>
			</div>
			<div className="btcpayserver-customer-address1">
				<label>
					{ __( 'Customer Address 1', 'quillforms-btcpayserver' ) }
				</label>
				<ComboboxControl
					value={ options.customer_address1 ?? {} }
					onChange={ ( value ) => {
						onChange( {
							...options,
							customer_address1: value,
						} );
					} }
					customize={ customize }
					isToggleEnabled={ false }
				/>
			</div>
			<div className="btcpayserver-customer-address2">
				<label>
					{ __( 'Customer Address 2', 'quillforms-btcpayserver' ) }
				</label>
				<ComboboxControl
					value={ options.customer_address2 ?? {} }
					onChange={ ( value ) => {
						onChange( {
							...options,
							customer_address2: value,
						} );
					} }
					customize={ customize }
					isToggleEnabled={ false }
				/>
			</div>
			<div className="btcpayserver-customer-city">
				<label>
					{ __( 'Customer City', 'quillforms-btcpayserver' ) }
				</label>
				<ComboboxControl
					value={ options.customer_city ?? {} }
					onChange={ ( value ) => {
						onChange( {
							...options,
							customer_city: value,
						} );
					} }
					customize={ customize }
					isToggleEnabled={ false }
				/>
			</div>
			<div className="btcpayserver-customer-state">
				<label>
					{ __( 'Customer State', 'quillforms-btcpayserver' ) }
				</label>
				<ComboboxControl
					value={ options.customer_state ?? {} }
					onChange={ ( value ) => {
						onChange( {
							...options,
							customer_state: value,
						} );
					} }
					customize={ customize }
					isToggleEnabled={ false }
				/>
			</div>
			<div className="btcpayserver-customer-zip">
				<label>
					{ __( 'Customer Zip', 'quillforms-btcpayserver' ) }
				</label>
				<ComboboxControl
					value={ options.customer_zip ?? {} }
					onChange={ ( value ) => {
						onChange( {
							...options,
							customer_zip: value,
						} );
					} }
					customize={ customize }
					isToggleEnabled={ false }
				/>
			</div>
			<div className="btcpayserver-customer-country">
				<label>
					{ __( 'Customer Country', 'quillforms-btcpayserver' ) }
				</label>
				<ComboboxControl
					value={ options.customer_country ?? {} }
					onChange={ ( value ) => {
						onChange( {
							...options,
							customer_country: value,
						} );
					} }
					customize={ customize }
					isToggleEnabled={ false }
				/>
			</div>
		</div>
	);
};

export default Options;
