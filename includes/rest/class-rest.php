<?php
/**
 * REST class.
 *
 * @since 1.0.0
 * @package QuillForms_BTCPayServer
 */

namespace QuillForms_BTCPayServer\REST;

use QuillForms\Addon\REST\REST as Abstract_REST;

/**
 * REST class.
 *
 * @since 1.0.0
 */
class REST extends Abstract_REST {

	/**
	 * Class names
	 *
	 * @var array
	 */
	protected static $classes = array(
		'settings_controller' => Settings_Controller::class,
	);

}
