<?php
/**
 *
 */

class FormIOField_Autocomplete extends FormIOField_Text
{
	public $buildString = '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label><input type="text" name="{$name}" id="{$id}"{$value? value="$value"}{$maxlen? maxlength="$maxlen"}{$behaviour? data-fio-type="$behaviour"}{$validation? data-fio-validation="$validation"} data-fio-searchurl="{$searchurl}"{$dependencies? data-fio-depends="$dependencies"} />{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>';
}
?>
