<?php
 /*===============================================================================
	form I/O handler
	----------------------------------------------------------------------------
	A class to handle input & output as it applies to the webserver's HTTP gateway.
	This class takes lists of variables, with given types, and performs
	form rendering, JSON output, JSON submission and HTTP form submission on them.
	It also performs data validation where appropriate.
	
	Full use of the Form's advanced controls requires that you include formio.js
	(and formio.css) on pages which use your form. You will also need to have
	jQuery and jQueryUI loaded, as these are used to handle the various form controls.
	
	Most form building methods are can be chained - these are denoted by the tag
	:CHAINABLE:. Most of these may be called independently, using the field name
	as first parameter - or chained, whereby the first parameter is filled by the
	name of the field last added to the form object.
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
	const T_CAPTCHA2 = 37;			// SecurImage plugin. DO NOT use this as the field type, instead use T_CAPTCHA and set FormIO::$captchaType accordingly
	const T_AUTOCOMPLETE = 36;		// a dropdown which polls a URL for possible values and can be freely entered into. If you wish to restrict to a range of values, check this yourself and use addError()
	const T_FILE = 38;

	// form builder strings for different element types :TODO: finish implementation
	private static $builder = array(
		FormIO::T_PASSWORD	=> '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$form}_{$name}">{$desc}{$required? <span class="required">*</span>}</label><input type="password" name="{$name}" id="{$form}_{$name}" />{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		FormIO::T_SUBMIT	=> '<input type="submit" name="{$name}" id="{$form}_{$name}" value="{$value}" />',
		FormIO::T_RESET		=> '<input type="reset" name="{$name}" id="{$form}_{$name}" value="{$value}" />',
		FormIO::T_INDENT	=> '<fieldset><legend>{$desc}</legend>',
		FormIO::T_OUTDENT	=> '</fieldset>',
		FormIO::T_DATERANGE	=> '<div class="row daterange{$alt? alt}{$classes? $classes}" id="{$form}_{$name}"><label for="{$form}_{$name}_start">{$desc}{$required? <span class="required">*</span>}</label><input type="text" name="{$name}[0]" id="{$form}_{$name}_start" value="{$value}" data-fio-type="date" /> - <input type="text" name="{$name}[1]" id="{$form}_{$name}_end" value="{$valueEnd}" data-fio-type="date" />{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		FormIO::T_DATETIME	=> '<div class="row datetime{$alt? alt}{$classes? $classes}" id="{$form}_{$name}"><label for="{$form}_{$name}_time">{$desc}{$required? <span class="required">*</span>}</label><input type="text" name="{$name}[0]" id="{$form}_{$name}_date" value="{$value}" data-fio-type="date" /> at <input type="text" name="{$name}[1]" id="{$form}_{$name}_time" value="{$valueTime}" data-fio-type="time" class="time" /><select name="{$name}[2]" id="{$form}_{$name}_meridian">{$am?<option value="am" selected="selected">am</option><option value="pm">pm</option>}{$pm?<option value="am">am</option><option value="pm" selected="selected">pm</option>}</select>{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		FormIO::T_BIGTEXT	=> '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$form}_{$name}">{$desc}{$required? <span class="required">*</span>}</label><textarea name="{$name}" id="{$form}_{$name}"{$maxlen? maxlength="$maxlen"}>{$value}</textarea>{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		FormIO::T_HIDDEN	=> '<input type="hidden" name="{$name}" id="{$form}_{$name}" value="{$value}" />',
		FormIO::T_CURRENCY	=> '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$form}_{$name}">{$desc}{$required? <span class="required">*</span>}</label><span class="currency"><span>$</span><input type="text" name="{$name}" id="{$form}_{$name}" value="{$value}"{$maxlen? maxlength="$maxlen"}{$behaviour? data-fio-type="$behaviour"}{$validation? data-fio-validation="$validation"} /></span>{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		
		FormIO::T_READONLY	=> '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$form}_{$name}">{$desc}</label><div class="readonly">{$value}</div><input type="hidden" name="{$name}" id="{$form}_{$name}" value="{$value}" />{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',

		FormIO::T_DROPDOWN	=> '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$form}_{$name}">{$desc}{$required? <span class="required">*</span>}</label><select id="{$form}_{$name}" name="{$name}"{$dependencies? data-fio-depends="$dependencies"}>{$options}</select>{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		FormIO::T_DROPOPTION=> '<option value="{$value}"{$disabled? disabled="disabled"}{$checked? selected="selected"}>{$desc}</option>',

		// T_RADIOGROUP is used for both radiogroup and checkgroup at present
		FormIO::T_RADIOGROUP=> '<fieldset id="{$form}_{$name}" class="row multiple{$alt? alt}"{$dependencies? data-fio-depends="$dependencies"}><legend>{$desc}{$required? <span class="required">*</span>}</legend>{$options}{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></fieldset>',
		FormIO::T_RADIO		=> '<label><input type="radio" name="{$name}" value="{$value}"{$disabled? disabled="disabled"}{$checked? checked="checked"} /> {$desc}</label>',
		FormIO::T_CHECKBOX	=> '<label><input type="checkbox" name="{$name}" value="{$value}"{$disabled? disabled="disabled"}{$checked? checked="checked"} /> {$desc}</label>',
		
		FormIO::T_AUTOCOMPLETE=> '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$form}_{$name}">{$desc}{$required? <span class="required">*</span>}</label><input type="text" name="{$name}" id="{$form}_{$name}" value="{$value}"{$maxlen? maxlength="$maxlen"}{$behaviour? data-fio-type="$behaviour"}{$validation? data-fio-validation="$validation"} data-fio-searchurl="{$searchurl}" />{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		FormIO::T_CAPTCHA	=> '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$form}_{$name}">{$desc}{$required? <span class="required">*</span>}</label>{$captcha}{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		FormIO::T_CAPTCHA2	=> '<div class="row{$alt? alt}{$classes? $classes}" data-fio-type="securimage"><label for="{$form}_{$name}">{$desc}{$required? <span class="required">*</span>}</label><input type="text" name="{$name}" id="{$form}_{$name}" {$maxlen? maxlength="$maxlen"} /><img src="{$captchaImage}" alt="CAPTCHA Image" class="captcha" /> <a class="reload" href="javascript: void(0);">Reload image</a> {$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',

		// this is our fallback input string as well. js is added via use of data-fio-* attributes.
		FormIO::T_TEXT		=> '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$form}_{$name}">{$desc}{$required? <span class="required">*</span>}</label><input type="text" name="{$name}" id="{$form}_{$name}" value="{$value}"{$maxlen? maxlength="$maxlen"}{$behaviour? data-fio-type="$behaviour"}{$validation? data-fio-validation="$validation"} />{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
	);
	
	// This contains an array of all field types which are presentational only.
	// Used by FormIO::getData() to filter the returned array
	private static $presentational = array(
		FormIO::T_RAW, FormIO::T_HEADER, FormIO::T_SUBHEADER, FormIO::T_PARAGRAPH, FormIO::T_SECTIONBREAK,
		FormIO::T_IMAGE, FormIO::T_INDENT, FormIO::T_OUTDENT, FormIO::T_BUTTON, FormIO::T_RESET, FormIO::T_CAPTCHA
	);

	//===============================================================================================\/
	//	Stuff you might want to change

	// default error messages for builtin validator methods
	private static $defaultErrors = array(
		'requiredValidator'	=> "This field is required",
		'arrayRequiredValidator' => "All elements are required",
		'equalValidator'	=> "You must select $2",
		'notEqualValidator'	=> "Must not be equal to $2",
		'minLengthValidator'=> "Must be at least $2 characters",
		'maxLengthValidator'=> "Must not be longer than $2 characters",
		'inArrayValidator'	=> "Must be one of $2",
		'regexValidator'	=> "Incorrect format",
		'dateValidator'		=> "Requires a valid date in dd/mm/yyyy format",
		'dateTimeValidator'	=> "Requires a valid date (dd/mm/yyyy), time (hh:mm) and time of day",
		'dateRangeValidator'=> "Dates must be in dd/mm/yyyy format",
		'emailValidator'	=> "Invalid email address",
		'phoneValidator'	=> "Invalid phone number. Phone numbers must contain numbers, spaces and brackets only, and may start with a plus sign.",
		'urlValidator'		=> "Invalid URL",
		'currencyValidator'	=> "Enter amount in dollars and cents",
		'captchaValidator'	=> "The text entered did not match the verification image",
	);

	// misc constants used for validation
	const dateRegex		= '/^\s*(\d{1,2})\/(\d{1,2})\/(\d{2}|\d{4})\s*$/';						// capture: day, month, year
	const timeRegex		= '/^\s*(\d{1,2})(:(\d{2}))?(:(\d{2}))?\s*$/';							// capture: hr, , min, , sec
	const emailRegex	= '/^[-!#$%&\'*+\\.\/0-9=?A-Z^_`{|}~]+@([-0-9A-Z]+\.)+([0-9A-Z]){2,4}$/i';
	const phoneRegex	= '/^(\+)?(\d|\s|\(|\))*$/';
	const currencyRegex	= '/^\s*\$?(\d*)(\.(\d{0,2}))?\s*$/';									// capture: dollars, , cents
	
	// parameters for T_CAPTCHA. Recommend you set these from your own scripts.
	
	public $captchaType		= 'securimage';			// must be 'securimage' or 'recaptcha'
	// Some notes on captcha types and requirements:
	//	- reCAPTCHA requires that your form be submitted via POST, and that socket connections
	//	  to external sites are possible. This may be an issue from behind a proxy server.
	//	- SecurImage requires that GD be installed and running on your server. It also requires sessions to be enabled.
	// All captchas attempt to use the session to store validation status - this way, a user only need authenticate once.
	
	public $CAPTCHA_session_var = '__formIO_CAPTCHA_ok';		// once we have authenticated as human, this will be stored in session so we don't have to do it again
	public $reCAPTCHA_pub	= '';
	public $reCAPTCHA_priv	= '';
	public $reCAPTCHA_inc	= 'recaptcha/recaptchalib.php';		// this should point to the reCAPTCHA php include file
	public $securImage_inc	= 'securimage/securimage.php';		// this should point to the SecurImage php include file
	public $securImage_img	= 'securimage/securimage_show.php';	// this should point to the SecurImage php image generation file

	//===============================================================================================/\

	private $lastBuilderReplacement;		// form builder hack for preg_replace_callback not being able to accept extra parameters
	private $lastAddedField;				// name of the last added field is stored here, for field attribute method chaining
	private $autoNameCounter;				// used for presentational field types where the field name doesn't matter and we don't want to have to specify it

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
	//	Form building

	/**
	 * Add a field to this form
	 *
	 * :CHAINABLE:
	 */
	public function addField($name, $displayText, $type, $value = null)
	{
		$this->data[$name] = $value;
		$this->dataAttributes[$name] = array('desc' => $displayText);
		$this->setDataType($name, $type);
		if ($type == FormIO::T_DROPDOWN || $type == FormIO::T_RADIOGROUP || $type == FormIO::T_CHECKGROUP || $type == FormIO::T_SURVEY) {
			$this->dataOptions[$name] = array();
		}
		
		$this->lastAddedField = $name;
		return $this;
	}

	/**
	 * Set some fields to be required. Either pass as many field names to the function as you
	 * desire, or call immediately after adding the field, with no parameters.
	 * 
	 * :CHAINABLE:
	 */
	public function setRequired()
	{
		$a = func_get_args();
		if (!sizeof($a)) {
			$a[] = $this->lastAddedField;
		}
		foreach ($a as $fieldName) {
			switch ($this->dataTypes[$fieldName]) {
				case FormIO::T_DATERANGE:
				case FormIO::T_DATETIME:
					$this->addValidator($fieldName, 'arrayRequiredValidator', array(), false);
					break;
				case FormIO::T_CAPTCHA:
					break;		// :NOTE: captcha's dont need to be required as they implicitly are already
				default:
					$this->addValidator($fieldName, 'requiredValidator', array(), false);
					break;
			}
		}
		return $this;
	}

	/**
	 * Adds a validator to a field
	 * @param	string	$k				data key in form data to apply validator to
	 * @param	string	$validatorName	name of validation function to run
	 * @param	array	$params			extra parameters to pass to the validation callback (value is always parameter 0)
	 * @param	bool	$customFunc		if true, look in the global namespace for this function. otherwise it is a method of the FormIO class.
	 *
	 * :CHAINABLE:
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

		return $this;
	}
	
	/**
	 * convenience :CHAINABLE: version of the above (no fieldname required). Use like $form->addField(...)->validateWith(...)->addField(......
	 */
	public function validateWith($validatorName, $params = array(), $customFunc = true)
	{
		return $this->addValidator($this->lastAddedField, $validatorName, $params, $customFunc);
	}

	/**
	 * Adds an attribute to a field. Use this for presentational things like CSS class names, maxlen attributes etc.
	 * You can add anything you like in here, but they will only be output if present in the form builder strings
	 * for the field type being processed. Also note that adding elements here linearly slows the performance of
	 * rendering the field in question.
	 * If called with 2 parameters, the last added field is used as the key.
	 * 
	 * :CHAINABLE:
	 */
	public function addAttribute()
	{
		$a = func_get_args();
		if (sizeof($a) < 3) {
			array_unshift($a, $this->lastAddedField);
		}
		list($k, $attr, $value) = $a;
		
		$this->dataAttributes[$k][$attr] = $value;
		
		return $this;
	}

	// Allows you to add an error message to the form from external scripts
	public function addError($dataKey, $msg)
	{
		if (isset($this->errors[$dataKey])) {
			if (!is_array($this->errors[$dataKey])) {
				$this->errors[$dataKey] = array($this->errors[$dataKey]);
			}
			$this->errors[$dataKey][] = $msg;
		} else {
			$this->errors[$dataKey] = $msg;
		}
	}

	/**
	 * Add an option for a multiple field type field (select, radiogroup etc)
	 *
	 * @param	string	$k			field name to add an option for
	 * @param	string	$optionVal	the value this option will have
	 * @param	mixed	$optionText either the option's description (as text);
	 * 								or array of desc(string) and optional disabled(bool), checked(bool) properties
	 * @param	mixed	$dependentField @see FormIO::addFieldDependency()
	 *
	 * :CHAINABLE:
	 */
	public function addFieldOption($k, $optionVal, $optionText, $dependentField = null)
	{
		$this->dataOptions[$k][$optionVal] = $optionText;
		if ($dependentField !== null) {
			$this->addFieldDependency($k, $optionVal, $dependentField);
		}
		return $this;
	}
	
	/**
	 * convenience :CHAINABLE: version of the above (no fieldname required). Use like $form->addField(...)->addOption(...)->addOption(...)->addField(......
	 */
	public function addOption($optionVal, $optionText, $dependentField = null)
	{
		return $this->addFieldOption($this->lastAddedField, $optionVal, $optionText, $dependentField);
	}

	/**
	 * Adds a dependency between one field and another. This sets up the javascript
	 * to toggle visibility of a field when the value of another changes.
	 *
	 * @param	string	$k				field to add the dependency to
	 * @param	mixed	$expectedValue	when the value of field $k is $expectedValue, $dependentField will be visible. Otherwise, it won't.
	 * @param	mixed	$dependentField	field name or array of field names to toggle when the value of field $k changes
	 * 
	 * :CHAINABLE:
	 */
	public function addFieldDependency()
	{
		$a = func_get_args();
		if (sizeof($a) < 3) {
			array_unshift($a, $this->lastAddedField);
		}
		list($k, $expectedValue, $dependentField) = $a;
		
		if (!isset($this->dataDepends[$k])) {
			$this->dataDepends[$k] = array();
		}
		if (!is_array($dependentField)) {
			$dependentField = array($dependentField);
		}
		$this->dataDepends[$k][$expectedValue] = $dependentField;
		return $this;
	}
	
	/**
	 * Sets the autocomplete data URL for an autocomplete field. This falls through
	 * to the jQuery UI autocomplete control, so the URL will have ?term=[input text]
	 * appended to it.
	 *
	 * :CHAINABLE:
	 */
	public function setAutocompleteURL()
	{
		$a = func_get_args();
		if (sizeof($a) < 2) {
			array_unshift($a, $this->lastAddedField);
		}
		list($k, $url) = $a;
		
		return $this->addAttribute($k, 'searchurl', $url);
	}

	// simplified mutators for adding various non-field types
	
	public function startFieldset($title) {
		return $this->addField('fs' . $this->autoNameCounter++, $title, FormIO::T_INDENT);
	}
	
	public function endFieldset() {
		return $this->addField('fs' . $this->autoNameCounter++, '', FormIO::T_OUTDENT);
	}
	
	public function addSubmitButton($text) {
		return $this->addField('fs' . $this->autoNameCounter++, '', FormIO::T_SUBMIT, $text);
	}
	
	public function addResetButton($text) {
		return $this->addField('fs' . $this->autoNameCounter++, '', FormIO::T_RESET, $text);
	}

	//==========================================================================
	//	Accessors
	
	/**
	 * Retrieves all the form's data, as an array. Non-input field types are filtered from the output.
	 * You may choose to also retrieve submit button values by passing true to the function.
	 */
	public function getData($includeSubmit = false)
	{
		$data = $this->data;
		foreach ($data as $k => $v) {
			if (in_array($this->dataTypes[$k], FormIO::$presentational) || (!$includeSubmit && $this->dataTypes[$k] == FormIO::T_SUBMIT)) {
				unset($data[$k]);
			}
		}
		return $data;
	}
	
	public function getRawData()
	{
		return $this->data;
	}

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
	//	Other mutators
	
	public function setFormAction($url)
	{
		$this->action = $url;
	}
	
	public function setPreamble($html)
	{
		$this->preamble = $html;
	}
	
	public function setSuffix($html)
	{
		$this->suffix = $html;
	}

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
			case FormIO::T_DATETIME:
				$this->addValidator($k, 'dateTimeValidator', array(), false); break;
			case FormIO::T_CAPTCHA:
				if ($this->captchaType == 'recaptcha') {
					$this->method = 'POST';						// force using POST submission for reCAPTCHA
				}
				$this->addValidator($k, 'captchaValidator', array(), false); break;
		}
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

	//==========================================================================
	//	Rendering

	public function getJSON()
	{
		return JSONParser::encode($this->data);
	}

	public function getQueryString()
	{
		return http_build_query($this->data);
	}

	public function getForm()
	{
		$form = "<form id=\"$this->name\" class=\"clean\" method=\"$this->method\" action=\"$this->action\"" . ($this->multipart ? ' enctype="multipart/form-data"' : '') . '>' . "\n";
		
		$hasErrors = sizeof($this->errors) > 0;
		if ($hasErrors || isset($this->preamble)) {
			$form .= '<div class="preamble">' . "\n" . (isset($this->preamble) ? $this->preamble : '') . "\n";
			$form .= $hasErrors ? "<p class=\"err\">Please review your submission: " . sizeof($this->errors) . " fields have errors.</p>\n" : '';
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
				case FormIO::T_DATETIME:
					$inputVars['value']		= $value[0];
					$inputVars['valueTime']	= $value[1];
					$inputVars['pm']		= $value[2] == 'pm';
					$inputVars['am']		= $value[2] != 'pm';
					break;
				case FormIO::T_CAPTCHA:
					if (!empty($_SESSION[$this->CAPTCHA_session_var])) {
						continue 2;								// already verified as human, so don't output the field anymore
					}
					switch ($this->captchaType) {
						case 'recaptcha':
							require_once($this->reCAPTCHA_inc);
							$inputVars['captcha'] = recaptcha_get_html($this->reCAPTCHA_pub);
							break;
						case 'securimage':
							require_once($this->securImage_inc);
							$inputVars['captchaImage'] = $this->securImage_img;
							$builderString = FormIO::$builder[FormIO::T_CAPTCHA2];
							break;
					}
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
					
					// determine if a value has been sent
					$valueSent = isset($this->data[$k]) && $this->data[$k] !== '';
					
					// Build field sub-elements
					$inputVars['options'] = '';
					foreach ($this->dataOptions[$k] as $optVal => $desc) {
						$radioVars = array(
							'name'		=> $k,
							'value'		=> $optVal,
						);
						if (is_array($desc)) {
							$radioVars['desc'] = $desc['desc'];
							if (isset($desc['disabled']))				$radioVars['disabled']	= $desc['disabled'];
							if (isset($desc['checked']) && !$valueSent)	$radioVars['checked']	= $desc['checked'];
						} else {
							$radioVars['desc'] = $desc;
						}
						// determine whether option should be selected if it hasn't explicitly been set
						if ($valueSent && $inputVars['value'] == $optVal) {
							$radioVars['checked'] = true;
						}
						$inputVars['options'] .= $this->replaceInputVars(FormIO::$builder[$subFieldType], $radioVars);
					}
					// Unset value, we don't use it for these field types
					unset($inputVars['value']);
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
		
		if (isset($this->suffix)) {
			$form .= '<div class="suffix">' . "\n" . (isset($this->suffix) ? $this->suffix : '') . "\n";
			$form .= '</div>' . "\n";
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
			if ($value === false || $value === null) {
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

			// run requiredValidator first, since any other validation need only run if the data is present
			$dataSubmitted = $this->requiredValidator($dataKey);

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
				// only perform validation if data has been sent, or we are checking a required fieldtype (captcha)
				if (!$externalValidator && $func == 'requiredValidator') {
					$valid = $dataSubmitted;
				} else if ($dataSubmitted || (!$externalValidator && $func == 'captchaValidator')) {
					$valid = call_user_func_array($externalValidator ? $func : array($this, $func), $params);
				}

				if (!$valid) {
					$this->addError($dataKey, FormIO::errorString($func, $params));
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
		return preg_match($regex, $this->data[$key]) > 0;
	}

	private function dateValidator($key) {					// performs date normalisation
		preg_match(FormIO::dateRegex, $this->data[$key], $matches);
		$success = sizeof($matches) == 4;
		if ($matches[1] > 31 || $matches[2] > 12) {
			return false;
		}
		if ($success) {
			$this->data[$key] = $this->normaliseDate($matches[1], $matches[2], $matches[3]);
		}
		return $success != false;
	}

	private function emailValidator($key) {
		return $this->regexValidator($key, FormIO::emailRegex);
	}

	private function phoneValidator($key) {
		return preg_match('/\d/', $this->data[$key]) && $this->regexValidator($key, FormIO::phoneRegex);
	}

	private function currencyValidator($key) {				// performs currency normalisation
		preg_match(FormIO::currencyRegex, $this->data[$key], $matches);
		$success = sizeof($matches) > 0;
		if ($success) {
			$this->data[$key] = $this->normaliseCurrency($matches[1], (isset($matches[3]) ? $matches[3] : null));
		}
		return $success != false;
	}

	private function urlValidator($key) {					// allows http, https & ftp *only*. Also performs url normalisation
		$this->data[$key] = $this->normaliseURL($this->data[$key]);
		
		if (false == $bits = parse_url($this->data[$key])) {
			return false;
		}
		if (empty($bits['host']) || !ctype_alpha(substr($bits['host'], 0, 1))) {
			return false;
		}

		return (empty($bits['scheme']) || $bits['scheme'] == 'http' || $bits['scheme'] == 'https' || $bits['scheme'] == 'ftp');
	}

	private function captchaValidator($key) {				// stores result in session, if available. We only need to authenticate as human once.
		if (isset($_SESSION[$this->CAPTCHA_session_var])) {
			return $_SESSION[$this->CAPTCHA_session_var];
		}
		$ok = false;
		switch ($this->captchaType) {
			case 'recaptcha':
				require_once($this->reCAPTCHA_inc);
				$resp = recaptcha_check_answer($this->reCAPTCHA_priv,
                                $_SERVER["REMOTE_ADDR"],
                                $_POST["recaptcha_challenge_field"],
                                $_POST["recaptcha_response_field"]);
				$ok = $resp->is_valid;
				break;
			case 'securimage':
				require_once($this->securImage_inc);
				$securimage = new Securimage();
				if ($securimage->check($this->data[$key])) {
					$ok = true;
				}
				break;
		}
		if (session_id() && $ok) {
			$_SESSION[$this->CAPTCHA_session_var] = true;
		}
		return $ok;
	}

	private function dateRangeValidator($key) {			// performs date normalisation
		if (isset($this->data[$key]) && is_array($this->data[$key])) {
			if ((!empty($this->data[$key][0]) || !empty($this->data[$key][1]))
			  && (!preg_match(FormIO::dateRegex, $this->data[$key][0], $matches1)
			  || !preg_match(FormIO::dateRegex, $this->data[$key][1], $matches2))) {
				return false;
			}

			if ($matches1[1] > 31 || $matches1[2] > 12 || $matches2[1] > 31 || $matches2[2] > 12) {
				return false;
			}
			
			$this->data[$key][0] = $this->normaliseDate($matches1[1], $matches1[2], $matches1[3]);
			$this->data[$key][1] = $this->normaliseDate($matches2[1], $matches2[2], $matches2[3]);

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

	private function dateTimeValidator($key) {			// performs date and time normalisation
		if (isset($this->data[$key]) && is_array($this->data[$key])) {
			// either both or none must be set
			if (empty($this->data[$key][0]) ^ empty($this->data[$key][1])) {
				return false;
			}
			if (empty($this->data[$key][0])) {		// none set, nothing being sent
				$this->data[$key] = array();
				return true;
			}

			$dateOk = preg_match(FormIO::dateRegex, $this->data[$key][0], $dateMatches);
			$timeOk = preg_match(FormIO::timeRegex, $this->data[$key][1], $timeMatches);

			if (!$dateOk || !$timeOk) {
				return false;
			}
			if ($dateMatches[1] > 31 || $dateMatches[2] > 12 || $timeMatches[1] > 12 || (isset($timeMatches[3]) && $timeMatches[3] > 59)) {
				return false;
			}

			$this->data[$key] = array(
								$this->normaliseDate($dateMatches[1], $dateMatches[2], $dateMatches[3]),
								$this->normaliseTime($timeMatches[1], (isset($timeMatches[3]) ? $timeMatches[3] : 0), (isset($timeMatches[5]) ? $timeMatches[5] : null)),
								$this->data[$key][2]
							);
		}
		return true;
	}

	//==========================================================================
	//	Data normalisers
	//		Run from within validators, these functions ensure that an input
	//		variable is in the expected input format.
	//		These will be returned to the user, so don't use them for 'modifying
	//		the value', if that differentiation makes sense.
	
	private function normaliseDate($d, $m, $y) {				// dd/mm/yyyy
		$yearPadStr = '20';
		if ($y < 100 && $y > 69) {
			$yearPadStr = '19';
		}
		
		return str_pad($d, 2, '0', STR_PAD_LEFT) . '/' . str_pad($m, 2, '0', STR_PAD_LEFT) . '/' . str_pad($y, 4, $yearPadStr, STR_PAD_LEFT);
	}
	
	private function normaliseTime($h, $m, $s = null) {			// hh:mm(:ss)
		return str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT) . ($s !== null ? ':' . str_pad($s, 2, '0', STR_PAD_LEFT) : '');
	}
	
	private function normaliseCurrency($d, $c = 0) {			// $d.cc
		return intval($d) . '.' . str_pad($c, 2, '0', STR_PAD_RIGHT);
	}
	
	private function normaliseURL($str) {						// ensures a scheme is present
		if (!preg_match('/^\w+:\/\//', $str)) {
			return 'http://' . $str;
		}
		return $str;
	}

	//==========================================================================
	//	Miscellaneous
	
	public static function dateTimeToMySQL($val)
	{
		@list($hr, $min, $sec) = explode(':', $val[1]);
		if ($val[2] == 'pm') {
			if ($hr != 12) {
				$hr += 12;
			}
		} else if ($hr == 12) {
			$hr = '00';
		}
		return FormIO::dateToMySQL($val[0]) . ' ' . $hr . ':' . $min . ':' . ($sec ? $sec : '00');
	}
	
	public static function dateToMySQL($val)
	{
		$bits = explode('/', $val);
		return $bits[2] . '-' . $bits[1] . '-' . $bits[0];
	}
	
	public static function mySQLDateTimeToForm($val)
	{
		$val = explode(' ', $val, 2);
		list($h, $min, $s) = explode(':', $val[1]);
		
		if ($h === null || $min === null || $s === null) {
			return '';
		}
		
		$meridian = 'am';
		if ($h > 11) {
			$meridian = 'pm';
			if ($h > 12) {
				$h -= 12;
			}
		} else if ($h == 0) {
			$h = 12;
		}
		
		return array(
			FormIO::mySQLDateToForm($val[0]),
			"$h:$min" . (intval($s) > 0 ? ":$s" : ""),
			$meridian
		);
	}
	
	public static function mySQLDateToForm($val)
	{
		list($y, $mth, $d) = explode('-', $val);
		if ($y === null || $mth === null || $d === null) {
			return '';
		}
		return "$d/$mth/$y";
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
