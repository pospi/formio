<?php
/**
 * Reset extends from Submit since we don't want its value included in form data output
 */

class FormIOField_Reset extends FormIOField_Submit
{
	public $buildString = '<input type="reset" name="{$name}" id="{$id}"{$value? value="$value"}{$classes? class="$classes"} />';
}
?>
