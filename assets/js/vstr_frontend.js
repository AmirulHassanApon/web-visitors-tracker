(function ($) {
    "use strict";

    var vstr_lastDom = false;
    var vstr_lastScrollY = -1;
    var vstr_lastScrollTime = 1000;
    vstr_ajaxurl = vstr_ajaxurl[0];
    vstr_noTactile = vstr_noTactile[0];
    vstr_userID[0] = vstr_userID[0].replace(/(\r\n|\n|\r)/gm, "");
    vstr_userID[0] = vstr_userID[0].replace(" ", "");
    jQuery(document).ready(function () {
        if (!vstr_isIframe()) {
            if (vstr_mode[0] == 'all' || vstr_userID[0] > 0) {
                if (vstr_noTactile == 1 && vstr_is_touch_device()) {
                } else {
                    vstr_initTracker();
                }
            }
        } else {
            if (jQuery(window.parent.document).find('#vstr_frame').length > 0) {
                  window.parent.jQuery('body').trigger('vstr_initFrame');
            }
        }
    });
    function vstr_getBrowser() {
        var ua = navigator.userAgent, tem,
                M = ua.match(/(opera|chrome|safari|firefox|msie|trident(?=\/))\/?\s*(\d+)/i) || [];
        if (/trident/i.test(M[1])) {
            tem = /\brv[ :]+(\d+)/g.exec(ua) || [];
            return 'IE ' + (tem[1] || '');
        }
        if (M[1] === 'Chrome') {
            tem = ua.match(/\bOPR\/(\d+)/);
            if (tem != null)
                return 'Opera ' + tem[1];
        }
        M = M[2] ? [M[1], M[2]] : [navigator.appName, navigator.appVersion, '-?'];
        if ((tem = ua.match(/version\/(\d+)/i)) != null)
            M.splice(1, 1, tem[1]);
        return M.join(' ');
    }
    function vstr_is_touch_device() {
        return (('ontouchstart' in window)
                || (navigator.MaxTouchPoints > 0)
                || (navigator.msMaxTouchPoints > 0));
    }

    var vstr_visitID = 0;
    function vstr_initTracker() {
        if (!sessionStorage.vstrID) {
            jQuery.ajax({
                url: vstr_ajaxurl,
                type: 'post',
                data: {
                    action: 'vstr_newVisit',
                    screenWidth: jQuery(window).width(),
                    screenHeight: jQuery(window).height(),
                    browser: vstr_getBrowser(),
                    ip: vstr_ip[0]
                },
                success: function (newVisitID) {
                    newVisitID = newVisitID.replace(/(\r\n|\n|\r)/gm, "");
                    newVisitID = newVisitID.replace(" ", "");
                    vstr_visitID = newVisitID;
                    sessionStorage.vstrID = vstr_visitID;
                    vstr_initListeners();
                }
            });
        } else {
            if (vstr_userID[0] > 0 && sessionStorage.vstr_userID && sessionStorage.vstr_userID != vstr_userID[0]) {
                sessionStorage.clear();
                vstr_initTracker();
            } else {
                vstr_visitID = sessionStorage.vstrID;
                vstr_newStep('changePage');
                vstr_initListeners();
            }
        }
    }
    function vstr_isIframe() {
        try {
            return window.self !== window.top;
        } catch (e) {
            return true;
        }
    }

    function vstr_checkIfChildHover($el, rep) {
        if (!rep) {
            var rep = false;
        }
        if (rep != 'start') {
            if ($el.is('.vstr_isHover')) {
                rep = true;
            }
        }
        if (rep == 'start') {
            rep = false;
        }
        if (!rep && $el.children().length > 0) {
            $el.children().each(function () {
                rep = vstr_checkIfChildHover(jQuery(this), rep);
            });
        }
        return rep;

    }
    function vstr_checkScroll() {
        var d = new Date();
        var n = d.getTime();
        if (n - vstr_lastScrollTime >= 1000) {

            if (window.pageYOffset != vstr_lastScrollY) {
                vstr_lastScrollY = window.pageYOffset;
                if (isNaN(vstr_lastScrollY)) {
                    vstr_lastScrollY = 0;
                }
                vstr_newStep('scroll', false, vstr_lastScrollY);
            }
        }
    }
    function vstr_initListeners() {
        jQuery('body *').on('mouseenter',function () {
            jQuery(this).addClass('vstr_isHover');
            var $el = jQuery(this);
            setTimeout(function () {
                if ($el.is('.vstr_isHover') && !vstr_checkIfChildHover($el, 'start')) {
                    if ((!vstr_lastDom) || (vstr_lastDom && !vstr_lastDom.is($el))) {
                        vstr_newStep('hover', $el.get(0));
                        vstr_lastDom = $el;
                    }
                }
            }, 600);
        }).on('mouseleave', function () {
            jQuery(this).removeClass('vstr_isHover');
        });
        if (vstr_is_touch_device()) {
            vstr_newStep('scroll', false, 0);
            jQuery(window).scroll(function () {
                var d = new Date();
                var n = d.getTime();
                vstr_lastScrollTime = n;
                setTimeout(vstr_checkScroll, 1000);
            });
        }

        jQuery('a,button,input,select,textarea,*:not(a)>img').on('click',function () {
            vstr_newStep('click', this);
            vstr_lastDom = jQuery(this);
        });
    }

    function vstr_getPath(el) {
        var path = '';
        if (jQuery(el).length > 0 && typeof (jQuery(el).prop('tagName')) != "undefined") {
            if (!jQuery(el).attr('id')) {
                path = '>' + jQuery(el).prop('tagName') + ':nth-child(' + (jQuery(el).index() + 1) + ')' + path;
                path = vstr_getPath(jQuery(el).parent()) + path;
            } else {
                path += '#' + jQuery(el).attr('id');
            }
        }
        return path;
    }

    function vstr_newStep(type, element, value) {
        var domElement = '';
        if (element) {
            domElement = vstr_getPath(element);
        }
        if (!value) {
            value = '';

        }
        if (type == 'scroll' && value == '') {
            value = 0;
        }
        jQuery.ajax({
            url: vstr_ajaxurl,
            type: 'post',
            data: {
                action: 'vstr_newStep',
                visitID: vstr_visitID,
                type: type,
                userID: vstr_userID[0],
                page: document.location.href,
                domElement: domElement,
                value: value,
            },
            success: function (rep) {

            }
        });
    }
})(jQuery);
