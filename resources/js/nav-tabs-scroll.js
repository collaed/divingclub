/**
 * DivingClub — Scrollable tab bar affordance
 * On phones, .nav-tabs scrolls horizontally when it doesn't fit (see
 * _bubbles.scss). Toggles .is-scrollable so the edge fade only shows while
 * there's actually more to scroll to, not once the bar is fully visible or
 * already scrolled to its end.
 */
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.nav-tabs').forEach(function(nav) {
        function update() {
            var atEnd = nav.scrollLeft + nav.clientWidth >= nav.scrollWidth - 2;
            nav.classList.toggle('is-scrollable', nav.scrollWidth > nav.clientWidth + 1 && !atEnd);
        }
        update();
        nav.addEventListener('scroll', update);
        window.addEventListener('resize', update);
    });
});
