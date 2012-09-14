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

	// mod10 validation for the card number before sending to any gateway
	final protected function creditcardValidator() {
		$val = preg_replace('/[^0-9]/', '', $this->getValue());
		$this->setValue($val);

		// check number format - it has to match one of the major card formats to be valid
		$validFormat = preg_match("/^5[1-5][0-9]{14}$/", $val) ||			// mastercard
					preg_match("/^4[0-9]{12}([0-9]{3})?$/", $val) ||		// visa
					preg_match("/^3[47][0-9]{13}$/", $val) ||				// amex
					preg_match("/^6011[0-9]{12}$/", $val) ||				// discover
					preg_match("/^3(0[0-5]|[68][0-9])[0-9]{11}$/", $val) ||	// diners club
					preg_match("/^(3[0-9]{4}|2131|1800)[0-9]{11}$/", $val);	// JCB

		if (!$validFormat) {
			return false;
		}

		// run mod10 validator
		$cardNumber = strrev($val);
		$numSum = 0;
		for ($i = 0; $i < strlen($cardNumber); $i++) {
			$currentNum = substr($cardNumber, $i, 1);

			// Double every second digit
			if ($i % 2 == 1) {
				$currentNum *= 2;
			}
			// Add digits of 2-digit numbers together
			if ($currentNum > 9) {
				$firstNum = $currentNum % 10;
				$secondNum = ($currentNum - $firstNum) / 10;
				$currentNum = $firstNum + $secondNum;
			}

			$numSum += $currentNum;
		}

		return $numSum % 10 == 0;
	}
}
?>
