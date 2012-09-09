<?php
/**
 * A simple text field. This class acts as a base for all input
 * field types (ie those which are non-presentational).
 */

class FormIOField_Text extends FormIOField_Raw
{
	public $buildString = '<div class="row{$alt? alt}{$classes? $classes}">
		<label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label>
		<input type="text" name="{$name}" id="{$id}"{$value? value="$value"}{$readonly? readonly="readonly"}{$maxlen? maxlength="$maxlen"}{$behaviour? data-fio-type="$behaviour"}{$validation? data-fio-validation="$validation"}{$dependencies? data-fio-depends="$dependencies"} />
		{$error?<p class="err">$error</p>}
		{$hint? <p class="hint">$hint</p>}
	</div>';

	public $excludeFromData = false;	// exclude this field and its value from any output of the form. Use for CAPTCHA fields.

	protected $value = null;				// field value

	// A validator may be one of the following:
	//		- a string
	//		  Simple validation. Pass the field value to this object method.
	//		- an associative array with 'func' and 'params', optionally 'external'
	//		  Validation function takes extra parameters besides value.
	//		  When 'external' is set, we are using an external function rather than member callback (legacy compatibility)
	//		- a numeric array comprising multiple string or associative ones -> multiple validators, performed in turn.
	// Validation functions should simply return true or false to use their default error stored in $VALIDATOR_ERRORS.
	protected $validators = array();

	// dependencies map values of this field to arrays of other field names to SHOW when selected
	protected $dependencies = array();

	// Validator error strings, keyed to callback names.
	// Argument substitutions are possible, where the argument number inserted represents
	// the nth parameter sent to the validator function. Argument 1 is the name of this field.
	// @see buildErrorString()
	public static $VALIDATOR_ERRORS = array(
		'regexValidator'		=> "Incorrect format",
		'requiredValidator'		=> "This field is required",
		'arrayRequiredValidator' => "All elements are required",
		'equalValidator'	=> "You must select \$2",
		'notEqualValidator'	=> "Must not be equal to \$2",
		'minLengthValidator'=> "Must be at least \$2 characters",
		'maxLengthValidator'=> "Must not be longer than \$2 characters",
		'inArrayValidator'	=> "Must be one of \$2",
	);

	public function __construct($form, $name, $displayText = null, $defaultValue = null)
	{
		$this->value = $defaultValue;
		parent::__construct($form, $name, $displayText);
	}

	// DATA

	public function setValue($newVal)
	{
		$this->value = $newVal;
	}

	// This should be overridden in subclasses to perform any conversion to standard formats required
	public function getValue()
	{
		return $this->getRawValue();
	}

	// this function should not be subclassed, except in special circumstances (repeater)
	public function getRawValue()
	{
		return $this->value === '' || $this->value === null || (is_array($this->value) && sizeof($this->value) == 0) ? null : $this->value;
	}

	/**
	 * returns the value in a format readable by the form's end-user
	 * @return	string
	 */
	public function getHumanReadableValue()
	{
		return $this->value === null ? '' : $this->getValue();
	}

	// DISPLAY

	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();
		$vars['value'] = $this->getRawValue();

		// add required flag if set
		if ($this->hasValidator('requiredValidator') || $this->hasValidator('arrayRequiredValidator')) {
			$vars['required'] = true;
		}

		// validation parameter output for JavaScript
		$params = $this->getValidatorParamString();
		if ($params) {
			$vars['validation'] = $params;
		}

		// dependencies for javascript
		if (sizeof($this->dependencies)) {
			$vars['dependencies'] = $this->getDependencyString();
		}

		return $vars;
	}

	// VALIDATION

	// Validation method - should return TRUE/FALSE depending on field's validity
	public function validate()
	{
		// if field is being hidden, it's not required so it is nullified and ignored
		if ($this->hiddenByDependency()) {
			return true;
		}

		// run requiredValidator first, since any other validation need only run if the data is present
		$dataSubmitted = $this->requiredValidator();

		// first reorder them so that the external validators are run last - that way,
		// any data passed to the external validator/s will already have been normalised
		uasort($this->validators, array($this, 'sortValidators'));

		$allValid = true;
		foreach ($this->validators as $validator) {
			$valid = true;
			$externalValidator = false;

			if (is_string($validator)) {			// validator with no extra parameters
				$func = $validator;
				$params = array();
			} else {								// validator with parameters
				$func = $validator['func'];
				$params = $validator['params'];
				if (!empty($validator['external'])) {
					$externalValidator = true;
				}
			}

			if (!$externalValidator && $func == 'requiredValidator') {
				$valid = $dataSubmitted;
			} else {
				if ($externalValidator) {
					array_unshift($params, $this->getName());
					array_unshift($params, $this->form);
					array_unshift($params, $this->value);
				}
				// only perform validation if data has been sent, or we are using an external validator (since we have no idea how this might run)
				// or if we are validating a CAPTCHA field (since CAPTCHAs are *always* required
				if ($dataSubmitted || $externalValidator || $this instanceof FormIOField_Captcha) {
					$valid = call_user_func_array($externalValidator ? $func : array($this, $func), $params);
				}
				if ($externalValidator) {
					array_shift($params);	// remove our extra parameters again (but not field name) so that we can create error strings
					array_shift($params);
				}
			}

			if (!$valid) {
				$this->form->addError($this->getName(), $this->buildErrorString($this, $func, $params));
				$allValid = false;
			}
		}

		return $allValid;
	}

	public function getValidators()
	{
		return $this->validators;
	}

	// callback for ordering multiple field validators
	private function sortValidators($a, $b)
	{
		if (is_string($a) && is_string($b)) {
			return 0;
		} else if (is_string($a) && is_array($b)) {
			return -1;
		} else if (is_array($a) && is_string($b)) {
			return 1;
		}
		$aext = !empty($a['external']);
		$bext = !empty($b['external']);
		return $aext && $bext ? 0 : ($aext ? 1 : -1);
	}

	/**
	 * Adds a validator.
	 * When run, validators are passed the following parameters:
	 *	Internal (class method) validators -
	 *		[extra params...]
	 *	External validators -
	 *		sent value, form object, data key, [extra params...]
	 *
	 * @param	string	$validatorName	name of validation function to run
	 * @param	array	$params			extra parameters to pass to the validation callback (value is always parameter 0)
	 * @param	bool	$customFunc		if true, look in the global namespace for this function. otherwise it is a method of this object.
	 * @param	string	$errorMsg		A custom error message to return if this validator is unsuccessful.
	 *									If you require multiple error messages to be set, use addError() from within the validator function itself.
	 */
	public function addValidator($validatorName, $params = array(), $customFunc = false, $errorMsg = null)
	{
		$this->removeValidator($validatorName);		// remove it if it exists, so we can use the most recently applied parameters

		if ($errorMsg) {
			$vars = get_class_vars(get_class($this));
			if (!isset($vars['VALIDATOR_ERRORS'])) {
				self::$VALIDATOR_ERRORS = array();
			}
			self::$VALIDATOR_ERRORS[$validatorName] = $errorMsg;
		}
		if (sizeof($params) || $customFunc) {
			$validatorName = array(
				'func'		=> $validatorName,
				'params'	=> $params,
				'external'	=> $customFunc
			);
		}
		$this->validators[] = $validatorName;
	}

	// Removes a validator from a field if it is found to exist. Returns true if one was erased.
	public function removeValidator($validatorName)
	{
		foreach ($this->validators as $i => $validator) {
			if ($validator == $validatorName || $validator['func'] == $validatorName) {
				unset($this->validators[$i]);
				return true;
			}
		}
		return false;
	}

	public function hasValidator($validatorName)
	{
		foreach ($this->validators as $validator) {
			if ($validator == $validatorName || $validator['func'] == $validatorName) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Adds whatever validation routine this field should use for a 'required' constraint
	 */
	public function setRequired()
	{
		$this->addValidator('requiredValidator', array(), false);
	}

	/**
	 * Build an error string for this field using the validator name in question.
	 *
	 * Validator error strings are determined by climbing up the class chain, looking for
	 * the name of the callback being set in $VALIDATOR_ERRORS. If this array has the callback
	 * name set, it will use that value for the error string. This means you can override error
	 * strings for validators you are inheriting from in base classes and have the new values
	 * used within your custom field class (as well as any classes inheriting from it).
	 *
	 * This function is not called for external validator functions
	 *
	 * :TODO: will break if more than 10 substitutions are required ($10 will be replaced as $1, etc)
	 *
	 * @param	FormIOField	$caller				caller field - this must be passed since $this in here will always refer to the Text field
	 * @param	string		$callbackName		name of validator member function to retrieve error string for
	 * @param	array		$params				arguments sent to validator callback
	 */
	private function buildErrorString($caller, $callbackName, $params)
	{
		$str = false;
		$class = get_class($caller);
		$prevClass = null;
		do {
			if ($prevClass == $class) {
				break;		// reached top of the class chain: parent class is same as the current one
			}
			$prevClass = $class;

			$vars = get_class_vars($class);
			if (isset($vars['VALIDATOR_ERRORS']) && isset($vars['VALIDATOR_ERRORS'][$callbackName])) {
				$str = $vars['VALIDATOR_ERRORS'][$callbackName];
				break;
			}
		} while ($class = get_parent_class($class));

		if (!$str) {
			trigger_error("Field validator error message was not found for " . get_class($caller) . "::$callbackName", E_USER_ERROR);
		}

		$i = 1;
		// turn the field name parameter into our readable name and add it to the substitution parameters
		array_unshift($params, $this->getHumanReadableName());
		foreach ($params as $param) {
			$str = str_replace('$' . $i++, $param, $str);
		}
		return $str;
	}

	// DEPENDENCIES

	/**
	 * Adds a dependency between one field and another. This sets up the javascript
	 * to toggle visibility of a field when the value of another changes. It also stops
	 * any validation for the hidden fields of a dependency from executing.
	 *
	 * This behaviour is created here rather than in FormIOField_Multiple for flexibility,
	 * to allow textual inputs and other nonstandard field types to toggle dependencies.
	 *
	 * @param	mixed	$expectedValue	when our value is $expectedValue, $dependentField will be visible. Otherwise, it won't.
	 * @param	mixed	$dependentField	field name or array of field names to toggle when our value changes
	 */
	public function addDependency($expectedValue, $dependentField)
	{
		if (!is_array($dependentField)) {
			$dependentField = array($dependentField);
		}
		$this->dependencies[$expectedValue] = $dependentField;
	}

	public function getDependencies()
	{
		return $this->dependencies;
	}

	/**
	 * Determine if the field is being hidden by a dependency rule and the value of another field.
	 * This will cause all validation for this field to be skipped.
	 * Also erases field values when being hidden.
	 */
	public function hiddenByDependency()
	{
		$shown = false;
		$hidden = false;
		$fields = $this->form->getFields();

		foreach ($fields as $field) {
			if (!$field->isPresentational()) {
				foreach ($field->getDependencies() as $postValue => $targetFields) {
					if (in_array($this->getName(), $targetFields)) {				// field is dependant on another field's submission
						if ($field->getValue() != $postValue) {				// and value for master field means this field is hidden
							$hidden = true;
						} else {
							$shown = true;
						}
					}
				}
			}
		}
		if ($hidden && !$shown) {
			$this->value = null;
			return true;
		}
		return false;
	}

	// JAVASCRIPT INTEGRATION LAYER

	public function getValidatorParamString()
	{
		if (!sizeof($this->validators)) {
			return null;
		}
		$params = array();
		foreach ($this->validators as $validator) {	// do not manipulate class validator array
			if (is_array($validator)) {
				foreach ($validator['params'] as &$param) {	// but do manipulate temporary one for encoding
					$param = urlencode($param);
				}
				$params[] = urlencode($validator['func']) . '=' . implode(';', $validator['params']);
			} else {
				$params[] = urlencode($validator);
			}
		}
		return sizeof($params) ? implode('&', $params) : '';
	}

	public function getDependencyString()
	{
		$depends = array();
		foreach ($this->dependencies as $fieldVal => $visibleFields) {
			foreach ($visibleFields as &$field) {
				$field = urlencode($field);
			}
			$depends[] = urlencode($fieldVal) . "=" . implode(';', $visibleFields);
		}
		return implode('&', $depends);
	}

	//========================================================================================
	// Some general-use validation routines
	// :NOTE: that validators are never run unless requiredvalidator() succeeds, so there is
	// no need to check for !isset($this->value) in validators.

	final private function requiredValidator() {
		return isset($this->value) && $this->value !== '';
	}

	final private function regexValidator($regex) {
		return preg_match($regex, $this->value) > 0;
	}

	final private function isRegexValidator() {
		@preg_match($this->value, 'nothing');
		return preg_last_error() == PREG_NO_ERROR;
	}

	// @param	array	$requiredKeys	a list of array keys which are required. When omitted, all keys are checked.
	final private function arrayRequiredValidator($requiredKeys = null) {
		if (is_array($this->value) && sizeof($this->value)) {
			foreach ($this->value as $k => $v) {
				if ((is_array($requiredKeys) && in_array($k, $requiredKeys) && empty($v)) || (!is_array($requiredKeys) && empty($v))) {
					return false;
				}
			}
			return true;
		}
		return false;
	}

	final private function equalValidator($expected) {
		return $this->value == $expected;
	}

	final private function notEqualValidator($unexpected) {
		return $this->value != $unexpected;
	}

	final private function minLengthValidator($length) {
		return strlen($this->value) >= $length;
	}

	final private function maxLengthValidator($length) {
		return strlen($this->value) <= $length;
	}

	final private function inArrayValidator($allowable) {
		return in_array($this->value, $allowable);
	}
}
?>
