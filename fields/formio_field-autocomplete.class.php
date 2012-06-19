<?php
/**
 *
 */

class FormIOField_Autocomplete extends FormIOField_Text
{
	public $buildString = '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label><input type="text" name="{$name}" id="{$id}"{$value? value="$value"}{$maxlen? maxlength="$maxlen"}{$behaviour? data-fio-type="$behaviour"}{$validation? data-fio-validation="$validation"} data-fio-searchurl="{$searchurl}"{$multiple? data-fio-multiple="$multiple"}{$delimiter? data-fio-delimiter="$delimiter"}{$dependencies? data-fio-depends="$dependencies"} />{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>';

	const DEFAULT_DELIM = ',';

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
	}

	public function setSingle()
	{
		$this->multiple = false;
	}

	public function setValue($value)
	{
		if (is_array($value)) {
			$value = implode($this->getAttribute('deimiter', self::DEFAULT_DELIM), $value);
		}
		parent::setValue($value);
	}

	/**
	 * Overridden to split into an array
	 */
	public function getHumanReadableValue()
	{
		$val = $this->getValue();
		if (is_array($val)) {
			return implode($this->getAttribute('delimiter', ','), $val);
		}
		return '';
	}
}
?>
