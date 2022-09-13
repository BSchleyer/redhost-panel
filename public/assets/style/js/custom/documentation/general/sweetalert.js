/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
var __webpack_exports__ = {};
/*!******************************************************************!*\
  !*** ../demo8/src/js/custom/documentation/general/sweetalert.js ***!
  \******************************************************************/


// Class definition
var KTGeneralSweetAlertDemos = function() {
    // Private functions
    const exampleBasic = function() {
        const button = document.getElementById('kt_docs_sweetalert_basic');

        button.addEventListener('click', e =>{
            e.preventDefault();

            Swal.fire({
                text: "Here's a basic example of SweetAlert!",
                icon: "success",
                buttonsStyling: false,
                confirmButtonText: "Ok, got it!",
                customClass: {
                    confirmButton: "btn btn-primary"
                }
            });
        });
    }

    const exampleHtml = function() {
        const button = document.getElementById('kt_docs_sweetalert_html');

        button.addEventListener('click', e =>{
            e.preventDefault();

            Swal.fire({
                html: 'A SweetAlert content with <strong>bold text</strong>, <a href="#">links</a> or any of our available <span class="badge badge-primary">components</span>',
                icon: "info",
                buttonsStyling: false,
                showCancelButton: true,
                confirmButtonText: "Ok, got it!",
                cancelButtonText: 'Nope, cancel it',
                customClass: {
                    confirmButton: "btn btn-primary",
                    cancelButton: 'btn btn-danger'
                }
            });
        });
    }

    const exampleStates = () => {
        // Select buttons
        const infoButton = document.getElementById('kt_docs_sweetalert_state_info');
        const warningButton = document.getElementById('kt_docs_sweetalert_state_warning');
        const errorButton = document.getElementById('kt_docs_sweetalert_state_error');
        const successButton = document.getElementById('kt_docs_sweetalert_state_success');
        const questionButton = document.getElementById('kt_docs_sweetalert_state_question');

        // Click action handlers
        infoButton.addEventListener('click', e => {
            e.preventDefault();

            Swal.fire({
                text: "Here's an example of an info SweetAlert!",
                icon: "info",
                buttonsStyling: false,
                confirmButtonText: "Ok, got it!",
                customClass: {
                    confirmButton: "btn btn-info"
                }
            });
        });

        warningButton.addEventListener('click', e => {
            e.preventDefault();

            Swal.fire({
                text: "Here's an example of a warning SweetAlert!",
                icon: "warning",
                buttonsStyling: false,
                confirmButtonText: "Ok, got it!",
                customClass: {
                    confirmButton: "btn btn-warning"
                }
            });
        });

        errorButton.addEventListener('click', e => {
            e.preventDefault();

            Swal.fire({
                text: "Here's an example of an error SweetAlert!",
                icon: "error",
                buttonsStyling: false,
                confirmButtonText: "Ok, got it!",
                customClass: {
                    confirmButton: "btn btn-danger"
                }
            });
        });

        successButton.addEventListener('click', e => {
            e.preventDefault();

            Swal.fire({
                text: "Here's an example of a success SweetAlert!",
                icon: "success",
                buttonsStyling: false,
                confirmButtonText: "Ok, got it!",
                customClass: {
                    confirmButton: "btn btn-success"
                }
            });
        });

        questionButton.addEventListener('click', e => {
            e.preventDefault();

            Swal.fire({
                text: "Here's an example of a question SweetAlert!",
                icon: "question",
                buttonsStyling: false,
                confirmButtonText: "Ok, got it!",
                customClass: {
                    confirmButton: "btn btn-primary"
                }
            });
        });
    }

    return {
        // Public Functions
        init: function() {
            exampleBasic();
            exampleHtml();
            exampleStates();
        }
    };
}();

// On document ready
KTUtil.onDOMContentLoaded(function() {
    KTGeneralSweetAlertDemos.init();
});

/******/ })()
;
//# sourceMappingURL=sweetalert.js.map