(function () {
  "use strict";

  function itemForToggle(toggle) {
    return toggle.closest(".starter-navigation__item");
  }

  function setExpanded(toggle, expanded) {
    var item = itemForToggle(toggle);
    if (!item) {
      return;
    }

    item.classList.toggle("is-open", expanded);
    toggle.setAttribute("aria-expanded", expanded ? "true" : "false");

    var label = toggle.querySelector(".starter-visually-hidden");
    if (label) {
      label.textContent = label.textContent.replace(
        expanded ? /^Show / : /^Hide /,
        expanded ? "Hide " : "Show "
      );
    }
  }

  function closeBranch(item) {
    Array.prototype.forEach.call(
      item.querySelectorAll("[data-starter-navigation-toggle]"),
      function (toggle) {
        setExpanded(toggle, false);
      }
    );
  }

  function closeSiblings(item) {
    if (!item.parentElement) {
      return;
    }

    Array.prototype.forEach.call(item.parentElement.children, function (sibling) {
      if (sibling !== item && sibling.classList.contains("starter-navigation__item")) {
        closeBranch(sibling);
      }
    });
  }

  function initializeNavigation(root) {
    var toggles = Array.prototype.slice.call(
      root.querySelectorAll("[data-starter-navigation-toggle]")
    );

    toggles.forEach(function (toggle) {
      var item = itemForToggle(toggle);
      setExpanded(toggle, Boolean(item && item.classList.contains("is-active")));

      toggle.addEventListener("click", function () {
        var expanded = toggle.getAttribute("aria-expanded") === "true";
        if (!expanded && item) {
          closeSiblings(item);
        }
        setExpanded(toggle, !expanded);
      });
    });

    root.addEventListener("keydown", function (event) {
      if (event.key !== "Escape") {
        return;
      }

      var item = event.target.closest(".starter-navigation__item.is-open");
      if (!item || !root.contains(item)) {
        return;
      }

      var toggle = item.querySelector("[data-starter-navigation-toggle]");
      if (!toggle) {
        return;
      }

      event.preventDefault();
      closeBranch(item);
      toggle.focus();
    });

    document.addEventListener("click", function (event) {
      if (root.contains(event.target)) {
        return;
      }

      Array.prototype.forEach.call(
        root.querySelectorAll(".starter-navigation__item.is-open"),
        closeBranch
      );
    });

    root.setAttribute("data-navigation-ready", "true");
  }

  function initializeAll() {
    Array.prototype.forEach.call(
      document.querySelectorAll("[data-starter-navigation]"),
      initializeNavigation
    );
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeAll, { once: true });
  } else {
    initializeAll();
  }
})();
