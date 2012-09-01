<?php

require_once(FORMIO_FIELDS . 'formio_field-radiogroup.class.php');

class FormIOField_Checkgroup extends FormIOField_Radiogroup
{
	public $subfieldBuildString = '<label><input type="checkbox" name="{$name}[{$value}]"{$disabled? disabled="disabled"}{$checked? checked="checked"} /> {$desc}</label>';

	/**
	 * Coerce string values to booleans for each option
	 */
	public function setValue($newVal)
	{
		// if a normal array is passed, flip it and use as selection
		if (is_array($newVal) && count($newVal) && array_keys($newVal) === range(0, count($newVal) - 1)) {
			$newVal = array_combine($newVal, array_fill(0, count($newVal), true));
		} else {
			foreach ($newVal as &$v) {
				$v = !!$v;
			}
		}

		parent::setValue($newVal);
	}

	public function getHumanReadableValue()
	{
		$output = array();
		$val = $this->getValue();
		if (is_array($val)) {
			foreach ($val as $idx => $choice) {
				if ($choice) {		// this is the only check we need here since unsent checkboxes will not even be set
					$output[] = $this->options[$idx];
				}
			}
		}

		return implode("\n", $output);
	}

	protected function getNextOptionVars()
	{
		if (!$vars = parent::getNextOptionVars()) {
			return false;
		}
		if (isset($this->value[$vars['value']])) {
			$val = $this->value[$vars['value']];
			$vars['checked'] = ($val === true || $val === 'on' || $val === 'true' || (is_numeric($val) && $val > 0));
		}
		return $vars;
	}
}
?>
