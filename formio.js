 /*===============================================================================
	FormIO Setup javascript
	----------------------------------------------------------------------------
	Picks out data-fio-* attributes in a page's form elements and initialises
	form behaviours.

	You can also call the script after load, targeting a specific element. The
	syntax to do this is simply:
		$('#my.element').formio();

	@depends	JQuery 1.4.4
	@depends	JQuery UI 1.8.6
	----------------------------------------------------------------------------
	@author		Sam Pospischil <pospi@spadgos.com>
	@date		2010-11-23
  ===============================================================================*/

(function($) {

$(document).ready(function() {
	$('form.clean').formio();
});

$.fn.formio = function(func) {
	var t = this;
	var myForm = t.data('formio');

	// constructor to create a formIO object and bind it to our elements
	var init = function (options) {
		t.data('formio', new FormIO(t, options));
	};

	// Run the appropriate behaviour
    if (func != undefined && myForm != undefined && typeof myForm[func] == 'function') {
      return myForm[func].apply( myForm, Array.prototype.slice.call( arguments, 1 ));
    } else if (typeof func === 'object' || !func ) {
      init.apply( t, arguments );
      return t;
    } else {
      $.error( 'Method ' +  func + ' does not exist in FormIO' );
    }
};

/**
 * FormIO handler object
 *
 * One is created per instance of elements added, each being handled with separate state.
 * Because of this, you should generally create separate FormIO objects for *each* element
 * in a jQuery selector, rather than one for an entire array. I was contemplating making it
 * throw an error for this, but I can see valid uses for multiple element selectors as well.
 */
var FormIO = function(formEl, options)
{
	var that = this;

	this.elements = formEl;
	this.elements.submit(function() { return that.onSubmit() });

	this.setOptions(options);

	this.setupFields(this.elements);
	this.initTabs();
};

//=============================================================================================================
//=============================================================================================================
//	Properties

FormIO.prototype.fieldDependencies = {};		// dependent element data for JavaScript element visibility toggling
FormIO.prototype.validators = {};				// map of field names & validator callbacks to run when form is submitted
FormIO.prototype.elements = null;				// jQuery element (or elements) we are creating the form inside
FormIO.prototype.options = {
	setupRoutines : {
		// interactions
		"[data-fio-type='date']"	: 'initDateField',
		"[data-fio-type='securimage']" : 'initSecurImageField',
		"[data-fio-type='repeater']"	: 'initRepeater',
		"[data-fio-searchurl]"		: 'initAutoCompleteField',
		"[data-fio-depends]"		: 'initDependencies',
		"input[type=submit], input[type=reset], input[type=button]" : 'initButton',

		// validators
		"[data-fio-validation]" : "setValidation"
	},
	repeaterRefreshCallbacks : []
};

//=============================================================================================================
//=============================================================================================================
//	Form methods

// Accessor to return the FormIO object from within jQuery plugin -> $(...).formio('get');
FormIO.prototype.get = function() {
	return this;
};

// overrides any keys provided with those in options
FormIO.prototype.setOptions = function(options) {
	$.extend(this.options, options);
};

FormIO.prototype.setupFields = function(inside)
{
	var t = this;
	$.each(this.options.setupRoutines, function(selector, method) {
		var ofInterest = $(selector, inside);

		ofInterest.each(function(j) {
			(t[method])($(this));
		});
	});
};

FormIO.prototype.restripeForm = function()
{
	// account for striper incrementation
	var skipParts = this.elements.data('fio-stripe');
	var striperSkips = {};
	skipParts = unescape(skipParts).split('&');
	$.each(skipParts, function(i) {
		var bits = this.split('=');
		striperSkips[bits[0]] = bits[1];
	});

	var shownRows = this.elements.find('div.tab>.row:visible');

	// remove all highlighting first
	shownRows.removeClass('alt');

	// redo new striping
	var spin = 1;
	shownRows.each(function(i, row) {
		if (spin % 2 == 0) {
			$(row).addClass('alt');
		}
		++spin;
		var fieldName = $(row).find('input[name], select[name]').attr('name');
		if (typeof striperSkips[fieldName] != 'undefined' && striperSkips[fieldName] > 0) {
			--spin;
			striperSkips[fieldName]--;
		}
	});
};

// refresh tab display after an update to the form
FormIO.prototype.refreshTabs = function()
{
	var tabs = this.elements.find('.tab:not(.header):not(.footer)');
	var navbar = this.elements.find('ul.formnav');

	tabs.each(function() {
		if ($(this).find('p.err:parent').length > 0) {
			var a = navbar.find('a[href=#' + $(this).attr('id') + ']');
			a.parent().addClass('sectionErrors');
		}
	});
};

FormIO.prototype.nextTab = function()
{
	var next = this.getCurrentTab().next();
	if (next.length) {
		this.elements.tabs('select', next.attr('id'));
	}
};

FormIO.prototype.prevTab = function()
{
	var prev = this.getCurrentTab().prev();
	if (prev.length) {
		this.elements.tabs('select', prev.attr('id'));
	}
};

FormIO.prototype.getCurrentTab = function()
{
	return this.elements.find('.tab:not(.header):not(.footer):visible');
};

//=============================================================================================================
//=============================================================================================================
//	Callbacks & behaviours
//		These functions run on targeted elements / inputs, not the form

FormIO.prototype.getFieldRowElement = function(el)
{
	var cl = el.closest('.row');
	var p = cl.parent();
	if (p.hasClass('blck')) {
		return p;
	}
	return cl;
};

FormIO.prototype.getFieldSubElements = function(el)
{
	var type = el.get(0).nodeName.toLowerCase();

	if (type == 'fieldset') {		// radio group or check group
		return el.find('input');
	} else {						// dropdown list
		return el.find('option');
	}
};

FormIO.prototype.getFieldValue = function(el)
{
	var type = el.get(0).nodeName.toLowerCase();

	if (type == 'fieldset') {		// radio group or check group
		el = el.find('input:checked');
	} else {						// dropdown list
		el = el.find('option:selected');
	}
	// :TODO: repeaters, etc

	var selected = [];
	el.each(function(i) {
		selected.push(this.value);
	});
	return selected;
};

// :TODO: handle complex conditions better. At present all are executed in order
FormIO.prototype.checkDependencies = function(el)
{
	var t = this;
	var current = this.getFieldValue(el);
	var formModified = false;
	var elType = el[0].tagName.toLowerCase();

	var depends = $.extend({}, this.fieldDependencies[el.attr('id')]);
	var affected = depends['__affected'];
	delete depends['__affected'];

	$.each(affected, function(k, elId) {
		t.getFieldRowElement($('#' + t.getFieldId(elId))).hide();
		formModified = true;
	});

	$.each(depends, function(value, visible) {
		var show = false;

		if (elType == 'fieldset') {
			$.each(current, function(i, selected) {
				if (value == selected) {
					show = true;
					return false;
				}
			});
		} else if (t.inputIsMatching(el, value)) {
			show = true;
		}

		if (show) {
			$.each(visible, function(unused, showEl) {
				var row = t.getFieldRowElement($('#' + t.getFieldId(showEl)));
				if (!row.is(':visible')) {
					row.show();
					formModified = true;
				}
			});
		}
	});

	if (formModified) {
		this.restripeForm();
	}
};

// helper to determine whether a checkbox / radiobutton / text field is matching a value
FormIO.prototype.inputIsMatching = function(el, value)
{
	var checkd	= el.attr('checked');
	var val		= el.val();
	return ( (		// checkbox / radio
				this.elementIsRadioOrSelect(el) && (checkd && value == 1 || !checkd && value == 0)
			) || (	// simple input / fallback
			  	val == value
			)
		);
};

// field ID helpers
FormIO.prototype.getFieldId = function(fldname)
{
	return this.elements.attr('id') + '_' + fldname.replace(/\[/g, '_').replace(/\]/g, '');
};

FormIO.prototype.getFieldName = function(fldId)
{
	return fldId.replace(new RegExp('^' + this.elements.attr('id') + '_'), '');
};

// serialised form property decoder for dependencies & validators
FormIO.prototype.splitParams = function(str)
{
	var params = {};

	str = str.split('&');

	$.each(str, function(unused, v) {
		var parts = v.split('=');
		// may be a string-only value
		if (parts.length == 1 && parts[0] == v) {
			params[unescape(v)] = null;	// in which case, set it to be returned in parent scope variable as parameterless
			return false;	// and abort the loop
		}
		var values = parts[1].split(';');
		if (values.length == 1 && parts[1] == values[0]) {	// may have subvalues or just be a string
			values = unescape(parts[1]);
		} else {
			$.each(values, function(i, val) {
				values[i] = unescape(val);
			});
		}

		params[unescape(parts[0])] = values;
	});

	return params;
};

// input type helpers
FormIO.prototype.elementIsRadioOrSelect = function(el)
{
	var elType	= el[0].tagName.toLowerCase();
	var inType	= el.attr('type');
	return elType == 'input' && (inType == 'checkbox' || inType == 'radio');
};
FormIO.prototype.elementIsTextual = function(el)
{
	var elType	= el[0].tagName.toLowerCase();
	var inType	= el.attr('type');
	return elType == 'textarea' || (elType == 'input' && inType == 'text');
};

/**
 * Clones a field row (without events), and negates its values
 */
FormIO.prototype.getNewEmptyField = function(row)
{
	var newField = row.clone(false);
	// reset the value of the new field
	newField.find(':input').val('');

	return newField;
};

// :TODO: handle repeated file inputs
FormIO.prototype.reorderRepeaterFields = function(el)
{
	var counter = 0;

	// strings to replace in subelements
	var nameBase = this.getFieldName(el.attr('id'));
	var idFind = new RegExp('^' + el.attr('id') + '_(\\d+)(.*)');
	var nameFind = new RegExp('^' + nameBase + '\\[(\\d+)\\](.*)');

	el.find('>.row').each(function() {
		var currId = $(this).attr('id');

		// renumber the row's ID
		$(this).attr('id', currId.replace(idFind, el.attr('id') + '_' + counter + '$2'))
			// renumber all ID, NAME and FOR attributes in child inputs
			.find('[name],[id],[for]').each(function() {
				var $this = $(this);

				var currName = $this.attr('name');
				var currId = $this.attr('id');
				var currFor = $this.attr('for');

				if (currName && currName.match(nameFind)) {
					$this.attr('name', currName.replace(nameFind, nameBase + '[' + counter + ']$2'));
				}
				if (currId && currId.match(idFind)) {
					$this.attr('id', currId.replace(idFind, el.attr('id') + '_' + counter + '$2'));
				}
				if (currFor && currFor.match(idFind)) {
					$this.attr('for', currFor.replace(idFind, el.attr('id') + '_' + counter + '$2'));
				}

				// clear jQueryUI flags from applicable elements so that setupFields() triggers work
				if (this.tagName.toLowerCase() == 'input' && $this.hasClass('hasDatepicker')) {
					$this.removeClass('hasDatepicker');
				}
			});

		++counter;
	});

	this.reinitRepeaterFields(el);
};

FormIO.prototype.reinitRepeaterFields = function(el)
{
	// clear ALL events from all repeater subinputs (not the row handlers)
	el.unbind().find('*:not(input.add):not(input.remove)').unbind();
	// rebind events, interfaces and stuff using form field initialiser
	this.setupFields(el);

	// trigger any repeater refresh callbacks defined
	if (this.options.repeaterRefreshCallbacks && this.options.repeaterRefreshCallbacks.length) {
		$.each(this.options.repeaterRefreshCallbacks, function(i, callback) {
			callback(el);
		});
	}
};

//=============================================================================================================
//=============================================================================================================
//	Initialisation routines.

FormIO.prototype.initTabs = function()
{
	var tabs = this.elements.find('.tab:not(.header):not(.footer)');
	if (tabs.length < 2) {
		return;
	}
	var that = this;

	// add navigation buttons to the base of the form
	var nextBtn = $("<input type=\"button\" class=\"navNext\" value=\"Next page\" />");
	var prevBtn = $("<input type=\"button\" class=\"navPrev\" value=\"Previous page\" />");

	// create the tab handler
	this.elements.tabs({
		show: function(evt, ui) {
			that.restripeForm();

			// scroll back to the top of the form, focus the first input
			tabs.filter(':visible').find(':input').get(0).focus();
		},
		select: function(event, ui) {
			if (ui.index == 0) {
				nextBtn.button('enable');
				prevBtn.button('disable');
			} else if (ui.index == tabs.length - 1) {
				nextBtn.button('disable');
				prevBtn.button('enable');
			} else {
				nextBtn.button('enable');
				prevBtn.button('enable');
			}
		}
	});

	this.elements.find('.tab.footer').prepend("<div class=\"tabNav\"></div>");
	this.elements.find('.tab.footer div.tabNav').prepend(nextBtn).prepend(prevBtn);
	prevBtn.button();
	nextBtn.button();
	prevBtn.click(function() {
		that.prevTab();
	}).button('disable');
	nextBtn.click(function() {
		that.nextTab();
	});

	this.refreshTabs();
};

FormIO.prototype.initButton = function(el)
{
	el.button();
}

FormIO.prototype.initDateField = function(el)
{
	el.datepicker({
		dateFormat:	'dd/mm/yy',
		beforeShow:	function(dateText, inst) {
			// clearfix jQueryUI class can prevent the picker from showing in some documents
			inst.dpDiv.removeClass('ui-helper-hidden-accessible');
		}
	});
};

FormIO.prototype.initAutoCompleteField = function(el)
{
	el.autocomplete({'source' : el.data('fio-searchurl')});
};

FormIO.prototype.initSecurImageField = function(el)		// adds 'reload image' behaviour
{
	el.find('.reload').click(function() {
		var img = el.find('.row img').get(0);
		img.src = img.src.match(/^[^\?]*/i)[0] + '?r=' + Math.random();
	});
};

FormIO.prototype.initRepeater = function(el)
{
	var that = this;

	// hold a reference to the first field in this closure
	var firstField = el.find('>.row').first();

	el.find('.add').click(function() {
		var newField = that.getNewEmptyField(firstField);
		el.find('>.row').last().after(newField);

		that.reorderRepeaterFields(el);
		return false;	// prevent submission
	});
	el.find('.remove').click(function() {
		var lastRow = el.find('>.row').last();
		if (lastRow.get(0) != firstField.get(0)) {
			lastRow.remove();
		}

		// :TODO: check more than the first input
		if (lastRow.find(':input').val() !== '') {
			var newField = that.getNewEmptyField(firstField);
			lastRow.after(newField);
		}

		that.reorderRepeaterFields(el);
		return false;	// prevent submission
	});
};

FormIO.prototype.initDependencies = function(el)
{
	var t = this;
	var dependencies = this.splitParams(el.data('fio-depends'));
	var affectedFields = [];

	$.each(dependencies, function(depName, depParams) {
		if (typeof depParams != "string") {
			$.each(depParams, function(i, str) {
				affectedFields.push(str);
			});
		} else {
			affectedFields.push(depParams);
			dependencies[depName] = [depParams];	// convert to array for easy interrogation later
		}
	});

	this.fieldDependencies[el.attr('id')] = dependencies;
	this.fieldDependencies[el.attr('id')]['__affected'] = affectedFields;

	// setup change events
	var elType = el[0].tagName.toLowerCase();
	if (elType == 'fieldset') {					// radiogroup and checklist
		this.getFieldSubElements(el).change(function () {
			t.checkDependencies(el);
		});
	} else if (t.elementIsTextual(el)) {		// textarea, text
		el.keyup(function() {
			t.checkDependencies(el);
		});
	} else {									// normal inputs (checkbox, radio, select etc)
		el.change(function() {
			t.checkDependencies(el);
		});
	}

	// also set initial visibility
	this.checkDependencies(el);
};

//=============================================================================================================
//=============================================================================================================
//	VALIDATION

FormIO.prototype.setValidation = function(el)
{
	var validatorData = this.splitParams(el.data("fio-validation"));

	this.validators[el.attr('id')] = validatorData;
};

FormIO.prototype.onSubmit = function()
{
	var that = this;
	var allOk = true;

	$.each(this.validators, function(field, validator) {
		$.each(validator, function(name, params) {
			var fieldEl = $('#' + field);
			// check that we should run the validator
			if (that.shouldSkipValidator(fieldEl, name)) {
				return true;		// continue;
			}

			if (!$.isArray(params)) {	// ensure validator params get sent through as an array
				params = [params];
			}
			params.unshift(fieldEl);				// pass element through as parameter 0
			if (typeof that[name] == 'function') {	// look in FormIO scope
				if (!(that[name]).apply(that, params)) {
					that.highlightError(fieldEl);
					allOk = false;
				}
			} else if (typeof name == 'function') {	// look for external validation function
				if (!name.apply(that, params)) {
					that.highlightError(fieldEl);
					allOk = false;
				}
			} else if (console && typeof console.error == 'function') {
				console.error("Unknown FormIO validator: " + name);		// :DEBUG:
			}
		});
	});

	return allOk;
};

// If a field is required, but its parent is as well - skip it
// :TODO: detect partially filled out records for group fields
FormIO.prototype.shouldSkipValidator = function(el, validatorName)
{
	if (validatorName == 'requiredValidator') {
		var parentField = el.closest('.row.group[data-fio-validation*=requiredValidator], .row[data-fio-type=repeater][data-fio-validation*=requiredValidator]');
		if (parentField.length > 0) {
			return true;
		}
	}

	return false;
};

FormIO.prototype.highlightError = function(field)
{
	// :TODO: show error messages, replicate the backend functionality
	field.animate({
		backgroundColor: "#ff9393",
	}, 1000 );
};

//=============================================================================================================
//=============================================================================================================
//	VALIDATORS

FormIO.prototype.requiredValidator = function(el) {
	return el.val().length > 0;
};

})(jQuery);
