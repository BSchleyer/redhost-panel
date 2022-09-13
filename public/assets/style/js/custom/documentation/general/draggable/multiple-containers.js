/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
var __webpack_exports__ = {};
/*!*************************************************************************************!*\
  !*** ../demo8/src/js/custom/documentation/general/draggable/multiple-containers.js ***!
  \*************************************************************************************/


// Class definition
var KTDraggableMultiple = function() {
    // Private functions
    var exampleMultiple = function() {
        var containers = document.querySelectorAll('.draggable-zone');

        if (containers.length === 0) {
            return false;
        }

        var swappable = new Sortable.default(containers, {
            draggable: '.draggable',
            handle: '.draggable .draggable-handle',
            mirror: {
                //appendTo: selector,
                appendTo: 'body',
                constrainDimensions: true
            }
        });
    }

    return {
        // Public Functions
        init: function() {
            exampleMultiple();
        }
    };
}();

// On document ready
KTUtil.onDOMContentLoaded(function() {
    KTDraggableMultiple.init();
});

/******/ })()
;
//# sourceMappingURL=multiple-containers.js.map