<?php
/**
 *
 */

class FormIOField_Alpha extends FormIOField_Text
{
	// override the regex validator's error message for this field type
	public static $VALIDATOR_ERRORS = array(
		'regexValidator' => "Must contain letters only"
	);

	// validate with regex validator internally
	protected $validators = array(
		array(
			'func' => 'regexValidator',
			'params' => array('/^[A-Za-z]*$/')
		)
	);

	// append 'behaviour' parameter to the input for JS validation
	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();
		$vars['behaviour'] = 'alpha';
		return $vars;
	}
}
?>
