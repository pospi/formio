<?php
/**
 *
 */

class FormIOField_Creditcard extends FormIOField_Text
{
	public static $VALIDATOR_ERRORS = array(
		'creditcardValidator'	=> "Invalid card number",
	);

	protected $validators = array(
		'creditcardValidator'
	);

	// append 'behaviour' parameter to the input for JS validation
	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();
		$vars['behaviour'] = 'credit';
		return $vars;
	}

	final protected function creditcardValidator() {
		// :TODO:
		return true;
	}
}
?>
