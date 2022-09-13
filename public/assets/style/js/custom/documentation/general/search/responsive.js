/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "../demo8/src/js/custom/documentation/general/search/responsive.js":
/*!*************************************************************************!*\
  !*** ../demo8/src/js/custom/documentation/general/search/responsive.js ***!
  \*************************************************************************/
/***/ ((module) => {



// Class definition
var KTGeneralSearchResponsiveDemos = function() {
    // Private variables
    var element;
    var recentlyViewedElement;
    var resultsElement;
    var wrapperElement;
    var emptyElement;
    var preferencesElement;
    var preferencesShowElement;
    var preferencesDismissElement;
    var searchObject;

    // Private functions
    var processs = function(search) {
        var timeout = setTimeout(function() {
            var number = KTUtil.getRandomInt(1, 3);

            // Hide recently viewed
            recentlyViewedElement.classList.add('d-none');

            if (number === 3) {
                // Hide results
                resultsElement.classList.add('d-none');
                // Show empty message 
                emptyElement.classList.remove('d-none');
            } else {
                // Show results
                resultsElement.classList.remove('d-none');
                // Hide empty message 
                emptyElement.classList.add('d-none');
            }                  

            // Complete search
            search.complete();
        }, 1500);
    }

    var clear = function(search) {
        // Show recently viewed
        recentlyViewedElement.classList.remove('d-none');
        // Hide results
        resultsElement.classList.add('d-none');
        // Hide empty message 
        emptyElement.classList.add('d-none');
    }    

    // Public methods
	return {
		init: function() {
            // Elements
            element = document.querySelector('#kt_docs_search_handler_responsive');

            if (!element) {
                return;
            }

            wrapperElement = element.querySelector('[data-kt-search-element="wrapper"]');
            recentlyViewedElement = element.querySelector('[data-kt-search-element="recently-viewed"]');
            resultsElement = element.querySelector('[data-kt-search-element="results"]');
            emptyElement = element.querySelector('[data-kt-search-element="empty"]');
            preferencesElement = element.querySelector('[data-kt-search-element="preferences"]');
            preferencesShowElement = element.querySelector('[data-kt-search-element="preferences-show"]');
            preferencesDismissElement = element.querySelector('[data-kt-search-element="preferences-dismiss"]');
            
            // Initialize search handler
            searchObject = new KTSearch(element);

            // Search handler
            searchObject.on('kt.search.process', processs);

            // Clear handler
            searchObject.on('kt.search.clear', clear);

            // Preference show handler
            preferencesShowElement.addEventListener('click', function() {
                wrapperElement.classList.add('d-none');
                preferencesElement.classList.remove('d-none');
            });

            // Preference dismiss handler
            preferencesDismissElement.addEventListener('click', function() {
                wrapperElement.classList.remove('d-none');
                preferencesElement.classList.add('d-none');
            });
		}
	};
}();

// On document ready
KTUtil.onDOMContentLoaded(function() {
    KTGeneralSearchResponsiveDemos.init();
});

// Webpack support
if ( true && typeof module.exports !== 'undefined') {
    module.exports = KTGeneralSearchResponsiveDemos;
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
/******/ 	var __webpack_exports__ = __webpack_require__("../demo8/src/js/custom/documentation/general/search/responsive.js");
/******/ 	
/******/ })()
;
//# sourceMappingURL=responsive.js.map