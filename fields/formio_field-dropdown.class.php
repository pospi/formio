<?php

class FormIOField_Dropdown extends FormIOField_Multiple
{
	public $buildString = '<div class="row{$alt? alt}{$classes? $classes}">
		<label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label>
		<select id="{$id}" name="{$name}"{$dependencies? data-fio-depends="$dependencies"}{$validation? data-fio-validation="$validation"}{$readonly? disabled="disabled"}>
			{$options}
		</select>
		{$error?<p class="err">$error</p>}
		{$hint? <p class="hint">$hint</p>}
	</div>';

	public $subfieldBuildString = '<option{$value? value="$value"}{$disabled? disabled="disabled"}{$checked? selected="selected"}>{$desc}</option>';

	private $subgroupBuildString = '<optgroup label="{$desc}">';
	private $subgroupEndBuildString = '</optgroup>';

	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();

		// build all option elements by performing replacements and concatenating the output
		reset($this->options);
		$optionsStr = '';
		while ($subVars = $this->getNextOptionVars()) {
			// determine the build string we will be using when we process these vars
			$subfieldStr = $this->subfieldBuildString;
			if (!empty($subVars['group'])) {
				$subfieldStr = $this->subgroupBuildString;
			} else if (!empty($subVars['groupend'])) {
				$subfieldStr = $this->subgroupEndBuildString;
			}

			$optionsStr .= $this->replaceInputVars($subfieldStr, $subVars);
		}
		reset($this->options);
		$vars['options'] = $optionsStr;

		$vars['columns'] = isset($this->attributes['columns']) ? $this->attributes['columns'] : 2;	// subclasses may or may not use this attribute
		return $vars;
	}

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
			if (isset($vars['desc']['group']))					$vars['group']		= $vars['desc']['group'];
			if (isset($vars['desc']['groupend']))				$vars['groupend']	= $vars['desc']['groupend'];
			$vars['desc'] = isset($vars['desc']['desc']) ? $vars['desc']['desc'] : '';
		}

		if (!empty($this->attributes['readonly'])) {
			$vars['disabled'] = true;
		}

		// determine whether option should be selected if it hasn't explicitly been set
		if ($valueSent && $this->value == $vars['value']) {
			$vars['checked'] = true;
		}

		return $vars;
	}
}
?>
