<?php
/**
 *
 */

class FormIOField_Date extends FormIOField_Text
{
	const dateRegex		= '/^\s*(\d{1,2})\/(\d{1,2})\/(\d{2}|\d{4})\s*$/';	// capture: day, month, year

	public static $VALIDATOR_ERRORS = array(
		'dateValidator'		=> "Requires a valid date in dd/mm/yyyy format",
	);

	protected $validators = array(
		'dateValidator'
	);

	public function setValue($val)
	{
		if (is_numeric($val)) {
			$this->value = $this->timestampToDate($val);
		} else {
			$this->value = $val;
		}
	}

	public function getValue()
	{
		$val = $this->getRawValue();
		return $this->dateToUnix($val);
	}

	public function getHumanReadableValue()
	{
		return date("d/m/Y", $this->getValue());
	}

	protected function getBuilderVars()
	{
		$inputVars = parent::getBuilderVars();
		$inputVars['behaviour'] = 'date';
		return $inputVars;
	}

	public static function timestampToDate($val)
	{
		if (!$val || FormIOField_Date::arrayAllNull($val)) {
			return null;
		}
		return date("d/m/Y", $val);
	}

	public static function dateToUnix($val)
	{
		$bits = explode('/', $val);
		if (!isset($bits[2])) {
			return null;
		}
		return mktime(0, 0, 0, $bits[1], $bits[0], $bits[2]);
	}

	// performs date normalisation
	final protected function dateValidator() {
		preg_match(FormIOField_Date::dateRegex, $this->value, $matches);
		$success = sizeof($matches) == 4;
		if ($matches[1] > 31 || $matches[2] > 12) {
			return false;
		}
		if ($success) {
			$this->value = $this->normaliseDate($matches[1], $matches[2], $matches[3]);
		}
		return $success != false;
	}

	protected function normaliseDate($d, $m, $y) {				// dd/mm/yyyy
		if ($d === null || $m === null || $y === null) {
			return '';
		}
		$yearPadStr = '20';
		if ($y < 100 && $y > 69) {
			$yearPadStr = '19';
		}

		return str_pad($d, 2, '0', STR_PAD_LEFT) . '/' . str_pad($m, 2, '0', STR_PAD_LEFT) . '/' . str_pad($y, 4, $yearPadStr, STR_PAD_LEFT);
	}

	// helper for checking empty timestamp values
	// :TODO: move this somewhere more appropriate
	private static function arrayAllNull($var)
	{
		foreach ($var as $val) {
			if (is_array($val)) {
				if (!FormIOField_Date::arrayAllNull($val)) {
					return false;
				}
			} else if (!is_null($val)) {
				return false;
			}
		}
		return true;
	}
}
?>
