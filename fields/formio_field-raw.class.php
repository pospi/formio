<?php
/**
 * A form field for echoing out raw HTML. This is the simplest form field you could possibly implement,
 * and acts as a base class for all other field types.
 */

class FormIOField_Raw
{
	protected $form;					// form to which this field is attached

	private $name;						// field name (data key)
	protected $attributes = array();	// field attributes (mainly DOM attributes but subclasses will use it for other stuff)

	public $buildString = '{$desc}';

	private $lastBuilderReplacement;		// form builder hack for preg_replace_callback not being able to accept extra parameters

	/**
	 * @param	FormIO	$form		parent form object
	 * @param	string	$name		name of this field
	 */
	public function __construct($form, $name, $displayText = null, $defaultValue = null)
	{
		$this->form = $form;

		$this->setName($name);
		if (isset($displayText)) {
			$this->setAttribute('desc', $displayText);
		}

		$this->handleCreation($this->form);
	}

	// unreference the form when we are destroyed
	public function __destruct()
	{
		$this->form = null;
	}

	/**
	 * Presentational fields are those that have a value. In other words,
	 * they must inherit from FormIOField_Text.
	 */
	public function isPresentational()
	{
		return !$this instanceof FormIOField_Text;
	}

	/**
	 * Generate and return the field's HTML string.
	 * @param	int		$spinVar		current table striper variable. This is an opportunity for fields to do their own striping behaviour.
	 *
	 * @return	mixed	if a string, this is the HTML to output for this field
	 *					if FALSE, this field has nothing to output and can be ignored
	 */
	public function getHTML(&$spinVar)
	{
		++$spinVar;
		$vars = $this->getBuilderVars();
		if ($spinVar % 2 == 0) {
			$vars['alt'] = true;
		}
		return $this->replaceInputVars($this->buildString, $vars);
	}

	// Field attribute manipulation
	public function getAttributes()
	{
		return $this->attributes;
	}

	public function getAttribute($key, $default = null)
	{
		return isset($this->attributes[$key]) ? $this->attributes[$key] : $default;
	}

	public function setAttributes($array)
	{
		if (!is_array($array)) {
			return false;
		}
		$this->attributes = $array;
		return true;
	}

	public function setAttribute($name, $value)
	{
		$this->attributes[$name] = $value;
	}

	public function clearAttribute($name)
	{
		unset($this->attributes[$name]);
	}

	public function addCSSClass($className)
	{
		$this->attributes['classes'] .= " " . (is_array($className) ? implode(' ', $className) : $className);
	}

	public function removeCSSClass($className)
	{
		$classes = explode(' ', $this->attributes['classes']);
		foreach ($classes as $c => $class) {
			$class = trim($class);
			if (!$class || $class == $className) {
				unset($classes[$c]);
			}
		}
		$this->attributes['classes'] = implode(' ', $classes);
	}

	public function getName()
	{
		return $this->name;
	}

	public function getHumanReadableName()
	{
		return isset($this->attributes['desc']) ? $this->attributes['desc'] : $this->getName();
	}

	/**
	 * This allows fields to modify the array key under which to store them whist
	 * masquerading a name value underneath by overriding getName() and setName()
	 * @see FormIOField_Submit
	 */
	final public function getInternalName()
	{
		return $this->name;
	}

	public function setName($newName)
	{
		$this->name = $newName;
	}

	/**
	 * Retrieves any errors for this field from its parent form.
	 *
	 * Whilst only input fields posess the methods required to generate errors,
	 * all field types can interrogate their results. This means that you can assign
	 * errors to presentational field types from within your own validator functions
	 * and have them display correctly.
	 *
	 * @pre		this method must be called after validating the parent form!
	 */
	public function getErrors()
	{
		if ($this->form->delaySubmission) {
			return null;
		}
		return $this->form->getError($this->getName());
	}

	protected function getBuilderVars()
	{
		// array of wildcard replacements to build with the form builder string in addition
		// to the automatically created ones. We start with our custom attributes (label, css classes etc)
		$vars = $this->attributes;

		$vars['id']		= $this->getFieldId();
		$vars['name']	= $this->getName();

		if ($errors = $this->getErrors()) {
			$errArray = is_array($errors) ? $errors : array($errors);
			$vars['error'] = implode("<br />", $errArray);
		}

		return $vars;
	}

	// Returns an HTML field ID for this form (form's name is prepended). Field name may be
	// given when you don't wish to use this field's name for the ID you're generating
	public function getFieldId($nameToEncode = null)
	{
		if (!isset($nameToEncode)) {
			$nameToEncode = $this->getName();
		}
		return ($this->form->name ? $this->form->name . '_' : '') . str_replace(array('[', ']'), array('_', ''), $nameToEncode);
	}

	final public function getFieldType()
	{
		$class = get_class($this);
		if (strpos('FormIOField_', $class) === 0) {
			return strtolower(substr($class, 11));
		}
		return $class;
	}

	/**
	 * Builds an input by replacing our custom-style string substitutions.
	 * {$varName} is replaced by $value, whilst {$varName?test="$varName"}
	 * is replaced by test="$value"
	 */
	protected function replaceInputVars($str, $varsMap)
	{
		if ($varsMap === null) {
			return '';		// null means don't generate the field
		}
		foreach ($varsMap as $property => $value) {
			if ($value === false || $value === null) {
				$str = preg_replace('/\{\$' . $property . '.*\}/U', '', $str);
				continue;
			}

			$this->lastBuilderReplacement = $value;
			$str = preg_replace_callback(
						'/\{\$(' . $property . ')(\?(.+))?\}/U',
						array($this, 'formInputBuildCallback'),
						$str
					);
		}
		// remove any properties we didn't process
		$str = preg_replace('/\{\$.+\}/U', '', $str);
		return $str;
	}

	// Callback for replaceInputVars(), used to replace variables in submatches with their own values
	private function formInputBuildCallback($matches)
	{
		if (isset($matches[3]) && isset($this->lastBuilderReplacement)) {
			return preg_replace('/\$' . $matches[1] . '/', $this->lastBuilderReplacement, $matches[3]);
		} else {
			return $this->lastBuilderReplacement;
		}
	}

	/**
	 * This function provides fields an opportunity to manipulate the state
	 * of their parent form when added. This can be used (for example) to automatically
	 * change the form to a multipart form when adding a FILE input.
	 */
	public function handleCreation($parentForm)
	{}

	// an opportunity to reset the field to a state suitable for accepting user input
	// this is usually a case of removing any default values the field's type may have set
	// (checkboxes, submit buttons etc)
	public function inputNotProvided()
	{}
}
?>
