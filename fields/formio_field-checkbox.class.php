<?php
/**
 * Checkboxes are actually sent as arrays in FormIO.
 * If a checkbox's default value is CHECKED, and you want to UNCHECK it, this is not possible without
 * sending a flag that the checkbox was submitted, since unchecked checkboxes aren't sent at all and
 * we have no other way of differentiating the absence of a submission (leave the box checked) with
 * an unchecked submission (uncheck the box).
 */

class FormIOField_Checkbox extends FormIOField_Text
{
	public $buildString = '<div class="row checkbox{$alt? alt}{$classes? $classes}"><label>&nbsp;{$required? <span class="required">*</span>}</label><label class="checkbox"><input type="checkbox" name="{$name}" id="{$id}"{$disabled? disabled="disabled"}{$checked? checked="checked"}{$dependencies? data-fio-depends="$dependencies"} />{$desc}</label>{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>';

	protected $value = false;		// start with a value of FALSE, since checkboxes aren't sent at all when not checked

	// Allow interpreting various truthy/falsey things as our field value
	public function setValue($val)
	{
		if (is_bool($val)) {
			// leave untouched
		} else {
			$val = (strtolower($val) === 'on' || strtolower($val) === 'true' || strtolower($val) === 'yes' || (is_numeric($val) && $val > 0));
		}
		parent::setValue($val);
	}

	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();
		$vars['checked'] = !!$this->value;
		unset($vars['value']);
		return $vars;
	}
}
?>
