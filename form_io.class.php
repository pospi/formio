<?php
/**
 * Form I/O
 *
 * A class to handle input & output as it applies to the webserver's HTTP gateway.
 * This class takes lists of variables, with given types, and performs
 * form rendering, JSON output, JSON submission and HTTP form submission on them.
 * It also performs data validation where appropriate.
 *
 * Copyright (c) 2010 Web Services, Dept. of Education and Training
 * @author	Sam Pospischil <sam.pospischil@deta.qld.gov.au>
 */

class FormIO implements ArrayAccess
{
	// non-input types
	const T_RAW		= 0;		// raw HTML output
	const T_HEADER	= 1;		// h1
	const T_SUBHEADER = 2;		// h3
	const T_PARAGRAPH = 3;
	const T_SECTIONBREAK = 4;	// new <tbody>, paginated via JScript
	const T_IMAGE	= 5;
	const T_INDENT	= 31;		// starts a <fieldset>
	const T_OUTDENT	= 32;		// ends a <fieldset>
	
	// form input types
	const T_TEXT	= 6;
		// All these indented types actually output normal text fields and are driven by JS and serverside validation
		const T_EMAIL	= 7;
		const T_PHONE	= 8;
		const T_CREDITCARD = 9;		// performs MOD10 validation
		const T_ALPHA	= 10;
		const T_NUMERIC	= 11;
		const T_CURRENCY = 12;		// rounded to 2 decimal points, allows $
		const T_DATE	= 13;
		const T_TIME	= 14;
		const T_AUSPOSTCODE = 15;	// australian postcode, 4 digits
		const T_URL		= 16;
	const T_DATETIME = 17;			// compound fields for date & time components
	const T_DATERANGE = 18;			// two date fields
	const T_BIGTEXT	= 19;			// textarea
	const T_HIDDEN	= 20;			// input[type=hidden]
	const T_READONLY = 21;			// a bit like a hidden input, only we show the variable
	const T_DROPDOWN = 22;			// select
	const T_CHECKBOX = 23;			// single checkbox (also used by T_CHECKGROUP)
	const T_RADIO	= 33;			// single radio button. Not useful by itself - used by T_RADIOGROUP
	const T_RADIOGROUP = 24;		// list of radio buttons
	const T_CHECKGROUP = 25;		// list of checkboxes
	const T_SURVEY	= 26;			// :TODO:
	const T_PASSWORD = 27;
	const T_BUTTON	= 28;
	const T_SUBMIT	= 29;
	const T_RESET	= 30;
	
	// form builder strings for different element types :TODO: finish implementation
	private static $builder = array(
		FormIO::T_PASSWORD	=> '<label for="{$name}">{$desc}{$required? <span class="required">(required)</span>}</label><input type="password" name="{$name}" id="{$form}_{$name}"{$classes? class="$classes"} />',
		FormIO::T_SUBMIT	=> '<input type="submit" name="{$name}" id="{$form}_{$name}" value="{$value}"{$classes? class="$classes"} />',
		FormIO::T_INDENT	=> '<fieldset><legend>{$desc}</legend>',
		FormIO::T_OUTDENT	=> '</fieldset>',
		FormIO::T_DATERANGE	=> '<label for="{$name}">{$desc}{$required? <span class="required">(required)</span>}</label><input type="text" name="{$name}[0]" id="{$form}_{$name}_start" value="{$value}"{$classes? class="$classes"} data-fio-type="date" /> - <input type="text" name="{$name}[1]" id="{$form}_{$name}_end" value="{$valueEnd}"{$classes? class="$classes"} data-fio-type="date" />',
		
		// T_RADIOGROUP is used for both radiogroup and checkgroup at present
		FormIO::T_RADIOGROUP=> '<fieldset id="{$form}_{$name}" class="checkbox multiple"><legend>{$desc}{$required? <span class="required">(required)</span>}</legend>{$options}</fieldset>',
		FormIO::T_RADIO		=> '<label><input type="radio" name="{$name}" value="{$value}"{$disabled? disabled="disabled"}{$checked? checked="checked"} /> {$desc}</label>',
		FormIO::T_CHECKBOX	=> '<label><input type="checkbox" name="{$name}" value="{$value}"{$disabled? disabled="disabled"}{$checked? checked="checked"} /> {$desc}</label>',
		
		// this is our fallback input string as well. js is added via use of data-fio-* attributes.
		FormIO::T_TEXT		=> '<label for="{$name}">{$desc}{$required? <span class="required">(required)</span>}</label><input type="text" name="{$name}" id="{$form}_{$name}" value="{$value}"{$maxlen? maxlength="$maxlen"}{$classes? class="$classes"}{$behaviour? data-fio-type="$behaviour"}{$validation? data-fio-validation="$validation"} />',
	);
	
	// default error messages for builtin validator methods
	private static $defaultErrors = array(
		'requiredValidator'	=> "Required field '$1' not found",
		'equalValidator'	=> "$1 must be equal to $2",
		'notEqualValidator'	=> "$1 must not be equal to $2",
		'minLengthValidator'=> "$1 must be at least $2 characters",
		'maxLengthValidator'=> "$1 must not be longer than $2 characters",
		'inArrayValidator'	=> "$1 must be one of $2",
		'regexValidator'	=> "$1 was not in the correct format",
	);
	
	private $lastBuilderReplacement;		// form builder hack for preg_replace_callback not being able to accept extra parameters
	
	// Form stuff
	private $name;		// unique html ID for this form
	private $action;
	private $method;	// GET or POST
	private $multipart;	// if true, render with enctype="multipart/form-data"
	private $preamble;	// HTML to output at top of form :TODO:
	private $suffix;	// HTML to output at bottom of form :TODO:
	
	// Field stuff
	private $data;
	private $errors;		// data validation errors, filled by call to validate()
	// These items, keyed to $data, determine how that data will be validated.
	// A validator may be one of the following:
	//		- a string															(simple validation, pass the value to this object method)
	//		- an associative array with 'func' and 'params'						(validation function takes extra parameters besides value)
	//		- a numeric array comprising multiple string or associative ones	(multiple validators, performed in turn)
	// Validation functions should simply return true or false
	private $dataValidators = array();
	private $dataTypes = array();			// indicates data type for each field
	private $dataDepends = array();			// :TODO:
	private $dataOptions = array();			// input options for checkbox, radio, dropdown etc types
	private $dataAttributes = array();		// any extra attributes to add to HTML output - maxlen, classes, desc
	
	//==========================================================================
	//	Important stuff
	
	/**
	 * Form constructor. We give it a method, action, enctype and name
	 */
	public function __construct($formName, $method = "GET", $action = null, $multipart = false)
	{
		$this->name = $formName;
		$this->action = $action === null ? $_SERVER['PHP_SELF'] : $action;
		$this->method = $method == "GET" ? "GET" : "POST";
		$this->multipart = $multipart;
	}
	
	/**
	 * Add a field to this form
	 */
	public function addField($name, $displayText, $type, $value = null)
	{
		$this->data[$name] = $value;
		$this->dataAttributes[$name] = array('desc' => $displayText);
		$this->setDataType($name, $type);
		if ($type == FormIO::T_DROPDOWN || $type == FormIO::T_RADIOGROUP || $type == FormIO::T_CHECKGROUP || $type == FormIO::T_SURVEY) {
			$this->dataOptions[$name] = array();
		}
	}
	
	/**
	 * Imports a data map from some other array. This does not erase existing values
	 * unless the source array overrides those properties.
	 * Best used when importing variables from $_POST, $_GET etc
	 * 
	 * @param	array	$assoc			Associative data array to import
	 * @param	bool	$allowAdditions	if true, we can set values that weren't initially declared on the form
	 */
	public function importData($assoc, $allowAdditions = false)
	{
		if (!$allowAdditions) {
			foreach ($assoc as $k => $unused) {
				if (!array_key_exists($k, $this->data)) {
					unset($assoc[$k]);
				}
			}
		}
		$this->data = array_merge($this->data, $assoc);
	}
	
	//==========================================================================
	//	Accessors
	
	public function getErrors()
	{
		return $this->errors;
	}
	
	public function getDataType($k)
	{
		return $this->dataTypes[$k];
	}
	
	public function getValidators($k)
	{
		return $this->dataValidators[$k];
	}
	
	public function getAttributes($k)
	{
		return $this->dataAttributes[$k];
	}
	
	// Determine if a validator is being run for a particular data key
	public function hasValidator($k, $validatorName)
	{
		if (!is_array($this->dataValidators[$k])) {
			return false;
		}
		foreach ($this->dataValidators[$k] as $validator) {
			if ($validator == $validatorName || $validator['func'] == $validatorName) {
				return true;
			}
		}
		return false;
	}
	
	//==========================================================================
	//	Mutators
	
	public function setDataType($k, $type)
	{
		$this->dataTypes[$k] = $type;
	}
	
	/**
	 * Adds a validator to a field
	 * @param	string	$k				data key in form data to apply validator to
	 * @param	string	$validatorName	name of validation function to run
	 * @param	array	$params			extra parameters to pass to the validation callback (value is always parameter 0)
	 * @param	bool	$customFunc		if true, look in the global namespace for this function. otherwise it is a method of the FormIO class.
	 */
	public function addValidator($k, $validatorName, $params = array(), $customFunc = false)
	{
		if (!isset($this->dataValidators[$k])) {
			$this->dataValidators[$k] = array();
		}
		if (sizeof($params) || $customFunc) {
			$validatorName = array(
				'func'		=> $validatorName,
				'params'	=> $params,
				'external'	=> $customFunc
			);
		}
		$this->dataValidators[$k][] = $validatorName;
	}
	
	/**
	 * Adds an attribute to a field. Use this for presentational things like CSS class names, maxlen attributes etc.
	 * You can add anything you like in here, but they will only be output if present in the form builder strings
	 * for the field type being processed. Also note that adding elements here linearly slows the performance of
	 * rendering the field in question.
	 */
	public function addAttribute($k, $attr, $value)
	{
		$this->dataAttributes[$k][$attr] = $value;
	}
	
	/**
	 * Add an option for a multiple field type field (select, radiogroup etc)
	 *
	 * @param	string	$k			field name to add an option for
	 * @param	string	$optionVal	the value this option will have
	 * @param	mixed	$optionText either the option's description (as text);
	 * 								or array of desc(string) and optional disabled(bool), checked(bool) and selected(bool) properties
	 * @param	mixed	$dependentField @see FormIO::addFieldDependency()
	 */
	public function addFieldOption($k, $optionVal, $optionText, $dependentField = null)
	{
		$this->dataOptions[$k][$optionVal] = $optionText;
		if ($dependentField !== null) {
			$this->addFieldDependency($k, $optionVal, $dependentField);
		}
	}
	
	/**
	 * Adds a dependency between one field and another. This sets up the javascript
	 * to toggle visibility of a field when the value of another changes.
	 *
	 * @param	string	$k				field to add the dependency to
	 * @param	mixed	$expectedValue	when the value of field $k is $expectedValue, $dependentField will be visible. Otherwise, it won't.
	 * @param	mixed	$dependentField	field name or array of field names to toggle when the value of field $k changes
	 */
	public function addFieldDependency($k, $expectedValue, $dependentField)
	{
		if (!isset($this->dataDepends[$k])) {
			$this->dataDepends[$k] = array();
		}
		$this->dataDepends[$k][$expectedValue] = $dependentField;
	}
	
	// Allows you to add an error message to the form from external scripts
	public function addError($msg)
	{
		$this->errors[] = $msg;
	}
	
	// :TODO: simplified mutators for adding various non-field types
	
	//==========================================================================
	//	Rendering
	
	public function getJSON()
	{
		require_once(DET_CLASSES . 'json_parser.class.php');
		return JSONParser::encode($this->data);
	}
	
	public function getForm()
	{
		$form = "<form id=\"$this->name\" class=\"clean\" method=\"$this->method\" action=\"$this->action\"" . ($this->multipart ? ' enctype="multipart/form-data"' : '') . '>' . "\n";
		
		if (sizeof($this->errors) || isset($this->preamble)) {
			$form .= '<div class="preamble">' . "\n" . (isset($this->preamble) ? $this->preamble : '') . "\n";
			foreach ($this->errors as $field => $errMsg) {
				$form .= "<p class=\"err\">$errMsg</p>\n";
			}
			$form .= '</div>' . "\n";
		}
		
		foreach ($this->data as $k => $value) {
			$fieldType = isset($this->dataTypes[$k]) ? $this->dataTypes[$k] : FormIO::T_RAW;
			
			// check for specific field type output string
			if (!isset(FormIO::$builder[$fieldType])) {
				$builderString = FormIO::$builder[FormIO::T_TEXT];
			} else {
				$builderString = FormIO::$builder[$fieldType];
			}
			
			// build input property list. We optimise this array as much as possible, as each item present requires extra processing.
			$inputVars = array(
				'form'		=> $this->name,
				'name'		=> $k,
				'value'		=> $value,
				'required'	=> $this->hasValidator($k, 'requiredValidator'),
			);
			// Add labels, extra css class names etc
			foreach ($this->dataAttributes[$k] as $attr => $attrVal) {
				$inputVars[$attr] = $attrVal;
			}
			// set data behaviour for form JavaScript, and any type-specific attributes
			switch ($fieldType) {
				case FormIO::T_EMAIL:		$inputVars['behaviour'] = 'email'; break;
				case FormIO::T_PHONE:		$inputVars['behaviour'] = 'phone'; break;
				case FormIO::T_CREDITCARD:	$inputVars['behaviour'] = 'credit'; break;
				case FormIO::T_ALPHA:		$inputVars['behaviour'] = 'alpha'; break;
				case FormIO::T_NUMERIC:		$inputVars['behaviour'] = 'numeric'; break;
				case FormIO::T_CURRENCY:	$inputVars['behaviour'] = 'currency'; break;
				case FormIO::T_DATE:		$inputVars['behaviour'] = 'date'; break;
				case FormIO::T_TIME:		$inputVars['behaviour'] = 'time'; break;
				case FormIO::T_AUSPOSTCODE:	$inputVars['behaviour'] = 'postcode'; break;
				case FormIO::T_URL: 		$inputVars['behaviour'] = 'url'; break;
				case FormIO::T_DATERANGE:
					$inputVars['value']		= $value[0];
					$inputVars['valueEnd']	= $value[1];
					break;
				case FormIO::T_RADIOGROUP:
				case FormIO::T_CHECKGROUP:
					// Use radiogroup string for both
					$builderString = FormIO::$builder[FormIO::T_RADIOGROUP];
					
					// Unset value and get ready to build options
					unset($inputVars['value']);
					$inputVars['options'] = '';
					$subFieldType = $fieldType == FormIO::T_RADIOGROUP ? FormIO::T_RADIO : FormIO::T_CHECKBOX;
					
					foreach ($this->dataOptions[$k] as $optVal => $desc) {
						$radioVars = array(
							'name'		=> $k,
							'value'		=> $optVal,
						);
						if (is_array($desc)) {
							$radioVars['desc'] = $desc['desc'];
							if (isset($desc['disabled']))	$radioVars['disabled']	= $desc['disabled'];
							if (isset($desc['checked']))	$radioVars['checked']	= $desc['checked'];
						} else {
							$radioVars['desc'] = $desc;
						}
						$inputVars['options'] .= $this->replaceInputVars(FormIO::$builder[$subFieldType], $radioVars);
					}
					break;
			}
			
			$form .= $this->replaceInputVars($builderString, $inputVars) . "\n";
		}
		
		return $form . "</form>\n";
	}
	
	/**
	 * Builds an input by replacing our custom-style string substitutions.
	 * {$varName} is replaced by $value, whilst {$varName?test="$varName"}
	 * is replaced by test="$value"
	 */
	private function replaceInputVars($str, $varsMap)
	{
		foreach ($varsMap as $property => $value) {
			if (!$value) {
				$str = preg_replace('/\{\$' . $property . '.*\}/U', '', $str);
				continue;
			}
			
			$this->lastBuilderReplacement = $value;
			$str = preg_replace_callback(
						'/\{\$(' . $property . ')(\?(.+))?\}/U',
						array($this, 'formInputBuildCallback'),
						$str
					);
		}
		// remove any properties we didn't process
		$str = preg_replace('/\{\$.+\}/U', '', $str);
		return $str;
	}
	
	// Callback for replaceInputVars(), used to replace variables in submatches with their own values
	private function formInputBuildCallback($matches)
	{
		if (isset($matches[3]) && isset($this->lastBuilderReplacement)) {
			return preg_replace('/\$' . $matches[1] . '/', $this->lastBuilderReplacement, $matches[3]);
		} else {
			return $this->lastBuilderReplacement;
		}
	}
	
	private function getReadableFieldName($k)
	{
		return isset($this->dataAttributes[$k]['desc']) ? $this->dataAttributes[$k]['desc'] : $k;
	}
	
	//==========================================================================
	//	Validation
	
	/**
	 * Validate our data and return a boolean as to whether everything is ok
	 * Specific error messages can be retrieved via getErrors()
	 *
	 * @see FormIO::$dataValidators
	 */
	public function validate()
	{
		$this->errors = array();
		return $this->handleValidations($this->dataValidators);
	}
	
	private function handleValidations($validators, $overrideDataKey = null)
	{
		$allValid = true;
		
		foreach ($validators as $dataKey => $validator) {
			$dataKey = $overrideDataKey === null ? $dataKey : $overrideDataKey;
			
			$valid = true;
			$externalValidator = false;
			
			if (is_string($validator)) {				// single validator, no extra parameters
				$func = $validator;
				$params = array($dataKey);
			} else if (!isset($validator[0])) {			// single validator with parameters
				$func = $validator['func'];
				$params = $validator['params'];
				array_unshift($params, $dataKey);
				if ($validator['external']) {
					$externalValidator = true;
				}
			}
			
			if (!isset($func)) {						// array of validators to be performed in sequence - recurse.
				$valid = $this->handleValidations($validator, $dataKey);
			} else {
				$valid = call_user_func_array($externalValidator ? $func : array($this, $func), $params);
				
				if (!$valid) {
					if (isset($this->errors[$dataKey])) {
						if (!is_array($this->errors[$dataKey])) {
							$this->errors[$dataKey] = array($this->errors[$dataKey]);
						}
						$this->errors[$dataKey][] = FormIO::errorString($func, $params);
					} else {
						$this->errors[$dataKey] = FormIO::errorString($func, $params);
					}
				}
			}
			
			if (!$valid) {
				$allValid = false;
			}
		}
		
		return $allValid;
	}
	
	// :WARNING: will break if more than 10 substitutions are required ($10 will be replaced as $1, etc)
	// not a requried fix yet as I really cant see a validator needing that many parameters passed
	private function errorString($callbackName, $params)
	{
		$str = FormIO::$defaultErrors[$callbackName];
		$i = 1;
		$params[0] = $this->getReadableFieldName($params[0]);
		foreach ($params as $param) {
			$str = str_replace('$' . $i++, $param, $str);
		}
		return $str;
	}
	
	//==========================================================================
	//	Callbacks for validation
	
	private function requiredValidator($key) {
		// check for dependent fields first
		foreach ($this->dataDepends as $masterField => $dependencies) {
			foreach ($dependencies as $postValue => $targetFields) {
				if ($key == $targetFields || in_array($key, $targetFields)		// field is dependant on another field's submission
				  && $this->data[$masterField] != $postValue) {					// and value for master field means this field is hidden
					
					// we should erase the field's value so it doesnt show if the user happens to have filled it out and changed their mind
					$this->data[$key] = null;
					// since this field is hidden by a dependency, it does not need to be checked
					return true;
				}
			}
		}
			
		return isset($this->data[$key]) && $this->data[$key] !== '';
	}
	
	private function equalValidator($key, $expected) {
		return isset($this->data[$key]) && $this->data[$key] == $expected;
	}
	
	private function notEqualValidator($key, $unexpected) {
		return !isset($this->data[$key]) || $this->data[$key] != $unexpected;
	}
	
	private function minLengthValidator($key, $length) {
		return strlen($this->data[$key]) >= $length;
	}
	
	private function maxLengthValidator($key, $length) {
		return strlen($this->data[$key]) <= $length;
	}
	
	private function inArrayValidator($key, $allowable) {
		return in_array($this->data[$key], $allowable);
	}
	
	private function regexValidator($key, $regex) {
		return preg_match($regex, $this->data[$key]);
	}
	
	//==========================================================================
	//	Array implementation to access form variables directly
	
	public function offsetSet($offset, $value)
	{
        if (is_null($offset)) {
            trigger_error("Attempted to set form variable, but no key given", E_USER_ERROR);
        } else {
            $this->data[$offset] = $value;
        }
    }
    public function offsetExists($offset)
	{
        return isset($this->data[$offset]);
    }
    public function offsetUnset($offset)
	{
        unset($this->data[$offset]);
    }
    public function offsetGet($offset)
	{
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }
}

?>
