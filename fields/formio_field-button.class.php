<?php
/**
 * Button extends from Submit since we don't want its value included in form data output
 */

class FormIOField_Button extends FormIOField_Submit
{
	public $buildString = '<div class="row{$alt? alt}{$classes? $classes}"><label>{$desc}</label><input type="button" name="{$name}" id="{$id}"{$value? value="$value"}{$js? onclick="$js"} /><p class="hint">{$hint}</p></div>';
}
?>
