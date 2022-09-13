/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
var __webpack_exports__ = {};
/*!********************************************************************!*\
  !*** ../demo8/src/js/custom/documentation/general/jstree/basic.js ***!
  \********************************************************************/


// Class definition
var KTJSTreeBasic = function() {
    // Private functions
    var exampleBasic = function() {
        $('#kt_docs_jstree_basic').jstree({
            "core" : {
                "themes" : {
                    "responsive": false
                }
            },
            "types" : {
                "default" : {
                    "icon" : "fa fa-folder"
                },
                "file" : {
                    "icon" : "fa fa-file"
                }
            },
            "plugins": ["types"]
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
    KTJSTreeBasic.init();
});

/******/ })()
;
//# sourceMappingURL=basic.js.map