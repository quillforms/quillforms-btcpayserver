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
					{ __( 'Customer First Name', 'quillforms-btcpayserver' ) }
				</label>
				<ComboboxControl
					value={ options.customer_first_name ?? {} }
					onChange={ ( value ) => {
						onChange( {
							...options,
							customer_first_name: value,
						} );
					} }
					customize={ customize }
					isToggleEnabled={ false }
				/>
			</div>
			<div className="btcpayserver-customer-email">
				<label>
					{ __( 'Customer Last Name', 'quillforms-btcpayserver' ) }
				</label>
				<ComboboxControl
					value={ options.customer_last_name ?? {} }
					onChange={ ( value ) => {
						onChange( {
							...options,
							customer_last_name: value,
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
