<?php
/**
 * Repeaters allow repetition of any other field type
 */

class FormIOField_Repeater extends FormIOField_Group
{
	public $buildString = '<div class="row blck{$alt? alt}{$classes? $classes}" id="{$id}"{$dependencies? data-fio-depends="$dependencies"}{$validation? data-fio-validation="$validation"} data-fio-type="repeater">
		<label for="{$id}_0">{$desc}{$required? <span class="required">*</span>}</label>
		{$inputs}
		{$isfiles?<input type="hidden" name="$isfiles[isfiles]" value="1" />}
		<div class="pad"></div>
		<input type="submit" name="{$name}[__add]" class="add" value="Add another" />
		<input type="submit" name="{$name}[__remove]" class="remove" value="Remove last" />
		{$error?<p class="err">$error</p>}
		{$hint? <p class="hint">$hint</p>}
	</div>';

	public static $VALIDATOR_ERRORS = array(
		'requiredValidator' => 'You must provide at least 1 value',
	);

	private $internals = array();		// internal variables of the repeater (add / remove button submit values, etc)

	public function setValue($values)
	{
		$this->value = array();
		if (empty($values)) {
			return;
		}
		foreach ($values as $i => $val) {
			if ($i === '__add' || $i === '__remove' || $i === 'isfiles') {	// field controls are stored in $this->value
				$this->internals[$i] = $val;
				unset($values[$i]);
				continue;
			}
			if (!isset($this->value[$i])) {							// add any new children to fit the sent data
				$this->createSubField($this->getAttribute('fieldtype'), $i);
			}
		}

		parent::setValue($values);
	}

	public function getHumanReadableValue()
	{
		if (!is_array($this->value)) {
			return null;
		}
		$values = array();
		foreach ($this->value as $i => $subField) {
			if (!$subField->isPresentational() && !$subField->excludeFromData) {
				$values[$i] = $subField->getHumanReadableValue();
			}
		}

		$sep = "\n";
		if ($fieldType == 'group' || is_subclass_of(FormIO::preloadFieldClass($this->getAttribute('fieldtype')), FormIO::preloadFieldClass('group'))) {
			$sep = "\n\n";
		}
		return implode($sep, $values);
	}

	protected function getBuilderVars()
	{
		$vars = FormIOField_Text::getBuilderVars();

		$numInputs	= $this->getMinRequiredInputs($this->getAttribute('numinputs'));
		$maxKey		= -1;
		$spin		= 0;
		$errors		= $this->handleSubErrors();		// $errors now holds any errors not specific to child fields. Others have been assigned to the appropriate fields

		$vars['inputs'] = '';

		// output all present child fields first, and work out the max array key to potentially add more

		if (is_array($this->value)) {
			foreach ($this->value as $idx => $childField) {
				if ($maxKey < $idx) {
					$maxKey = $idx;
				}

				$vars['inputs'] .= $childField->getHTML($spin);

				$numInputs--;
			}
		}

		// create and output any remaining fields required to fill the number requested
		$newIndex = $maxKey + 1;				// keep going from the end of current field array
		while ($numInputs > 0) {				// now add remainder to make up minimum count
			$childField = $this->createSubField($this->getAttribute('fieldtype'), $newIndex);

			$vars['inputs'] .= $childField->getHTML($spin);

			$numInputs--;
			$newIndex++;
		}

		if (sizeof($errors)) {
			$vars['error'] = is_array($errors) ? implode("<br />", $errors) : $errors;	// add any errors which weren't for a particular child field
		} else {
			unset($vars['error']);
		}
		if ($this->getAttribute('fieldtype') == FormIO::T_FILE) {
			$vars['isfiles'] = $this->getName();		// we pass the fieldname as it's the only thing we need to output in that builder string
		}

		unset($vars['value']);

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

	private function getMinRequiredInputs($minNum = 1)
	{
		if ($minNum < sizeof($this->value) + 1) {
			return sizeof($this->value) + 1;
		}
		if ($minNum < 1) {
			return 1;
		}
		return $minNum;
	}

	/**
	 * The repeater validation routine simply has to kick off validation for its subfields,
	 * as well as handling its repeater state variables
	 */
	public function validate()
	{
		$success = parent::validate();

		// kill any fields which don't have a value
		if (is_array($this->value)) {
			foreach ($this->value as $idx => $subField) {
				if ($subField->getValue() === null) {
					unset($this->value[$idx]);
				}
			}
		}

		$add = !empty($this->internals['__add']);
		$remove = !empty($this->internals['__remove']);
		$numSent = sizeof($this->value);

		// check for use of add/remove field buttons, and tell the form to ignore errors if so
		if ($add || $remove) {
			if ($remove) {
				if (sizeof($this->value) == $numSent) {
					end($this->value);
					unset($this->value[key($this->value)]);
					reset($this->value);
				}
				$this->setAttribute('numinputs', $this->getMinRequiredInputs($numSent - 1));
			} else if ($add) {
				$this->setAttribute('numinputs', $this->getMinRequiredInputs($numSent + 1));
			}
			$this->form->delaySubmission = true;
			$this->form->submitted = false;
		}

		return $success;
	}
}
?>
