<?php
/**
 *
 */

class FormIOField_Passwordchange extends FormIOField_Text
{
	public $buildString = '<div class="row pwdchange blck{$alt? alt}{$classes? $classes}" id="{$id}">
		<label for="{$id}_0">{$desc}{$required? <span class="required">*</span>}</label>
		<div class="row">
			<input type="password" name="{$name}[0]" id="{$id}_0"{$validation? data-fio-validation="$validation"} />
			<input type="password" name="{$name}[1]" id="{$id}_1" /> (verify)
		</div>
		{$error?<p class="err">$error</p>}
		{$hint? <p class="hint">$hint</p>}
	</div>';

	public static $VALIDATOR_ERRORS = array(
		'chpasswdValidator'	=> "Entered passwords do not match",
	);

	protected $validators = array(
		'chpasswdValidator'
	);

	// normalises new password value by getting rid of confirmation value when ok
	final protected function chpasswdValidator() {
		if ((!empty($this->value[0]) || !empty($this->value[1])) && ($this->value[0] != $this->value[1])) {
			$this->value = null;
			return false;
		}
		$this->value = $this->value[0];
		return true;
	}
}
?>
