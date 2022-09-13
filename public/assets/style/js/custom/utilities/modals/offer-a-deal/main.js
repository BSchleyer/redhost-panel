/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "../demo8/src/js/custom/utilities/modals/offer-a-deal/main.js":
/*!********************************************************************!*\
  !*** ../demo8/src/js/custom/utilities/modals/offer-a-deal/main.js ***!
  \********************************************************************/
/***/ ((module) => {



// Class definition
var KTModalOfferADeal = function () {
    // Private variables
	var stepper;
	var stepperObj;
	var form;	

	// Private functions
	var initStepper = function () {
		// Initialize Stepper
		stepperObj = new KTStepper(stepper);
	}

	return {
		// Public functions
		init: function () {
			stepper = document.querySelector('#kt_modal_offer_a_deal_stepper');
			form = document.querySelector('#kt_modal_offer_a_deal_form');

			initStepper();
		},

		getStepper: function () {
			return stepper;
		},

		getStepperObj: function () {
			return stepperObj;
		},
		
		getForm: function () {
			return form;
		}
	};
}();

// On document ready
KTUtil.onDOMContentLoaded(function () {
	if (!document.querySelector('#kt_modal_offer_a_deal')) {
		return;
	}

    KTModalOfferADeal.init();
    KTModalOfferADealType.init();
    KTModalOfferADealDetails.init();
    KTModalOfferADealFinance.init();
    KTModalOfferADealComplete.init();
});

// Webpack support
if ( true && typeof module.exports !== 'undefined') {
	window.KTModalOfferADeal = module.exports = KTModalOfferADeal;
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
/******/ 	var __webpack_exports__ = __webpack_require__("../demo8/src/js/custom/utilities/modals/offer-a-deal/main.js");
/******/ 	
/******/ })()
;
//# sourceMappingURL=main.js.map