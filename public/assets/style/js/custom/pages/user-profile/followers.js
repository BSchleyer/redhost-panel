/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
var __webpack_exports__ = {};
/*!**************************************************************!*\
  !*** ../demo8/src/js/custom/pages/user-profile/followers.js ***!
  \**************************************************************/


// Class definition
var KTProfileFollowers = function () {
    // init variables
    var showMoreButton = document.getElementById('kt_followers_show_more_button');
    var showMoreCards = document.getElementById('kt_followers_show_more_cards');

    // Private functions
    var handleShowMore = function () {
        // Show more click
        showMoreButton.addEventListener('click', function (e) {
            showMoreButton.setAttribute('data-kt-indicator', 'on');

            // Disable button to avoid multiple click 
            showMoreButton.disabled = true;
            
            setTimeout(function() {
                // Hide loading indication
                showMoreButton.removeAttribute('data-kt-indicator');

                // Enable button
				showMoreButton.disabled = false;

                // Hide button
                showMoreButton.classList.add('d-none');

                // Show card
                showMoreCards.classList.remove('d-none');

                // Scroll to card
                KTUtil.scrollTo(showMoreCards, 200);
            }, 2000);
        });
    }

    // Public methods
    return {
        init: function () {
            handleShowMore();
        }
    }
}();


// On document ready
KTUtil.onDOMContentLoaded(function() {
    KTProfileFollowers.init();
});
/******/ })()
;
//# sourceMappingURL=followers.js.map