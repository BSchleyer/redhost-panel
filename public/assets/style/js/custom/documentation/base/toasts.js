/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
var __webpack_exports__ = {};
/*!***********************************************************!*\
  !*** ../demo8/src/js/custom/documentation/base/toasts.js ***!
  \***********************************************************/


// Class definition
const KTBaseToastDemos = function () {
    // Private functions
    const exampleToggle = () => {
        // Select elements
        const button = document.getElementById('kt_docs_toast_toggle_button');
        const toastElement = document.getElementById('kt_docs_toast_toggle');

        // Get toast instance --- more info: https://getbootstrap.com/docs/5.1/components/toasts/#getinstance
        const toast = bootstrap.Toast.getOrCreateInstance(toastElement);

        // Handle button click
        button.addEventListener('click', e => {
            e.preventDefault();

            // Toggle toast to show --- more info: https://getbootstrap.com/docs/5.1/components/toasts/#show
            toast.show();
        });
    }

    const exampleStack = () => {
        // Select elements
        const button = document.getElementById('kt_docs_toast_stack_button');
        const container = document.getElementById('kt_docs_toast_stack_container');
        const targetElement = document.querySelector('[data-kt-docs-toast="stack"]'); // Use CSS class or HTML attr to avoid duplicating ids

        // Remove base element markup
        targetElement.parentNode.removeChild(targetElement);

        // Handle button click
        button.addEventListener('click', e => {
            e.preventDefault();

            // Create new toast element
            const newToast = targetElement.cloneNode(true);
            container.append(newToast);

            // Create new toast instance --- more info: https://getbootstrap.com/docs/5.1/components/toasts/#getorcreateinstance
            const toast = bootstrap.Toast.getOrCreateInstance(newToast);

            // Toggle toast to show --- more info: https://getbootstrap.com/docs/5.1/components/toasts/#show
            toast.show();
        });
    }

    return {
        // Public Functions
        init: function () {
            exampleToggle();
            exampleStack();
        }
    };
}();

// On document ready
KTUtil.onDOMContentLoaded(function () {
    KTBaseToastDemos.init();
});
/******/ })()
;
//# sourceMappingURL=toasts.js.map