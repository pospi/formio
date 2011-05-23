<?php
/**
 * Button extends from Reset & Submit since we don't want its value included in form data output either
 */

require_once(FORMIO_FIELDS . 'formio_field-reset.class.php');

class FormIOField_Button extends FormIOField_Reset
{
	public $buildString = '<div class="row{$alt? alt}{$classes? $classes}"><label>{$desc}</label><input type="button" name="{$name}" id="{$id}"{$value? value="$value"}{$js? onclick="$js"} /><p class="hint">{$hint}</p></div>';
}
?>
