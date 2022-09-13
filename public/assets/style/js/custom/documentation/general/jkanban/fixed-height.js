/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
var __webpack_exports__ = {};
/*!****************************************************************************!*\
  !*** ../demo8/src/js/custom/documentation/general/jkanban/fixed-height.js ***!
  \****************************************************************************/


// Class definition
var KTJKanbanDemoFixedHeight = function () {
    var element;
    var kanbanEl;

    // Private functions
    var exampleFixedHeight = function () {
        // Get kanban height value
        const kanbanHeight = kanbanEl.getAttribute('data-kt-jkanban-height');

        // Init jKanban
        var kanban = new jKanban({
            element: element,
            gutter: '0',
            widthBoard: '250px',
            boards: [{
                'id': '_fixed_height',
                'title': 'Fixed Height',
                'class': 'primary',
                'item': [
                    {
                        'title': '<span class="fw-bold">Item 1</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 2</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 3</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 4</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 5</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 6</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 7</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 8</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 9</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 10</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 11</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 12</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 13</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 14</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 15</span>'
                    },
                ]
            },
            {
                'id': '_fixed_height2',
                'title': 'Fixed Height 2',
                'class': 'success',
                'item': [
                    {
                        'title': '<span class="fw-bold">Item 1</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 2</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 3</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 4</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 5</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 6</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 7</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 8</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 9</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 10</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 11</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 12</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 13</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 14</span>'
                    },
                    {
                        'title': '<span class="fw-bold">Item 15</span>'
                    },
                ]
            }
            ],

            // Handle item scrolling
            dragEl: function (el, source) {
                document.addEventListener('mousemove', isDragging);
            },

            dragendEl: function (el) {
                document.removeEventListener('mousemove', isDragging);
            }
        });

        // Set jKanban max height
        const allBoards = kanbanEl.querySelectorAll('.kanban-drag');
        allBoards.forEach(board => {
            board.style.maxHeight = kanbanHeight + 'px';
        });
    }

    const isDragging = (e) => {
        const allBoards = kanbanEl.querySelectorAll('.kanban-drag');
        allBoards.forEach(board => {
            // Get inner item element
            const dragItem = board.querySelector('.gu-transit');

            // Stop drag on inactive board
            if (!dragItem) {
                return;
            }

            // Get jKanban drag container
            const containerRect = board.getBoundingClientRect();

            // Get inner item size
            const itemSize = dragItem.offsetHeight;

            // Get dragging element position
            const dragMirror = document.querySelector('.gu-mirror');
            const mirrorRect = dragMirror.getBoundingClientRect();

            // Calculate drag element vs jKanban container
            const topDiff = mirrorRect.top - containerRect.top;
            const bottomDiff = containerRect.bottom - mirrorRect.bottom;

            // Scroll container
            if (topDiff <= itemSize) {
                // Scroll up if item at top of container
                board.scroll({
                    top: board.scrollTop - 3,
                });
            } else if (bottomDiff <= itemSize) {
                // Scroll down if item at bottom of container
                board.scroll({
                    top: board.scrollTop + 3,
                });
            } else {
                // Stop scroll if item in middle of container
                board.scroll({
                    top: board.scrollTop,
                });
            }
        });
    }

    return {
        // Public Functions
        init: function () {
            element = '#kt_docs_jkanban_fixed_height';
            kanbanEl = document.querySelector(element);

            exampleFixedHeight();
        }
    };
}();

// On document ready
KTUtil.onDOMContentLoaded(function () {
    KTJKanbanDemoFixedHeight.init();
});

/******/ })()
;
//# sourceMappingURL=fixed-height.js.map