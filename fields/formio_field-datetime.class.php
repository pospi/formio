<?php
/**
 *
 */

class FormIOField_Datetime extends FormIOField_Time
{
	public $buildString = '<div class="row datetime{$alt? alt}{$classes? $classes}" id="{$id}"{$dependencies? data-fio-depends="$dependencies"}><label for="{$id}_time">{$desc}{$required? <span class="required">*</span>}</label><input type="text" name="{$name}[0]" id="{$id}_date"{$value? value="$value"} data-fio-type="date" class="date" /> at <input type="text" name="{$name}[1]" id="{$id}_time" value="{$valueTime}" data-fio-type="time" class="time" /><select name="{$name}[2]" id="{$id}_meridian">{$am?<option value="am" selected="selected">am</option><option value="pm">pm</option>}{$pm?<option value="am">am</option><option value="pm" selected="selected">pm</option>}</select>{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>';

	public static $VALIDATOR_ERRORS = array(
		'dateTimeValidator'		=> "Requires a valid date (dd/mm/yyyy), time (hh:mm) and time of day",
	);

	protected $validators = array(
		'dateTimeValidator'
	);

	public function setValue($val)
	{
		if (is_array($val)) {
			$this->value = $val;
		} else {
			$this->value = $this->timestampToDateTime($val);
		}
	}

	public function getValue()
	{
		$val = $this->getRawValue();
		return $this->dateTimeToUnix($val);
	}

	public function getHumanReadableValue()
	{
		return $this->dateTimeToHuman($this->getValue());
	}

	protected function getBuilderVars()
	{
		$inputVars = FormIOField_Text::getBuilderVars();
		if (is_array($this->value) && isset($this->value[0]) && isset($this->value[1]) && isset($this->value[2])) {
			$inputVars['value']		= $this->value[0];
			$inputVars['valueTime']	= $this->value[1];
			$inputVars['pm']		= $this->value[2] == 'pm';
			$inputVars['am']		= $this->value[2] != 'pm';
		} else {
			$inputVars['am']		= true;	// where no value is set, this must be present in order to output meridian values
		}
		return $inputVars;
	}

	/**
	 * We should use the arrayRequiredValidator if the field is required
	 */
	public function setRequired()
	{
		$this->addValidator('arrayRequiredValidator', array(), false);
	}

	public function timestampToDateTime($val)
	{
		if (!$val) {
			return null;
		}
		$format = "h:i";
		if ($secs = date('s', $val) && intval($secs) != 0) {
			$format = $format . ":$secs";
		}

		return array(
			$this->timestampToDate($val),
			date($format, $val),
			date('a', $val)
		);
	}

	public function dateTimeToUnix($val)
	{
		@list($hr, $min, $sec) = explode(':', $val[1]);
		if ($hr === null || $min === null) {
			return null;
		} else if ($val[2] == 'pm') {
			if ($hr != 12) {
				$hr += 12;
			}
		} else if ($hr == 12) {
			$hr = 0;
		}
		return $this->dateToUnix($val[0]) + $hr*3600 + $min*60 + ($sec ? $sec : 0);
	}

	public function dateTimeToHuman($val)
	{
		$secs = date('s', $val);
		if (intval($secs) > 0) {
			$secs = ':' . $secs;
		} else {
			$secs = '';
		}
		return ltrim(date("h:i{$secs}a D d/m/Y", $val), '0');
	}

	// performs date and time normalisation
	final protected function dateTimeValidator() {
		if (isset($this->value) && is_array($this->value)) {
			// either both or none must be set
			if (empty($this->value[0]) ^ empty($this->value[1])) {
				return false;
			}
			if (empty($this->value[0])) {		// none set, nothing being sent
				$this->value = array();
				return true;
			}

			$dateOk = preg_match(FormIOField_Date::dateRegex, $this->value[0], $dateMatches);
			$timeOk = preg_match(FormIOField_Time::timeRegex, $this->value[1], $timeMatches);

			if (!$dateOk || !$timeOk) {
				return false;
			}
			if ($dateMatches[1] > 31 || $dateMatches[2] > 12 || $timeMatches[1] > 12 || (isset($timeMatches[4]) && $timeMatches[4] > 59) || (isset($timeMatches[7]) && $timeMatches[7] > 59)) {
				return false;
			}

			$this->value = array(
								$this->normaliseDate($dateMatches[1], $dateMatches[2], $dateMatches[3]),
								$this->normaliseTime($timeMatches[1], (isset($timeMatches[4]) ? $timeMatches[4] : 0), (isset($timeMatches[7]) ? $timeMatches[7] : null)),
								$this->value[2]
							);
		}
		return true;
	}
}
?>
