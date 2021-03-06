<?php

require_once(FORMIO_FIELDS . 'formio_field-dropdown.class.php');

class FormIOField_Radiogroup extends FormIOField_Dropdown
{
	public $buildString = '<fieldset id="{$id}" class="row multiple col{$columns}{$alt? alt}{$classes? $classes}"{$dependencies? data-fio-depends="$dependencies"}{$validation? data-fio-validation="$validation"}>
		<legend>{$desc}{$required? <span class="required">*</span>}</legend>
		{$options}
		{$error?<p class="err">$error</p>}
		{$hint? <p class="hint">$hint</p>}
	</fieldset>';
	public $subfieldBuildString = '<label><input type="radio" name="{$name}"{$value? value="$value"}{$disabled? disabled="disabled"}{$checked? checked="checked"} /> {$desc}</label>';

	protected function getNextOptionVars()
	{
		if (!$vars = parent::getNextOptionVars()) {
			return false;
		}
		$vars['name'] = $this->getName();
		return $vars;
	}
}
?>
