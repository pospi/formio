<?php
/**
 * :TODO: i18n
 */

class FormIOField_Postcode extends FormIOField_Text
{
	public static $VALIDATOR_ERRORS = array(
		'AUPostCodeValidator'	=> "Unknown post code",
	);

	protected $validators = array(
		'AUPostCodeValidator'
	);

	// append 'behaviour' parameter to the input for JS validation
	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();
		$vars['behaviour'] = 'postcodeAU';
		return $vars;
	}

	final protected function AUPostCodeValidator() {
		return strlen($this->value) == 4 && intval($this->value) < 10000;
	}
}
?>
