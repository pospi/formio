<?php
/**
 * Simple form data interrogation layer to provide confirmation pages.
 *
 * @author		Sam Pospischil <pospi@spadgos.com>
 * @date		2011-05-27
 */

class FormIO_ConfirmPage
{
	private $form;
	private $callback;
	private $ignoreFields = array();

	private $tableText = "";

	/**
	 * @param	FormIO		$form					form to generate a confirmation page for
	 * @param	callback	$preGenerationCallback	a callback to run before the internal walker which generates the table. Use this to ignore fields or inject extra table rows.
	 */
	public function __construct($form, $preGenerationCallback = null)
	{
		$this->form = $form;
		if ($preGenerationCallback) {
			$this->callback = $preGenerationCallback;
		}
	}

	// generates the confirmation page as an HTML table
	public function getTable()
	{
		$this->form->walkData(array($this, 'walkFormKeys'), array(), array($this, 'walkFormValues'), array());

		return "{$this->form->getFormTag()}
			<table cellpadding=\"0\" cellspacing=\"0\" class=\"formio\">
				{$this->tableText}
			</table>
			<input type=\"submit\" name=\"confirm\" value=\"Confirm submission\" />
			<input type=\"submit\" name=\"change\" value=\"Modify\" />
			</form>";
	}

	public function addRow($col1, $col2 = null)
	{
		if (isset($col2)) {
			$this->tableText .= "<tr><td>$col1</td><td>$col2</td></tr>";
		} else {
			$this->tableText .= "<tr><td colspan=\"2\">$col1</td></tr>";
		}
	}

	// exclude a field from the output HTML
	public function ignoreField($name)
	{
		$this->ignoreFields[$name] = true;
	}

	/**
	 * Form field walker callback to retrive the human readable value (for display)
	 * as well as outputting the real value in hidden inputs (for postback)
	 */
	public function walkFormValues($field)
	{
		if (isset($this->ignoreFields[$field->getName()])) {
			return null;
		}

		$this->tableText .= "<tr><td>" . htmlentities($field->getHumanReadableName()) . "</td>"
						  . "<td>" . htmlentities($field->getHumanReadableValue()) . $this->makeHiddenInputs($field->getName(), $field->getRawValue()) . "</td></tr>";

		return null;
	}

	// executed before walkFormValues() to allow client code to manipulate the table
	public function walkFormKeys($field)
	{
		if (isset($this->callback)) {
			call_user_func($this->callback, $this, $field);
		}
		return $field->getName();
	}

	// recursively generates hidden HTML inputs corresponding to a scalar or array variable
	public function makeHiddenInputs($fieldName, $fieldValue)
	{
		if (!is_array($fieldValue)) {
			$fieldValue = htmlentities($fieldValue);
			$fieldName = htmlentities($fieldName);
			return "<input type=\"hidden\" name=\"$fieldName\" value=\"$fieldValue\" />";
		} else {
			$inputStr = '';
			foreach ($fieldValue as $i => $v) {
				$inputStr .= $this->makeHiddenInputs("{$fieldName}[{$i}]", $v);
			}
			return $inputStr;
		}
	}
}
?>
