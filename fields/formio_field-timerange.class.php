<?php
/**
 * :TODO: reorder components when provided in the wrong order
 */

class FormIOField_Timerange extends FormIOField_DateTime
{
	public $buildString = '<div class="row daterange datetime{$alt? alt}{$classes? $classes}" id="{$id}"{$dependencies? data-fio-depends="$dependencies"}{$validation? data-fio-validation="$validation"}>
		<label for="{$id}_start">{$desc}{$required? <span class="required">*</span>}</label>
		<span class="timerange"><span class="fromtime">
			<input type="text" name="{$name}[0][0]" id="{$id}_0_date"{$startdate? value="$startdate"}{$readonly? readonly="readonly"} data-fio-type="date"{$required? data-fio-validation="requiredValidator"} class="date" />
			 at <input type="text" name="{$name}[0][1]" id="{$id}_0_time"{$starttime? value="$starttime"}{$readonly? readonly="readonly"} data-fio-type="time"{$required? data-fio-validation="requiredValidator"} class="time" />
			<select name="{$name}[0][2]" id="{$id}_0_meridian"{$readonly? disabled="disabled"}>
				{$startam?<option value="am" selected="selected">am</option><option value="pm">pm</option>}
				{$startpm?<option value="am">am</option><option value="pm" selected="selected">pm</option>}
			</select>
		</span> to <span class="totime">
			<input type="text" name="{$name}[1][0]" id="{$id}_1_date"{$enddate? value="$enddate"}{$readonly? readonly="readonly"} data-fio-type="date"{$required? data-fio-validation="requiredValidator"} class="date" />
			 at <input type="text" name="{$name}[1][1]" id="{$id}_1_time"{$endtime? value="$endtime"}{$readonly? readonly="readonly"} data-fio-type="time"{$required? data-fio-validation="requiredValidator"} class="time" />
			<select name="{$name}[1][2]" id="{$id}_1_meridian"{$readonly? disabled="disabled"}>
				{$endam?<option value="am" selected="selected">am</option><option value="pm">pm</option>}
				{$endpm?<option value="am">am</option><option value="pm" selected="selected">pm</option>}
			</select>
		</span></span>
		{$error?<p class="err">$error</p>}
		{$hint? <p class="hint">$hint</p>}
	</div>';

	public static $VALIDATOR_ERRORS = array(
		'timeRangeValidator'=> "Invalid date (dd/mm/yyyy) or time (hh:mm)",
	);

	protected $validators = array(
		'timeRangeValidator'
	);

	public function setValue($value)
	{
		if (is_array($value) && is_array($value[0])) {
			$this->value = $value;
		} else {
			$this->value = array($this->timestampToDateTime($value[0]), $this->timestampToDateTime($value[1]));
		}
	}

	public function getValue()
	{
		$val = $this->getRawValue();
		if (!is_array($val) || !is_array($val[0]) || !is_array($val[1])) {
			return null;
		}
		return array($this->dateTimeToUnix($val[0]), $this->dateTimeToUnix($val[1]));
	}

	public function getHumanReadableValue()
	{
		$val = $this->getValue();
		if (!is_array($val) || !isset($val[0]) || !isset($val[1])) {
			return '';
		}
		return $this->dateTimeToHuman($val[0]) . ' to '. $this->dateTimeToHuman($val[1]);
	}

	protected function getBuilderVars()
	{
		$inputVars = FormIOField_Text::getBuilderVars();
		unset($inputVars['value']);
		$inputVars['startdate']	= $this->_attr($this->value[0][0]);
		$inputVars['enddate']	= $this->_attr($this->value[1][0]);
		$inputVars['starttime']	= $this->_attr($this->value[0][1]);
		$inputVars['endtime']	= $this->_attr($this->value[1][1]);
		$inputVars['startam']	= $this->value[0][2] != 'pm';
		$inputVars['endam']		= $this->value[1][2] != 'pm';
		$inputVars['startpm']	= $this->value[0][2] == 'pm';
		$inputVars['endpm']		= $this->value[1][2] == 'pm';
		return $inputVars;
	}

	/**
	 * We should use the arrayRequiredValidator if the field is required
	 */
	public function setRequired()
	{
		$this->addValidator('arrayRequiredValidator', array(), false);
	}

	// performs date and time normalisation
	final protected function timeRangeValidator() {
		if (is_array($this->value)) {
			// either both or none must be set
			if ((empty($this->value[0][0]) && empty($this->value[0][1])) ^ (empty($this->value[1][0]) && empty($this->value[1][1]))) {
				return false;
			}
			if (empty($this->value[0][0])) {		// none set, nothing being sent
				$this->value = null;
				return true;
			}

			foreach ($this->value as &$datetime) {
				$dateOk = preg_match(FormIOField_Date::dateRegex, $datetime[0], $dateMatches);
				$timeOk = preg_match(FormIOField_Time::timeRegex, $datetime[1], $timeMatches);

				if (!$dateOk || !$timeOk) {
					return false;
				}
				if ($dateMatches[1] > 31 || $dateMatches[2] > 12 || $timeMatches[1] > 12 || (isset($timeMatches[4]) && $timeMatches[4] > 59)) {
					return false;
				}

				$datetime = array(
								$this->normaliseDate($dateMatches[1], $dateMatches[2], $dateMatches[3]),
								$this->normaliseTime($timeMatches[1], (isset($timeMatches[4]) ? $timeMatches[4] : 0), (isset($timeMatches[7]) ? $timeMatches[7] : null)),
								$datetime[2]
							);
			}
			return true;
		}
		return false;
	}
}
?>
