/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
var __webpack_exports__ = {};
/*!***************************************************************************!*\
  !*** ../demo8/src/js/custom/documentation/forms/formrepeater/advanced.js ***!
  \***************************************************************************/


// Class definition
var KTFormRepeaterAdvanced = function () {
    // Private functions
    var example1 = function () {
        $('#kt_docs_repeater_advanced').repeater({
            initEmpty: false,

            defaultValues: {
                'text-input': 'foo'
            },

            show: function () {
                $(this).slideDown();

                // Re-init select2
                $(this).find('[data-kt-repeater="select2"]').select2();

                // Re-init flatpickr
                $(this).find('[data-kt-repeater="datepicker"]').flatpickr();

                // Re-init tagify
                new Tagify(this.querySelector('[data-kt-repeater="tagify"]'));
            },

            hide: function (deleteElement) {
                $(this).slideUp(deleteElement);
            },

            ready: function(){
                // Init select
                $('[data-kt-repeater="select2"]').select2();

                // Init flatpickr
                $('[data-kt-repeater="datepicker"]').flatpickr();

                // Init Tagify
                new Tagify(document.querySelector('[data-kt-repeater="tagify"]'));
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
    KTFormRepeaterAdvanced.init();
});

/******/ })()
;
//# sourceMappingURL=advanced.js.map