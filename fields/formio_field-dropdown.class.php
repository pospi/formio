<?php

class FormIOField_Dropdown extends FormIOField_Multiple
{
	public $buildString = '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label><select id="{$id}" name="{$name}"{$dependencies? data-fio-depends="$dependencies"}>{$options}</select>{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>';
	public $subfieldBuildString = '<option{$value? value="$value"}{$disabled? disabled="disabled"}{$checked? selected="selected"}>{$desc}</option>';

	protected function getNextOptionVars()
	{
		if (!$vars = parent::getNextOptionVars()) {
			return false;
		}

		$valueSent = $this->value != null && $this->value !== '';

		// unpack any extra option properties which were packed into its value
		if (is_array($vars['desc'])) {
			if (isset($vars['desc']['disabled']))				$vars['disabled']	= $vars['desc']['disabled'];
			if (isset($vars['desc']['checked']) && !$valueSent)	$vars['checked']	= $vars['desc']['checked'];
			$vars['desc'] = $vars['desc']['desc'];
		}

		// determine whether option should be selected if it hasn't explicitly been set
		if ($valueSent && $this->value == $vars['value']) {
			$vars['checked'] = true;
		}
		return $vars;
	}
}
?>
