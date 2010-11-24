<?php
 /*===============================================================================
	form I/O handler
	----------------------------------------------------------------------------
	A class to handle input & output as it applies to the webserver's HTTP gateway.
	This class takes lists of variables, with given types, and performs
	form rendering, JSON output, JSON submission and HTTP form submission on them.
	It also performs data validation where appropriate.
	----------------------------------------------------------------------------
	@author		Sam Pospischil <pospi@spadgos.com>
	@date		2010-11-01
  ===============================================================================*/

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
	const T_DROPOPTION = 34;		// single <select> option element. Not useful by itself - used by T_DROPDOWN
	const T_CHECKBOX = 23;			// single checkbox (also used by T_CHECKGROUP)
	const T_RADIO	= 33;			// single radio button. Not useful by itself - used by T_RADIOGROUP
	const T_RADIOGROUP = 24;		// list of radio buttons
	const T_CHECKGROUP = 25;		// list of checkboxes
	const T_SURVEY	= 26;			// :TODO:
	const T_PASSWORD = 27;
	const T_BUTTON	= 28;
	const T_SUBMIT	= 29;
	const T_RESET	= 30;
	const T_CAPTCHA	= 35;			// reCAPTCHA plugin
	const T_AUTOCOMPLETE = 36;		// a dropdown which polls a URL for possible values and can be freely entered into
	
	// form builder strings for different element types :TODO: finish implementation
	private static $builder = array(
		FormIO::T_PASSWORD	=> '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$form}_{$name}">{$desc}{$required? <span class="required">*</span>}</label><input type="password" name="{$name}" id="{$form}_{$name}" />{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		FormIO::T_SUBMIT	=> '<input type="submit" name="{$name}" id="{$form}_{$name}" value="{$value}" />',
		FormIO::T_RESET		=> '<input type="reset" name="{$name}" id="{$form}_{$name}" value="{$value}" />',
		FormIO::T_INDENT	=> '<fieldset><legend>{$desc}</legend>',
		FormIO::T_OUTDENT	=> '</fieldset>',
		FormIO::T_DATERANGE	=> '<div class="row daterange{$alt? alt}{$classes? $classes}" id="{$form}_{$name}"><label for="{$form}_{$name}_start">{$desc}{$required? <span class="required">*</span>}</label><input type="text" name="{$name}[0]" id="{$form}_{$name}_start" value="{$value}" data-fio-type="date" /> - <input type="text" name="{$name}[1]" id="{$form}_{$name}_end" value="{$valueEnd}" data-fio-type="date" />{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		FormIO::T_BIGTEXT	=> '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$form}_{$name}">{$desc}{$required? <span class="required">*</span>}</label><textarea name="{$name}" id="{$form}_{$name}"{$maxlen? maxlength="$maxlen"}>{$value}</textarea>{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		
		FormIO::T_DROPDOWN	=> '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$form}_{$name}">{$desc}{$required? <span class="required">*</span>}</label><select id="{$form}_{$name}" name="{$name}"{$dependencies? data-fio-depends="$dependencies"}>{$options}</select>{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		FormIO::T_DROPOPTION=> '<option value="{$value}"{$disabled? disabled="disabled"}{$checked? selected="selected"}>{$desc}</option>',
		
		// T_RADIOGROUP is used for both radiogroup and checkgroup at present
		FormIO::T_RADIOGROUP=> '<fieldset id="{$form}_{$name}" class="row multiple{$alt? alt}"{$dependencies? data-fio-depends="$dependencies"}><legend>{$desc}{$required? <span class="required">*</span>}</legend>{$options}{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></fieldset>',
		FormIO::T_RADIO		=> '<label><input type="radio" name="{$name}" value="{$value}"{$disabled? disabled="disabled"}{$checked? checked="checked"} /> {$desc}</label>',
		FormIO::T_CHECKBOX	=> '<label><input type="checkbox" name="{$name}" value="{$value}"{$disabled? disabled="disabled"}{$checked? checked="checked"} /> {$desc}</label>',
		
		// this is our fallback input string as well. js is added via use of data-fio-* attributes.
		FormIO::T_TEXT		=> '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$form}_{$name}">{$desc}{$required? <span class="required">*</span>}</label><input type="text" name="{$name}" id="{$form}_{$name}" value="{$value}"{$maxlen? maxlength="$maxlen"}{$behaviour? data-fio-type="$behaviour"}{$validation? data-fio-validation="$validation"} />{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
	);
	
	//===============================================================================================\/
	//	Stuff you might want to change
	
	// default error messages for builtin validator methods
	private static $defaultErrors = array(
		'requiredValidator'	=> "This field is required",
		'arrayRequiredValidator' => "All elements are required",
		'equalValidator'	=> "$1 must be equal to $2",
		'notEqualValidator'	=> "$1 must not be equal to $2",
		'minLengthValidator'=> "$1 must be at least $2 characters",
		'maxLengthValidator'=> "$1 must not be longer than $2 characters",
		'inArrayValidator'	=> "$1 must be one of $2",
		'regexValidator'	=> "$1 was not in the correct format",
		'dateValidator'		=> "$1 must be a valid date in dd/mm/yyyy format",
		'dateRangeValidator'=> "$1 contains invalid dates not in dd/mm/yyyy format",
		'emailValidator'	=> "$1 must be a valid email address",
		'phoneValidator'	=> "$1 must be a valid phone number. Phone numbers must contain numbers, spaces and brackets only, and may start with a plus sign",
		'urlValidator'		=> "$1 must be a valid URL",
		'currencyValidator'	=> "$1 should be written as dollars and cents",
		'captchaValidator'	=> "The text entered did not match the verification image",
	);
	
	// misc constants used for validation
	const dateRegex		= '/(\d{1,2})\/(\d{1,2})\/(\d{2}|\d{4})/';					// capture: day, month, year
	const emailRegex	= '/^[-!#$%&\'*+\\.\/0-9=?A-Z^_`{|}~]+@([-0-9A-Z]+\.)+([0-9A-Z]){2,4}$/i';
	const phoneRegex	= '/^(\+)?(\d|\s|\(|\))*$/';
	const currencyRegex	= '/^\s*\$?(\d+)(\.(\d{0,2}))?/';							// capture: dollars, , cents
	
	//===============================================================================================/\
	
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
		// Add any internal validation routines that apply to this field type
		switch ($type) {
			case FormIO::T_EMAIL:
				$this->addValidator($k, 'emailValidator', array(), false); break;
			case FormIO::T_PHONE:
				$this->addValidator($k, 'phoneValidator', array(), false); break;
			case FormIO::T_CURRENCY:
				$this->addValidator($k, 'currencyValidator', array(), false); break;
			case FormIO::T_URL:
				$this->addValidator($k, 'urlValidator', array(), false); break;
			case FormIO::T_DATE:
				$this->addValidator($k, 'dateValidator', array(), false); break;
			case FormIO::T_DATERANGE:
				$this->addValidator($k, 'dateRangeValidator', array(), false); break;
			case FormIO::T_CAPTCHA:
				$this->addValidator($k, 'captchaValidator', array(), false);
				$this->addValidator($k, 'requiredValidator', array(), false); break;
		}
	}
	
	/**
	 * Adds a validator to a field
	 * @param	string	$k				data key in form data to apply validator to
	 * @param	string	$validatorName	name of validation function to run
	 * @param	array	$params			extra parameters to pass to the validation callback (value is always parameter 0)
	 * @param	bool	$customFunc		if true, look in the global namespace for this function. otherwise it is a method of the FormIO class.
	 */
	public function addValidator($k, $validatorName, $params = array(), $customFunc = true)
	{
		$this->removeValidator($k, $validatorName);		// remove it if it exists, so we can use the most recently applied parameters
		
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
		
		return true;
	}
	
	// Removes a validator from a field if it is found to exist. Returns true if one was erased.
	public function removeValidator($k, $validatorName)
	{
		if (!is_array($this->dataValidators[$k])) {
			return false;
		}
		foreach ($this->dataValidators[$k] as $i => $validator) {
			if ($validator == $validatorName || $validator['func'] == $validatorName) {
				unset($this->dataValidators[$k][$i]);
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Set some fields to be required. Simply pass as many field names to the function as you desire.
	 */
	public function setRequired()
	{
		$a = func_get_args();
		foreach ($a as $fieldName) {
			switch ($this->dataTypes[$fieldName]) {
				case FormIO::T_DATERANGE:
					$this->addValidator($fieldName, 'arrayRequiredValidator', array(array(0, 1)), false);
					break;
				default:
					$this->addValidator($fieldName, 'requiredValidator', array(), false);
					break;
			}
		}
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
	 * 								or array of desc(string) and optional disabled(bool), checked(bool) properties
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
		if (!is_array($dependentField)) {
			$dependentField = array($dependentField);
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
	
	public function getQueryString()
	{
		return http_build_query($this->data);
	}
	
	public function getForm()
	{
		$form = "<form id=\"$this->name\" class=\"clean\" method=\"$this->method\" action=\"$this->action\"" . ($this->multipart ? ' enctype="multipart/form-data"' : '') . '>' . "\n";
		
		if (sizeof($this->errors) || isset($this->preamble)) {
			$form .= '<div class="preamble">' . "\n" . (isset($this->preamble) ? $this->preamble : '') . "\n";
			$form .= "<p class=\"err\">Please review your submission: " . sizeof($this->errors) . " fields have errors.</p>\n";
			$form .= '</div>' . "\n";
		}
		
		$spin = 1;
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
				'required'	=> ($this->hasValidator($k, 'requiredValidator') || $this->hasValidator($k, 'arrayRequiredValidator')),
			);
			// Add validation parameter output for JS
			$params = $this->getValidatorParams($k);
			if ($params) {
				$inputVars['validation'] = $params;
			}
			// Add labels, extra css class names etc
			foreach ($this->dataAttributes[$k] as $attr => $attrVal) {
				$inputVars[$attr] = $attrVal;
			}
			// Add error output, if any
			if (isset($this->errors[$k])) {
				$errArray = is_array($this->errors[$k]) ? $this->errors[$k] : array($this->errors[$k]);
				$inputVars['error'] = implode("<br />", $errArray);
			}
			// set data behaviour for form JavaScript, and any other type-specific attributes
			switch ($fieldType) {
				case FormIO::T_HIDDEN:		// these field types don't increment the striper
				case FormIO::T_OUTDENT:
					--$spin;
					break;
				case FormIO::T_INDENT:		// these field types reset the striper
				case FormIO::T_SECTIONBREAK:
					$spin = 1;
					break;
				case FormIO::T_DATERANGE:
					$inputVars['value']		= $value[0];
					$inputVars['valueEnd']	= $value[1];
					break;
				case FormIO::T_RADIOGROUP:	// these field types contain subelements
				case FormIO::T_CHECKGROUP:
				case FormIO::T_DROPDOWN:
					// determine subfield output format
					switch ($fieldType) {
						case FormIO::T_RADIOGROUP:
							$subFieldType = FormIO::T_RADIO;
							break;
						case FormIO::T_CHECKGROUP:
							$builderString = FormIO::$builder[FormIO::T_RADIOGROUP]; // Use radiogroup string for checkgroup as well
							$subFieldType = FormIO::T_CHECKBOX;
							break;
						case FormIO::T_DROPDOWN:
							$subFieldType = FormIO::T_DROPOPTION;
							break;
					}
					
					// dependencies for javascript
					if (isset($this->dataDepends[$k])) {
						$inputVars['dependencies'] = $this->getDependencyString($k);
					}
					
					// Unset value and get ready to build options
					unset($inputVars['value']);
					$inputVars['options'] = '';
					
					// Build field sub-elements
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
				// these field types are normal text inputs that have extra clientside behaviours
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
			}
			
			// add row striping
			$inputVars['alt'] = ++$spin % 2 == 0;
			
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
	
	// for use in data-fio-depends field attributes
	private function getDependencyString($k)
	{
		$depends = array();
		foreach ($this->dataDepends[$k] as $fieldVal => $visibleFields) {
			$depends[] = "$fieldVal=" . implode(';', $visibleFields);
		}
		return implode('&', $depends);
	}
	
	// for use in data-fio-validation field attributes
	private function getValidatorParams($k)
	{
		if (!is_array($this->dataValidators[$k])) {
			return null;
		}
		$params = array();
		foreach ($this->dataValidators[$k] as $validator) {
			if (!is_array($validator)) {
				continue;		// parameterless validator
			} else {
				if (isset($validator['params'])) {
					$params[] = $validator['func'] . '=' . implode(';', $validator['params']);
				}
			}
		}
		return sizeof($params) ? implode('&', $params) : '';
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
			
			if (!array_key_exists($dataKey, $this->data) || $this->fieldHiddenByDependency($dataKey)) {
				// if field is being hidden or isn't present, it's not required so it is nullified and ignored
				continue;
			}
			
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
	
	/**
	 * Determine if the field is being hidden by a dependency rule and the value of another field
	 *
	 * Also erases field values when being hidden
	 */
	private function fieldHiddenByDependency($key)
	{
		foreach ($this->dataDepends as $masterField => $dependencies) {
			foreach ($dependencies as $postValue => $targetFields) {
				if (in_array($key, $targetFields)								// field is dependant on another field's submission
				  && $this->data[$masterField] != $postValue) {					// and value for master field means this field is hidden

					// we should erase the field's value so it doesnt show if the user happens to have filled it out and changed their mind
					$this->data[$key] = null;
					
					return true;
				}
			}
		}
		return false;
	}
	
	//==========================================================================
	//	Callbacks for validation
	
	private function requiredValidator($key) {
		return isset($this->data[$key]) && $this->data[$key] !== '';
	}
	
	// @param	array	$requiredKeys	a list of array keys which are required. When omitted, all keys are checked.
	private function arrayRequiredValidator($key, $requiredKeys = null) {
		if (isset($this->data[$key]) && is_array($this->data[$key])) {
			foreach ($this->data[$key] as $k => $v) {
				if ((is_array($requiredKeys) && in_array($k, $requiredKeys) && empty($v)) || (!is_array($requiredKeys) && empty($v))) {
					return false;
				}
			}
			return true;
		}
		return false;
	}
	
	private function equalValidator($key, $expected) {
		return isset($this->data[$key]) && $this->data[$key] == $expected;
	}
	
	private function notEqualValidator($key, $unexpected) {
		// If it's not been sent, this validation is fine
		if (!$this->requiredValidator($key)) return true;
		
		return !isset($this->data[$key]) || $this->data[$key] != $unexpected;
	}
	
	private function minLengthValidator($key, $length) {
		// If it's not been sent, this validation is fine
		if (!$this->requiredValidator($key)) return true;
		
		return strlen($this->data[$key]) >= $length;
	}
	
	private function maxLengthValidator($key, $length) {
		// If it's not been sent, this validation is fine
		if (!$this->requiredValidator($key)) return true;
		
		return strlen($this->data[$key]) <= $length;
	}
	
	private function inArrayValidator($key, $allowable) {
		// If it's not been sent, this validation is fine
		if (!$this->requiredValidator($key)) return true;
		
		return in_array($this->data[$key], $allowable);
	}
	
	private function regexValidator($key, $regex) {
		return preg_match($regex, $this->data[$key]);
	}
	
	private function dateValidator($key) {					// also sets stored data to DD/MM/YYYY format
		// If it's not been sent, this validation is fine
		if (!$this->requiredValidator($key)) return true;
		
		preg_match(FormIO::dateRegex, $this->data[$key], $matches);
		$success = sizeof($matches) == 4;
		if ($success) {
			$this->data[$key] = str_pad($matches[1], 2, '0', STR_PAD_LEFT) . '/' . str_pad($matches[2], 2, '0', STR_PAD_LEFT) . '/' . str_pad($matches[3], 4, '20', STR_PAD_LEFT);
		}
		return $success != false;
	}
	
	private function emailValidator($key) {
		// If it's not been sent, this validation is fine
		if (!$this->requiredValidator($key)) return true;
		
		return $this->regexValidator($key, FormIO::emailRegex);
	}
	
	private function phoneValidator($key) {
		// If it's not been sent, this validation is fine
		if (!$this->requiredValidator($key)) return true;
		
		return $this->regexValidator($key, FormIO::phoneRegex);
	}
	
	private function currencyValidator($key) {				// also sets stored data to float representation
		// If it's not been sent, this validation is fine
		if (!$this->requiredValidator($key)) return true;
		
		preg_match(FormIO::currencyRegex, $this->data[$key], $matches);
		$success = sizeof($matches) > 0;
		if ($success) {
			$this->data[$key] = intval($matches[1]) + (isset($matches[3]) ? intval($matches[3]) / 100 : 0);
		}
		return $success != false;
	}
	
	private function urlValidator($key) {					// allows http, https & ftp *only*. Also ensures stored data has scheme present
		// If it's not been sent, this validation is fine
		if (!$this->requiredValidator($key)) return true;
		
		if (false == $bits = parse_url($this->data[$key])) {
			return false;
		}
		if (empty($bits['host']) || !ctype_alpha(substr($bits['host'], 0, 1))) {
			return false;
		}
		
		if (empty($bits['scheme'])) {
			$this->data[$key] = 'http://' . $this->data[$key];
		}
		
		return (empty($bits['scheme']) || $bits['scheme'] == 'http' || $bits['scheme'] == 'https' || $bits['scheme'] == 'ftp');
	}
	
	private function captchaValidator($key) {
		
	}
	
	private function dateRangeValidator($key) {
		if (isset($this->data[$key]) && is_array($this->data[$key])) {
			if ((!empty($this->data[$key][0]) || !empty($this->data[$key][1]))
			  && (false === preg_match(FormIO::dateRegex, $this->data[$key][0], $matches1)
			  || false === preg_match(FormIO::dateRegex, $this->data[$key][1], $matches2))) {
				return false;
			}
			// also swap the values if they are in the wrong order
			if (($matches1[3] > $matches2[3])
			 || ($matches1[3] >= $matches2[3] && $matches1[2] > $matches2[2])
			 || ($matches1[3] >= $matches2[3] && $matches1[2] >= $matches2[2] && $matches1[1] > $matches2[1])) {
				$temp = $this->data[$key][0];
				$this->data[$key][0] = $this->data[$key][1];
				$this->data[$key][1] = $temp;
			}
			return true;
		}
		return true;		// not set, so validate as OK and let requiredValidator pick it up
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
