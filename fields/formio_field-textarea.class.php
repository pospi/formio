<?php
/**
 *
 */

class FormIOField_Textarea extends FormIOField_Text
{
	public $buildString = '<div class="row{$alt? alt}{$classes? $classes}">
		<label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label>
		<textarea name="{$name}" id="{$id}"{$readonly? readonly="readonly"}{$maxlen? maxlength="$maxlen"}{$dependencies? data-fio-depends="$dependencies"}{$validation? data-fio-validation="$validation"}>{$value}</textarea>
		{$error?<p class="err">$error</p>}
		{$hint? <p class="hint">$hint</p>}
	</div>';
}
?>
