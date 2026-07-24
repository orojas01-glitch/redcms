(function () {
  "use strict";

  function initializeSlider(root) {
    var slides = Array.prototype.slice.call(root.querySelectorAll("[data-starter-hero-slide]"));
    if (slides.length < 2) {
      return;
    }

    var previous = root.querySelector("[data-starter-hero-previous]");
    var next = root.querySelector("[data-starter-hero-next]");
    var current = root.querySelector("[data-starter-hero-current]");
    var dots = Array.prototype.slice.call(root.querySelectorAll("[data-starter-hero-go-to]"));
    var activeIndex = 0;

    function showSlide(requestedIndex) {
      var nextIndex = (requestedIndex + slides.length) % slides.length;

      slides.forEach(function (slide, index) {
        var active = index === nextIndex;
        slide.hidden = !active;
        slide.classList.toggle("is-active", active);
      });

      dots.forEach(function (dot, index) {
        var active = index === nextIndex;
        dot.classList.toggle("is-active", active);
        if (active) {
          dot.setAttribute("aria-current", "true");
        } else {
          dot.removeAttribute("aria-current");
        }
      });

      activeIndex = nextIndex;
      if (current) {
        current.textContent = String(activeIndex + 1);
      }
    }

    if (previous) {
      previous.addEventListener("click", function () {
        showSlide(activeIndex - 1);
      });
    }

    if (next) {
      next.addEventListener("click", function () {
        showSlide(activeIndex + 1);
      });
    }

    dots.forEach(function (dot) {
      dot.addEventListener("click", function () {
        showSlide(parseInt(dot.getAttribute("data-starter-hero-go-to"), 10) || 0);
      });
    });

    root.addEventListener("keydown", function (event) {
      if (event.key === "ArrowLeft") {
        event.preventDefault();
        showSlide(activeIndex - 1);
      }
      if (event.key === "ArrowRight") {
        event.preventDefault();
        showSlide(activeIndex + 1);
      }
    });

    root.setAttribute("data-slider-ready", "true");
    showSlide(0);
  }

  function initializeAll() {
    Array.prototype.forEach.call(
      document.querySelectorAll("[data-starter-hero-slider]"),
      initializeSlider
    );
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeAll, { once: true });
  } else {
    initializeAll();
  }
})();
