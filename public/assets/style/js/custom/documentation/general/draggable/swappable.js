/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
var __webpack_exports__ = {};
/*!***************************************************************************!*\
  !*** ../demo8/src/js/custom/documentation/general/draggable/swappable.js ***!
  \***************************************************************************/


// Class definition
var KTDraggableSwappable = function() {
    // Private functions
    var exampleSwappable = function() {
        var containers = document.querySelectorAll('.draggable-zone');

        if (containers.length === 0) {
            return false;
        }

        var swappable = new Swappable.default(containers, {
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
            exampleSwappable();
        }
    };
}();

// On document ready
KTUtil.onDOMContentLoaded(function() {
    KTDraggableSwappable.init();
});

/******/ })()
;
//# sourceMappingURL=swappable.js.map