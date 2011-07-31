<?php
/**
 * A grouping of fields
 *
 * This allows you to manage several inputs as if they are a single entity.
 * Very useful when combined with a Repeater field to repeat sets of inputs.
 */

class FormIOField_Group extends FormIOField_Text
{
	public $buildString = '<div class="row group{$alt? alt}{$classes? $classes}" id="{$id}"{$dependencies? data-fio-depends="$dependencies"}{$validation? data-fio-validation="$validation"}>
		<label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label>
		{$error?<p class="err">$error</p>}{$inputs}
		<p class="hint">{$hint}</p></div>';

	public function addChild($fieldObj, $idx = null)
	{
		if (!isset($idx)) {
			$this->value[] = $fieldObj;
		} else {
			$this->value[$idx] = $fieldObj;
		}
	}

	public function removeChild($idx)
	{
		unset($this->value[$idx]);
	}

	// creates a new FormIO field and injects it into the $value array
	public function createSubField($fieldType, $index = null, $desc = null)
	{
		if ($index === null) {
			$index = sizeof($this->value);
		}
		// we don't array index subfields for file inputs, as they don't support array sending
		if ($fieldType == 'file' || is_subclass_of(FormIO::preloadFieldClass($fieldType), FormIO::preloadFieldClass('file'))) {
			$name = $this->getName() . '_f' . $index;
		} else {
			$name = $this->getName() . '[' . $index . ']';
		}
		$field = FormIO::loadFieldByClass($fieldType, $name, $desc, $this->form);

		if ($field) {
			$this->addChild($field, $index);
		}

		return $field;
	}

	public function setValue($values)
	{
		if (empty($values)) {
			return;
		}
		foreach ($values as $i => $val) {
			if (isset($this->value[$i])) {
				$this->value[$i]->setValue($val);
			}
		}
	}

	public function getValue()
	{
		if (!is_array($this->value)) {
			return null;
		}
		$values = array();
		foreach ($this->value as $i => $subField) {
			if (!$subField->isPresentational() && !$subField->excludeFromData) {
				$val = $subField->getValue();
				if ($val !== null) {
					$values[$i] = $val;
				}
			}
		}
		return sizeof($values) ? $values : null;
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
		return implode("\n", $values);
	}

	public function getRawValue()
	{
		if (!is_array($this->value)) {
			return null;
		}
		$values = array();
		foreach ($this->value as $i => $subField) {
			if (!$subField->isPresentational() && !$subField->excludeFromData) {
				$values[$i] = $subField->getRawValue();
			}
		}
		return $values;
	}

	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();

		$spin = 0;
		$errors = $this->handleSubErrors();		// $errors now holds any errors not specific to child fields

		$vars['inputs'] = '';

		// output child fields for substitution into builder '$inputs'
		foreach ($this->value as $idx => $childField) {
			$vars['inputs'] .= $childField->getHTML($spin);
		}

		// add any errors which weren't for a particular child field
		if (sizeof($errors)) {
			$vars['error'] = is_array($errors) ? implode("<br />", $errors) : $errors;
		} else {
			unset($vars['error']);
		}

		unset($vars['value']);

		return $vars;
	}

	/**
	 * kick off validation for our subfields
	 */
	public function validate()
	{
		$success = parent::validate();

		if (is_array($this->value)) {
			foreach ($this->value as $subKey => $subField) {
				// ignore any presentational fields
				if ($subField->isPresentational()) {
					continue;
				}
				// Run internal validation routines for subfields
				if ($subField->getValue() !== null && !$subField->validate()) {
					$success = false;
				}
			}
		}

		return $success;
	}

	/**
	 * Handles the error data array for this field, and assigns appropriate
	 * errors to the child inputs. Any leftover errors are assumed to be for
	 * the parent field (ie this one), and returned as-is.
	 */
	protected function handleSubErrors()
	{
		$errors = $this->getErrors();		// this will be an array of errors, indexed by child field key

		$vars['inputs'] = '';

		if (is_array($errors)) {
			foreach ($this->value as $idx => $childField) {
				$childError = null;
				if (isset($errors[$idx])) {
					$childError = is_array($errors[$idx]) ? implode("<br />", $errors[$idx]) : $errors[$idx];
					unset($errors[$idx]);					// mark this error as handled
				}

				if ($childError !== null) {
					$childField->setAttribute('error', $childError);
				}
			}
		}

		return $errors;
	}
}
?>
