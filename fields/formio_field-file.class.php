<?php
/**
 * :TODO:
 */

class FormIOField_File extends FormIOField_Text
{
	public $buildString = '<div class="row{$alt? alt}{$classes? $classes}"><label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label><input type="file" name="{$name}" id="{$id}"{$required? data-fio-validation="requiredValidator"} />{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>';

	public static $VALIDATOR_ERRORS = array(
		'fileUploadValidator'	=> "File upload failed",
		'fileInvalid1'/*UPLOAD_ERR_INI_SIZE*/	=> "File too big (exceeded system size)",
		'fileInvalid2'/*UPLOAD_ERR_FORM_SIZE*/	=> "File too big (exceeded form size)",
		'fileInvalid3'/*UPLOAD_ERR_PARTIAL*/	=> "File upload interrupted",
		'fileInvalid6'/*UPLOAD_ERR_NO_TMP_DIR*/	=> "Could not save file",
		'fileInvalid7'/*UPLOAD_ERR_CANT_WRITE*/	=> "Could not write file",
		'fileInvalid8'/*UPLOAD_ERR_EXTENSION*/	=> "Upload prevented by server extension",
	);

	protected $validators = array(
		'fileUploadValidator'
	);

	public function handleCreation($parentForm)
	{
		$parentForm->setMultipart(true);
		parent::handleCreation($parentForm);
	}

	final protected function fileUploadValidator() {
		// :TODO: handle adding errors when we are nested inside a repeater ($this->name won't be enough then!)

		$ok = true;
		switch ($this->value['error']) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
			case UPLOAD_ERR_PARTIAL:
			case UPLOAD_ERR_NO_TMP_DIR:
			case UPLOAD_ERR_CANT_WRITE:
			case UPLOAD_ERR_EXTENSION:
				$ok = false;
				$this->form->addError($this->getName(), FormIOField_File::$VALIDATOR_ERRORS['fileInvalid' . $this->value['error']]);
		}
		return $ok;
	}
}
?>
