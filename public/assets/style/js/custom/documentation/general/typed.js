/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
var __webpack_exports__ = {};
/*!*************************************************************!*\
  !*** ../demo8/src/js/custom/documentation/general/typed.js ***!
  \*************************************************************/


// Class definition
var KTGeneralTypedJsDemos = function() {
    // Private functions
    var exampleBasic = function() {
        var typed = new Typed("#kt_typedjs_example_1", {
            strings: ["First sentence.", "Second sentence.", "Third sentense", "And some longer sentence"],
            typeSpeed: 30
        });
    }

    return {
        // Public Functions
        init: function() {
            exampleBasic();
        }
    };
}();

// On document ready
KTUtil.onDOMContentLoaded(function() {
    KTGeneralTypedJsDemos.init();
});

/******/ })()
;
//# sourceMappingURL=typed.js.map