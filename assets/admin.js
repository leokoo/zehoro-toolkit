/**
 * Bricks Site Toolkit — Admin JS
 */
jQuery(document).ready(function($) {
    // Initialize WordPress color picker
    if ($.isFunction($.fn.wpColorPicker)) {
        $('.lkst-color-picker').wpColorPicker();
    }
});
