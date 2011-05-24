<?php
/**
 *
 */

class FormIOField_Sectionbreak extends FormIOField_Fieldsetstart
{
	public $buildString = '{$hasPrevious?</div>}<div class="tab{$classes? $classes}" id="{$id}">';

	/**
	 * @param	bool	$fakeSection	if true, do not add this section break to the parent form's sections array
	 */
	public function __construct($form, $name, $displayText = null, $defaultValue = null)
	{
		if (empty($displayText)) {
			$displayText = "Page {$form->tabCounter}";
		}
		parent::__construct($form, $name, $displayText, $defaultValue);

		$this->setAttribute('hasPrevious', true);
		$this->form->sectionAdded($this);
	}
}
?>
