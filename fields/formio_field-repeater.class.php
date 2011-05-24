<?php
/**
 * Repeaters allow repetition of any other field type
 */

class FormIOField_Repeater extends FormIOField_Text
{
	public $buildString = '<div class="row blck{$alt? alt}{$classes? $classes}" id="{$id}"{$dependencies? data-fio-depends="$dependencies"} data-fio-type="repeater">
		<label for="{$id}_0">{$desc}{$required? <span class="required">*</span>}</label>{$inputs}
		{$isfiles?<input type="hidden" name="$isfiles[isfiles]" value="1" />}
		<div class="pad"></div>
			<input type="submit" name="{$name}[__add]" value="Add another" />
			<input type="submit" name="{$name}[__remove]" value="Remove last" />
		{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>';

	private $children = array();		// child field objects (this is where the 'value' is stored for this field type)

	public function setValue($values)
	{
		$this->value = array();
		if (empty($values)) {
			return;
		}
		foreach ($values as $i => $val) {
			if ($i === '__add' || $i === '__remove' || $i === 'isfiles') {	// field controls are stored in $value
				$this->value[$i] = $val;
				continue;
			}
			if (!isset($this->children[$i])) {							// subfield data is stored in $children
				$this->children[$i] = $this->createSubField($i);
			}
			$this->children[$i]->setValue($val);
		}
	}

	public function getValue()
	{
		$values = array();
		foreach ($this->children as $i => $subField) {
			$values[$i] = $subField->getValue();
		}
		return empty($values) ? null : $values;
	}

	public function getHumanReadableValue()
	{
		$values = array();
		foreach ($this->children as $i => $subField) {
			$values[$i] = $subField->getHumanReadableValue();
		}
		return implode("\n", $values);
	}

	public function getRawValue()
	{
		$values = array();
		foreach ($this->children as $i => $subField) {
			$values[$i] = $subField->getRawValue();
		}
		return $values;
	}

	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();

		$numInputs	= $this->getMinRequiredInputs($this->getAttribute('numinputs'));
		$maxKey		= -1;
		$spin		= 0;
		$errors		= $this->getErrors();

		$vars['inputs'] = '';

		// output all present child fields first
		foreach ($this->children as $idx => $childField) {
			if ($maxKey < $idx) {
				$maxKey = $idx;
			}

			$childError = '';
			if (isset($errors[$idx])) {
				$childError = is_array($errors[$idx]) ? implode("<br />", $errors[$idx]) : $errors[$idx];
				unset($errors[$idx]);					// mark this error as handled
			}

			$childField->setAttribute('error', $childError);

			$vars['inputs'] .= $childField->getHTML($spin);

			$numInputs--;
		}

		// create and output any remaining fields required to fill the number requested
		$newIndex = $maxKey + 1;				// keep going from the end of current field array
		while ($numInputs > 0) {				// now add remainder to make up minimum count
			$childField = $this->createSubField($newIndex);

			$this->children[$newIndex] = $childField;
			$vars['inputs'] .= $childField->getHTML($spin);

			$numInputs--;
			$newIndex++;
		}

		if (sizeof($errors)) {
			$vars['error'] = is_array($errors) ? implode("<br />", $errors) : $errors;	// add any errors which weren't for a particular child field
		}
		if ($this->getAttribute('fieldtype') == FormIO::T_FILE) {
			$vars['isfiles'] = $this->name;		// we pass the fieldname as it's the only thing we need to output in that builder string
		}

		return $vars;
	}

	/**
	 * Sets the repeated field type. The repeater will allow multiple entries of this field
	 * type, handled via JavaScript and form resubmissions as appropriate.
	 *
	 * :NOTE: although the attribute we set is 'fieldtype', the related output variable is
	 * 		  generated as 'inputs'.
	 */
	public function setRepeaterType($fieldType)
	{
		$this->setAttribute('fieldtype', $fieldType);
	}

	// creates a new FormIO field for internal use by this repeater, based on the 'fieldtype' attribute
	private function createSubField($index)
	{
		// we don't array index subfields for file inputs, as they don't support array sending
		if ($this->getAttribute('fieldtype') == FormIO::T_FILE) {
			$name = $this->name . '_f' . $index;
		} else {
			$name = $this->name . '[' . $index . ']';
		}
		return FormIO::loadFieldByClass($this->getAttribute('fieldtype'), $name, '', $this->form);
	}

	private function getMinRequiredInputs($minNum = 1)
	{
		if ($minNum < sizeof($this->children)) {
			return sizeof($this->children) + 1;
		}
		if ($minNum < 1) {
			return 1;
		}
		return $minNum;
	}

	/**
	 * Repeater should use the arrayRequiredValidator for when all elements are required
	 */
	public function setRequired()
	{
		$this->addValidator('arrayRequiredValidator', array(), false);
	}

	/**
	 * The repeater validation routine simply has to kick off validation for its subfields,
	 * as well as handling its repeater state variables
	 */
	public function validate()
	{
		$errors = !parent::validate();

		$add = !empty($this->value['__add']);
		$remove = !empty($this->value['__remove']);
		$numSent = sizeof($this->children);

		foreach ($this->children as $subKey => $subField) {
			// kill any fields with values not sent
			if ($subField->getValue() === null) {
				unset($this->children[$subKey]);	// :TODO: check reference counting here
				continue;
			}
			// Run internal validation routines for subfields
			if (!$subField->validate()) {
				$errors = true;
			}
		}

		// check for use of add/remove field buttons, and tell the form to ignore errors if so
		if ($add || $remove) {
			if ($remove) {
				if (sizeof($this->children) == $numSent) {
					end($this->children);
					unset($this->children[key($this->children)]);	// :TODO: check reference counting here
					reset($this->children);
				}
				$this->setAttribute('numinputs', $this->getMinRequiredInputs($numSent - 1));
			} else if ($add) {
				$this->setAttribute('numinputs', $this->getMinRequiredInputs($numSent + 1));
			}
			$this->form->delaySubmission = true;
			$this->form->submitted = false;
		}

		return !$errors;
	}
}
?>
