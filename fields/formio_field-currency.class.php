<?php
/**
 *
 */

require_once(FORMIO_FIELDS . 'formio_field-numeric.class.php');

class FormIOField_Currency extends FormIOField_Numeric
{
	public $buildString = '<div class="row{$alt? alt}{$classes? $classes}">
		<label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label>
		<span class="currency"><span>$</span>
			<input type="text" name="{$name}" id="{$id}"{$value? value="$value"}{$readonly? readonly="readonly"}{$maxlen? maxlength="$maxlen"}{$behaviour? data-fio-type="$behaviour"}{$validation? data-fio-validation="$validation"}{$dependencies? data-fio-depends="$dependencies"} />
		</span>
		{$error?<p class="err">$error</p>}
		{$hint? <p class="hint">$hint</p>}
	</div>';

	public static $VALIDATOR_ERRORS = array(
		'currencyValidator'	=> "Enter amount in dollars and cents",
	);

	protected $validators = array(
		'currencyValidator'
	);

	public function getHumanReadableValue()
	{
		$val = $this->getValue();
		return isset($val) ? '$' . $val : '';
	}

	// append 'behaviour' parameter to the input for JS validation
	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();
		$vars['behaviour'] = 'currency';
		return $vars;
	}

	// performs currency normalisation
	final protected function currencyValidator() {
		preg_match('/^\s*\$?(-?\d*)(\.(\d{0,2}))?\s*$/', $this->value, $matches);		// capture: dollars, , cents
		$success = sizeof($matches) > 0;
		if ($success) {
			$this->value = $this->normaliseCurrency($matches[1], (isset($matches[3]) ? $matches[3] : null));
		}
		return $success != false;
	}

	protected function normaliseCurrency($d, $c = 0) {			// $d.cc
		return intval($d) . '.' . str_pad($c, 2, '0', STR_PAD_RIGHT);
	}
}
?>
