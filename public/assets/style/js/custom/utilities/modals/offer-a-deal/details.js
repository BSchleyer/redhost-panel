/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "../demo8/src/js/custom/utilities/modals/offer-a-deal/details.js":
/*!***********************************************************************!*\
  !*** ../demo8/src/js/custom/utilities/modals/offer-a-deal/details.js ***!
  \***********************************************************************/
/***/ ((module) => {



// Class definition
var KTModalOfferADealDetails = function () {
	// Variables
	var nextButton;
	var previousButton;
	var validator;
	var form;
	var stepper;

	// Private functions
	var initForm = function() {
		// Due date. For more info, please visit the official plugin site: https://flatpickr.js.org/
		var dueDate = $(form.querySelector('[name="details_activation_date"]'));
		dueDate.flatpickr({
			enableTime: true,
			dateFormat: "d, M Y, H:i",
		});

		// Expiry year. For more info, plase visit the official plugin site: https://select2.org/
        $(form.querySelector('[name="details_customer"]')).on('change', function() {
            // Revalidate the field when an option is chosen
            validator.revalidateField('details_customer');
        });
	}

	var initValidation = function() {
		// Init form validation rules. For more info check the FormValidation plugin's official documentation:https://formvalidation.io/
		validator = FormValidation.formValidation(
			form,
			{
				fields: {
					'details_customer': {
						validators: {
							notEmpty: {
								message: 'Customer is required'
							}
						}
					},
					'details_title': {
						validators: {
							notEmpty: {
								message: 'Deal title is required'
							}
						}
					},					
					'details_activation_date': {
						validators: {
							notEmpty: {
								message: 'Activation date is required'
							}
						}
					},
					'details_notifications[]': {
						validators: {
							notEmpty: {
								message: 'Notifications are required'
							}
						}
					}
				},
				
				plugins: {
					trigger: new FormValidation.plugins.Trigger(),
					bootstrap: new FormValidation.plugins.Bootstrap5({
						rowSelector: '.fv-row',
                        eleInvalidClass: '',
                        eleValidClass: ''
					})
				}
			}
		);
	}

	var handleForm = function() {
		nextButton.addEventListener('click', function (e) {
			// Prevent default button action
			e.preventDefault();

			// Disable button to avoid multiple click 
			nextButton.disabled = true;

			// Validate form before submit
			if (validator) {
				validator.validate().then(function (status) {
					console.log('validated!');

					if (status == 'Valid') {
						// Show loading indication
						nextButton.setAttribute('data-kt-indicator', 'on');

						// Simulate form submission
						setTimeout(function() {
							// Simulate form submission
							nextButton.removeAttribute('data-kt-indicator');

							// Enable button
							nextButton.disabled = false;
							
							// Go to next step
							stepper.goNext();
						}, 1500);   						
					} else {
						// Enable button
						nextButton.disabled = false;
						
						// Show popup warning. For more info check the plugin's official documentation: https://sweetalert2.github.io/
						Swal.fire({
							text: "Sorry, looks like there are some errors detected, please try again.",
							icon: "error",
							buttonsStyling: false,
							confirmButtonText: "Ok, got it!",
							customClass: {
								confirmButton: "btn btn-primary"
							}
						});
					}
				});
			}			
		});

		previousButton.addEventListener('click', function () {
			// Go to previous step
			stepper.goPrevious();
		});
	}

	return {
		// Public functions
		init: function () {
			form = KTModalOfferADeal.getForm();
			stepper = KTModalOfferADeal.getStepperObj();
			nextButton = KTModalOfferADeal.getStepper().querySelector('[data-kt-element="details-next"]');
			previousButton = KTModalOfferADeal.getStepper().querySelector('[data-kt-element="details-previous"]');

			initForm();
			initValidation();
			handleForm();
		}
	};
}();

// Webpack support
if ( true && typeof module.exports !== 'undefined') {
	window.KTModalOfferADealDetails = module.exports = KTModalOfferADealDetails;
}

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module is referenced by other modules so it can't be inlined
/******/ 	var __webpack_exports__ = __webpack_require__("../demo8/src/js/custom/utilities/modals/offer-a-deal/details.js");
/******/ 	
/******/ })()
;
//# sourceMappingURL=details.js.map