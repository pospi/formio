Field class hierarchy
---------------------

Italicised items denote abstract classes. All classnames are by convention and follow the template `FormIOField_` + the field name with an uppercase first letter.

- raw
	- text
		- alpha
		- alphanumeric
		- numeric
			- currency
		- autocomplete
		- submit
		- button
			- reset
		- *captcha*
			- recaptcha
			- securimage
		- checkbox
		- *multiple*
			- dropdown
				- radiogroup
					- checkgroup
			- survey
		- creditcard
		- date
			- daterange
			- time
				- datetime
					- timerange
		- email
		- file
		- hidden
		- password
		- passwordchange
		- phone
		- postcode
		- readonly
		- group
			- repeater
		- textarea
		- url
	- fieldsetstart
		- sectionbreak
	- spacer
		- fieldsetend
	- header
	- subheader
	- image
	- paragraph


Output order
------------

As it may be important for some frontend integrations, the DOM order of the form's output is clear and flows in a straightforward way. When forms process and generate their HTML, templates for each section are rendered in the following order:

1. header section
	1. *FIELDS*
1. status message
1. errors
1. navigation
	1. *FIELDS*
1. footer section
	1. *FIELDS*


TODO
----

Improve documentation :p

#### New fields

- implement survey, base on Repeater? (+ dependency handling JS)

#### Frontend features

- create theming system and implement HTML5 frontend
- allow reconfiguring output order of various form sections
- implement remaining core validators in JS:
	- regex matching for primitive types
	- email (HTML4)
	- credit card MOD10
	- time & date types
	- password matching for passwordchange
	- repeater / repeated field dependency handling?
- ajax submission
- readonly mode / confirmation screens

#### Backend features

- configurable storage for file upload persistence between requests
- implement correct eror handling for file inputs when placed inside a repeater

#### Structural changes

- redo CSS using a preprocessor
- store errors in Field instances instead of Form object

#### Add to demo

- groups (and repeaters)
- entirely custom field types
- validators:
	regex
	others defined in Text and Numeric
- status messages


License
-------

This software is provided under an MIT open source license, read the 'LICENSE.txt' file for details.

Copyright &copy; 2010-20** Sam Pospischil (pospi at spadgos dot com)
