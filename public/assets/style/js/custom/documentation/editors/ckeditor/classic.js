/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
var __webpack_exports__ = {};
/*!************************************************************************!*\
  !*** ../demo8/src/js/custom/documentation/editors/ckeditor/classic.js ***!
  \************************************************************************/


// Class definition
var KTFormsCKEditorClassic = function () {
    // Private functions
    var exampleClassic = function () {
        ClassicEditor
            .create(document.querySelector('#kt_docs_ckeditor_classic'))
            .then(editor => {
                console.log(editor);
            })
            .catch(error => {
                console.error(error);
            });
    }

    return {
        // Public Functions
        init: function () {
            exampleClassic();
        }
    };
}();

// On document ready
KTUtil.onDOMContentLoaded(function () {
    KTFormsCKEditorClassic.init();
});

/******/ })()
;
//# sourceMappingURL=classic.js.map