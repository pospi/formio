<?php
/**
 * :TODO: change output format to include a meridian dropdown and validate correctly
 */

class FormIOField_Time extends FormIOField_Date
{
	const timeRegex		= '/^\s*(\d{1,2})((:|\.)(\d{2}))?((:|\.)(\d{2}))?\s*$/';		// capture: hr, , , min, , , sec

	public static $VALIDATOR_ERRORS = array(
		'timeValidator'		=> "Requires a valid time (hh:mm) and time of day",
	);

	protected $validators = array(
		'timeValidator'
	);

	protected function getBuilderVars()
	{
		$inputVars = parent::getBuilderVars();
		$inputVars['behaviour'] = 'time';
		return $inputVars;
	}

	// performs time normalisation
	final protected function timeValidator() {
		if (isset($this->value)) {
			$win = $this->regexValidator(FormIOField_Time::timeRegex);
			if (!$win) {
				return false;
			}
			$this->value = $this->normaliseTime($this->value);
		}
		return true;
	}

	protected function normaliseTime($h = null, $m = null, $s = null) {			// hh:mm(:ss)
		if ($h === null && $m === null && $s === null) {
			return null;
		}
		return str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT) . ($s !== null ? ':' . str_pad($s, 2, '0', STR_PAD_LEFT) : '');
	}
}
?>
