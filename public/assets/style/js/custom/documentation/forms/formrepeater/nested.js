/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
var __webpack_exports__ = {};
/*!*************************************************************************!*\
  !*** ../demo8/src/js/custom/documentation/forms/formrepeater/nested.js ***!
  \*************************************************************************/


// Class definition
var KTFormRepeaterNested = function() {
    // Private functions
    var example1 = function() {
        $('#kt_docs_repeater_nested').repeater({
            // (Required if there is a nested repeater)
            // Specify the configuration of the nested repeaters.
            // Nested configuration follows the same format as the base configuration,
            // supporting options "defaultValues", "show", "hide", etc.
            // Nested repeaters additionally require a "selector" field.
            repeaters: [{
                // (Required)
                // Specify the jQuery selector for this nested repeater
                selector: '.inner-repeater',
                show: function () {
                    $(this).slideDown();
                },
    
                hide: function (deleteElement) {
                    $(this).slideUp(deleteElement);
                }
            }],

            show: function () {
                $(this).slideDown();
            },

            hide: function (deleteElement) {
                $(this).slideUp(deleteElement);
            }
        });
    }

    return {
        // Public Functions
        init: function() {
            example1();
        }
    };
}();

// On document ready
KTUtil.onDOMContentLoaded(function() {
    KTFormRepeaterNested.init();
});

/******/ })()
;
//# sourceMappingURL=nested.js.map