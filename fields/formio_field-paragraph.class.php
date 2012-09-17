<?php
/**
 *
 */

class FormIOField_Paragraph extends FormIOField_Raw
{
	public $buildString = '<p id="{$id}" class="{$classes}{$alt? alt}"{$styles? style="$styles"}>{$desc}</p>';
}
?>
