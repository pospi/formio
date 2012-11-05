<?php
/**
 *
 */

require_once(FORMIO_FIELDS . 'formio_field-captcha.class.php');

class FormIOField_Recaptcha extends FormIOField_Captcha
{
	public $buildString = '<div class="row blck{$alt? alt}{$classes? $classes}">
		<label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label>
		<div class="row" id="{$id}">{$captcha}</div>
		{$error?<p class="err">$error</p>}
		{$hint? <p class="hint">$hint</p>}
	</div>';

	public function handleCreation($parentForm)
	{
		$parentForm->setMethod('POST');
		parent::handleCreation($parentForm);
	}

	protected function getBuilderVars()
	{
		$inputVars = parent::getBuilderVars();
		if (strpos(FormIO::$reCAPTCHA_inc, '/') === 0) {
			require_once(FormIO::$reCAPTCHA_inc);
		} else {
			require_once(FORMIO_LIB . FormIO::$reCAPTCHA_inc);
		}
		$inputVars['captcha'] = recaptcha_get_html(FormIO::$reCAPTCHA_pub);
		return $inputVars;
	}

	// stores result in session, if available. We only need to authenticate as human once.
	final protected function captchaValidator() {
		if ($this->captchaAlreadyPassed()) {
			return true;
		}

		if (strpos(FormIO::$reCAPTCHA_inc, '/') === 0) {
			require_once(FormIO::$reCAPTCHA_inc);
		} else {
			require_once(FORMIO_LIB . FormIO::$reCAPTCHA_inc);
		}

		$resp = recaptcha_check_answer(FormIO::$reCAPTCHA_priv,
						$_SERVER["REMOTE_ADDR"],
						$_POST["recaptcha_challenge_field"],
						$_POST["recaptcha_response_field"]);

		// also set the field's value for external code to inspect, now that we know it's valid
		$this->value = $_POST["recaptcha_challenge_field"];

		if ($resp->is_valid) {
			$this->storeCaptchaPass();
		}
		return $resp->is_valid;
	}
}
?>
