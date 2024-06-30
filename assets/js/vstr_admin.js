(function ($) {
    "use strict";

    var vstr_infos;
    var vstr_panel;
    var vstr_frame;
    var vstr_cursor;
    var vstr_step = 0;
    var vstr_timer = false;
    
    jQuery(document).ready(function () {
        vstr_step = 0;
        if (vstr_visitDatas != "0" && vstr_visitDatas.steps) {
            vstr_viewVisit();
        }
        initListeners();
        jQuery(document).tooltip({
            items: ".infoUrls",
            content: function () {
                var element = jQuery(this);
                return element.attr('data-urls');
            }, open: function (event, ui) {
                ui.tooltip.css("max-width", "980px");
            }
        });
    });

    function initListeners() {
        
        $('body').on('vstr_initFrame',vstr_initFrame);
        
        $('#vstr_form_settings').on('submit', function () {
            jQuery("#vstr_response").hide();
            var data = {action: "vstr_settings_save"};
            jQuery('#vstr_form_settings input, #vstr_form_settings select, #vstr_form_settings textarea').each(function () {
                if (jQuery(this).attr('name')) {
                    eval('data.' + jQuery(this).attr('name') + ' = jQuery(this).val();');
                }
            });
            jQuery.post(ajaxurl, data, function (response) {
                jQuery("#vstr_response").html('<div id="message" class="updated"><p><strong>' + vstr_settings.txt_settingsSaved + '</strong>.</p></div>');
                setTimeout(function () {
                    document.location.href = 'admin.php?page=vstr-settings';
                }, 250);
            });

            return false;
        });

    }

    function vstr_viewVisit() {
        vstr_infos = jQuery('<div id="vstr_infos"></div>');
        if (vstr_settings[0].panelPosition == 'left') {
            vstr_infos.css({
                right: 'auto',
                left: 20
            });
        }
        vstr_panel = jQuery('<div id="vstr_panel"></div>');
        var $frame = jQuery('<iframe id="vstr_frame"></iframe>');

        if (vstr_visitDatas.steps[0].page.substr(0, 8) == 'https://' && document.location.href.substr(0, 7) == 'http://') {
            vstr_visitDatas.steps[0].page = 'http://' + vstr_visitDatas.steps[0].page.substr(8, vstr_visitDatas.steps[0].page.length);
        } else if (vstr_visitDatas.steps[0].page.substr(0, 7) == 'http://' && document.location.href.substr(0, 8) == 'https://') {
            vstr_visitDatas.steps[0].page = 'https://' + vstr_visitDatas.steps[0].page.substr(7, vstr_visitDatas.steps[0].page.length);
        }

        $frame.prop('src', vstr_visitDatas.steps[0].page);
        if (parseInt(vstr_visitDatas.screenWidth) > jQuery(window).width()) {
            vstr_visitDatas.screenWidth = jQuery(window).width();
        }
        if (parseInt(vstr_visitDatas.screenHeight) + 32 > jQuery(window).height()) {
            vstr_visitDatas.screenHeight = jQuery(window).height() - 32;
        } else {
            $frame.css({
                marginTop: 32 + (jQuery(window).height() / 2 - parseInt(vstr_visitDatas.screenHeight / 2))
            });
        }
        $frame.css({
            width: vstr_visitDatas.screenWidth,
            height: vstr_visitDatas.screenHeight
        });
        vstr_panel.append($frame);
        vstr_cursor = jQuery('<div id="vstr_cursor"></div>');
        jQuery('body').append(vstr_panel);
        jQuery('body').append(vstr_infos);
        vstr_panel.fadeIn(250);
        if (!sessionStorage.vstrAdminStep) {
            sessionStorage.vstrAdminStep = vstr_step;
        } else {
            vstr_step = parseInt(sessionStorage.vstrAdminStep);
        }
    }

    function vstr_initFrame() {
        vstr_panel.children('iframe').contents().find('html,body').css('overflow-y', 'auto !important');
        vstr_panel.children('iframe').contents().find('body').append(vstr_cursor);
        vstr_panel.children('iframe').contents().find('*').on('click',function (e) {
            e.preventDefault();
        });
        vstr_cursor.delay(500).fadeIn(250);
        vstr_startStep();
        vstr_displayInfosPage();
    }
    function vstr_getDelay(step1, step2) {
        var a = step1.date.split(" ");
        var d = a[0].split("-");
        var t = a[1].split(":");
        var date1 = new Date(d[0], (d[1] - 1), d[2], t[0], t[1], t[2]);
        a = step2.date.split(" ");
        d = a[0].split("-");
        t = a[1].split(":");
        var date2 = new Date(d[0], (d[1] - 1), d[2], t[0], t[1], t[2]);
        return Math.round((date2 - date1));
    }

    function vstr_displayInfosPage() {
        vstr_infos.html('');
        if (vstr_visitDatas.userID == 0) {
            vstr_infos.append('<p><span>User : </span><strong>' + vstr_visitDatas.user + '</strong></p>');
        } else {
            vstr_infos.append('<p><span>User : </span><a href="user-edit.php?user_id=' + vstr_visitDatas.userID + '"><strong>' + vstr_visitDatas.user + '</strong></a></p>');
        }

        var visitTimePast = (vstr_visitDatas.timePast);

        if (visitTimePast > 60) {
            var hour = Math.floor(visitTimePast / 3600);
            var mins = Math.floor(visitTimePast / 60);
            var sec = Math.floor(visitTimePast - mins * 60);
            if (hour < 10) {
                hour = '0' + hour;
            }
            if (mins < 10) {
                mins = '0' + mins;
            }
            if (sec < 10) {
                sec = Math.floor(sec);
                sec = '0' + sec;
            }
            visitTimePast = hour + ':' + mins + ':' + sec;
        } else {
            if (visitTimePast < 10) {
                visitTimePast = '0' + Math.floor(visitTimePast);
            }
            visitTimePast = '00:00:' + Math.floor(visitTimePast);
        }
        vstr_infos.append('<p><span>Visit total duration : </span><strong>' + visitTimePast + '</strong></p>');
        if (vstr_panel.children('iframe').contents().find('title').length > 0) {
            vstr_infos.append('<p><span>Current page : </span><strong>' + vstr_panel.children('iframe').contents().find('title').html() + '</strong></p>');
        }
        vstr_infos.append('<p><span>Current url : </span><strong>' + vstr_panel.children('iframe').prop('src') + '</strong></p>');


        var nextPageStep = false;
        var timePast = 0;
        jQuery.each(vstr_visitDatas.steps, function (i, step) {
            if (!nextPageStep && i > vstr_step) {
                if (step.timePast && step.timePast > 0) {
                    timePast += parseInt(step.timePast);
                }
            }
            if (!nextPageStep && i > vstr_step && step.page != '' && step.page != vstr_panel.children('iframe').prop('src')) {
                nextPageStep = step;
            }
        });
        if (nextPageStep) {
            var hour = Math.floor(timePast / 3600);
            var mins = Math.floor(timePast / 60);
            var sec = Math.floor(timePast - mins * 60);
            if (hour < 10) {
                hour = '0' + hour;
            }
            if (mins < 10) {
                mins = '0' + mins;
            }
            if (sec < 10) {
                sec = Math.floor(sec);
                sec = '0' + sec;
            }
            timePast = hour + ':' + mins + ':' + sec;
            vstr_infos.append('<p><span>Time past on this page : </span><strong>' + timePast + '</strong></p>');
        }
        var $arrows = jQuery('<div id="vstr_arrows"></div>');
        $arrows.append('<a href="javascript:" class="vstr_btn_prev"></a>');
        $arrows.append('<a href="javascript:" class="vstr_btn_pause"></a>');
        $arrows.append('<a href="javascript:" class="vstr_btn_next"></a>');
        
        $arrows.find('.vstr_btn_prev').on('click',vstr_prev);
        $arrows.find('.vstr_btn_pause').on('click',vstr_pause);
        $arrows.find('.vstr_btn_next').on('click',vstr_next);
        vstr_infos.append($arrows);
        var $link = jQuery('<a id="vstr_close" href="javascript:"></a>');
        $link.on('click',vstr_endVisit);
        vstr_infos.prepend($link);

    }

    function vstr_Timer(callback, delay) {
        var timerId, start, remaining = delay;

        this.pause = function () {
            window.clearTimeout(timerId);
            remaining -= new Date() - start;
        };

        this.resume = function () {
            start = new Date();
            timerId = window.setTimeout(callback, remaining);
        };

        this.resume();
    }

    function vstr_pause() {
        if (jQuery('.vstr_btn_pause').is('.play')) {
            jQuery('.vstr_btn_pause').removeClass('play');
            vstr_timer.resume();
        } else {
            jQuery('.vstr_btn_pause').addClass('play');
            vstr_timer.pause();
        }
    }
    function vstr_hasPrev() {
        var hasPrev = false;
        jQuery.each(vstr_visitDatas.steps, function (i, step) {
            if (i < vstr_step && step.page != '' && step.page != vstr_panel.children('iframe').prop('src')) {
                hasPrev = true;
            }
        });
        return hasPrev;
    }
    function vstr_prev() {
        var prevPageStep = -1;
        jQuery.each(vstr_visitDatas.steps, function (i, step) {
            if (prevPageStep < 0 && i < vstr_step && step.page != '' && step.page != vstr_panel.children('iframe').prop('src')) {
                prevPageStep = i;
            }
        });
        if (prevPageStep > -1) {
            vstr_step = prevPageStep;
            vstr_startStep();
        }
    }
    function vstr_next() {
        var nextPageStep = false;
        jQuery.each(vstr_visitDatas.steps, function (i, step) {
            if (!nextPageStep && i > vstr_step && step.page != '' && step.page != vstr_panel.children('iframe').prop('src')) {
                nextPageStep = i;
            }
        });
        if (nextPageStep) {
            vstr_step = nextPageStep;
            vstr_startStep();
        } else {
            vstr_endVisit();
        }
    }

    function vstr_clickFx() {
        var fx = jQuery('<div class="vstr_fx"></div>');
        fx.css({
            left: vstr_cursor.offset().left + vstr_cursor.outerWidth() / 2 - 15,
            top: vstr_cursor.offset().top + vstr_cursor.outerHeight() / 2 - 20
        });
        vstr_panel.children('iframe').contents().find('body').append(fx);
        fx.fadeIn(100);
        setTimeout(function () {
            fx.remove();
        }, 600);
    }
    function vstr_isAnyParentFixed($el, rep) {
        if (!rep) {
            var rep = false;
        }
        try {
            if ($el.parent().length > 0 && $el.parent().css('position') == "fixed") {
                rep = true;
            }
        } catch (e) {

        }

        if (!rep && $el.parent().length > 0) {
            rep = vstr_isAnyParentFixed($el.parent(), rep);
        }
        return rep;
    }

    function vstr_fakeClick(anchorObj) {
        if (anchorObj.click) {
            anchorObj.trigger('click');
        } else if (document.createEvent) {

            var evt = document.createEvent("MouseEvents");
            evt.initMouseEvent("click", true, true, window,
                    0, 0, 0, 0, 0, false, false, false, false, 0, null);
            var allowDefault = anchorObj.dispatchEvent(evt);

        }
    }

    function vstr_startStep() {
        sessionStorage.vstrAdminStep = vstr_step;
        var step = vstr_visitDatas.steps[vstr_step];
        if (step.page) {
            if (step.page.substr(0, 8) == 'https://' && document.location.href.substr(0, 7) == 'http://') {
                step.page = 'http://' + step.page.substr(8, step.page.length);
            } else if (step.page.substr(0, 7) == 'http://' && document.location.href.substr(0, 8) == 'https://') {
                step.page = 'https://' + step.page.substr(7, step.page.length);
            }
        }


        if (!vstr_hasPrev()) {
            jQuery('.vstr_btn_prev').hide();
        } else {
            jQuery('.vstr_btn_prev').show();
        }
        if (step.page && step.page != vstr_panel.children('iframe').prop('src')) {
            vstr_clickFx();
            setTimeout(function () {
                vstr_panel.children('iframe').prop('src', step.page);
                vstr_displayInfosPage();
            }, 1000);
        } else {
            vstr_panel.children('iframe').contents().find('#wpadminbar').remove();
            if (step.domElement != "" || step.type == 'scroll') {
                if (vstr_panel.children('iframe').contents().find(step.domElement).length > 0) {
                    vstr_cursor.animate({
                        left: vstr_panel.children('iframe').contents().find(step.domElement).offset().left + vstr_panel.children('iframe').contents().find(step.domElement).outerWidth() / 2,
                        top: vstr_panel.children('iframe').contents().find(step.domElement).offset().top + vstr_panel.children('iframe').contents().find(step.domElement).outerHeight() / 2
                    }, 400);
                    if (vstr_isAnyParentFixed(vstr_panel.children('iframe').contents().find(step.domElement))) {
                    } else {                       
                        vstr_panel.children('iframe').contents().find('html,body').animate({scrollTop: vstr_panel.children('iframe').contents().find(step.domElement).position().top - 200, scrollLeft: vstr_panel.children('iframe').contents().find(step.domElement).position().left - 100}, 500, 'easeInOutCubic');
                    }
                    if (step.type == 'click') {
                        setTimeout(function () {
                            vstr_clickFx();
                            if (step.domElement.indexOf('submit') == -1) {
                                if (vstr_panel.children('iframe').contents().find(step.domElement).length > 0 && !vstr_panel.children('iframe').contents().find(step.domElement).is('[type="submit"]')) {
                                    setTimeout(function () {
                                        vstr_panel.children('iframe').contents().find(step.domElement).get(0).trigger('click');
                                    }, 500);
                                }
                            }
                        }, 400);

                    }

                    if (step.type == 'hover') {
                        setTimeout(function () {
                            vstr_panel.children('iframe').contents().find(step.domElement).addClass('hover');
                        }, 600);
                    }
                    if (step.type == 'click' || step.type == 'hover') {
                        vstr_cursor.addClass('pointer');
                    } else {
                        vstr_cursor.removeClass('pointer');
                    }
                } else if (step.type == 'scroll') {
                    setTimeout(function () {
                        vstr_panel.children('iframe').contents().find('html,body').animate({scrollTop: parseInt(step.value)}, 1000);
                    }, 600);
                }
                if (vstr_visitDatas.steps[vstr_step + 1]) {
                    vstr_step++;
                    vstr_timer = new vstr_Timer(vstr_startStep, vstr_getDelay(step, vstr_visitDatas.steps[vstr_step]) + 300);
                } else {
                    vstr_timer = new vstr_Timer(vstr_endVisit, 3000);
                }
            } else {
                if (vstr_visitDatas.steps[vstr_step + 1]) {
                    vstr_step++;
                    vstr_startStep();
                } else {
                    vstr_endVisit();
                }

            }

        }

    }
    function vstr_endVisit() {
        sessionStorage.vstrAdminStep = 0;
        vstr_infos.fadeOut(500);
        vstr_panel.fadeOut(500);
        setTimeout(function () {
            vstr_panel.remove();
            vstr_infos.remove();
        }, 800);
    }
})(jQuery);
