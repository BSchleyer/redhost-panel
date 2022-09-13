/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
var __webpack_exports__ = {};
/*!***************************************************************!*\
  !*** ../demo8/src/js/custom/documentation/forms/recaptcha.js ***!
  \***************************************************************/


// Class definition
var KTFormsGoogleRecaptchaDemos = function () {
    // Private functions
    var example = function (element) {
        document.querySelector("#kt_form_submit_button").addEventListener("click", function (e) {
            e.preventDefault();

            grecaptcha.ready(function () {
                if (grecaptcha.getResponse() === "") {
                    alert("Please validate the Google reCaptcha.");
                } else {
                    alert("Successful validation! Now you can submit this form to your server side processing.");
                }
            });
        });
    }

    return {
        // Public Functions
        init: function (element) {
            example();
        }
    };
}();

// On document ready
KTUtil.onDOMContentLoaded(function () {
    KTFormsGoogleRecaptchaDemos.init();
});

/******/ })()
;
//# sourceMappingURL=recaptcha.js.map