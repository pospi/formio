<?php
/**
 * Abstract base class for multivalued field types.
 */

abstract class FormIOField_Multiple extends FormIOField_Text
{
	public $subfieldBuildString = '{$value}';		// builder string for field subelement

	protected $options = array();

	// allow setting a flat array as the field's value instead of POST format keyed array
	public function setValue($newVal)
	{
		// if a normal array is passed, flip it and use as selection
		if (is_array($newVal) && count($newVal) && array_keys($newVal) === range(0, count($newVal) - 1)) {
			$newVal = array_combine($newVal, array_fill(0, count($newVal), true));
		}

		parent::setValue($newVal);
	}

	public function getHumanReadableValue()
	{
		if (is_array($this->options) && isset($this->options[$this->value])) {
			return $this->options[$this->value];
		}
		return '';
	}

	public function setOption($optionKey, $optionValue, $dependentField = null)
	{
		$this->options[$optionKey] = $optionValue;
		if ($dependentField !== null) {
			$this->addDependency($optionKey, $dependentField);
		}
	}

	/**
	 * If it is not a requirement that your field have dependencies attached to its options, or
	 * you wish to add those dependencies manually, you may set the entire options array at once.
	 */
	public function setOptions($array)
	{
		$this->options = $array;
	}

	/**
	 * We also build a string for the '$options' wildcard by processing $this->options
	 */
	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();

		// build all option elements by performing replacements and concatenating the output
		reset($this->options);
		$optionsStr = '';
		while ($subVars = $this->getNextOptionVars()) {
			$optionsStr .= $this->replaceInputVars($this->subfieldBuildString, $subVars);
		}
		reset($this->options);
		$vars['options'] = $optionsStr;

		$vars['columns'] = isset($this->attributes['columns']) ? $this->attributes['columns'] : 2;	// subclasses may or may not use this attribute
		return $vars;
	}

	/**
	 * Retrieve the variables required to build the next option of this field
	 * Child classes which overload this function should first call the parent,
	 * and return FALSE if the returned value is not an array.
	 * $this->options is reset both before and after the loop calling this function is executed.
	 *
	 * @return	an array of wildcards for the next subinput, or FALSE if there are no more options left to retrieve
	 */
	protected function getNextOptionVars()
	{
		@list($idx, $option) = each($this->options);
		if ($idx === null || $idx === false) {
			return false;
		}

		return array('value' => $idx, 'desc' => $option);
	}
}
?>
