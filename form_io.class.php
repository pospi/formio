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
	const T_TIMERANGE = 42;			// two datetime fields
	const T_BIGTEXT	= 19;			// textarea
	const T_HIDDEN	= 20;			// input[type=hidden]
	const T_READONLY = 21;			// a bit like a hidden input, only we show the variable
	const T_DROPDOWN = 22;			// select
	const T_DROPOPTION = 34;		// single <select> option element. Not useful by itself - used by T_DROPDOWN
	const T_CHECKBOX = 23;			// single checkbox
	const T_RADIO	= 33;			// single radio button. Not useful by itself - used by T_RADIOGROUP
	const T_RADIOGROUP = 24;		// list of radio buttons
	const T_CHECKGROUP = 25;		// list of checkboxes
	const T_CHECKOPTION = 43;		// used by T_CHECKGROUP
	const T_SURVEY	= 26;			// :TODO:
	const T_PASSWORD = 27;
	const T_BUTTON	= 28;
	const T_SUBMIT	= 29;
	const T_RESET	= 30;
	const T_CAPTCHA	= 35;			// reCAPTCHA plugin
	const T_CAPTCHA2 = 37;			// SecurImage plugin. DO NOT use this as the field type, instead use T_CAPTCHA and set FormIO::$captchaType accordingly
	const T_AUTOCOMPLETE = 36;		// a dropdown which polls a URL for possible values and can be freely entered into. If you wish to restrict to a range of values, check this yourself and use addError()
	const T_FILE = 38;
	const T_SPACER = 39;			// does nothing. Use this to increment the row striper, etc
	const T_PASSWORDCHANGE = 40;	// outputs two password inputs which must match to validate. :TODO: also does complexity check ?
	const T_REPEATER = 41;		// give an input type via set

	// form builder strings for different element types :TODO: finish implementation
	private static $builder = array(
		FormIO::T_SUBMIT	=> '<input type="submit" name="{$name}" id="{$id}"{$value? value="$value"}{$classes? class="$classes"} />',
		FormIO::T_RESET		=> '<input type="reset" name="{$name}" id="{$id}"{$value? value="$value"}{$classes? class="$classes"} />',
		FormIO::T_BUTTON	=> '<div class="row{$alt? alt}{$classes? $classes}"><label>{$desc}</label><input type="button" name="{$name}" id="{$id}"{$value? value="$value"} /><p class="hint">{$hint}</p></div>',
		FormIO::T_INDENT	=> '<fieldset><legend>{$desc}</legend>',
		FormIO::T_OUTDENT	=> '</fieldset>',
		FormIO::T_RAW		=> '{$desc}',
		FormIO::T_PARAGRAPH	=> '<p id="{$id}">{$desc}</p>',
		FormIO::T_HEADER	=> '<h2 id="{$id}">{$desc}</h2>',
		FormIO::T_SUBHEADER	=> '<h3 id="{$id}">{$desc}</h3>',
		FormIO::T_SECTIONBREAK => '</div><div class="tab" id="{$id}">',
		FormIO::T_IMAGE		=> '<img id="{$id}" src="{$value}" alt="{$desc}" />',

		FormIO::T_READONLY	=> '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$id}">{$desc}</label><div class="readonly">{$value}</div><input type="hidden" name="{$name}" id="{$id}"{$escapedvalue? value="$escapedvalue"} />{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',

		FormIO::T_FILE		=> '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label><input type="file" name="{$name}" id="{$id}" />{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		FormIO::T_PASSWORD	=> '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label><input type="password" name="{$name}" id="{$id}" />{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		FormIO::T_BIGTEXT	=> '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label><textarea name="{$name}" id="{$id}"{$maxlen? maxlength="$maxlen"}{$dependencies? data-fio-depends="$dependencies"}>{$value}</textarea>{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		FormIO::T_HIDDEN	=> '<input type="hidden" name="{$name}" id="{$id}"{$value? value="$value"} />',
		FormIO::T_CHECKBOX	=> '<div class="row checkbox{$alt? alt}{$classes? $classes}"><label>&nbsp;{$required? <span class="required">*</span>}</label><label class="checkbox"><input type="checkbox" name="{$name}" id="{$id}"{$value? value="$value"}{$disabled? disabled="disabled"}{$checked? checked="checked"}{$dependencies? data-fio-depends="$dependencies"} />{$desc}</label>{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		FormIO::T_CURRENCY	=> '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label><span class="currency"><span>$</span><input type="text" name="{$name}" id="{$id}"{$value? value="$value"}{$maxlen? maxlength="$maxlen"}{$behaviour? data-fio-type="$behaviour"}{$validation? data-fio-validation="$validation"}{$dependencies? data-fio-depends="$dependencies"} /></span>{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',

		FormIO::T_PASSWORDCHANGE => '<div class="row blck{$alt? alt}{$classes? $classes}" id="{$id}"><label for="{$id}_0">{$desc}{$required? <span class="required">*</span>}</label><div class="row"><input type="password" name="{$name}[0]" id="{$id}_0" /><input type="password" name="{$name}[1]" id="{$id}_1" /> (verify)</div>{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		FormIO::T_DATETIME	=> '<div class="row datetime{$alt? alt}{$classes? $classes}" id="{$id}"{$dependencies? data-fio-depends="$dependencies"}><label for="{$id}_time">{$desc}{$required? <span class="required">*</span>}</label><input type="text" name="{$name}[0]" id="{$id}_date"{$value? value="$value"} data-fio-type="date" class="date" /> at <input type="text" name="{$name}[1]" id="{$id}_time" value="{$valueTime}" data-fio-type="time" class="time" /><select name="{$name}[2]" id="{$id}_meridian">{$am?<option value="am" selected="selected">am</option><option value="pm">pm</option>}{$pm?<option value="am">am</option><option value="pm" selected="selected">pm</option>}</select>{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		FormIO::T_DATERANGE	=> '<div class="row daterange{$alt? alt}{$classes? $classes}" id="{$id}"{$dependencies? data-fio-depends="$dependencies"}><label for="{$id}_start">{$desc}{$required? <span class="required">*</span>}</label><input type="text" name="{$name}[0]" id="{$id}_start"{$value? value="$value"} data-fio-type="date" /> - <input type="text" name="{$name}[1]" id="{$id}_end" value="{$valueEnd}" data-fio-type="date" />{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		FormIO::T_TIMERANGE	=> '<div class="row daterange datetime{$alt? alt}{$classes? $classes}" id="{$id}"{$dependencies? data-fio-depends="$dependencies"}><label for="{$id}_start">{$desc}{$required? <span class="required">*</span>}</label>
									<span style="white-space: nowrap;"><input type="text" name="{$name}[0][0]" id="{$id}_0_date"{$startdate? value="$startdate"} data-fio-type="date" class="date" /> at <input type="text" name="{$name}[0][1]" id="{$id}_0_time"{$starttime? value="$starttime"} data-fio-type="time" class="time" /><select name="{$name}[0][2]" id="{$id}_0_meridian">{$startam?<option value="am" selected="selected">am</option><option value="pm">pm</option>}{$startpm?<option value="am">am</option><option value="pm" selected="selected">pm</option>}</select></span> -
									<span style="white-space: nowrap;"><input type="text" name="{$name}[1][0]" id="{$id}_1_date"{$enddate? value="$enddate"} data-fio-type="date" class="date" /> at <input type="text" name="{$name}[1][1]" id="{$id}_1_time"{$endtime? value="$endtime"} data-fio-type="time" class="time" /><select name="{$name}[1][2]" id="{$id}_1_meridian">{$endam?<option value="am" selected="selected">am</option><option value="pm">pm</option>}{$endpm?<option value="am">am</option><option value="pm" selected="selected">pm</option>}</select></span>
								{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		FormIO::T_REPEATER	=> '<div class="row blck{$alt? alt}{$classes? $classes}" id="{$id}"{$dependencies? data-fio-depends="$dependencies"} data-fio-type="repeater"><label for="{$id}_0">{$desc}{$required? <span class="required">*</span>}</label>{$inputs}<input type="hidden"{$isfiles? name="$isfiles[isfiles]"} value="1" /><div class="pad"></div>{$controls}{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',

		FormIO::T_DROPDOWN	=> '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label><select id="{$id}" name="{$name}"{$dependencies? data-fio-depends="$dependencies"}>{$options}</select>{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		FormIO::T_DROPOPTION=> '<option{$value? value="$value"}{$disabled? disabled="disabled"}{$checked? selected="selected"}>{$desc}</option>',

		// T_RADIOGROUP is used for both radiogroup and checkgroup at present
		FormIO::T_RADIOGROUP=> '<fieldset id="{$id}" class="row multiple col{$columns}{$alt? alt}"{$dependencies? data-fio-depends="$dependencies"}><legend>{$desc}{$required? <span class="required">*</span>}</legend>{$options}{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></fieldset>',
		FormIO::T_RADIO		=> '<label><input type="radio" name="{$name}"{$value? value="$value"}{$disabled? disabled="disabled"}{$checked? checked="checked"} /> {$desc}</label>',
		FormIO::T_CHECKOPTION=> '<label><input type="checkbox" name="{$name}"{$value? value="$value"}{$disabled? disabled="disabled"}{$checked? checked="checked"} /> {$desc}</label>',

		FormIO::T_AUTOCOMPLETE=> '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label><input type="text" name="{$name}" id="{$id}"{$value? value="$value"}{$maxlen? maxlength="$maxlen"}{$behaviour? data-fio-type="$behaviour"}{$validation? data-fio-validation="$validation"} data-fio-searchurl="{$searchurl}"{$dependencies? data-fio-depends="$dependencies"} />{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		FormIO::T_CAPTCHA	=> '<div class="row blck{$alt? alt}{$classes? $classes}"><label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label><div class="row" id="{$id}">{$captcha}</div>{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
		FormIO::T_CAPTCHA2	=> '<div class="row blck{$alt? alt}{$classes? $classes}" data-fio-type="securimage"><label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label><div class="row"><input type="text" name="{$name}" id="{$id}" {$maxlen? maxlength="$maxlen"} /><img src="{$captchaImage}" alt="CAPTCHA Image" class="captcha" /> <a class="reload" href="javascript: void(0);">Reload image</a></div>{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',

		// this is our fallback input string as well. js is added via use of data-fio-* attributes.
		FormIO::T_TEXT		=> '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label><input type="text" name="{$name}" id="{$id}"{$value? value="$value"}{$maxlen? maxlength="$maxlen"}{$behaviour? data-fio-type="$behaviour"}{$validation? data-fio-validation="$validation"}{$dependencies? data-fio-depends="$dependencies"} />{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>',
	);

	// This contains an array of all field types which are presentational only.
	// Used by FormIO::getData() to filter the returned array
	private static $presentational = array(
		FormIO::T_RAW, FormIO::T_HEADER, FormIO::T_SUBHEADER, FormIO::T_PARAGRAPH, FormIO::T_SECTIONBREAK,
		FormIO::T_IMAGE, FormIO::T_INDENT, FormIO::T_OUTDENT, FormIO::T_BUTTON, FormIO::T_RESET, FormIO::T_CAPTCHA, FormIO::T_CAPTCHA2
	);

	//===============================================================================================\/
	//	Stuff you might want to change

	// default error messages for builtin validator methods
	private static $defaultErrors = array(
		'requiredValidator'	=> "This field is required",
		'arrayRequiredValidator' => "All elements are required",
		'equalValidator'	=> "You must select \$2",
		'notEqualValidator'	=> "Must not be equal to \$2",
		'minLengthValidator'=> "Must be at least \$2 characters",
		'maxLengthValidator'=> "Must not be longer than \$2 characters",
		'inArrayValidator'	=> "Must be one of \$2",
		'regexValidator'	=> "Incorrect format",
		'dateValidator'		=> "Requires a valid date in dd/mm/yyyy format",
		'dateTimeValidator'	=> "Requires a valid date (dd/mm/yyyy), time (hh:mm) and time of day",
		'dateRangeValidator'=> "Dates must be in dd/mm/yyyy format",
		'timeRangeValidator'=> "Invalid date (dd/mm/yyyy) or time (hh:mm)",
		'emailValidator'	=> "Invalid email address",
		'phoneValidator'	=> "Invalid phone number. Phone numbers must contain numbers, spaces, dashes and brackets only, and may start with a plus sign.",
		'urlValidator'		=> "Invalid URL",
		'currencyValidator'	=> "Enter amount in dollars and cents",
		'captchaValidator'	=> "The text entered did not match the verification image",
		'chpasswdValidator'	=> "Entered passwords do not match",
		'fileUploadValidator'					=> "File upload failed",
		'fileInvalid1'/*UPLOAD_ERR_INI_SIZE*/	=> "File too big (exceeded system size)",
		'fileInvalid2'/*UPLOAD_ERR_FORM_SIZE*/	=> "File too big (exceeded form size)",
		'fileInvalid3'/*UPLOAD_ERR_PARTIAL*/	=> "File upload interrupted",
		'fileInvalid6'/*UPLOAD_ERR_NO_TMP_DIR*/	=> "Could not save file",
		'fileInvalid7'/*UPLOAD_ERR_CANT_WRITE*/	=> "Could not write file",
		'fileInvalid8'/*UPLOAD_ERR_EXTENSION*/	=> "Upload prevented by server extension",
	);

	// misc constants used for validation
	const dateRegex		= '/^\s*(\d{1,2})\/(\d{1,2})\/(\d{2}|\d{4})\s*$/';						// capture: day, month, year
	const timeRegex		= '/^\s*(\d{1,2})(:(\d{2}))?(:(\d{2}))?\s*$/';							// capture: hr, , min, , sec
	const emailRegex	= '/^[-!#$%&\'*+\\.\/0-9=?A-Z^_`{|}~]+@([-0-9A-Z]+\.)+([0-9A-Z]){2,4}$/i';
	const phoneRegex	= '/^(\+)?(\d|\s|-|(\(\d+\)))*$/';
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

	public static $default_multiinput_columns = 2;				// default column count for radiogroup and checkgroup inputs

	//===============================================================================================/\

	private $lastBuilderReplacement;		// form builder hack for preg_replace_callback not being able to accept extra parameters
	private $lastAddedField;				// name of the last added field is stored here, for field attribute method chaining
	private $autoNameCounter;				// used for presentational field types where the field name doesn't matter and we don't want to have to specify it
	private $delaySubmission = false;		// this is used by fields which post back to the form in fallback mode to prevent validation
	private $tabCounter;					// used by T_SECTIONBREAK to set field IDs for JavaScript

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
	private $customValidatorErrors = array();	// any custom validation functions can have an error message set per-function, which go in here
	private $dataTypes = array();			// indicates data type for each field
	private $dataDepends = array();			// defines which fields are dependent on the values of others
	private $dataOptions = array();			// input options for checkbox, radio, dropdown etc types
	private $dataAttributes = array();		// any extra attributes to add to HTML output - maxlen, classes, desc

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
		$this->action = $action === null ? $_SERVER['PHP_SELF'] : $action;
		$this->method = $method == "GET" ? "GET" : "POST";
		$this->multipart = $multipart;
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
	 * @param	bool	$allowAdditions	if true, we can set values that weren't initially declared on the form
	 */
	public function importData($assoc, $allowAdditions = false, $isPost = false, $isRepeatedFile = false)
	{
		if (!is_array($assoc) && !is_string($assoc)) {
			return false;
		}

		// file inputs cannot be sent as arrays, and so repeated file inputs must be treated differently.
		// hacky but best that can be done.
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

		if (!$allowAdditions) {
			foreach ($assoc as $k => $val) {
				if (!array_key_exists($k, $this->data)) {
					unset($assoc[$k]);
				}

				// if we are POSTing, and the input variable is a repeated file input, act accordingly.
				if ($isPost
				  && $this->dataTypes[$k] == FormIO::T_REPEATER
				  && $this->dataAttributes[$k]['fieldtype'] == FormIO::T_FILE
				  && isset($val['isfiles'])) {
					$assoc[$k] = $this->importData($k, $allowAdditions, false, true);
				}
			}
		}
		if (empty($assoc)) {
			return false;
		}
		$this->data = array_merge($this->data, $assoc);

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
		$g = $this->importData($_GET);
		$p = $this->importData($_POST, false, true);
		return ($g || $p);
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
		if (isset($this->dataAttributes[$name])) {
			$this->dataAttributes[$name] = array_merge($this->dataAttributes[$name], array('desc' => $displayText));
		} else {
			$this->dataAttributes[$name] = array('desc' => $displayText);
		}
		if ($type == FormIO::T_DROPDOWN || $type == FormIO::T_RADIOGROUP || $type == FormIO::T_CHECKGROUP || $type == FormIO::T_SURVEY) {
			$this->dataOptions[$name] = array();
		}

		// if this is a repeater, we should handle its data in the correct subfield form. Must have repeater type set first.
		$subtype = null;
		if ($type == FormIO::T_REPEATER) {
			$subtype = $this->dataAttributes[$name]['fieldtype'];
			$values = &$this->data[$name];
		} else {
			$values = array(0 => &$this->data[$name]);
		}
		$this->setDataType($name, $type, $subtype);
		if ($subtype) {
			// override the rest of the processing to use the subfield's type in the same way that
			// we dereference the field's values
			$type = $subtype;
		}

		// now we manipulate / convert any values in the input array by modifying the references
		if (is_array($values)) {
			foreach ($values as &$value) {
				// convert timestamp values passed in for date-related fields
				if ( ($type == FormIO::T_DATE || $type == FormIO::T_DATETIME)
				  && (is_int($value) || (is_string($value) && !preg_match('/[^\d]/', $value))) ) {
					$value = $type == FormIO::T_DATE ? FormIO::timestampToDate($value) : FormIO::timestampToDateTime($value);
				}
				if ( ($type == FormIO::T_DATERANGE || $type == FormIO::T_TIMERANGE)
				  && (is_array($value)
					 && (is_int($value[0]) || (is_string($value[0]) && !preg_match('/[^\d]/', $value[0])))
					 && (is_int($value[1]) || (is_string($value[1]) && !preg_match('/[^\d]/', $value[1])))
				  ) ) {
					$value = $type == FormIO::T_TIMERANGE
								? array(FormIO::timestampToDateTime($value[0]), FormIO::timestampToDateTime($value[1]))
								: array(FormIO::timestampToDate($value[0]), FormIO::timestampToDate($value[1]));
				}
			}
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
				case FormIO::T_TIMERANGE:
				case FormIO::T_DATETIME:
				case FormIO::T_REPEATER:
					$this->addValidator($fieldName, 'arrayRequiredValidator', array(), false);
					break;
				case FormIO::T_CAPTCHA:
				case FormIO::T_CAPTCHA2:
					break;		// :NOTE: captcha's dont need to be required as they implicitly are already
				default:
					$this->addValidator($fieldName, 'requiredValidator', array(), false);
					break;
			}
		}
		return $this;
	}

	/**
	 * Adds a validator to a field.
	 * When run, validators are passed the following parameters before any extra validation params:
	 *	Internal (class method) validators -
	 *		data key, ...
	 *	External validators -
	 *		form object, data key, ...
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
		$this->removeValidator($k, $validatorName);		// remove it if it exists, so we can use the most recently applied parameters

		if (!isset($this->dataValidators[$k])) {
			$this->dataValidators[$k] = array();
		}
		if ($errorMsg) {
			$this->customValidatorErrors[$validatorName] == $errorMsg;
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
	public function validateWith($validatorName, $params = array(), $errorMsg = null, $customFunc = true)
	{
		return $this->addValidator($this->lastAddedField, $validatorName, $params, $customFunc, $errorMsg);
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
	 * @param	mixed	$dependentField @see FormIO::addFieldDependency()
	 *
	 * :CHAINABLE:
	 */
	public function addFieldOption($k, $optionVal, $optionText, $dependentField = null)
	{
		$this->dataOptions[$k][$optionVal] = $optionText;
		if ($dependentField !== null) {
			if (($optionVal === true || $optionVal === false) && $this->dataTypes[$k] == FormIO::T_CHECKBOX) {
				$optionVal = 1;		// allow passing true/false for checkbox field status'
			}
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

		return $this->addAttribute($k, 'searchurl', $url);
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

		return $this->addAttribute($k, 'fieldtype', $fieldType);
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

		return $this->addAttribute($k, 'columns', $num);
	}

	// simplified mutators for adding common field attributes. All are chainable.

	public function setHint()
	{
		$a = func_get_args();
		if (sizeof($a) < 2) {
			array_unshift($a, $this->lastAddedField);
		}
		list($k, $hint) = $a;

		return $this->addAttribute($k, 'hint', $hint);
	}

	// simplified mutators for adding various non-field types. All are chainable.

	public function addHiddenField($name, $value) {
		return $this->addField($name, '', FormIO::T_HIDDEN, $value);
	}

	public function startFieldset($title) {
		return $this->addField('__fs' . $this->autoNameCounter++, $title, FormIO::T_INDENT);
	}

	public function endFieldset() {
		return $this->addField('__fs' . $this->autoNameCounter++, '', FormIO::T_OUTDENT);
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

	public function addSectionBreak() {
		return $this->addField('__s' . $this->autoNameCounter++, '', FormIO::T_SECTIONBREAK);
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
	 * setRepeaterType() manually first.
	 *
	 * @param	array	$default	an array of default values for this field. At least this many inputs will be drawn.
	 * @param	int		$numInputs	the minimum total number of inputs to draw. Empty ones will be added until there are at least this many visible.
	 *
	 * :CHAINABLE:
	 */
	public function addRepeater($name, $description, $repeatedFieldType = FormIO::T_TEXT, $default = array(), $numInputs = null) {
		$this->setRepeaterType($name, $repeatedFieldType);	// this must be done before adding the field, so that addField() knows what the repeated type is
		$win = $this->addField($name, $description, FormIO::T_REPEATER, $default);
		if ($win) {
			if (intval($numInputs) > 0) {
				$this->addAttribute($name, 'numinputs', intval($numInputs));
			}
		}
		return $win;
	}

	// Incrementing the striper adds a spacer element internally. The last added field is not advanced.
	public function incrementStriper() {
		$lastField = $this->lastAddedField;
		$this->addField('__n' . $this->autoNameCounter++, '', FormIO::T_SPACER);
		$this->lastAddedField = $lastField;
		return $this;
	}

	/**
	 * Note that this method doesn't allow you to choose a submit name, since it is most often not important.
	 * If you wish to do this, call addField() directly.
	 */
	public function addSubmitButton($text = 'Submit') {
		return $this->addField('btn' . $this->autoNameCounter++, '', FormIO::T_SUBMIT, $text);
	}

	public function addResetButton($text = 'Reset') {
		return $this->addField('__btn' . $this->autoNameCounter++, '', FormIO::T_RESET, $text);
	}

	public function addButton($text, $javascript, $name = null) {
		if (!$name) {
			$name = '__btn' . $this->autoNameCounter++;
		}
		return $this->addField($name, '', FormIO::T_BUTTON, $text)
					->addAttribute($name, 'js', $javascript);
	}

	//==========================================================================
	//	Accessors

	/**
	 * Retrieves all the form's data, as an array. Non-input field types are filtered from the output.
	 * You may choose to also retrieve submit button values by passing true to the function.
	 *
	 * @param	mixed	$param		- if boolean, return all the form's data with submit button values if true
	 * 								- if string, return the specific data element converted to externally useful format
	 */
	public function getData($param = false)
	{
		$includeSubmit = false;
		$flatten = false;
		if (is_bool($param)) {
			$data = $this->data;
			$includeSubmit = $param;
		} else {
			$data = array($param => $this->data[$param]);
			$flatten = true;
		}
		$data = $this->standardiseData($data, null, $includeSubmit);
		return $flatten ? $data[$param] : $data;
	}

	/**
	 * Transforms data from internal formats most useful for FormIO into standardised
	 * formats.. pretty much just date type fields to timestamps, let's be honest.
	 */
	private function standardiseData($data, $overrideDataType = null, $includeSubmit = false)
	{
		if (is_array($data)) {
			foreach ($data as $k => $v) {
				$dataType = !$overrideDataType ? $this->dataTypes[$k] : $overrideDataType;

				if (in_array($dataType, FormIO::$presentational) || (!$includeSubmit && $dataType == FormIO::T_SUBMIT)) {
					unset($data[$k]);
				} else if ($dataType == FormIO::T_DATE) {
					$data[$k] = FormIO::dateToUnix($v);
				} else if ($dataType == FormIO::T_DATETIME) {
					$data[$k] = FormIO::dateTimeToUnix($v);
				} else if ($dataType == FormIO::T_DATERANGE) {
					$data[$k] = array(FormIO::dateToUnix($v[0]), FormIO::dateToUnix($v[1]));
				} else if ($dataType == FormIO::T_TIMERANGE) {
					$data[$k] = array(FormIO::dateTimeToUnix($v[0]), FormIO::dateTimeToUnix($v[1]));
				} else if ($dataType == FormIO::T_REPEATER) {
					$data[$k] = $this->standardiseData($data[$k], $this->dataAttributes[$k]['fieldtype'], $includeSubmit);
				}
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

	public function getError($k)
	{
		return $this->errors[$k];
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

	/**
	 * The header section of a form is the first elements added to it. These will
	 * always display, independently of other tabs and pagination.
	 */
	public function startHeaderSection()
	{
		$this->tabCounter = 0;
		return $this;
	}

	/**
	 * To end the header, we simply output a section break to start the first tab.
	 */
	public function endHeaderSection()
	{
		return $this->addSectionBreak();
	}

	// Subtype is used by the repeater field to recurse its checks
	public function setDataType($k, $type, $subtype = null)
	{
		$this->dataTypes[$k] = $type;

		// Add any internal validation routines that apply to this field type
		$validators = $this->getDefaultFieldValidators($type);
		foreach ($validators as $validatorName => $params) {
			$this->addValidator($k, $validatorName, $params, false);
		}

		// Do type-specific things
		$this->handleDataType($type, $subtype);
	}

	/**
	 * Handles any particular requirements of adding a certain type of
	 * field to the form
	 */
	private function handleDataType($type, $subtype = null)
	{
		switch ($type) {
			case FormIO::T_FILE:
				$this->multipart = true;
				break;
			case FormIO::T_CAPTCHA:
				if ($this->captchaType == 'recaptcha') {
					$this->method = 'POST';						// force using POST submission for reCAPTCHA
				}
				break;
			case FormIO::T_REPEATER:
				$this->handleDataType($subtype);
				break;
		}
	}

	/**
	 * Used by both setDataType() and repeater validation to determine which validators to add / run
	 */
	private function getDefaultFieldValidators($fieldType)
	{
		switch ($fieldType) {
			case FormIO::T_EMAIL:
				return array('emailValidator' => array());
			case FormIO::T_PHONE:
				return array('phoneValidator' => array());
			case FormIO::T_CURRENCY:
				return array('currencyValidator' => array());
			case FormIO::T_URL:
				return array('urlValidator' => array());
			case FormIO::T_DATE:
				return array('dateValidator' => array());
			case FormIO::T_DATERANGE:
				return array('dateRangeValidator' => array());
			case FormIO::T_DATETIME:
				return array('dateTimeValidator' => array());
			case FormIO::T_TIMERANGE:
				return array('timeRangeValidator' => array());
			case FormIO::T_FILE:
				return array('fileUploadValidator' => array());
			case FormIO::T_CAPTCHA:
				return array('captchaValidator' => array());
			case FormIO::T_CAPTCHA2:
				return array('captchaValidator' => array());
			case FormIO::T_REPEATER:
				return array('repeaterValidator' => array());
			case FormIO::T_PASSWORDCHANGE:
				return array('chpasswdValidator' => array());
		}
		return array();
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

	public function setFieldBuilderString($fieldType, $string)
	{
		FormIO::$builder[$fieldType] = $string;
	}

	//==========================================================================
	//	Rendering

	public function getJSON($includeSubmit = false)
	{
		//JSONParser::encode
		return json_encode($this->getData($includeSubmit));
	}

	public function getQueryString($includeSubmit = false)
	{
		return http_build_query($this->getData($includeSubmit));
	}

	public function getForm()
	{
		$form = '';

		$firstSection = '';
		$hasHeader = isset($this->tabCounter);
		if (!$hasHeader) {
			// default behaviour is to skip the first tab (0), which is used as the form's header section if present
			$this->tabCounter = 1;
			$form .= "<div id=\"{$this->name}_tab{$this->tabCounter}\" class=\"tab\">\n";
		} else {
			$firstSection = "<div id=\"{$this->name}_tab{$this->tabCounter}\" class=\"tab header\">\n";
		}

		$this->getFieldsHTML($firstSection, $form);

		$form .= "</div>";

		if (isset($this->suffix)) {
			$form .= '<div class="suffix">' . "\n" . (isset($this->suffix) ? $this->suffix : '') . "\n";
			$form .= '</div>' . "\n";
		}

		// build preamble section if present
		$preamble = '';
		$hasErrors = !$this->delaySubmission && sizeof($this->errors) > 0;
		if ($hasErrors || isset($this->preamble)) {
			$preamble .= '<div class="preamble">' . "\n" . (isset($this->preamble) ? $this->preamble : '') . "\n";
			$preamble .= $hasErrors ? "<p class=\"err\">Please review your submission: " . sizeof($this->errors) . " fields have errors.</p>\n" : '';
			$preamble .= '</div>' . "\n";
		}

		$head = "<form id=\"$this->name\" class=\"clean\" method=\"" . strtolower($this->method)
				. "\" action=\"$this->action\"" . ($this->multipart ? ' enctype="multipart/form-data"' : '')
				. " data-fio-stripe=\"" . http_build_query($this->getRowStriperIncrements()) . "\""
				. '>' . "\n";
		$start = $hasHeader ? $firstSection . $this->getFormTabNav() : $this->getFormTabNav() . $firstSection;
		return $head . $preamble . $start . $form . "</form>\n";
	}

	/**
	 * Parameters are strings to append fields to if one requires the first section of
	 * the form to be separated. Otherwise, just use the return value and implode() it!
	 */
	public function getFieldsHTML(&$firstSection = null, &$form = null)
	{
		$html = array();
		$spin = 1;
		foreach ($this->data as $k => $value) {
			$fieldType = isset($this->dataTypes[$k]) ? $this->dataTypes[$k] : FormIO::T_RAW;

			// Special striping handling
			if ($fieldType == FormIO::T_SPACER) {
				--$spin;			// decrement stripe counter and keep going for spacer inputs
				continue;		// :TODO: link to JS
			} else if ($fieldType == FormIO::T_HIDDEN || $fieldType == FormIO::T_OUTDENT) {
				--$spin;			// these field types don't increment the striper
			} else if ($fieldType == FormIO::T_INDENT || $fieldType == FormIO::T_SECTIONBREAK) {
				$spin = 1;			// these field types reset the striper
			}

			// check for specific field type output string
			if (!isset(FormIO::$builder[$fieldType])) {
				$builderString = FormIO::$builder[FormIO::T_TEXT];
			} else {
				$builderString = FormIO::$builder[$fieldType];
			}

			// array of wildcard replacements to build with the form builder string in addition
			// to the automatically created ones. We start with our custom attributes (label, css classes etc)
			$extraWildcards = $this->dataAttributes[$k];

			// add required flag if set
			if ($this->hasValidator($k, 'requiredValidator') || $this->hasValidator($k, 'arrayRequiredValidator')) {
				$extraWildcards['required'] = true;
			}

			// validation parameter output for JavaScript
			$params = $this->getValidatorParams($k);
			if ($params) {
				$extraWildcards['validation'] = $params;
			}

			// dependencies for javascript
			if (isset($this->dataDepends[$k])) {
				$extraWildcards['dependencies'] = $this->getDependencyString($k);
			}

			// Add error output, if any
			if (!$this->delaySubmission && isset($this->errors[$k])) {
				$errArray = is_array($this->errors[$k]) ? $this->errors[$k] : array($this->errors[$k]);
				$extraWildcards['error'] = implode("<br />", $errArray);
			}

			// add row striping
			$extraWildcards['alt'] = ++$spin % 2 == 0;

			// send any field options as a primitive array for getBuilderVars() to squash into subfield HTML
			if ($fieldType == FormIO::T_RADIOGROUP || $fieldType == FormIO::T_CHECKGROUP || $fieldType == FormIO::T_DROPDOWN) {
				$extraWildcards['options'] = $this->dataOptions[$k];
			}

			// now get the system-generated ones
			$inputVars = $this->getBuilderVars($fieldType, $builderString, $k, $value, $extraWildcards);

			$inputStr = $this->replaceInputVars($builderString, $inputVars) . "\n";

			if ($this->tabCounter == 0) {
				$firstSection .= $inputStr;
			} else {
				$form .= $inputStr;
			}
			$html[] = $inputStr;
		}

		return $html;
	}

	private function getFormTabNav()
	{
		if ($this->tabCounter == 1) {
			return '';
		}
		$count = 0;
		$str = "<ul>\n";
		while ($count < $this->tabCounter) {
			$count++;
			$str .= "<li><a href=\"#{$this->name}_tab{$count}\">Page $count</a></li>\n";
		}
		return $str . "</ul>";
	}

	private function getRowStriperIncrements()
	{
		$spacers = array();
		$lastField = null;
		foreach ($this->dataTypes as $k => $fieldType) {
			if ($fieldType == FormIO::T_SPACER) {
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

	/**
	 * Creates a wildcard replacement array for use with replaceInputVars().
	 * Use of these methods together is what builds the HTML output for fields.
	 *
	 * We optimise the returned array as much as possible, as each item present requires extra processing.
	 *
	 * @param	const	$fieldType		one of the FormIO field type constants
	 * @param	string	$builderString	reference to the current string being used to build the field.
	 *									This basically allows this method to switch field types
	 * @param	string	$fieldName		the name of this input in the form, which is also used to build its HTML id
	 * @param	mixed	$value			the value of this field (internal FormIO format for dates etc)
	 * @param	array	$extraAttributes	Any extra attributes to add into the form builder array
	 *
	 * @return	array of wildcards to build the field with, or NULL if the field should not be built
	 */
	private function getBuilderVars($fieldType, &$builderString, $fieldName, $value = null, $extraAttributes = array())
	{
		// add to provided input property list.
		$inputVars = $extraAttributes;
		$inputVars['id'] = $this->getFieldId($fieldName);
		$inputVars['name'] = $fieldName;
		if ($value !== null) {
			$inputVars['value'] = $value;
		}

		// set data behaviour for form JavaScript, and any other type-specific attributes
		switch ($fieldType) {
			case FormIO::T_READONLY:
				$inputVars['escapedvalue'] = htmlentities($value);
				break;
			case FormIO::T_DATERANGE:
				$inputVars['value']		= $value[0];
				$inputVars['valueEnd']	= $value[1];
				break;
			case FormIO::T_TIMERANGE:
				unset($inputVars['value']);
				$inputVars['startdate']	= $value[0][0];
				$inputVars['enddate']	= $value[1][0];
				$inputVars['starttime']	= $value[0][1];
				$inputVars['endtime']	= $value[1][1];
				$inputVars['startam']	= $value[0][2] != 'pm';
				$inputVars['endam']		= $value[1][2] != 'pm';
				$inputVars['startpm']	= $value[0][2] == 'pm';
				$inputVars['endpm']		= $value[1][2] == 'pm';
				break;
			case FormIO::T_DATETIME:
				$inputVars['value']		= $value[0];
				$inputVars['valueTime']	= $value[1];
				$inputVars['pm']		= $value[2] == 'pm';
				$inputVars['am']		= $value[2] != 'pm';
				break;
			case FormIO::T_SECTIONBREAK:
				$inputVars['id'] = $this->getFieldId("tab" . ++$this->tabCounter);	// override ID with incremented tab counter var
				break;
			case FormIO::T_RADIO:
			case FormIO::T_CHECKOPTION:
			case FormIO::T_DROPOPTION:
				$inputVars['id'] = $this->getFieldId("{$fieldName}[{$value}]");
				break;
			case FormIO::T_CAPTCHA:
			case FormIO::T_CAPTCHA2:
				if (!empty($_SESSION[$this->CAPTCHA_session_var])) {
					return null;								// already verified as human, so don't output the field anymore
				}
				if ($this->captchaType == 'securimage' || $fieldType == FormIO::T_CAPTCHA2) {
					require_once($this->securImage_inc);
					$inputVars['captchaImage'] = $this->securImage_img;
					$builderString = FormIO::$builder[FormIO::T_CAPTCHA2];	// modify form builder string internally
				} else if ($this->captchaType == 'recaptcha') {
					require_once($this->reCAPTCHA_inc);
					$inputVars['captcha'] = recaptcha_get_html($this->reCAPTCHA_pub);
				}
				break;
			case FormIO::T_REPEATER:
				unset($value['__add']);		// we don't care about these anymore, the validator has updated the numinputs property by now...
				unset($value['__remove']);
				unset($value['isfiles']);
				$subFieldsString	= '';
				$subFieldType		= $extraAttributes['fieldtype'];
				$subFieldTemplate	= isset(FormIO::$builder[$subFieldType]) ? FormIO::$builder[$subFieldType] : FormIO::$builder[FormIO::T_TEXT];
				$numInputs			= $this->getMinRequiredRepeaterInputs($fieldName, $extraAttributes['numinputs']);
				$maxKey				= -1;
				$errors				= $this->errors[$fieldName];

				// iterate through values and output all currently sent submissions
				if (is_array($value)) {
					foreach ($value as $subField => $subValue) {		// add currently set vars
						if ($maxKey < $subField) {
							$maxKey = $subField;
						}
						$extras = array();
						if (is_array($errors[$subField])) {
							$extras['error'] = implode("<br />", $errors[$subField]);
							unset($errors[$subField]);					// mark this error as handled
						}

						$subFieldName = $fieldName . "[$subField]";
						if ($subFieldType == FormIO::T_FILE) {
							// we don't array index subfields for file inputs, as they don't support array sending
							$subFieldName = $fieldName . "_f$subField";
						}

						$subInputVars = $this->getBuilderVars($subFieldType, $subFieldTemplate, $subFieldName, $subValue, $extras);
						$subFieldsString .= $this->replaceInputVars($subFieldTemplate, $subInputVars) . "\n";
						$numInputs--;
					}
				}

				// output any remaining fields required to fill the number requested
				$subField = $maxKey + 1;				// keep going from the end of current field array
				while ($numInputs > 0) {				// now add remainder to make up minimum count
					$subFieldName = $fieldName . "[$subField]";
					if ($subFieldType == FormIO::T_FILE) {
						// we don't array index subfields for file inputs, as they don't support array sending
						$subFieldName = $fieldName . "_$subField";
					}
					$subInputVars = $this->getBuilderVars($subFieldType, $subFieldTemplate, $subFieldName);
					$subFieldsString .= $this->replaceInputVars($subFieldTemplate, $subInputVars) . "\n";
					$numInputs--;
					$subField++;
				}

				// Add submit buttons to control row adding / removing for no-JS support
				$buttonTemplate = FormIO::$builder[FormIO::T_SUBMIT];

				$buttonVars = $this->getBuilderVars(FormIO::T_SUBMIT, $buttonTemplate, $fieldName . "[__add]", "Add another");
				$controlsString = $this->replaceInputVars($buttonTemplate, $buttonVars) . "\n";

				$buttonVars = $this->getBuilderVars(FormIO::T_SUBMIT, $buttonTemplate, $fieldName . "[__remove]", "Remove last");
				$controlsString .= $this->replaceInputVars($buttonTemplate, $buttonVars) . "\n";

				// put all this in the 'inputs' variable
				$inputVars['inputs'] = $subFieldsString;
				$inputVars['controls'] = $controlsString;
				if (!$this->delaySubmission && sizeof($errors)) {
					$inputVars['error'] = is_array($errors) ? implode("<br />", $errors) : $errors;	// also add any unhandled errors
				}
				if ($subFieldType == FormIO::T_FILE) {
					$inputVars['isfiles'] = $fieldName;		// we pass the fieldname as it's the only thing we need to output in that builder string
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
						$subFieldType = FormIO::T_CHECKOPTION;
						break;
					case FormIO::T_DROPDOWN:
						$subFieldType = FormIO::T_DROPOPTION;
						break;
				}

				// determine if a value has been sent
				$valueSent = $value != null && $value !== '';

				// set the column count
				$inputVars['columns'] = isset($extraAttributes['columns']) ? $extraAttributes['columns'] : FormIO::$default_multiinput_columns;

				// Build field sub-elements
				$inputVars['options'] = '';
				foreach ($extraAttributes['options'] as $optVal => $desc) {
					$extraSubAttrs = array();
					if (is_array($desc)) {
						$extraSubAttrs['desc'] = $desc['desc'];
						if (isset($desc['disabled']))				$extraSubAttrs['disabled']	= $desc['disabled'];
						if (isset($desc['checked']) && !$valueSent)	$extraSubAttrs['checked']	= $desc['checked'];
					} else {
						$extraSubAttrs['desc'] = $desc;
					}
					// determine whether option should be selected if it hasn't explicitly been set
					if ($valueSent && $value == $optVal) {
						$extraSubAttrs['checked'] = true;
					}
					$subString = FormIO::$builder[$subFieldType];
					$radioVars = $this->getBuilderVars($subFieldType, $subString, $fieldName, $optVal, $extraSubAttrs);
					$inputVars['options'] .= $this->replaceInputVars($subString, $radioVars);
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
		return $inputVars;
	}

	/**
	 * Builds an input by replacing our custom-style string substitutions.
	 * {$varName} is replaced by $value, whilst {$varName?test="$varName"}
	 * is replaced by test="$value"
	 */
	private function replaceInputVars($str, $varsMap)
	{
		if ($varsMap === null) {
			return '';		// null means don't generate the field
		}
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

	// Returns an HTML field ID for this form (form's name is prepended), given a field name
	private function getFieldId($name)
	{
		return $this->name . '_' . str_replace(array('[', ']'), array('_', ''), $name);
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
					if ($externalValidator) {
						array_unshift($params, $this);
					}
					$valid = call_user_func_array($externalValidator ? $func : array($this, $func), $params);
				}

				if (!$valid) {
					$this->addError($dataKey, $this->errorString($func, $params));
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
		if (isset(FormIO::$defaultErrors[$callbackName])) {		// only the internal validators have an entry set in $defaultErrors
			$str = FormIO::$defaultErrors[$callbackName];
		} else if (isset($this->customValidatorErrors[$callbackName])) {
			$str = $this->customValidatorErrors[$callbackName];	// external validators can attempt to get theirs from the customValidatorErrors array
		} else {
			return null;
		}
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
		$shown = false;
		$hidden = false;
		foreach ($this->dataDepends as $masterField => $dependencies) {
			foreach ($dependencies as $postValue => $targetFields) {
				if (in_array($key, $targetFields)) {							// field is dependant on another field's submission
					if ($this->data[$masterField] != $postValue) {				// and value for master field means this field is hidden
						$hidden = true;
					} else {
						$shown = true;
					}
				}
			}
		}
		if ($hidden && !$shown) {
			$this->data[$key] = null;
			return true;
		}
		return false;
	}

	//==========================================================================
	//	Callbacks for validation
	//		Most validators may have $overrideData passed to them to override
	//		the array from which the data is read (default is $this->data).
	//		This is useful for eg. repeater fields, which need to recurse.

	private function requiredValidator($key, &$overrideData = null) {
		$overrideData ? $data = &$overrideData : $data = &$this->data;
		return isset($data[$key]) && $data[$key] !== '';
	}

	// @param	array	$requiredKeys	a list of array keys which are required. When omitted, all keys are checked.
	private function arrayRequiredValidator($key, $requiredKeys = null, &$overrideData = null) {
		$overrideData ? $data = &$overrideData : $data = &$this->data;
		if (isset($data[$key]) && is_array($data[$key]) && sizeof($data[$key])) {
			foreach ($data[$key] as $k => $v) {
				if ((is_array($requiredKeys) && in_array($k, $requiredKeys) && empty($v)) || (!is_array($requiredKeys) && empty($v))) {
					return false;
				}
			}
			return true;
		}
		return false;
	}

	private function equalValidator($key, $expected, &$overrideData = null) {
		$overrideData ? $data = &$overrideData : $data = &$this->data;
		return isset($data[$key]) && $data[$key] == $expected;
	}

	private function notEqualValidator($key, $unexpected, &$overrideData = null) {
		$overrideData ? $data = &$overrideData : $data = &$this->data;
		return !isset($data[$key]) || $data[$key] != $unexpected;
	}

	private function minLengthValidator($key, $length, &$overrideData = null) {
		$overrideData ? $data = &$overrideData : $data = &$this->data;
		return strlen($data[$key]) >= $length;
	}

	private function maxLengthValidator($key, $length, &$overrideData = null) {
		$overrideData ? $data = &$overrideData : $data = &$this->data;
		return strlen($data[$key]) <= $length;
	}

	private function inArrayValidator($key, $allowable, &$overrideData = null) {
		$overrideData ? $data = &$overrideData : $data = &$this->data;
		return in_array($data[$key], $allowable);
	}

	private function regexValidator($key, $regex, &$overrideData = null) {
		$overrideData ? $data = &$overrideData : $data = &$this->data;
		return preg_match($regex, $data[$key]) > 0;
	}

	private function dateValidator($key, &$overrideData = null) {					// performs date normalisation
		$overrideData ? $data = &$overrideData : $data = &$this->data;
		preg_match(FormIO::dateRegex, $data[$key], $matches);
		$success = sizeof($matches) == 4;
		if ($matches[1] > 31 || $matches[2] > 12) {
			return false;
		}
		if ($success) {
			$data[$key] = $this->normaliseDate($matches[1], $matches[2], $matches[3]);
		}
		return $success != false;
	}

	private function emailValidator($key, &$overrideData = null) {
		return $this->regexValidator($key, FormIO::emailRegex, $overrideData);
	}

	private function phoneValidator($key, &$overrideData = null) {
		$overrideData ? $data = &$overrideData : $data = &$this->data;
		return preg_match('/\d/', $data[$key]) && $this->regexValidator($key, FormIO::phoneRegex, $overrideData);
	}

	private function currencyValidator($key, &$overrideData = null) {				// performs currency normalisation
		$overrideData ? $data = &$overrideData : $data = &$this->data;
		preg_match(FormIO::currencyRegex, $data[$key], $matches);
		$success = sizeof($matches) > 0;
		if ($success) {
			$data[$key] = $this->normaliseCurrency($matches[1], (isset($matches[3]) ? $matches[3] : null));
		}
		return $success != false;
	}

	private function urlValidator($key, &$overrideData = null) {					// allows http, https & ftp *only*. Also performs url normalisation
		$overrideData ? $data = &$overrideData : $data = &$this->data;
		$data[$key] = $this->normaliseURL($data[$key]);

		if (false == $bits = parse_url($data[$key])) {
			return false;
		}
		if (empty($bits['host']) || !ctype_alpha(substr($bits['host'], 0, 1))) {
			return false;
		}

		return (empty($bits['scheme']) || $bits['scheme'] == 'http' || $bits['scheme'] == 'https' || $bits['scheme'] == 'ftp');
	}

	private function chpasswdValidator($key, &$overrideData = null) {
		$overrideData ? $data = &$overrideData : $data = &$this->data;

		if ((!empty($data[$key][0]) || !empty($data[$key][1])) && ($data[$key][0] != $data[$key][1])) {
			return false;
		}
		$data[$key] = null;
		return true;
	}

	// :NOTE: repeater data override parameter not implemented, but why would you?
	private function captchaValidator($key) {				// stores result in session, if available. We only need to authenticate as human once.
		if (isset($_SESSION[$this->CAPTCHA_session_var])) {
			return $_SESSION[$this->CAPTCHA_session_var];
		}
		$ok = false;

		if ($this->captchaType == 'securimage' || $fieldType == FormIO::T_CAPTCHA2) {
			require_once($this->securImage_inc);
			$securimage = new Securimage();
			if ($securimage->check($this->data[$key])) {
				$ok = true;
			}
		} else if ($this->captchaType == 'recaptcha') {
			require_once($this->reCAPTCHA_inc);
			$resp = recaptcha_check_answer($this->reCAPTCHA_priv,
							$_SERVER["REMOTE_ADDR"],
							$_POST["recaptcha_challenge_field"],
							$_POST["recaptcha_response_field"]);
			$ok = $resp->is_valid;
		}
		if (session_id() && $ok) {
			$_SESSION[$this->CAPTCHA_session_var] = true;
		}
		return $ok;
	}

	private function dateRangeValidator($key, &$overrideData = null) {			// performs date normalisation
		$overrideData ? $data = &$overrideData : $data = &$this->data;
		if (isset($data[$key]) && is_array($data[$key])) {
			if ((!empty($data[$key][0]) || !empty($data[$key][1]))
			  && (!preg_match(FormIO::dateRegex, $data[$key][0], $matches1)
			  || !preg_match(FormIO::dateRegex, $data[$key][1], $matches2))) {
				return false;
			}

			if ($matches1[1] > 31 || $matches1[2] > 12 || $matches2[1] > 31 || $matches2[2] > 12) {
				return false;
			}

			$data[$key][0] = $this->normaliseDate($matches1[1], $matches1[2], $matches1[3]);
			$data[$key][1] = $this->normaliseDate($matches2[1], $matches2[2], $matches2[3]);

			// also swap the values if they are in the wrong order
			if (($matches1[3] > $matches2[3])
			 || ($matches1[3] >= $matches2[3] && $matches1[2] > $matches2[2])
			 || ($matches1[3] >= $matches2[3] && $matches1[2] >= $matches2[2] && $matches1[1] > $matches2[1])) {
				$temp = $data[$key][0];
				$data[$key][0] = $data[$key][1];
				$data[$key][1] = $temp;
			}
			return true;
		}
		return true;		// not set, so validate as OK and let requiredValidator pick it up
	}

	private function timeRangeValidator($key, &$overrideData = null) {			// performs date and time normalisation
		$overrideData ? $data = &$overrideData : $data = &$this->data;
		if (isset($data[$key]) && is_array($data[$key])) {
			// either both or none must be set
			if ((empty($data[$key][0][0]) && empty($data[$key][0][1])) ^ (empty($data[$key][1][0]) && empty($data[$key][1][1]))) {
				return false;
			}
			if (empty($data[$key][0][0])) {		// none set, nothing being sent
				unset($data[$key]);
				return true;
			}

			foreach ($data[$key] as &$datetime) {
				$dateOk = preg_match(FormIO::dateRegex, $datetime[0], $dateMatches);
				$timeOk = preg_match(FormIO::timeRegex, $datetime[1], $timeMatches);

				if (!$dateOk || !$timeOk) {
					return false;
				}
				if ($dateMatches[1] > 31 || $dateMatches[2] > 12 || $timeMatches[1] > 12 || (isset($timeMatches[3]) && $timeMatches[3] > 59)) {
					return false;
				}

				$datetime = array(
								$this->normaliseDate($dateMatches[1], $dateMatches[2], $dateMatches[3]),
								$this->normaliseTime($timeMatches[1], (isset($timeMatches[3]) ? $timeMatches[3] : 0), (isset($timeMatches[5]) ? $timeMatches[5] : null)),
								$datetime[2]
							);
			}
			return true;
		}
		return true;		// not set, so validate as OK and let requiredValidator pick it up
	}

	private function dateTimeValidator($key, &$overrideData = null) {			// performs date and time normalisation
		$overrideData ? $data = &$overrideData : $data = &$this->data;
		if (isset($data[$key]) && is_array($data[$key])) {
			// either both or none must be set
			if (empty($data[$key][0]) ^ empty($data[$key][1])) {
				return false;
			}
			if (empty($data[$key][0])) {		// none set, nothing being sent
				$data[$key] = array();
				return true;
			}

			$dateOk = preg_match(FormIO::dateRegex, $data[$key][0], $dateMatches);
			$timeOk = preg_match(FormIO::timeRegex, $data[$key][1], $timeMatches);

			if (!$dateOk || !$timeOk) {
				return false;
			}
			if ($dateMatches[1] > 31 || $dateMatches[2] > 12 || $timeMatches[1] > 12 || (isset($timeMatches[3]) && $timeMatches[3] > 59)) {
				return false;
			}

			$data[$key] = array(
								$this->normaliseDate($dateMatches[1], $dateMatches[2], $dateMatches[3]),
								$this->normaliseTime($timeMatches[1], (isset($timeMatches[3]) ? $timeMatches[3] : 0), (isset($timeMatches[5]) ? $timeMatches[5] : null)),
								$data[$key][2]
							);
		}
		return true;
	}

	private function fileUploadValidator($key, &$overrideData = null, $parentKey = null) {
		$overrideData ? $data = &$overrideData : $data = &$this->data;
		$errorKey = $key;
		if ($parentKey) {
			$errorKey = array($parentKey, $key);
		}

		$ok = true;
		switch ($data[$key]['error']) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
			case UPLOAD_ERR_PARTIAL:
			case UPLOAD_ERR_NO_TMP_DIR:
			case UPLOAD_ERR_CANT_WRITE:
			case UPLOAD_ERR_EXTENSION:
				$ok = false;
				$this->addError($errorKey, FormIO::$defaultErrors['fileInvalid' . $data[$key]['error']]);
		}
		return $ok;
	}

	/**
	 * The repeater validator also performs array culling and removes any unused elments.
	 * :NOTE: Repeaters cannot currently be nested, but then again why would you?
	 */
	private function repeaterValidator($key) {
		$fieldType = $this->dataAttributes[$key]['fieldtype'];
		$errors = false;

		$add = !empty($this->data[$key]['__add']);
		$remove = !empty($this->data[$key]['__remove']);
		unset($this->data[$key]['__add']);
		unset($this->data[$key]['__remove']);
		unset($this->data[$key]['isfiles']);
		$numSent = sizeof($this->data[$key]);

		// kill any values not sent
		foreach ($this->data[$key] as $subKey => $subValue) {
			if (!$subValue) {
				unset($this->data[$key][$subKey]);
				continue;
			}
		}

		// Run internal validation routines that apply to this field type
		$validators = $this->getDefaultFieldValidators($fieldType);
		foreach ($validators as $validatorName => $params) {
			// Add the $overrideData parameter to each validator call, setting it to our array
			$params[] = &$this->data[$key];

			// Validate each array element in turn
			foreach ($this->data[$key] as $subKey => $subValue) {
				$subParams = $params;
				array_unshift($subParams, $subKey);

				// add extra needed parameter for file upload validator to be able to sent errors
				if ($fieldType == FormIO::T_FILE) {
					array_push($subParams, $key);
				}

				$valid = call_user_func_array(array($this, $validatorName), $subParams);
				if (!$valid) {
					$this->addError(array($key, $subKey), $this->errorString($validatorName, $subParams));
					$errors = true;
				}
			}
		}

		// check for use of add/remove field buttons, and tell the form to ignore errors if so
		if ($add || $remove) {
			if ($remove) {
				if (sizeof($this->data[$key]) == $numSent) {
					end($this->data[$key]);
					unset($this->data[$key][key($this->data[$key])]);
					reset($this->data[$key]);
				}
				$this->dataAttributes[$key]['numinputs'] = $this->getMinRequiredRepeaterInputs($key, $numSent - 1);
			} else if ($add) {
				$this->dataAttributes[$key]['numinputs'] = $this->getMinRequiredRepeaterInputs($key, $numSent + 1);
			}
			$this->delaySubmission = true;
			$this->submitted = false;
		}

		return !$errors;
	}

	//==========================================================================
	//	Data normalisers
	//		Run from within validators, these functions ensure that an input
	//		variable is in the expected input format.
	//		These will be returned to the user, so don't use them for 'modifying
	//		the value', if that differentiation makes sense.

	private function normaliseDate($d, $m, $y) {				// dd/mm/yyyy
		if ($d === null || $m === null || $y === null) {
			return '';
		}
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

	public static function dateTimeToUnix($val)
	{
		@list($hr, $min, $sec) = explode(':', $val[1]);
		if ($hr === null || $min === null) {
			return null;
		} else if ($val[2] == 'pm') {
			if ($hr != 12) {
				$hr += 12;
			}
		} else if ($hr == 12) {
			$hr = 0;
		}
		return FormIO::dateToUnix($val[0]) + $hr*3600 + $min*60 + ($sec ? $sec : 0);
	}

	public static function dateToUnix($val)
	{
		$bits = explode('/', $val);
		if (!isset($bits[2])) {
			return null;
		}
		return mktime(0, 0, 0, $bits[1], $bits[0], $bits[2]);
	}

	public static function timestampToDateTime($val)
	{
		if (!$val) {
			return null;
		}
		$format = "h:i";
		if ($secs = date('s', $val) && intval($secs) != 0) {
			$format = $format . ":$secs";
		}

		return array(
			FormIO::timestampToDate($val),
			date($format, $val),
			date('a', $val)
		);
	}

	public static function timestampToDate($val)
	{
		if (!$val) {
			return null;
		}
		return date("d/m/Y", $val);
	}

	private function getMinRequiredRepeaterInputs($key, $minNum = 1)
	{
		if ($minNum < sizeof($this->data[$key])) {
			return sizeof($this->data[$key]) + 1;
		}
		if ($minNum < 1) {
			return 1;
		}
		return $minNum;
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
