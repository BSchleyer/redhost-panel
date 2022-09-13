/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
var __webpack_exports__ = {};
/*!************************************************************************!*\
  !*** ../demo8/src/js/custom/documentation/forms/formrepeater/basic.js ***!
  \************************************************************************/


// Class definition
var KTFormRepeaterBasic = function () {
    // Private functions
    var example1 = function () {
        $('#kt_docs_repeater_basic').repeater({
            initEmpty: false,

            defaultValues: {
                'text-input': 'foo'
            },

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
        init: function () {
            example1();
        }
    };
}();

// On document ready
KTUtil.onDOMContentLoaded(function () {
    KTFormRepeaterBasic.init();
});

/******/ })()
;
//# sourceMappingURL=basic.js.map