/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
var __webpack_exports__ = {};
/*!*************************************************************************!*\
  !*** ../demo8/src/js/custom/documentation/editors/ckeditor/document.js ***!
  \*************************************************************************/


// Class definition
var KTFormsCKEditorDocument = function () {
    // Private functions
    var exampleDocument = function () {
        DecoupledEditor
            .create(document.querySelector('#kt_docs_ckeditor_document'))
            .then(editor => {
                const toolbarContainer = document.querySelector('#kt_docs_ckeditor_document_toolbar');

                toolbarContainer.appendChild(editor.ui.view.toolbar.element);
            })
            .catch(error => {
                console.error(error);
            });
    }

    return {
        // Public Functions
        init: function () {
            exampleDocument();
        }
    };
}();

// On document ready
KTUtil.onDOMContentLoaded(function () {
    KTFormsCKEditorDocument.init();
});

/******/ })()
;
//# sourceMappingURL=document.js.map