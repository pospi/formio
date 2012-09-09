<?php
/**
 *
 */

class FormIOField_Button extends FormIOField_Text
{
	public $buildString = '<div class="row button{$alt? alt}{$classes? $classes}">
		<label>{$desc}</label>
		<input type="button" name="{$name}" id="{$id}"{$value? value="$value"}{$js? onclick="$js"} />
		{$hint? <p class="hint">$hint</p>}
	</div>';

	public $excludeFromData = true;
}
?>
