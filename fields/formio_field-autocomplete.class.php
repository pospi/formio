<?php
/**
 *
 */

class FormIOField_Autocomplete extends FormIOField_Text
{
	public $singleBuildString = '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label><input type="text" name="{$name}" id="{$id}"{$value? value="$value"}{$maxlen? maxlength="$maxlen"}{$behaviour? data-fio-type="$behaviour"}{$validation? data-fio-validation="$validation"} data-fio-searchurl="{$searchurl}"{$multiple? data-fio-multiple="$multiple"}{$delimiter? data-fio-delimiter="$delimiter"}{$dependencies? data-fio-depends="$dependencies"} />{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>';
	public $multiBuildString = '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label><input type="hidden" name="{$name}"{$value? value="$value"} /><input type="text" name="{$friendlyName}" id="{$id}"{$friendlyValue? value="$friendlyValue"}{$maxlen? maxlength="$maxlen"}{$behaviour? data-fio-type="$behaviour"}{$validation? data-fio-validation="$validation"} data-fio-searchurl="{$searchurl}"{$multiple? data-fio-multiple="$multiple"}{$delimiter? data-fio-delimiter="$delimiter"}{$dependencies? data-fio-depends="$dependencies"} />{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>';

	const DEFAULT_DELIM = ',';

	public function __construct($form, $name, $displayText = null, $defaultValue = null)
	{
		parent::__construct($form, $name, $displayText, $defaultValue);

		$this->buildString = $this->singleBuildString;
	}

	/**
	 * Set the URL which returns JSON data for our request
	 * @param string $url url to load autocomplete results from, passed to jquery UI
	 */
	public function setAutocompleteUrl($url)
	{
		$this->setAttribute('searchurl', $url);
	}

	/**
	 * sets this input to receive multiple values, separated by a delimiter of some sort
	 * @param string $delim delimiter for separating values
	 */
	public function setMultiple($delim = ',')
	{
		$this->multiple = true;
		$this->setAttribute('multiple', '1');
		$this->setAttribute('delimiter', $delim);
		$this->buildString = $this->multiBuildString;
	}

	public function setSingle()
	{
		$this->multiple = false;
		$this->buildString = $this->singleBuildString;
	}

	/**
	 * override in child classes to set friendly value as appropriate for internal autocomplete workings
	 * @param array/string $value new value for the field
	 */
	public function setValue($value)
	{
		if (!is_array($value)) {
			// filter out empty entries in the list
			$value = $this->getArrayValue($value);
		}

		parent::setValue(implode($this->getAttribute('delimiter', self::DEFAULT_DELIM), $value));
	}

	/**
	 * Overridden to concatenate if array is stored internally
	 */
	public function getHumanReadableValue()
	{
		$val = $this->getArrayValue();
		return implode($this->getAttribute('delimiter', self::DEFAULT_DELIM), $val);
	}

	protected function getArrayValue($val = null)
	{
		if (!isset($val)) $val = $this->getValue();

		if (!is_array($val)) {
			$val = explode($this->getAttribute('delimiter', self::DEFAULT_DELIM), $val);
			$val = array_filter($val, function($var) {
				return $var || $var === '0' || $var === 0;
			});
			$val = array_map('trim', $val);
		}
		return $val;
	}

	// add output variables for the friendly prepopulated data
	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();

		$vars['friendlyValue'] = $this->getHumanReadableValue();

		$name = $this->getName();
		if (substr($name, -1) == ']') {
			$name = substr($name, 0, strlen($name) - 1) . '_friendly]';
		} else {
			$name .= '_friendly';
		}
		$vars['friendlyName'] = $name;
		$vars['behaviour'] = 'autocomplete';

		return $vars;
	}
}
?>
