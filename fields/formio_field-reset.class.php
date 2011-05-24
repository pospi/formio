<?php
/**
 * Reset extends from Button since we don't want its value included in form data output
 */

require_once(FORMIO_FIELDS . 'formio_field-button.class.php');

class FormIOField_Reset extends FormIOField_Button
{
	public $buildString = '<input type="reset" name="{$name}" id="{$id}"{$value? value="$value"}{$classes? class="$classes"} />';
}
?>
