 /*===============================================================================
	FormIO Setup javascript
	----------------------------------------------------------------------------
	Picks out data-fio-* attributes in a page's form elements and initialises 
	form behaviours
	
	@depends	JQuery 1.4.4
	----------------------------------------------------------------------------
	@author		Sam Pospischil <pospi@spadgos.com>
	@date		2010-11-23
  ===============================================================================*/
(function($) {
$(document).ready(function() {
	
	//==========================================================================
	//	Form globals & state
	
	var fieldDependencies = {};
	
	//==========================================================================
	//	Callbacks & helpers
	
	var getFieldRowElement = function(el)
	{
		return el.closest('.row');
	};
	
	var getFieldSubElements = function(el)
	{
		var type = el.get(0).nodeName.toLowerCase();
		
		if (type == 'fieldset') {		// radio group or check group
			return el.find('input');
		} else {						// dropdown list
			return el.find('option');
		}
	};
	
	var getFieldValue = function(el)
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
	
	var restripeForm = function(form)
	{
		var rows = form.find('.row:visible');
		
		var spin = 1;
		rows.each(function() {
			$(this).removeClass('alt');
			if (++spin % 2 == 0) {
				$(this).addClass('alt');
			}
		});
	};
	
	// :TODO: handle complex conditions better. At present all are executed in order
	var checkDependencies = function(el)
	{
		var current = getFieldValue(el);
		var formModified = false;
		var formId = el.closest('form.clean');
		
		$.each(fieldDependencies[el.attr('id')], function(value, visible) {
			var hide = true;
			$.each(current, function(unused, activeValue) {
				if (value == activeValue) {
					hide = false;
					return false;
				}
				return true;
			});
			
			$.each(visible, function(unused, hideEl) {
				var row = getFieldRowElement($('#' + formId.attr('id') + '_' + hideEl));
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
			restripeForm(formId);
		}
	};
	
	//==========================================================================
	//	Initialisation routines
	
	var initDateField = function(el)
	{
		el.datepicker({'dateFormat' : 'dd/mm/yy'});
	};
	
	var initDependencies = function(el)
	{
		var dependencies = {};
		var dependent = el.data('fio-depends');
		dependent = dependent.split('&');
		$.each(dependent, function(unused, v) {
			var parts = v.split('=');
			dependencies[parts[0]] = parts[1].split(';');
		});
		
		fieldDependencies[el.attr('id')] = dependencies;
		
		// setup change events
		getFieldSubElements(el).change(function () {
			checkDependencies(el);
		});
		
		// also set initial visibility
		checkDependencies(el);
	};
	
	//==========================================================================
	//	Element mapping
	
	var setupRoutines = {
		"[data-fio-type='date']"	: initDateField,
		"[data-fio-depends]"		: initDependencies
	};
	
	//==========================================================================
	//	Processing
	
	var forms = $('form.clean');
	
	// iterate selectors & init methods
	forms.each(function(i, form) {
		$.each(setupRoutines, function(selector, method) {
			var ofInterest = $(selector, form);
			
			ofInterest.each(function(j) {
				method($(this));
			});
		});
	});

});
})(jQuery);
