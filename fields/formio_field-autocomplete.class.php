<?php
/**
 *
 */

class FormIOField_Autocomplete extends FormIOField_Text
{
	public $buildString = '<div class="row{$alt? alt}{$classes? $classes}">
		<label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label>
		<input type="hidden" name="{$name}"{$value? value="$value"}{$extradata? data-fio-value-metadata="$extradata"} />
		<input type="text" name="{$friendlyName}" id="{$id}"{$friendlyValue? value="$friendlyValue"}{$readonly? readonly="readonly"}{$maxlen? maxlength="$maxlen"}{$behaviour? data-fio-type="$behaviour"}{$validation? data-fio-validation="$validation"} data-fio-searchurl="{$searchurl}"{$multiple? data-fio-multiple="$multiple"}{$delimiter? data-fio-delimiter="$delimiter"}{$dependencies? data-fio-depends="$dependencies"} />
		{$error?<p class="err">$error</p>}
		{$hint? <p class="hint">$hint</p>}
	</div>';

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
		$this->clearAttribute('multiple');
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

	/**
	 * Add output variables for the friendly prepopulated data
	 *
	 * Child classes may wish to override this method and add other data
	 * to an 'extradata' field - this can take any format but would generally
	 * be an array of extra metadata (beyond key and name as present by default in the
	 * hidden input and friendly text field respectively) to output in JSON for
	 * some client code to pick up and play with. The data attribute this goes into
	 * is named 'fio-value-metadata'.
	 */
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
