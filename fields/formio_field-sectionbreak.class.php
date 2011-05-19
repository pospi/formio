<?php
/**
 *
 */

class FormIOField_Sectionbreak extends FormIOField_Fieldsetstart
{
	public $buildString = '</div><div class="tab" id="{$id}">';

	protected function getBuilderVars()
	{
		$inputVars = parent::getBuilderVars();
		$inputVars['id'] = $this->getFieldId("tab" . ++$this->form->tabCounter);	// override ID with form's incremented tab counter var
		return $inputVars;
	}
}
?>
