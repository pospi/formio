<?php
/**
 *
 */

class FormIOField_Numeric extends FormIOField_Text
{
	// override the regex validator's error message for this field type
	public static $VALIDATOR_ERRORS = array(
		'regexValidator' => "Must be a number",
		'greaterThanValidator' => "Must be greater than \$2",
		'lessThanValidator' => "Must be less than \$2",
		'minValueValidator' => "Must be at least \$2",
		'maxValueValidator' => "Must not be over \$2",
		'integerValidator' => "Must be a whole number",
	);

	// validate with regex validator internally
	protected $validators = array(
		array(
			'func' => 'regexValidator',
			'params' => array('/^(-?\d*\.?\d*)?$/')
		)
	);

	// append 'behaviour' parameter to the input for JS validation
	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();
		$vars['behaviour'] = 'numeric';
		return $vars;
	}

	final protected function greaterThanValidator($expected) {
		return $this->value > $expected;
	}

	final protected function lessThanValidator($expected) {
		return $this->value < $expected;
	}

	final protected function minValueValidator($expected) {
		return $this->value >= $expected;
	}

	final protected function maxValueValidator($expected) {
		return $this->value <= $expected;
	}

	final protected function integerValidator() {
		return intval($this->value) == $this->value;
	}
}
?>
