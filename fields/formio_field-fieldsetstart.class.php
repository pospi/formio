<?php
/**
 * This can be used as a base class where the striper should be reset
 */

class FormIOField_Fieldsetstart extends FormIOField_Raw
{
	public $buildString = '<fieldset><legend>{$desc}</legend>';

	public function getHTML(&$spinVar)
	{
		$spinVar = -1;		// reset the striper
		return parent::getHTML($spinVar);
	}
}
?>
