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

define('FORMIO_FIELDS', dirname(__FILE__) . '/fields/');

// Important base field classes. Others can be loaded via FormIO::loadFieldByClass()
require_once(FORMIO_FIELDS . 'formio_field-raw.class.php');			// base field class for presentational fields
require_once(FORMIO_FIELDS . 'formio_field-text.class.php');		// base field class for input fields
require_once(FORMIO_FIELDS . 'formio_field-group.class.php');		// base class for a group of fields
require_once(FORMIO_FIELDS . 'formio_field-multiple.class.php');	// base field class for array-type fields
require_once(FORMIO_FIELDS . 'formio_field-submit.class.php');		// base field class for input fields where the value is for triggering submission only
require_once(FORMIO_FIELDS . 'formio_field-captcha.class.php');		// base field class for CAPTCHA fields
// These remaining field types aren't that important, but their names & functions are used as checks
// in this class and in other fields, and so will cause __autoload() to be executed unless already declared.
require_once(FORMIO_FIELDS . 'formio_field-repeater.class.php');
require_once(FORMIO_FIELDS . 'formio_field-spacer.class.php');
require_once(FORMIO_FIELDS . 'formio_field-fieldsetstart.class.php');
require_once(FORMIO_FIELDS . 'formio_field-autocomplete.class.php');
require_once(FORMIO_FIELDS . 'formio_field-date.class.php');
require_once(FORMIO_FIELDS . 'formio_field-time.class.php');

class FormIO implements ArrayAccess
{
	// Field types. Use them if you wish, they are really mostly
	// here for compatibility reasons. The field type your're adding
	// corresponds to the (lowercase) portion of the class name after 'FormIOField_'

	// non-input types
	const T_RAW		= 'raw';			// raw HTML output
	const T_HEADER	= 'header';			// h1
	const T_SUBHEADER = 'subheader';	// h3
	const T_PARAGRAPH = 'paragraph';
	const T_SECTIONBREAK = 'sectionbreak';	// new <tbody>, paginated via JScript
	const T_IMAGE	= 'image';
	const T_INDENT	= 'fieldsetstart';	// starts a <fieldset>
	const T_OUTDENT	= 'fieldsetend';	// ends a <fieldset>

	// form input types
	const T_TEXT	= 'text';
		// All these indented types actually output normal text fields and are driven by JS and serverside validation
		const T_EMAIL	= 'email';
		const T_PHONE	= 'phone';
		const T_CREDITCARD = 'creditcard';		// performs MOD10 validation
		const T_ALPHA	= 'alpha';
		const T_NUMERIC	= 'numeric';
		const T_ALPHANUMERIC = 'alphanumeric';
		const T_CURRENCY = 'currency';		// rounded to 2 decimal points, allows $
		const T_DATE	= 'date';
		const T_TIME	= 'time';
		const T_AUSPOSTCODE = 'postcode';	// australian postcode, 4 digits
		const T_URL		= 'url';
	const T_DATETIME = 'datetime';			// compound fields for date & time components
	const T_DATERANGE = 'daterange';			// two date fields
	const T_TIMERANGE = 'timerange';			// two datetime fields
	const T_BIGTEXT	= 'textarea';			// textarea
	const T_HIDDEN	= 'hidden';			// input[type=hidden]
	const T_READONLY = 'readonly';			// a bit like a hidden input, only we show the variable
	const T_DROPDOWN = 'dropdown';			// select
	const T_CHECKBOX = 'checkbox';			// single checkbox
	const T_RADIOGROUP = 'radiogroup';		// list of radio buttons
	const T_CHECKGROUP = 'checkgroup';		// list of checkboxes
	const T_SURVEY	= 'survey';			// :TODO:
	const T_PASSWORD = 'password';
	const T_BUTTON	= 'button';
	const T_SUBMIT	= 'submit';
	const T_RESET	= 'reset';
	const T_CAPTCHA	= 'recaptcha';		// reCAPTCHA plugin
	const T_CAPTCHA2 = 'securimage';	// SecurImage plugin. You may also use T_CAPTCHA and set FormIO::$captchaType to 'securimage'
	const T_AUTOCOMPLETE = 'autocomplete';		// a dropdown which polls a URL for possible values and can be freely entered into. If you wish to restrict to a range of values, check this yourself and use addError()
	const T_FILE = 'file';
	const T_SPACER = 'spacer';			// does nothing. Use this to increment the row striper, etc
	const T_PASSWORDCHANGE = 'passwordchange';	// outputs two password inputs which must match to validate. :TODO: also does complexity check ?
	const T_REPEATER = 'repeater';		// give an input type to repeat an arbitrary number of times
	const T_GROUP = 'group';			// a logical grouping of form fields. Best used in tandem with T_REPEATER

	//===============================================================================================\/
	//	Stuff you might want to change

	// parameters for T_CAPTCHA. Recommend you set these from your own scripts rather than overriding here.

	public $captchaType		= 'securimage';		// The Field name to instantiate for T_CAPTCHA fields. At present, this means a choice of 'securimage' or 'recaptcha'
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

	private $lastAddedField;				// name of the last added field is stored here, for field attribute method chaining
	public	$nextRepeaterFieldType = false;	// this must be flagged prior to adding a repeater with the type (classname substring) of field to repeat
	private $autoNameCounter;				// used for presentational field types where the field name doesn't matter and we don't want to have to specify it
	public $delaySubmission = false;		// this is used by fields which post back to the form in fallback mode to prevent validation
	public $tabCounter = 0;					// used by SectionBreak fields to set their names

	// Form stuff
	public $name;		// unique html ID for this form
	private $action;
	private $method;	// GET or POST
	private $multipart;	// if true, render with enctype="multipart/form-data"

	private $statusMessage;		// this allows setting a form message to display at the top of the form. use for notifying of success, etc
	private $defaultSubmit;		// the default submit action (enter key) MUST be the first submit button on the form. This is echoed out twice (hidden on the first one) for this to work without JS

	// Field stuff
	private $fields = array();
	private $errors = array();		// data validation errors, filled by call to validate()

	private $sections = array();	// SectionBreak fields are referenced into this array as well, to simplify pulling out tab navigation

	// state variables
	public $submitted	= false;
	public $valid		= false;

	//==========================================================================
	//	Important stuff

	/**
	 * Form constructor. We give it a method, action, enctype and name
	 */
	public function __construct($formName, $method = "GET", $action = null, $multipart = false)
	{
		$this->name = $formName;
		if ($action === null) {
			$this->action = $_SERVER['PHP_SELF'] . ($method != "GET" && !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
		} else {
			$this->action = $action;
		}
		$this->method = $method == "GET" ? "GET" : "POST";
		$this->multipart = $multipart;
	}

	/**
	 * Load the class file for a field type, given its class name. Return an object of the desired field type.
	 * The class names of your fields MUST match the pattern 'FormIOField_{Myclassname}', where
	 * The 'Myclassname' portion is your field's name as given to this function, all in lowercase
	 * except for the first letter.
	 */
	public static function loadFieldByClass($fieldClass, $name, $displayText = '', $formObj = null)
	{
		$className = FormIO::preloadFieldClass($fieldClass);

		if (!isset($this)) {
			$field = new $className($formObj, $name, $displayText);
		} else {
			$field = new $className($this, $name, $displayText);
		}
		return $field;
	}

	public static function getFieldClassName($formIOName)
	{
		return 'FormIOField_' . ucfirst($formIOName);
	}

	// Attempts to ensure that a field's class file is loaded, given the FormIOField_ class suffix.
	// If a class matching the field's name with the 'FormIOField_' prefix is found, return the full class name.
	// Otherwise, we attempt loading one from the 'fields/' subdirectory, and if unsuccessful, throw an error.
	public static function preloadFieldClass($fieldClass)
	{
		$className = FormIO::getFieldClassName($fieldClass);
		if (!class_exists($className) && file_exists(FORMIO_FIELDS . 'formio_field-' . $fieldClass . '.class.php')) {
			require_once(FORMIO_FIELDS . 'formio_field-' . $fieldClass . '.class.php');
		}
		if (!class_exists($className)) {
			trigger_error("Unknown FormIO field type '$fieldClass'", E_USER_ERROR);
		}
		return $className;
	}

	/**
	 * Imports a data map from some other array. This does not erase existing values
	 * unless the source array overrides those properties.
	 * Best used when importing variables from $_POST, $_GET etc.
	 *
	 * :NOTE: To import from repeated file inputs, you must send:
	 *	$assoc = the repeater field's name
	 *	$isFile = true
	 *
	 * @param	array	$assoc			Associative data array to import
	 */
	public function importData($assoc, $isPost = false, $isRepeatedFile = false)
	{
		if ((!is_array($assoc) && !is_string($assoc)) || !sizeof($assoc)) {
			return false;
		}

		// file inputs cannot be sent as arrays, and so repeated file inputs must be treated differently.
		// hacky but best that can be done. :TODO: make this work when inputs aren't sequential
		if ($isRepeatedFile) {
			$idx = $assoc . '_';
			$i = 0;
			$assoc = array();
			while (isset($_FILES[$idx.$i])) {
				$assoc['f' . $i] = $_FILES[$idx.$i];
				++$i;
			}
			return $assoc;
		}

		$unhandledFields = $this->fields;               // reference the fields array so we can remove the processed ones

		$fieldNames = $this->getInputFieldNames();

		// now we add the new data
		foreach ($assoc as $k => $val) {
			if (!array_key_exists($k, $fieldNames)) {
				unset($assoc[$k]);
				continue;
			}

			// get the field objects related to this data value, eg for when submit buttons share a name
			$relatedFields = $fieldNames[$k];

			foreach ($relatedFields as $fieldIndex) {
				$field = $this->fields[$fieldIndex];
				// if we are POSTing, and the input variable is a repeated file input, act accordingly.
				if ($isPost
				  && $field instanceof FormIOField_Repeater
				  && $field->getAttribute('fieldtype') == FormIO::T_FILE
				  && isset($val['isfiles'])) {
					$val = $this->importData($k, false, true);
				}

				$field->setValue($val);
				unset($unhandledFields[$fieldIndex]);		// remove the processed field from the list
			}
		}

		// go through all unhandled fields and give them an opportunity to reset their value to an 'unprovided' state
		foreach ($unhandledFields as $name => $field) {
			$field->inputNotProvided();
		}

		$this->submitted = true;
		$this->valid = true;		// assume validity until a validator or some user function adds an error
		return true;
	}

	/**
	 * An accessor for importData() which imports from $_POST and $_GET data. Note
	 * That data from $_FILES is handled differently, and imports based on the presence
	 * of the same $_POST variables.
	 */
	public function takeSubmission()
	{
		return $this->method == 'GET' ? $this->importData($_GET) : $this->importData($_POST, true);
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
		// mimic old CAPTCHA behaviour by reading class captcha type variable
		if ($type == FormIO::T_CAPTCHA) {
			$type = $this->captchaType;
		}

		// create field
		$field = FormIO::loadFieldByClass($type, $name, $displayText, $this);

		$arrayKey = $field->getInternalName();

		if (isset($this->fields[$arrayKey])) {
			trigger_error("Attempted to add new FormIO field, but field name already exists", E_USER_ERROR);
			return null;
		}

		// repeater type must be set before value, hence this little hack!
		if ($this->nextRepeaterFieldType !== false) {
			$field->setRepeaterType($this->nextRepeaterFieldType);
			$this->nextRepeaterFieldType = false;
		}

		// set its value
		if (method_exists($field, 'setValue')) {	// this check allows presentational fields to use setValue() as a default setter for some property (img field)
			$field->setValue($value);
		}

		$this->fields[$arrayKey] = $field;

		$this->lastAddedField = $arrayKey;
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
			$this->fields[$fieldName]->setRequired();
		}
		return $this;
	}

	/**
	 * Adds a validator to a field.
	 * When run, validators are passed the following parameters:
	 *	Internal (class method) validators -
	 *		data key, [extra params...]
	 *	External validators -
	 *		sent value, form object, data key, [extra params...]
	 *
	 * @param	string	$k				data key in form data to apply validator to
	 * @param	string	$validatorName	name of validation function to run
	 * @param	string	$errorMsg		A custom error message to return if this validator is unsuccessful.
	 *									If you require multiple error messages to be set, use addError() from within the validator function itself.
	 * @param	array	$params			extra parameters to pass to the validation callback (value is always parameter 0)
	 * @param	bool	$customFunc		if true, look in the global namespace for this function. otherwise it is a method of the FormIO class.
	 *
	 * :CHAINABLE:
	 */
	public function addValidator($k, $validatorName, $params = array(), $customFunc = true, $errorMsg = null)
	{
		$this->fields[$k]->addValidator($validatorName, $params, $customFunc, $errorMsg);
		return $this;
	}

	// same as above, only this adds a member function validator rather than an external function
	public function addFieldValidator($k, $validatorName, $params = array())
	{
		return $this->addValidator($k, $validatorName, $params, false);
	}

	/**
	 * convenience :CHAINABLE: version of the above (no fieldname required). Use like $form->addField(...)->validateWith(...)->addField(......
	 */
	public function validateWith($validatorName, $params = array(), $errorMsg = null, $customFunc = true)
	{
		if (!is_array($params)) {
			$params = array($params);
		}
		return $this->addValidator($this->lastAddedField, $validatorName, $params, $customFunc, $errorMsg);
	}

	// same as above, only this adds a member function validator rather than an external function
	public function useValidator($validatorName, $params = array())
	{
		return $this->validateWith($validatorName, $params, null, false);
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

		$this->fields[$k]->setAttribute($attr, $value);

		return $this;
	}

	// Allows you to add an error message to the form from external scripts
	// :TODO: handle nested repeater fields
	public function addError($dataKey, $msg)
	{
		if (!$msg) {
			return false;
		}
		$subKey = null;
		if (is_array($dataKey)) {
			list($dataKey, $subKey) = $dataKey;
		}
		if (isset($this->errors[$dataKey]) || $subKey !== null) {
			if (!is_array($this->errors[$dataKey])) {
				$this->errors[$dataKey] = array($this->errors[$dataKey]);
			}
			if ($subKey !== null) {
				if (!isset($this->errors[$dataKey][$subKey])) {
					$this->errors[$dataKey][$subKey] = array();
				}
				$this->errors[$dataKey][$subKey][] = $msg;
			} else {
				$this->errors[$dataKey][] = $msg;
			}
		} else {
			$this->errors[$dataKey] = $msg;
		}
		$this->valid = false;
		return true;
	}

	/**
	 * Add an option for a multiple field type field (select, radiogroup etc)
	 *
	 * @param	string	$k			field name to add an option for
	 * @param	string	$optionVal	the value this option will have
	 * @param	mixed	$optionText either the option's description (as text);
	 * 								or array of desc(string) and optional disabled(bool), checked(bool) properties
	 * @param	mixed	$dependentField @see FormIOField_Text::addDependency()
	 *
	 * :CHAINABLE:
	 */
	public function addFieldOption($k, $optionVal, $optionText, $dependentField = null)
	{
		if (!$this->fields[$k] instanceof FormIOField_Multiple) {
			trigger_error("Wrong field type for adding field options with field '$k'", E_USER_WARNING);
		}
		$this->fields[$k]->setOption($optionVal, $optionText, $dependentField);

		return $this;
	}

	/**
	 * convenience :CHAINABLE: version of the above (no fieldname required). Use like $form->addField(...)->addOption(...)->addOption(...)->addField(......
	 */
	public function addOption($optionVal, $optionText, $dependentField = null)
	{
		return $this->addFieldOption($this->lastAddedField, $optionVal, $optionText, $dependentField);
	}

	// :CHAINABLE:
	public function setFieldOptions()
	{
		$a = func_get_args();
		if (sizeof($a) < 2) {
			array_unshift($a, $this->lastAddedField);
		}
		list($k, $array) = $a;
		if (!$this->fields[$k] instanceof FormIOField_Multiple) {
			trigger_error("Wrong field type for setting field options with field '$k'", E_USER_WARNING);
		}
		$this->fields[$k]->setOptions($array);
		return $this;
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

		$this->fields[$k]->addDependency($expectedValue, $dependentField);

		return $this;
	}

	/**
	 * Sets the autocomplete data URL for an autocomplete field. This falls through
	 * to the jQuery UI autocomplete control, so the URL will have ?term=[input text]
	 * appended to it by default.
	 * :CHAINABLE:
	 */
	public function setAutocompleteURL()
	{
		$a = func_get_args();
		if (sizeof($a) < 2) {
			array_unshift($a, $this->lastAddedField);
		}
		list($k, $url) = $a;

		if (!$this->fields[$k] instanceof FormIOField_Autocomplete) {
			trigger_error("Wrong field type for autocomplete URL with field '$k'", E_USER_WARNING);
		}

		$this->fields[$k]->setAttribute('searchurl', $url);

		return $this;
	}

	/**
	 * Sets the repeated field type for a repeater field. The repeater will allow multiple
	 * entries of this field type, handled via JavaScript and form resubmissions as appropriate.
	 *
	 * :NOTE: although the attribute we set is 'fieldtype', the output variable generated is
	 * 		  named 'inputs'.
	 *
	 * :CHAINABLE:
	 */
	public function setRepeaterType()
	{
		$a = func_get_args();
		if (sizeof($a) < 2) {
			array_unshift($a, $this->lastAddedField);
		}
		list($k, $fieldType) = $a;

		if (!$this->fields[$k] instanceof FormIOField_Repeater) {
			trigger_error("Wrong field type for repeater type with field '$k'", E_USER_WARNING);
		}

		$this->fields[$k]->setRepeaterType($fieldType);

		return $this;
	}

	/**
	 * Sets a checkgroup or radiogroup's columns property. This is used to output a CSS
	 * class which changes the field's layout - valid range is between 1 and 5.
	 * :CHAINABLE:
	 */
	public function setOptionColumns()
	{
		$a = func_get_args();
		if (sizeof($a) < 2) {
			array_unshift($a, $this->lastAddedField);
		}
		list($k, $num) = $a;

		if (!$this->fields[$k] instanceof FormIOField_Multiple) {
			trigger_error("Wrong field type for option columns with field '$k'", E_USER_WARNING);
		}

		$this->fields[$k]->setAttribute('columns', $num);

		return $this;
	}

	// simplified mutators for adding common field attributes. All are chainable.

	public function setHint()
	{
		$a = func_get_args();
		if (sizeof($a) < 2) {
			array_unshift($a, $this->lastAddedField);
		}
		list($k, $hint) = $a;

		$this->fields[$k]->setAttribute('hint', $hint);

		return $this;
	}

	// simplified mutators for adding various non-field types. All are chainable.

	public function addHiddenField($name, $value) {
		return $this->addField($name, null, FormIO::T_HIDDEN, $value);
	}

	public function startFieldset($title, $name = null) {
		if (!$name) {
			$name = '__fs' . $this->autoNameCounter++;
		}
		return $this->addField($name, $title, FormIO::T_INDENT);
	}

	public function endFieldset() {
		return $this->addField('__fs' . $this->autoNameCounter++, null, FormIO::T_OUTDENT);
	}

	public function addParagraph($html) {
		return $this->addField('__p' . $this->autoNameCounter++, $html, FormIO::T_PARAGRAPH);
	}

	public function addHeader($text) {
		return $this->addField('__h' . $this->autoNameCounter++, $text, FormIO::T_HEADER);
	}

	public function addSubHeader($text) {
		return $this->addField('__h' . $this->autoNameCounter++, $text, FormIO::T_SUBHEADER);
	}

	public function addSectionBreak($sectionTitle = null) {
		return $this->addField("tab" . ++$this->tabCounter, $sectionTitle, FormIO::T_SECTIONBREAK);
	}

	public function addImage($url, $altText, $name = null) {
		if (!$name) {
			$name = '__i' . $this->autoNameCounter++;
		}
		return $this->addField($name, $altText, FormIO::T_IMAGE, $url);
	}

	/**
	 * Simplified function to add a repeater field. Function signature is much the same as
	 * addField(), only the field type is the type of the field to be repeated.
	 *
	 * Repeaters should generally be added via this function, as they require their repeated
	 * field type to be set before adding. You can still add them via addField() if you call
	 * $field->setRepeaterType(...) manually first.
	 *
	 * @param	array	$default	an array of default values for this field. At least this many inputs will be drawn.
	 * @param	int		$numInputs	the minimum total number of inputs to draw. Empty ones will be added until there are at least this many visible.
	 *
	 * :CHAINABLE:
	 */
	public function addRepeater($name, $description, $repeatedFieldType = FormIO::T_TEXT, $default = array(), $numInputs = null) {
		// setting this value causes the next added field to be added as a repeater for this field type - only way to work it, currently
		$this->nextRepeaterFieldType = $repeatedFieldType;	// this must be done before adding the field, so that addField() knows what the repeated type is

		$this->addField($name, $description, FormIO::T_REPEATER, $default);
		if (intval($numInputs) > 0) {
			$this->addAttribute('numinputs', intval($numInputs));
		}
		return $this;
	}

	// Incrementing the striper adds a spacer element internally. The last added field is not advanced.
	public function incrementStriper() {
		$lastField = $this->lastAddedField;
		$this->addField('__n' . $this->autoNameCounter++, null, FormIO::T_SPACER);
		$this->lastAddedField = $lastField;
		return $this;
	}

	/**
	 * @param	bool	$defaultAction	The first submit button added to a form becomes the default action of the
	 *									form when the user presses "enter" in a text field - this prevents repeater
	 *									postback buttons becoming the default submission action. If this param is
	 *									true, you can make a later submit button the default enter key handler.
	 */
	public function addSubmitButton($text = 'Submit', $name = null, $defaultAction = false) {
		$name = $name ? $name : '_btn' . $this->autoNameCounter++;
		$this->addField($name, $text, FormIO::T_SUBMIT, $text);

		if ($defaultAction || !isset($this->defaultSubmit)) {
			$this->defaultSubmit = $this->lastAddedField;
		}

		return $this;
	}

	public function addResetButton($text = 'Reset') {
		return $this->addField('_btn' . $this->autoNameCounter++, null, FormIO::T_RESET, $text);
	}

	public function addButton($text, $javascript, $name = null) {
		if (!$name) {
			$name = '__btn' . $this->autoNameCounter++;
		}
		return $this->addField($name, null, FormIO::T_BUTTON, $text)
					->addAttribute($name, 'js', $javascript);
	}

	//==========================================================================
	//	Accessors

	public function getFields()
	{
		return $this->fields;
	}

	public function getField($name)
	{
		return $this->fields[$name];
	}

	public function getLastField()
	{
		return $this->fields[$this->lastAddedField];
	}

	public function getErrors()
	{
		return $this->errors;
	}

	public function getError($k)
	{
		return isset($this->errors[$k]) ? $this->errors[$k] : null;
	}

	public function getDataType($k)
	{
		if (isset($this->fields[$k])) {
			return $this->fields[$k]->getFieldType();
		}
		return null;
	}

	public function getValidators($k)
	{
		return $this->fields[$k]->getValidators();
	}

	public function getAttributes($k)
	{
		return $this->fields[$k]->getAttributes();
	}

	// Determine if a validator is being run for a particular data key
	public function hasValidator($k, $validatorName)
	{
		return $this->fields[$k]->hasValidator($validatorName);
	}

	//==========================================================================
	//	Other mutators

	public function setFormAction($url)
	{
		$this->action = $url;
	}

	public function setMethod($gorp)
	{
		$this->method = $gorp == 'GET' ? $gorp : 'POST';
	}

	public function setMultipart($mult)
	{
		$this->multipart = (bool)$mult;
	}

	public function setStatusMessage($msgHTML)
	{
		$this->statusMessage = strval($msgHTML);
	}

	/**
	 * The header section of a form is the first elements added to it. These will
	 * always display, independently of other tabs and pagination.
	 */
	public function startHeaderSection()
	{
		if (sizeof($this->sections)) {
			trigger_error("FormIO header section must be the first section added to the form", E_USER_ERROR);
		}
		$this->tabCounter = -1;
		$this->addField("tab" . ++$this->tabCounter, null, FormIO::T_SECTIONBREAK);
		$this->addAttribute('hasPrevious', false);
		return $this->addAttribute('classes', 'header');
	}

	/**
	 * To end the header, we simply output a section break to start the first tab.
	 */
	public function endHeaderSection($firstSectionName = null)
	{
		return $this->addSectionBreak($firstSectionName);
	}

	/**
	 * The footer is created by adding a section with the class "footer" - the FormIO
	 * JavaScript will ignore this section.
	 */
	public function startFooterSection()
	{
		$this->addField("tab" . ++$this->tabCounter, null, FormIO::T_SECTIONBREAK);
		return $this->addAttribute('classes', 'footer');
	}

	// Called by SectionBreak field after creation, to add a reference into $this->sections
	public function sectionAdded($sectionField)
	{
		$this->sections[] = $sectionField;
	}

	// Removes a validator from a field if it is found to exist. Returns true if one was erased.
	public function removeValidator($k, $validatorName)
	{
		return $this->fields[$k]->removeValidator($validatorName);
	}

	public function setFieldBuilderString($k, $string)
	{
		$this->fields[$k]->buildString = $string;
	}

	//==========================================================================
	//	Data

	/**
	 * Retrieves all the form's data, as an array. Non-input field types are filtered from the output.
	 * You may choose to also retrieve submit button values by passing true to the function.
	 *
	 * @param	mixed	$param		- if boolean, return all the form's data with submit button values if true
	 * 								- if string, return the specific data element converted to externally useful format
	 */
	public function getData($param = false)
	{
		list($includeSubmit, $fieldName) = $this->interpretDataParam($param);
		return $this->walkData(array('$field', 'getName'), array(), array('$field', 'getValue'), array(), $includeSubmit, $fieldName);
	}

	/**
	 * returns a data array of the internal field values as they are handled by FormIO
	 * @see getData()
	 */
	public function getRawData($param = false)
	{
		list($includeSubmit, $fieldName) = $this->interpretDataParam($param);
		return $this->walkData(array('$field', 'getName'), array(), array('$field', 'getRawValue'), array(), $includeSubmit, $fieldName);
	}

	/**
	 * returns a data structure representing the form's input as JSON
	 * @see getData()
	 */
	public function getJSON($includeSubmit = false)
	{
		//JSONParser::encode
		return json_encode($this->getData($includeSubmit));
	}

	/**
	 * returns a data structure representing the form's input as an HTTP query string
	 * @see getData()
	 */
	public function getQueryString($includeSubmit = false)
	{
		return http_build_query($this->getData($includeSubmit));
	}
	/**
	 * Simimlar to getData(), only the form's information is displayed in a format
	 * suitable to the user. Use this to build your own tables, layouts etc by
	 * iterating the returned array as $optionDescription => $convertedValue
	 *
	 * This function calls FormIOField_Text::getHumanReadableValue() internally.
	 *
	 * @param	mixed	$param		- if boolean, return all the form's data with submit button values if true
	 * 								- if string, return the specific data element converted to externally useful format
	 */
	public function getHumanReadableData($param = false)
	{
		list($includeSubmit, $fieldName) = $this->interpretDataParam($param);
		return $this->walkData(array('$field', 'getHumanReadableName'), array(), array('$field', 'getHumanReadableValue'), array(), $includeSubmit, $fieldName);
	}

	/**
	 * Walk over the field data with your own callbacks to generate the keys and values of the array returned
	 *
	 * The callbacks passed to this function may have the object set to the special string '$field', which will
	 * cause the methods to be called on the field objects in the loop themselves.
	 * If the object is anything else (or absent), the field object is passed to the callback as the first parameter.
	 *
	 * This function ignores fields which are:
	 *	- presentational	(do not extend from FormIOField_Text)
	 *	- hidden by another field's dependency
	 *	- configured to be excluded from all form data (@see FormIOField::excludeFromData)
	 *	- submit actions, if $includeSubmit = false (extend from FormIOField_Submit)
	 *
	 * @param	callback	$keyMethod		callback func to call on each field to generate array keys
	 * @param	array		$keyArgs		arguments to pass to the key method
	 * @param	callback	$valueMethod	callback func to call on each field to generate array values
	 * @param	array		$valueArgs		arguments to pass to the value method
	 * @param	bool		$includeSubmit	whether or not to return submit button data in the results
	 * @param	string		$fieldName		if given, the returned array only contains this field's data
	 *
	 * @return	array		associative array of field-derived data
	 */
	public function walkData($keyMethod, $keyArgs, $valueMethod, $valueArgs, $includeSubmit = false, $fieldName = null)
	{
		$data = array();

		$fields = $this->fields;
		if (is_string($fieldName)) {
			$fields = array($fieldName => $this->fields[$fieldName]);
		}

		// add a empty arguments to the start of the args array so we can easily assign the loop's field
		if ($keyMethod[0] != '$field') {
			array_unshift($keyArgs, null);
		}
		if ($valueMethod[0] != '$field') {
			array_unshift($valueArgs, null);
		}

		foreach ($fields as $name => $field) {
			if (!$field->isPresentational() && !$field->hiddenByDependency() && !$field->excludeFromData && ($includeSubmit || !$field instanceof FormIOField_Submit)) {
				if (is_array($keyMethod) && $keyMethod[0] == '$field') {
					$myKeyMethod = array($field, $keyMethod[1]);
				} else {
					$myKeyMethod = $keyMethod;
					$keyArgs[0] = $field;
				}
				if (is_array($valueMethod) && $valueMethod[0] == '$field') {
					$myValueMethod = array($field, $valueMethod[1]);
				} else {
					$myValueMethod = $valueMethod;
					$valueArgs[0] = $field;
				}

				$key = call_user_func_array($myKeyMethod, $keyArgs);
				$value = call_user_func_array($myValueMethod, $valueArgs);

				if (!isset($data[$key]) || $value) {
					$data[$key] = $value;
				}
			}
		}

		return $data;
	}

	/**
	 * @return	an array of (whether to include submit button values => field name to retrieve)
	 */
	private function interpretDataParam($param)
	{
		$fieldName = null;
		$includeSubmit = false;
		if (is_bool($param)) {
			$includeSubmit = $param;
		} else {
			$fieldName = $param;
		}
		return array($includeSubmit, $fieldName);
	}

	// retrieve an array mapping the names of all fields in the form to instances of
	// inputs with those field names - multiple fields may share the same input name
	private function getInputFieldNames()
	{
		$names = array();
		foreach ($this->fields as $key => $field) {
			$name = $field->getName();
			if (!$field->isPresentational()) {
				if (isset($names[$name])) {
					$names[$name][] = $key;
				}
				$names[$name] = array($key);
			}
		}
		return $names;
	}

	//==========================================================================
	//	Rendering

	public function getForm()
	{
		// build error string, if present
		$errorStr = !$this->delaySubmission && sizeof($this->errors) > 0
						? "<p class=\"errSummary\">Please review your submission: " . sizeof($this->errors) . " fields have errors.</p>\n"
						: '';
		// add any form status messages that have been set
		if ($this->statusMessage) {
			$errorStr = '<p class="status">' . $this->statusMessage . '</p>' . $errorStr;
		}

		// ensure that the default submit button is echoed out first (as a duplicate) so that pressing enter
		// submits the form properly (and doesnt just send the first repeater's submit button, preventing ACTUAL submission)
		if ($this->defaultSubmit) {
			$unusedSpin = 0;
			$hiddenSubmit = clone $this->fields[$this->defaultSubmit];
			$hiddenSubmit->setAttribute('styles', 'display: none;');
			$form = $hiddenSubmit->getHTML($unusedSpin);
		} else {
			$form = '';
		}

		$form .= $this->getFieldsHTML($errorStr);

		if (sizeof($this->sections)) {
			$form .= "</div>";
		}

		return $this->getFormTag() . "\n" . $form . "</form>\n";
	}

	// generates and returns the opening <form> tag for this form
	public function getFormTag()
	{
		return "<form id=\"$this->name\" class=\"clean\" method=\"" . strtolower($this->method)
				. "\" action=\"$this->action\"" . ($this->multipart ? ' enctype="multipart/form-data"' : '')
				. " data-fio-stripe=\"" . http_build_query($this->getRowStriperIncrements()) . "\""
				. '>';
	}

	public function getFieldsHTML($statusMessage)
	{
		$hasHeader = isset($this->sections[0]) && $this->sections[0]->getAttribute('classes') == 'header';
		$foundFirstSection = false;
		$spin = 1;

		$string = $hasHeader ? '' : $statusMessage . $this->getFormTabNav();

		foreach ($this->fields as $k => $field) {
			if ($hasHeader && !$foundFirstSection && $field instanceof FormIOField_Sectionbreak && $field != $this->sections[0]) {
				$string .= $statusMessage . $this->getFormTabNav();
				$foundFirstSection = true;
			}
			$string .= $field->getHTML($spin) . "\n";
		}

		// form has no sections except a header, so treat it as if there's just 1 section
		if ($hasHeader && !$foundFirstSection) {
			$string = $statusMessage . $this->getFormTabNav() . $string;
		}

		return $string;
	}

	private function getFormTabNav()
	{
		$numSections = 0;	// this counts the number of REAL sections (ignores header & footer sections)

		$str = "<ul class=\"formnav\">\n";
		foreach ($this->sections as $section) {
			$class = $section->getAttribute('classes');
			if ($class == 'footer' || $class == 'header') {
				continue;
			}
			++$numSections;
			$str .= "<li><a href=\"#{$section->getFieldId()}\">{$section->getAttribute('desc')}</a></li>\n";
		}

		if ($numSections < 2) {
			return '';
		}

		return $str . "</ul>";
	}

	// :TODO: won't work if you insert a spacer as the first field of a form, but that doesn't make sense anyway
	private function getRowStriperIncrements()
	{
		$spacers = array();
		$lastField = null;
		foreach ($this->fields as $k => $field) {
			if ($field instanceof FormIOField_Spacer) {
				if (!isset($spacers[$lastField])) {
					$spacers[$lastField] = 0;
				}
				$spacers[$lastField]++;
			} else {
				$lastField = $k;
			}
		}
		return $spacers;
	}

	//==========================================================================
	//	Validation

	/**
	 * Validate our data and return a boolean as to whether everything is ok
	 * Specific error messages can be retrieved via getError() / getErrors()
	 *
	 * @see FormIO::$dataValidators
	 */
	public function validate()
	{
		$this->errors = array();
		$allValid = true;

		foreach ($this->fields as $fieldName => $field) {
			if (!$field->isPresentational() && !$field->validate()) {
				$allValid = false;
			}
		}

		return $allValid ? !(sizeof($this->errors) > 0) : false;
	}

	//==========================================================================
	//	Array implementation to access form variables directly

	public function offsetSet($offset, $value)
	{
		if (is_null($offset)) {
			trigger_error("Attempted to set form variable, but no key given", E_USER_ERROR);
		} else {
			$this->fields[$offset]->setValue($value);
		}
	}
	public function offsetExists($offset)
	{
		return isset($this->fields[$offset]);
	}
	public function offsetUnset($offset)
	{
		unset($this->fields[$offset]);
	}
	public function offsetGet($offset)
	{
		return isset($this->fields[$offset]) ? $this->fields[$offset]->getValue() : null;
	}
}

?>
