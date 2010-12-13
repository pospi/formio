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
    if (func !== undefined && typeof myForm[func] == 'function') {
      return myForm[func].apply( t, Array.prototype.slice.call( arguments, 1 ));
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
var FormIO = function(el, options)
{
	var t = this;
	this.elements = el;

	$.extend(this.options, options);

	$.each(this.options.setupRoutines, function(selector, method) {
		var ofInterest = $(selector, el);

		ofInterest.each(function(j) {
			(t[method])($(this));
		});
	});
};

//==========================================================================
//	Properties

FormIO.prototype.fieldDependencies = {};		// dependent element data for JavaScript element visibility toggling
FormIO.prototype.elements = null;				// jQuery element (or elements) we are creating the form inside
FormIO.prototype.options = {
	setupRoutines : {
		"[data-fio-type='date']"	: 'initDateField',
		"[data-fio-type='securimage']" : 'initSecurImageField',
		"[data-fio-searchurl]"		: 'initAutoCompleteField',
		"[data-fio-depends]"		: 'initDependencies'
	}
};
	
//==========================================================================
//	Callbacks & helpers

FormIO.prototype.getFieldRowElement = function(el)
{
	return el.closest('.row');
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

	var selected = [];
	el.each(function(i) {
		selected.push(this.value);
	});
	return selected;
};

FormIO.prototype.restripeForm = function()
{
	var rows = this.elements.find('.row:visible');

	var spin = 1;
	rows.each(function() {
		$(this).removeClass('alt');
		if (++spin % 2 == 0) {
			$(this).addClass('alt');
		}
	});
};

// :TODO: handle complex conditions better. At present all are executed in order
FormIO.prototype.checkDependencies = function(el)
{
	var t = this;
	var current = this.getFieldValue(el);
	var formModified = false;

	$.each(this.fieldDependencies[el.attr('id')], function(value, visible) {
		var hide = true;
		$.each(current, function(unused, activeValue) {
			if (value == activeValue) {
				hide = false;
				return false;
			}
			return true;
		});

		$.each(visible, function(unused, hideEl) {
			var row = t.getFieldRowElement($('#' + t.elements.attr('id') + '_' + hideEl));
			if (hide && row.is(':visible')) {
				row.hide();
				formModified = true;
			} else if (!row.is(':visible')) {
				row.show();
				formModified = true;
			}
		});
	});

	if (formModified) {
		this.restripeForm();
	}
};

//==========================================================================
//	Initialisation routines

FormIO.prototype.initDateField = function(el)
{
	el.datepicker({'dateFormat' : 'dd/mm/yy'});
};

FormIO.prototype.initAutoCompleteField = function(el)
{
	el.autocomplete({'source' : el.data('fio-searchurl')});
};

FormIO.prototype.initSecurImageField = function(el)		// adds 'reload image' behaviour
{
	el.find('.reload').click(function() {
		var img = el.find('.captcha').get(0);
		img.src = img.src.match(/^[^\?]*/i)[0] + '?r=' + Math.random();
	});
};

FormIO.prototype.initDependencies = function(el)
{
	var t = this;
	var dependencies = {};
	var dependent = el.data('fio-depends');
	dependent = dependent.split('&');
	$.each(dependent, function(unused, v) {
		var parts = v.split('=');
		dependencies[parts[0]] = parts[1].split(';');
	});

	this.fieldDependencies[el.attr('id')] = dependencies;

	// setup change events
	this.getFieldSubElements(el).change(function () {
		t.checkDependencies(el);
	});

	// also set initial visibility
	this.checkDependencies(el);
};

})(jQuery);
