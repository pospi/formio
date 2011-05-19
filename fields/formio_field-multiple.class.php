<?php
/**
 * Abstract base class for multivalued field types.
 */

abstract class FormIOField_Multiple extends FormIOField_Text
{
	public $subfieldBuildString = '{$value}';		// builder string for field subelement

	protected $options = array();

	public function setOption($optionKey, $optionValue, $dependentField = null)
	{
		$this->options[$optionKey] = $optionValue;
		if ($dependentField !== null) {
			$this->addDependency($optionKey, $dependentField);
		}
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
