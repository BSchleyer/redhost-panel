/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
var __webpack_exports__ = {};
/*!***********************************************************************!*\
  !*** ../demo8/src/js/custom/documentation/editors/tinymce/plugins.js ***!
  \***********************************************************************/


// Class definition
var KTFormsTinyMCEPlugins = function() {
    // Private functions
    var examplePlugins = function() {
        tinymce.init({
            selector: '#kt_docs_tinymce_plugins',
            toolbar: 'advlist | autolink | link image | lists charmap | print preview',
            plugins : 'advlist autolink link image lists charmap print preview'
        });
    }

    return {
        // Public Functions
        init: function() {
            examplePlugins();
        }
    };
}();

// On document ready
KTUtil.onDOMContentLoaded(function() {
    KTFormsTinyMCEPlugins.init();
});

/******/ })()
;
//# sourceMappingURL=plugins.js.map