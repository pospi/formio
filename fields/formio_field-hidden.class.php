<?php
/**
 * We are replicating Spacer's spin variable decrement behaviour for row striping
 * but this field differs in that it can carry a value
 */

class FormIOField_Hidden extends FormIOField_Text
{
	public $buildString = '<input type="hidden" name="{$name}" id="{$id}"{$value? value="$value"}{$validation? data-fio-validation="$validation"} />';

	public function getHTML(&$spinVar)
	{
		$spinVar -= 2;		// decrement the striper
		return parent::getHTML($spinVar);
	}
}
?>
