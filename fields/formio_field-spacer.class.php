<?php
/**
 * Base class for fields which don't increment the table row striper
 */

class FormIOField_Spacer extends FormIOField_Raw
{
	public $buildString = '';

	public function getHTML(&$spinVar)
	{
		$spinVar -= 2;		// decrement the striper
		return parent::getHTML($spinVar);
	}
}
?>
