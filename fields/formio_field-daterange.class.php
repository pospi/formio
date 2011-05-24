<?php
/**
 *
 */

class FormIOField_Daterange extends FormIOField_Date
{
	public $buildString = '<div class="row daterange{$alt? alt}{$classes? $classes}" id="{$id}"{$dependencies? data-fio-depends="$dependencies"}><label for="{$id}_start">{$desc}{$required? <span class="required">*</span>}</label><input type="text" name="{$name}[0]" id="{$id}_start"{$value? value="$value"} data-fio-type="date" /> - <input type="text" name="{$name}[1]" id="{$id}_end" value="{$valueEnd}" data-fio-type="date" />{$error?<p class="err">$error</p>}<p class="hint">{$hint}</p></div>';

	public static $VALIDATOR_ERRORS = array(
		'dateRangeValidator'		=> "Dates must be in dd/mm/yyyy format",
	);

	protected $validators = array(
		'dateRangeValidator'
	);

	public function setValue($value)
	{
		if (is_array($value) && !is_numeric($value[0])) {
			$this->value = $value;
		} else {
			$this->value = array($this->timestampToDate($value[0]), $this->timestampToDate($value[1]));
		}
	}

	public function getValue()
	{
		$val = $this->getRawValue();
		if (!is_array($val) || empty($val[0]) || empty($val[1])) {
			return null;
		}
		return array($this->dateToUnix($val[0]), $this->dateToUnix($val[1]));
	}

	public function getHumanReadableValue()
	{
		$val = $this->getRawValue();
		return $val[0] . ' to '. $val[1];
	}

	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();
		unset($vars['behaviour']);
		$vars['value']		= $this->value[0];
		$vars['valueEnd']	= $this->value[1];
		return $vars;
	}

	/**
	 * We should use the arrayRequiredValidator if the field is required
	 */
	public function setRequired()
	{
		$this->addValidator('arrayRequiredValidator', array(), false);
	}

	// performs date normalisation
	final protected function dateRangeValidator() {
		if (isset($this->value) && is_array($this->value) && (!empty($this->value[0]) || !empty($this->value[1]))) {
			$firstOk	= preg_match(FormIOField_Date::dateRegex, $this->value[0], $matches1);
			$secondOk 	= preg_match(FormIOField_Date::dateRegex, $this->value[1], $matches2);
			if (!$firstOk || !$secondOk) {
				return false;
			}

			if ($matches1[1] > 31 || $matches1[2] > 12 || $matches2[1] > 31 || $matches2[2] > 12) {
				return false;
			}

			$this->value[0] = $this->normaliseDate($matches1[1], $matches1[2], $matches1[3]);
			$this->value[1] = $this->normaliseDate($matches2[1], $matches2[2], $matches2[3]);

			// also swap the values if they are in the wrong order
			if (($matches1[3] > $matches2[3])
			 || ($matches1[3] >= $matches2[3] && $matches1[2] > $matches2[2])
			 || ($matches1[3] >= $matches2[3] && $matches1[2] >= $matches2[2] && $matches1[1] > $matches2[1])) {
				$temp = $this->value[0];
				$this->value[0] = $this->value[1];
				$this->value[1] = $temp;
			}
			return true;
		}
		return true;		// not set, so validate as OK and let requiredValidator pick it up if required
	}
}
?>
