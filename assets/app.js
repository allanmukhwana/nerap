/**
 * =========================================================================
 * app.js — shared front-end helpers used across public + admin pages.
 * Requires jQuery (loaded in header). No build step — plain jQuery/JS.
 * =========================================================================
 */

/**
 * Animates a counter element from 0 to its target value.
 * Usage: <span class="count-up" data-target="1234">0</span>
 */
function animateCounters(scope) {
    $(scope || document).find('.count-up').each(function () {
        var $el = $(this);
        var target = parseInt($el.data('target'), 10) || 0;
        var current = parseInt($el.text(), 10) || 0;
        if (current === target) return;
        $({ val: current }).animate({ val: target }, {
            duration: 800,
            step: function (now) { $el.text(Math.floor(now).toLocaleString()); },
            complete: function () { $el.text(target.toLocaleString()); }
        });
    });
}

/** Toggle the admin sidebar on mobile (hamburger button). */
$(function () {
    $('#adminSidebarToggle').on('click', function () {
        $('#adminSidebar').toggleClass('open');
    });
    $(document).on('click', function (e) {
        if ($(window).width() < 992 && !$(e.target).closest('#adminSidebar, #adminSidebarToggle').length) {
            $('#adminSidebar').removeClass('open');
        }
    });

    // Highlight active bottom-tab / sidebar link based on current page.
    var page = window.location.pathname.split('/').pop();
    $('.mobile-tabbar .tab-item, .admin-sidebar .nav-link').each(function () {
        var href = $(this).attr('href');
        if (href && page.indexOf(href) === 0) $(this).addClass('active');
    });

    animateCounters(document);
});

/**
 * Simple toast helper (Bootstrap 5 toast) for AJAX success/error feedback.
 * Requires a #toastContainer element in the page footer.
 */
function nerapToast(message, type) {
    type = type || 'success';
    var id = 'toast' + Date.now();
    var bg = type === 'success' ? 'text-bg-success' : (type === 'error' ? 'text-bg-danger' : 'text-bg-secondary');
    var html = '<div id="' + id + '" class="toast ' + bg + '" role="alert"><div class="d-flex">' +
        '<div class="toast-body">' + message + '</div>' +
        '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>' +
        '</div></div>';
    $('#toastContainer').append(html);
    var toastEl = document.getElementById(id);
    var toast = new bootstrap.Toast(toastEl, { delay: 3500 });
    toast.show();
    $(toastEl).on('hidden.bs.toast', function () { $(this).remove(); });
}
