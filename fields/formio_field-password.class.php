<?php
/**
 *
 */

class FormIOField_Password extends FormIOField_Text
{
	public $buildString = '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label><input type="password" name="{$name}" id="{$id}" />{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>';
}
?>
