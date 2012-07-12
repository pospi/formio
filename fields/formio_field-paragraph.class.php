<?php
/**
 *
 */

class FormIOField_Paragraph extends FormIOField_Raw
{
	public $buildString = '<p id="{$id}"{$classes? class="$classes"}>{$desc}</p>';
}
?>
