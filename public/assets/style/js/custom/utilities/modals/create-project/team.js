/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "../demo8/src/js/custom/utilities/modals/create-project/team.js":
/*!**********************************************************************!*\
  !*** ../demo8/src/js/custom/utilities/modals/create-project/team.js ***!
  \**********************************************************************/
/***/ ((module) => {



// Class definition
var KTModalCreateProjectTeam = function () {
	// Variables
	var nextButton;
	var previousButton;
	var form;
	var stepper;

	// Private functions
	var handleForm = function() {
		nextButton.addEventListener('click', function (e) {
			// Prevent default button action
			e.preventDefault();

			// Disable button to avoid multiple click 
			nextButton.disabled = true;

			// Show loading indication
			nextButton.setAttribute('data-kt-indicator', 'on');

			// Simulate form submission
			setTimeout(function() {
				// Enable button
				nextButton.disabled = false;
				
				// Simulate form submission
				nextButton.removeAttribute('data-kt-indicator');
				
				// Go to next step
				stepper.goNext();
			}, 1500); 		
		});

		previousButton.addEventListener('click', function () {
			stepper.goPrevious();
		});
	}

	return {
		// Public functions
		init: function () {
			form = KTModalCreateProject.getForm();
			stepper = KTModalCreateProject.getStepperObj();
			nextButton = KTModalCreateProject.getStepper().querySelector('[data-kt-element="team-next"]');
			previousButton = KTModalCreateProject.getStepper().querySelector('[data-kt-element="team-previous"]');

			handleForm();
		}
	};
}();

// Webpack support
if ( true && typeof module.exports !== 'undefined') {
	window.KTModalCreateProjectTeam = module.exports = KTModalCreateProjectTeam;
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
/******/ 	var __webpack_exports__ = __webpack_require__("../demo8/src/js/custom/utilities/modals/create-project/team.js");
/******/ 	
/******/ })()
;
//# sourceMappingURL=team.js.map