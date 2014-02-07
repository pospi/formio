<?php
// CAPTCHAs can cache their results in session, so that users only need authenticate once
@session_start('formio_test');

// define your reCAPTCHA auth here in place of mine
$reCAPTCHA_pub =	'';
$reCAPTCHA_priv =	'';

// this constant and HTML juggling is for my own use - I include this template into my website
if (defined('DID_HEADER') && class_exists('P_Site')) {
	$localPath = P_Site::getPluginUri('pospi_base/formio/');
	list($reCAPTCHA_pub, $reCAPTCHA_priv) = P_Site::getRecaptchaAuth();
} else {
	$localPath = '../';
}

$stylesheets = <<<EOT
<link rel="stylesheet" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.13/themes/cupertino/jquery-ui.css" type="text/css" />
<link rel="stylesheet" href="{$localPath}formio.css" type="text/css" />
<link rel="stylesheet" href="{$localPath}themes/modern.css" type="text/css" />
<style type="text/css">
	div.panel { width: 700px; margin: 2em auto; border: 1px solid #AAA; padding: 1em; background: white; }
	div#output pre {
		white-space: pre-wrap; /* css-3 */
		white-space: -moz-pre-wrap !important; /* Mozilla, since 1999 */
		white-space: -pre-wrap; /* Opera 4-6 */
		white-space: -o-pre-wrap; /* Opera 7 */
		word-wrap: break-word; /* Internet Explorer 5.5+ */
	}
	div#output p { font-weight: bold; color: #429BC8; font-size: 1.2em; }
</style>
EOT;

if (!defined('DID_HEADER')) :
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title>FormIO example</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="description" content="FormIO form building and validation library" />
<meta name="keywords" content="FormIO,PHP forms,PHP form builder,form validation,secure form,pospi" />
<style type="text/css">
	html, body { color: #444; font-family: tahoma, verdana, arial, sans-serif; font-size: 12px; background: white; }
</style>

<?php echo $stylesheets; ?>

</head>
<body>
<?php else: ?>
	<?php echo $stylesheets; ?>
<?php endif; ?>

<div class="panel">
<!-- START DEMO -->
<?php
	require_once(dirname(__FILE__) . '/../form_io.class.php');

	// create form
	$form = new FormIO('exampleForm', 'POST');

	// setup the captcha system
	$form->captchaType =	'recaptcha';
	$form->reCAPTCHA_pub =	$reCAPTCHA_pub;
	$form->reCAPTCHA_priv =	$reCAPTCHA_priv;
	$form->reCAPTCHA_inc = dirname(__FILE__) . "/../lib/recaptcha-php-1.11/recaptchalib.php";
	$form->securImage_inc = dirname(__FILE__) . "/../lib/securimage/securimage.php";
	$form->securImage_img = "{$localPath}lib/securimage/securimage_show.php";

	// A custom validator function.
	// Note that you can add additional errors from within the validator
	// itself for more complex validation, like cross-field dependencies:
	function checkPassword($val, $form, $key) {
		if ($val == $form->getData('changepwd')) {
			$form->addError($key, "New password cannot equal current password");
		}
		return $val == 'ohai';
	}

	// First, we build the form's fields to setup its initial data
	$form->startHeaderSection()
			->addHeader("An example FormIO form")

		->endHeaderSection('Simple fields')

		->addField('text',		"Plain text input", 			FormIO::T_TEXT,			"This field is required, and has been prefilled.")
			->setRequired()
		->addField('passwd',	'Enter your old password...',	FormIO::T_PASSWORD)
			->setHint('This field uses a custom validation function to verify your password. It simply matches "ohai".')
			->validateWith('checkPassword', array(), "Incorrect password")
			->setRequired()
		->incrementStriper()
		->addField('changepwd',	'Here is a password change input, too', FormIO::T_PASSWORDCHANGE)
			->setHint('We have incremented the table striper to make this row the same colour as the password input')

		->addSubHeader("Other textual inputs...")
		->addField('email',		"Email address", 				FormIO::T_EMAIL)
		->addField('phone',		"Phone number", 				FormIO::T_PHONE)
		->addField('credit',	"Credit card", 					FormIO::T_CREDITCARD)
			->setHint("This performs a quick check for validity using MOD10, not a full verification")
		->addField('alphanum',	"Alphanumeric characters only",	FormIO::T_ALPHANUMERIC)
		->addField('alpha',		"Letters only",					FormIO::T_ALPHA)
		->addField('numeric',	"A number", 					FormIO::T_NUMERIC)
			->setHint("Numeric fields allow any number of digits, with a single period.")
		->addField('currency',	"A currency value", 			FormIO::T_CURRENCY)
			->setHint("This is an input for dollars and cents. Allows for pretty relaxed input (whole dollar, cents only, rounded decimal fractions, optional dollar signs)")
		->addField('postcode',	"An Australian postcode", 		FormIO::T_AUSPOSTCODE)
		->addField('url',		"URL validation", 				FormIO::T_URL)
			->setHint("This has the effect of normalising internet addresses with http:// as well as validating the URI")
		->addField('multiline',	"And of course textareas",		FormIO::T_BIGTEXT)
		->addField('raw',		'<div><p>This is a raw snippet of html inside the form.<br />Consequently, we can use it to tell you there are more fields overleaf...</p></div>', FormIO::T_RAW)

		->addSectionBreak('Time-related fields')

		->startFieldset("Here are a bunch of other time-related inputs in a fieldset")
		->addField('time',		"A time of day", 				FormIO::T_TIME)
			->setHint("Input here can be pretty relaxed. Hours is all that is required, minutes and seconds can be added optionally.")
		->addField('date',		"A specific date",				FormIO::T_DATE)
			->setHint("This falls back to the jQueryUI date widget")
		->addField('dateandtime',	"A certain point in time",	FormIO::T_DATETIME)
			->setHint("We can also do both inputs combined for an exact time")
		->addField('daterange',	"A range of dates",				FormIO::T_DATERANGE)
		->addField('timeperiod',	"A specific period of time",	FormIO::T_TIMERANGE)
		->endFieldset()

		->addSectionBreak('Field grouping & repetition')

		->addParagraph("You can use the repeater field type to repeat an input type as many times as you wish...")
		->addRepeater('texts',	"A variable-length list of things",	FormIO::T_TEXT, 	array(
			"We've prefilled two values for you.",
			"This can be done simply by setting an array of values as the input's value."
		))
		->addRepeater('dates',	"A variable-length date list",	FormIO::T_DATE,			array(), 	3)
			->setHint("Repeaters work for any field type.<br />We have also started the repeater with three inputs.")

		->addParagraph("You can of course have other data sent through. Below here is a readonly and a hidden field, which you will see in the output when you submit the form.")
		->addField('readonly',	"Readonly value",				FormIO::T_READONLY,		"Cannot be changed")
		->addHiddenField('hidden',	"hidden value")

		->addSectionBreak('Composite fields')

		->startFieldset("Complex field types")
		->addField('dropdown',	"A dropdown box",				FormIO::T_DROPDOWN)
			->addOption('',		'Please select...')
			->addOption('1',	'Option 1')
			->addOption('2',	'Option 2')
			->addOption('3',	'Option 3')
			->addOption('4',	'Option 4')
			->setRequired()
			->setHint("Dropdowns cannot be put into 'multiple' mode - this would give identical (but less usable) functionality to a checkbox list. You can use an empty value option along with a required constraint to force a selection, as above.")
		->addField('radiogrp',	"A list of choices",			FormIO::T_RADIOGROUP)
			->addOption('1',	'Choice 1')
			->addOption('2',	'Choice 2')
			->addOption('3',	'Choice 3')
			->addOption('4',	'Choice 4')
		->addField('checkgrp',	"A list of options",			FormIO::T_CHECKGROUP)
			->addOption('1',	'Choice 1')
			->addOption('2',	'Choice 2')
			->addOption('3',	'Choice 3')
			->addOption('4',	'Choice 4')
		->addField('unchecktest',	"A checkbox",	FormIO::T_CHECKBOX)
		->addField('checkedtest',	"A checked checkbox",	FormIO::T_CHECKBOX, true)
			->addFieldDependency(true, 'survey')
			->setHint("This field has a dependency set to trigger a survey field to show when checked")
		->addField('survey',	"Do you like to:",				FormIO::T_SURVEY)		// :TODO: survey builder
		->addField('autocom',	"An autocomplete field",		FormIO::T_AUTOCOMPLETE)	// :TODO: mutator to restrict to input array
			->setAutocompleteURL("autocomplete_data.php")
		->endFieldset()

		->addSubHeader("And now for some other stuff...")
		->addButton("Click me!",	"swapImage('#exampleForm_cycleimage');")
		->addImage('http://www.sxc.hu/pic/m/m/ma/matchstick/442672_lime_light.jpg',	"An example image", 'cycleimage')

		->addField('textwatch',		"Field dependencies", 		FormIO::T_TEXT)
			->addFieldDependency('cap', array('captcha1', 'captcha2'))
			->setHint("When this field's value is 'cap', two CAPTCHA inputs will appear below.<br />If you have cookies enabled, once you have successfully submitted a CAPTCHA it will disappear from the form, and you should see nothing.")
		->addField('captcha1',	"A reCAPTCHA captcha",			FormIO::T_CAPTCHA)
		->addField('captcha2',	"A SecurImage captcha",			FormIO::T_CAPTCHA2)		// you REALLY shouldn't add this field manually - use the form's captcha type configurator or they will conflict in session, etc. Only done for demonstration purposes.
		->addField('radiowatch',	"More dependencies",		FormIO::T_RADIOGROUP)
			->setHint("Obviously a more useful solution is something akin to a radiogroup which switches between options. Options can be programmed with a whitelist of field names to show when selected.")
			->addOption('email', "Contact's email",				'con_email')
			->addOption('phone', "Contact's phone details",		array('con_phone', 'con_fax'))
		->addField('con_email',		"An email address", 			FormIO::T_EMAIL)
		->addField('con_phone',		"A phone number", 				FormIO::T_PHONE)
		->addField('con_fax',		"A fax number", 				FormIO::T_PHONE)
		->addField('file',		"And why not a file upload to finish!",	FormIO::T_FILE)

		->startFooterSection()
		->addSubmitButton('Send form')
		->addResetButton('Reset everything')
	;

	// then, we handle the form's submission, updating the internal state of the form
	if ($form->takeSubmission() && $form->validate()) {
		if (!$form->valid) {
			// this should actually never get called, since $form->validate() returns false in case of an error
		} else {
			echo "<div id=\"output\">";
			echo "<p>Form data submission successful. Output is:</p>\n<pre>";
			echo $form->getJSON();
			echo "</pre><p>or...</p><pre>";
			echo $form->getQueryString();
			echo "</pre><p>or (with submit button values)...</p><pre>";
			print_r($form->getData(true));
			echo "</pre><p>or, more readably:</p><pre>";
			print_r($form->getHumanReadableData(true));
			echo "</pre><p>We can also walk the data with our own functions using FormIO::walkData(). This example generates the data with the field's uppercase HTML ID and JSON encoded value.</p><pre>";

			$interpretedData = $form->walkData('getFieldIdUpperCase', array(), 'getFieldJSONText', array());
			print_r($interpretedData);

			echo "</pre>";
			echo "</div>";
		}
	}

	// finally, draw it out, along with any newly submitted data & error output
	echo $form->getForm();

	// example field walking functions for data array interpretation
	function getFieldIdUpperCase($field)
	{
		return strtoupper($field->getFieldId());
	}
	function getFieldJSONText($field)
	{
		return json_encode($field->getValue());
	}
?>
<!-- /END DEMO -->
</div>
<?php if (!(defined('DID_HEADER') && class_exists('P_Site'))) : ?>
	<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
<?php endif; ?>
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.10.2/jquery-ui.min.js"></script>
<script type="text/javascript" src="<?php echo $localPath; ?>lib/jquery-tokeninput/src/jquery.tokeninput.js"></script>
<script type="text/javascript" src="<?php echo $localPath; ?>formio.js"></script>
</body>
</html>
