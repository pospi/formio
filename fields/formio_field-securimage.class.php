<?php
/**
 *
 */

require_once(FORMIO_FIELDS . 'formio_field-captcha.class.php');

class FormIOField_Securimage extends FormIOField_Captcha
{
	public $buildString = '<div class="row blck{$alt? alt}{$classes? $classes}" data-fio-type="securimage">
		<label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label>
		<div class="row">
			<input type="text" name="{$name}" id="{$id}" {$maxlen? maxlength="$maxlen"} data-fio-validation="requiredValidator" />
			<img src="{$captchaImage}" alt="CAPTCHA Image" class="captcha" />
			<a class="reload" href="javascript: void(0);">Reload image</a>
		</div>
		{$error?<p class="err">$error</p>}
		{$hint? <p class="hint">$hint</p>}
	</div>';

	protected function getBuilderVars()
	{
		$inputVars = parent::getBuilderVars();
		if (strpos(FormIO::$securImage_inc, '/') === 0) {
			require_once(FormIO::$securImage_inc);
		} else {
			require_once(FORMIO_LIB . FormIO::$securImage_inc);
		}
		$inputVars['captchaImage'] = FormIO::$securImage_img;
		return $inputVars;
	}

	// stores result in session, if available. We only need to authenticate as human once.
	final protected function captchaValidator() {
		if ($this->captchaAlreadyPassed()) {
			return true;
		}

		$ok = false;
		require_once(FormIO::$securImage_inc);
		$securimage = new Securimage();
		if ($securimage->check($this->value)) {
			$ok = true;
		}

		if ($ok) {
			$this->storeCaptchaPass();
		}
		return $ok;
	}
}
?>
