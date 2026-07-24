(function () {
    'use strict';

    function initializeCarousel(carousel) {
        if (!(carousel instanceof HTMLElement) || carousel.dataset.redGalleryReady === 'true') {
            return;
        }

        var slides = Array.prototype.slice.call(
            carousel.querySelectorAll('[data-red-gallery-slide]')
        );
        var previous = carousel.querySelector('[data-red-gallery-previous]');
        var next = carousel.querySelector('[data-red-gallery-next]');
        var dots = Array.prototype.slice.call(
            carousel.querySelectorAll('[data-red-gallery-dot]')
        );
        var status = carousel.querySelector('[data-red-gallery-status]');

        if (slides.length < 2 || !previous || !next || dots.length !== slides.length) {
            return;
        }

        var currentIndex = 0;

        function update(nextIndex, announce) {
            currentIndex = (nextIndex + slides.length) % slides.length;

            slides.forEach(function (slide, index) {
                var active = index === currentIndex;
                slide.hidden = !active;
                slide.setAttribute('aria-hidden', active ? 'false' : 'true');
            });

            dots.forEach(function (dot, index) {
                if (index === currentIndex) {
                    dot.setAttribute('aria-current', 'true');
                } else {
                    dot.removeAttribute('aria-current');
                }
            });

            if (status) {
                status.textContent = 'Photo ' + (currentIndex + 1) + ' of ' + slides.length;
                if (announce) {
                    status.setAttribute('aria-live', 'polite');
                }
            }
        }

        previous.addEventListener('click', function () {
            update(currentIndex - 1, true);
        });

        next.addEventListener('click', function () {
            update(currentIndex + 1, true);
        });

        dots.forEach(function (dot, index) {
            dot.addEventListener('click', function () {
                update(index, true);
            });
        });

        carousel.addEventListener('keydown', function (event) {
            if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
                return;
            }

            var nextIndex = null;
            if (event.key === 'ArrowLeft') {
                nextIndex = currentIndex - 1;
            } else if (event.key === 'ArrowRight') {
                nextIndex = currentIndex + 1;
            } else if (event.key === 'Home') {
                nextIndex = 0;
            } else if (event.key === 'End') {
                nextIndex = slides.length - 1;
            }

            if (nextIndex === null) {
                return;
            }

            event.preventDefault();
            update(nextIndex, true);
        });

        carousel.dataset.redGalleryReady = 'true';
        update(0, false);
        carousel.classList.add('is-ready');
    }

    function initializeGalleries() {
        document.querySelectorAll('[data-red-gallery-carousel]').forEach(initializeCarousel);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeGalleries, { once: true });
    } else {
        initializeGalleries();
    }
}());
