<?php
/**
 * :TODO: prevent overriding field values
 */

class FormIOField_Readonly extends FormIOField_Text
{
	public $buildString = '<div class="row{$alt? alt}{$classes? $classes}">
		<label for="{$id}">{$desc}</label>
		<div class="readonly">{$value}</div>
		<input type="hidden" name="{$name}" id="{$id}"{$escapedvalue? value="$escapedvalue"} />
		{$error?<p class="err">$error</p>}
		{$hint? <p class="hint">$hint</p>}
	</div>';

	protected function getBuilderVars()
	{
		$inputVars = parent::getBuilderVars();
		$inputVars['escapedvalue'] = $this->_attr($this->value);
		return $inputVars;
	}
}
?>
