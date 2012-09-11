<?php
/**
 * If you extend from this class, the results won't be output in
 * the form's data array unless you have flagged to include submission values.
 * This may be useful in some circumstances
 */

class FormIOField_Submit extends FormIOField_Text
{
	public $buildString = '<input type="submit" name="{$name}" id="{$id}"{$desc? value="$desc"} class="{$hidden_default_action?hidden_default_action}{$classes? $classes}"{$styles? style="$styles"} />';

	// Allows submit buttons to share the same field name
	private $realName;
	private static $totalSubmitButtons = 0;

	public function inputNotProvided()
	{
		$this->setValue(null);
	}

	public function getName()
	{
		return $this->realName;
	}

	public function setName($name)
	{
		$this->realName = $name;
		parent::setName($name . '_' . (self::$totalSubmitButtons++));
	}

	protected function getBuilderVars()
	{
		$vars = parent::getBuilderVars();
		if (!empty($vars['hidden_default_action'])) {
			$vars['id'] = '_DEF_' . $vars['id'];
		}
		return $vars;
	}
}
?>
