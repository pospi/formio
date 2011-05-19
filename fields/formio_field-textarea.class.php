<?php
/**
 *
 */

class FormIOField_Textarea extends FormIOField_Text
{
	public $buildString = '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label><textarea name="{$name}" id="{$id}"{$maxlen? maxlength="$maxlen"}{$dependencies? data-fio-depends="$dependencies"}>{$value}</textarea>{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>';
}
?>
