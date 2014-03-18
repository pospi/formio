<?php
/**
 *
 */

class FormIOField_Url extends FormIOField_Text
{
	public static $VALIDATOR_ERRORS = array(
		'urlValidator'	=> "Invalid URL",
	);

	protected $validators = array(
		'urlValidator'
	);

	// append 'behaviour' parameter to the input for JS validation
	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();
		$vars['behaviour'] = 'url';
		return $vars;
	}

	// allows http, https & ftp *only*. Also performs url normalisation
	final protected function urlValidator() {
		$this->value = $this->normaliseURL($this->value);

		if (false == $bits = parse_url($this->value)) {
			return false;
		}
		if (empty($bits['host']) || !ctype_alpha(substr($bits['host'], 0, 1))) {
			return false;
		}

		return (empty($bits['scheme']) || $bits['scheme'] == 'http' || $bits['scheme'] == 'https' || $bits['scheme'] == 'ftp');
	}

	// ensures a scheme is present
	protected function normaliseURL($str) {
		if (!preg_match('/^(\w+:)?\/\//', $str)) {
			return 'http://' . $str;
		}
		return $str;
	}
}
?>
