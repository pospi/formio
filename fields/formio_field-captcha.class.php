<?php
/**
 * Base class for all CAPTCHAs.
 * You should be able to make use of these simply by overriding captchaValidator() in child classes
 */

abstract class FormIOField_Captcha extends FormIOField_Text
{
	public static $VALIDATOR_ERRORS = array(
		'captchaValidator'	=> "The text entered did not match the verification image",
	);

	protected $validators = array(
		'captchaValidator'
	);

	/**
	 * Captchas aren't set as required since they implicitly already are anyway.
	 * This behaviour happens in the captchaValidator callback
	 */
	public function setRequired()
	{}

	public function getHTML(&$spinVar)
	{
		if ($this->captchaAlreadyPassed()) {
			return null;
		}
		return parent::getHTML($spinVar);
	}

	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();
		$vars['required'] = true;
		return $vars;
	}

	protected function captchaValidator() {
		return true;
	}

	protected function captchaAlreadyPassed()
	{
		return isset($_SESSION[$this->form->CAPTCHA_session_var]) ? $_SESSION[$this->form->CAPTCHA_session_var] : false;
	}

	// stores result in session, if available. We only need to authenticate as human once.
	protected function storeCaptchaPass()
	{
		if (session_id()) {
			$_SESSION[$this->form->CAPTCHA_session_var] = true;
		} else {
			trigger_error("CAPTCHA field unable to save session - user must reauthenticate every refresh", E_USER_NOTICE);
		}
	}
}
?>
