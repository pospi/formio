<?php
/**
 * If you extend from this class, the results won't be output in
 * the form's data array unless you have flagged to include submission values.
 * This may be useful in some circumstances
 */

class FormIOField_Submit extends FormIOField_Text
{
	public $buildString = '<input type="submit" name="{$name}" id="{$id}"{$desc? value="$desc"}{$classes? class="$classes"}{$styles? style="$styles"} />';

	public function inputNotProvided()
	{
		$this->setValue(null);
	}
}
?>
