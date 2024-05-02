/**
 * QuillForms Dependencies.
 */

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * External Dependencies
 */

/**
 * Internal Dependencies
 */
import './style.scss';

interface Props {
	slug: string;
	options: any;
}

const NotConfigured: React.FC< Props > = () => {
	return <div>Please set btcpayserver configurations before using</div>;
};

export default NotConfigured;
