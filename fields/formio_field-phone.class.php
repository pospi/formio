<?php
/**
 *
 */

class FormIOField_Phone extends FormIOField_Text
{
	public static $VALIDATOR_ERRORS = array(
		'phoneValidator'	=> "Invalid phone number. Phone numbers must contain numbers, spaces, dashes and brackets only, and may start with a plus sign.",
	);

	protected $validators = array(
		'phoneValidator'
	);

	// append 'behaviour' parameter to the input for JS validation
	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();
		$vars['behaviour'] = 'phone';
		return $vars;
	}

	final protected function phoneValidator() {
		return preg_match('/\d/', $this->value) && preg_match('/^(\+)?(\d|\s|-|(\(\d+\)))*$/', $this->value);
	}
}
?>
