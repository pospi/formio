<?php
/**
 *
 */

class FormIOField_Email extends FormIOField_Text
{
	// override the regex validator's error message for this field type
	public static $VALIDATOR_ERRORS = array(
		'regexValidator' => "Invalid email address"
	);

	// validate with regex validator internally
	protected $validators = array(
		array(
			'func' => 'regexValidator',
			'params' => array('/^[-!#$%&\'*+\\.\/0-9=?A-Z^_`{|}~]+@([-0-9A-Z]+\.)+([0-9A-Z]){2,4}$/i')
		)
	);

	// append 'behaviour' parameter to the input for JS validation
	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();
		$vars['behaviour'] = 'email';
		return $vars;
	}
}
?>
