<?php
/**
 *
 */

class FormIOField_Image extends FormIOField_Raw
{
	public $buildString = '<img id="{$id}" src="{$url}" alt="{$desc}" />';

	public function __construct($form, $name, $displayText = '', $defaultValue = null)
	{
		parent::__construct($form, $name, $displayText, $defaultValue);

		// image 'value' is actually the URL attribute since this field is presentational and has no value
		$this->setValue($defaultValue);
	}

	public function setValue($value)
	{
		$this->setAttribute('url', $value);
	}
}
?>
