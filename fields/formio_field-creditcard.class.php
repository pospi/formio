<?php
/**
 * Credit card input field. Contains separate inputs for card information.
 */

class FormIOField_Creditcard extends FormIOField_Dropdown
{
	public $buildString = '<div class="row blck credit{$alt? alt}{$classes? $classes}" id="{$id}"{$dependencies? data-fio-depends="$dependencies"}{$validation? data-fio-validation="$validation"}>
		<label for="{$id}_type">{$desc}{$required? <span class="required">*</span>}</label>
		<div class="rows">
		<div class="row card-type">
			<label>Card type: <select name="{$name}[type]" id="{$id}_type"{$readonly? readonly="readonly"}{$required? data-fio-validation="requiredValidator"}>{$options}</select></label>
		</div>
		<div class="row card-name">
			<label>Name on card: <input type="text" name="{$name}[name]" id="{$id}_name"{$cardname? value="$cardname"}{$readonly? readonly="readonly"}{$required? data-fio-validation="requiredValidator"} /></label>
		</div>
		<div class="row card-number">
			<label>Card number: <input type="text" name="{$name}[number]" id="{$id}_number"{$cardnumber? value="$cardnumber"}{$readonly? readonly="readonly"}{$required? data-fio-validation="requiredValidator"} /></label>
		</div>
		<div class="row card-details clearfix">
			<label class="card-expiry">Expiry <em>(MM/YY)</em>: <input type="text" name="{$name}[expiry]" id="{$id}_expiry"{$cardexpiry? value="$cardexpiry"}{$readonly? readonly="readonly"}{$required? data-fio-validation="requiredValidator"} /></label>
			<label class="card-ccv">CCV: <input type="text" name="{$name}[ccv]" id="{$id}_ccv"{$cardccv? value="$cardccv"}{$readonly? readonly="readonly"}{$required? data-fio-validation="requiredValidator"} /></label>
		</div>
		</div>
		{$error?<p class="err">$error</p>}
		{$hint? <p class="hint">$hint</p>}
	</div>';

	public static $VALIDATOR_ERRORS = array(
		'creditcardValidator'	=> "Invalid card details",
	);

	protected $validators = array(
		'creditcardValidator'
	);

	protected $cardFormats = array();		// credit card regex formats

	public function __construct($form, $name, $displayText = null, $defaultValue = null)
	{
		parent::__construct($form, $name, $displayText, $defaultValue);

		$this->addCardType('mastercard', 'MasterCard',	"/^5[1-5][0-9]{14}$/");
		$this->addCardType('visa',		'Visa',			"/^4[0-9]{12}([0-9]{3})?$/");
		$this->addCardType('amex', 		'Amex',			"/^3[47][0-9]{13}$/");
		$this->addCardType('discover',	'Discover',		"/^6011[0-9]{12}$/");
		$this->addCardType('diners',	'Diner\'s Club', "/^3(0[0-5]|[68][0-9])[0-9]{11}$/");
		$this->addCardType('jcb',		'JCB', 			"/^(3[0-9]{4}|2131|1800)[0-9]{11}$/");
	}

	public function addCardType($name, $humanName, $regex)
	{
		$this->setOption($name, $humanName);
		$this->cardFormats[$name] = $regex;
	}

	// append 'behaviour' parameter to the input for JS validation
	protected function getBuilderVars()
	{
		$inputVars = FormIOField_Text::getBuilderVars();
		if (is_array($this->value) && !empty($this->value[0]) && !empty($this->value[1]) && !empty($this->value[2])) {
			$inputVars['cardnumber']	= $this->value['number'];
			$inputVars['cardname']		= $this->value['name'];
			$inputVars['cardexpiry']	= $this->value['expiry'];
			$inputVars['cardccv']		= $this->value['ccv'];
		}

		// bring option string across from dropdown parent class
		$ddVars = parent::getBuilderVars();
		$inputVars['options'] = $ddVars['options'];

		return $inputVars;
	}

	public function setValue($val)
	{
		if (is_array($val) && isset($val['number'])) {
			$val['number'] = preg_replace('/[^0-9]/', '', $val['number']);
		}
		parent::setValue($val);
	}

	// mod10 validation for the card number before sending to any gateway
	final protected function creditcardValidator() {
		$val = $this->getValue();

		// no number means nothing set
		if (!isset($val['number']) || !isset($val['type'])) {
			return true;
		}

		// check other possible errors with this field before the main event
		if (!preg_match('/^\d\d\/\d\d$/', $val['expiry']) || !preg_match('/^\d{3,4}$/', $val['ccv'])) {
			return false;
		}

		// check number format
		$validFormat = preg_match($this->cardFormats[$val['type']], $val['number']);

		if (!$validFormat) {
			return false;
		}

		// run mod10 validator
		$cardNumber = strrev($val['number']);
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
