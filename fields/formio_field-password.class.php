<?php
/**
 *
 */

class FormIOField_Password extends FormIOField_Text
{
	public $buildString = '<div class="row{$alt? alt}{$classes? $classes}">
		<label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label>
		<input type="password" name="{$name}" id="{$id}"{$validation? data-fio-validation="$validation"}{$readonly? readonly="readonly"} />
		{$error? <p class="err">$error</p>}
		{$hint? <p class="hint">$hint</p>}
	</div>';
}
?>
