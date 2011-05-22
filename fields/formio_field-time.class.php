<?php
/**
 * :TODO: change output format to include a meridian dropdown and validate correctly
 */

class FormIOField_Time extends FormIOField_Date
{
	const timeRegex		= '/^\s*(\d{1,2})((:|\.)(\d{2}))?((:|\.)(\d{2}))?\s*$/';		// capture: hr, , , min, , , sec

	public $buildString = '<div class="row datetime{$alt? alt}{$classes? $classes}"{$dependencies? data-fio-depends="$dependencies"}><label for="{$id}">{$desc}{$required? <span class="required">*</span>}</label><input type="text" name="{$name}[0]" id="{$id}" value="{$value}" data-fio-type="time" class="time" /><select name="{$name}[1]" id="{$id}_meridian">{$am?<option value="am" selected="selected">am</option><option value="pm">pm</option>}{$pm?<option value="am">am</option><option value="pm" selected="selected">pm</option>}</select>{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>';

	public static $VALIDATOR_ERRORS = array(
		'timeValidator'		=> "Requires a valid time (hh:mm) and time of day",
	);

	protected $validators = array(
		'timeValidator'
	);

	public function setValue($val)
	{
		if (is_array($val)) {
			$this->value = $val;
		} else {
			$this->value = $this->strTimeToArrTime($val);
		}
	}

	public function getValue()
	{
		$val = $this->getRawValue();
		return $this->timeTo24hr($val);
	}

	protected function getBuilderVars()
	{
		$inputVars = parent::getBuilderVars();
		$val = $this->getRawValue();
		$inputVars['value'] = $val[0];
		$inputVars['pm']	= $val[1] == 'pm';
		$inputVars['am']	= $val[1] != 'pm';
		return $inputVars;
	}

	private function strTimeToArrTime($str)
	{
		if (preg_match(FormIOField_Time::timeRegex, $str, $matches)) {
			$this->value = array();
			if ($matches[1] > 23 || (isset($matches[4]) && $matches[4] > 59) || (isset($matches[7]) && $matches[7] > 59)) {
				return null;
			}
			if ($matches[1] > 11) {
				$this->value[1] = 'pm';
				$matches[1] -= 12;
			} else {
				$this->value[1] = 'am';
				if ($matches[1] == 0) {
					$matches[1] = 12;
				}
			}
			$this->value[0] = $this->normaliseTime($matches[1], isset($matches[4]) ? $matches[4] : null, isset($matches[7]) ? $matches[7] : null);
		}
		return null;
	}

	private function timeTo24hr($arr)
	{
		if (is_array($this->value) && preg_match(FormIOField_Time::timeRegex, $this->value[0], $matches)) {
			if ($this->value[1] == 'pm') {
				if ($matches[1] != 12) {
					$matches[1] += 12;
				}
			} else if ($matches[1] == 12) {
				$matches[1] = 0;
			}
			return $this->normaliseTime($matches[1], isset($matches[4]) ? $matches[4] : null, isset($matches[7]) ? $matches[7] : null);
		}
		return null;
	}

	// performs time normalisation
	final protected function timeValidator() {
		if (is_array($this->value) && !empty($this->value[0])) {
			if (preg_match(FormIOField_Time::timeRegex, $this->value[0], $matches)) {
				if ($matches[1] > 12 || (isset($matches[4]) && $matches[4] > 59) || (isset($matches[7]) && $matches[7] > 59)) {
					return false;
				}
			} else {
				return false;
			}
			$this->value[0] = $this->normaliseTime($matches[1], isset($matches[4]) ? $matches[4] : null, isset($matches[7]) ? $matches[7] : null);
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
