<?php
/**
 *
 */

class FormIOField_Alphanumeric extends FormIOField_Text
{
	// override the regex validator's error message for this field type
	public static $VALIDATOR_ERRORS = array(
		'regexValidator' => "May contain letters and numbers only"
	);

	// validate with regex validator internally
	protected $validators = array(
		array(
			'func' => 'regexValidator',
			'params' => array('/^[A-Za-z0-9]*$/')
		)
	);

	// append 'behaviour' parameter to the input for JS validation
	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();
		$vars['behaviour'] = 'alphanumeric';
		return $vars;
	}
}
?>
