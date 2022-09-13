// begin::Global Metronic 8 Engagement
window.onload = function(e){
    // Class definition
    var KTCookie2 = function() {
        return {
            // returns the cookie with the given name,
            // or undefined if not found
            get: function(name) {
                var matches = document.cookie.match(new RegExp(
                    "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
                ));

                return matches ? decodeURIComponent(matches[1]) : null;
            },

            // Please note that a cookie value is encoded,
            // so getCookie uses a built-in decodeURIComponent function to decode it.
            set: function(name, value, options) {
                if ( typeof options === "undefined" || options === null ) {
                    options = {};
                }

                options = Object.assign({}, {
                    path: '/'
                }, options);

                if ( options.expires instanceof Date ) {
                    options.expires = options.expires.toUTCString();
                }

                var updatedCookie = encodeURIComponent(name) + "=" + encodeURIComponent(value);

                for ( var optionKey in options ) {
                    if ( options.hasOwnProperty(optionKey) === false ) {
                        continue;
                    }

                    updatedCookie += "; " + optionKey;
                    var optionValue = options[optionKey];

                    if ( optionValue !== true ) {
                        updatedCookie += "=" + optionValue;
                    }
                }

                document.cookie = updatedCookie;
            },

            // To remove a cookie, we can call it with a negative expiration date:
            remove: function(name) {
                this.set(name, "", {
                    'max-age': -1
                });
            }
        }
    }();

    var htmlCode = '';
    htmlCode += '<div style="display: flex; align-items: center; margin-right: 10px;">';
    htmlCode += '    <img style="height: 26px; margin-left: 10px; margin-right: 25px; margin-bottom: 2px" src="https://preview.keenthemes.com/assets/kt_logo.svg" alt=""/>';
    htmlCode += '    <div style="color: #FFFFFF; font-size: 14px;">';
    htmlCode += '        <b style="padding-right: 5px;">All New Metronic 8 is LIVE!</b>';
    htmlCode += '        <span style="opacity: 0.75">With Bootststrap 5, improved documentation and many more amazing features.</span>';
    htmlCode += '    </div>';
    htmlCode += '</div>';

    htmlCode += '<div style="display: flex; align-items: center;">';
    htmlCode += '    <a href="https://keenthemes.com/metronic/?page=metronic8" style="text-decoration: none !important; background-color: #00AB4E; color: #ffffff; font-weight: bold; text-transform: uppercase; padding: 10px 16px 9px 16px; border-radius: 6px; margin-right: 12px;">';
    htmlCode += '        Preview Metronic 8';
    htmlCode += '    </a>';
    htmlCode += '    <a href="#" style="padding: 6px;" id="kt_metronic_8_engage_dismiss">';
    htmlCode += '        <img style="height: 26px" src="https://preview.keenthemes.com/assets/kt_close_icon.svg"  alt=""/>';
    htmlCode += '    </a>'
    htmlCode += '</div>';

    // get name
    var path = window.location.pathname;
    var page = path.split('/').filter(function (el) {
        return el !== '';
    })[0];

    if (!KTCookie2.get(page) || (KTCookie2.get(page) && !KTCookie2.get(page + '_2')) ) {
        var metronic8Engage = document.createElement("div");

        metronic8Engage.setAttribute('style', 'transform: translateX(-50%); position: fixed; z-index: 10000; max-width: 1300px; width: 100%; margin: 0 30px; left: 50%; bottom: 60px; background-color: #050421; padding: 20px; border-radius: 6px; box-shadow: 0px 5px 30px rgba(5, 4, 33, 0.3); display: flex; align-items: center; justify-content: space-between;');
        metronic8Engage.innerHTML = htmlCode;

        setTimeout(function() {
            document.body.appendChild(metronic8Engage);

            document.querySelector('#kt_metronic_8_engage_dismiss').addEventListener('click', function(e) {
                e.preventDefault();
                var date = new Date();
                var time3days = 1000 * 60 * 60 * 24 * 3;
                var time30days = 1000 * 60 * 60 * 24 * 30;

                KTCookie2.set(page, '1', {expires: new Date(date.getTime() + time3days)});
                KTCookie2.set(page + '_2', '1', {expires: new Date(date.getTime() + time30days)});

                document.body.removeChild(metronic8Engage);
            });
        }, 1000 * 8);
    }
};
// end::Global Metronic 8 Engagement