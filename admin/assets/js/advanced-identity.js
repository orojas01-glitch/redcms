(function (window, document) {
  "use strict";

  function inputFor(form) {
    return form.querySelector("[data-advanced-identity-input]");
  }

  function setStatus(form, text, state) {
    var status = form.querySelector("[data-advanced-identity-status]");
    if (!status) {
      return;
    }
    status.textContent = text;
    status.setAttribute("data-state", state);
  }

  function setMessage(form, text, tone) {
    var message = form.querySelector("#msggbox_update_advanced");
    if (!message) {
      return;
    }
    message.textContent = text;
    message.setAttribute("data-tone", tone);
    message.hidden = false;
  }

  function updatePresentation(form) {
    var input = inputFor(form);
    if (!input) {
      return;
    }

    var value = input.value;
    var trimmed = value.trim();
    var savedValue = form.getAttribute("data-saved-value") || "";
    var idealMin = parseInt(form.getAttribute("data-ideal-min") || "0", 10);
    var idealMax = parseInt(form.getAttribute("data-ideal-max") || "0", 10);
    var emptyPreview = form.getAttribute("data-empty-preview") || "Not configured";
    var emptyGuidance = form.getAttribute("data-empty-guidance") || "This field is optional.";
    var count = form.querySelector("[data-advanced-identity-count]");
    var guidance = form.querySelector("[data-advanced-identity-guidance]");
    var restore = form.querySelector("[data-advanced-identity-restore]");
    var changed = value !== savedValue;

    if (count) {
      count.textContent = String(value.length);
    }

    Array.prototype.forEach.call(
      form.querySelectorAll("[data-advanced-identity-preview]"),
      function (preview) {
        preview.textContent = trimmed || emptyPreview;
        preview.classList.toggle("is-empty", trimmed === "");
      }
    );

    if (guidance) {
      if (trimmed === "") {
        guidance.textContent = emptyGuidance;
        guidance.setAttribute("data-state", "empty");
      } else if (value.length < idealMin) {
        guidance.textContent = "Clear, but very short. Add a little context if it improves recognition.";
        guidance.setAttribute("data-state", "short");
      } else if (idealMax > 0 && value.length > idealMax) {
        guidance.textContent = "Consider shortening this for browser tabs and compact template areas.";
        guidance.setAttribute("data-state", "long");
      } else {
        guidance.textContent = "Good length for clear, compact display.";
        guidance.setAttribute("data-state", "ready");
      }
    }

    if (restore) {
      restore.disabled = !changed;
    }
    setStatus(form, changed ? "Unsaved changes" : "Saved value", changed ? "changed" : "ready");
  }

  function copyValue(form) {
    var input = inputFor(form);
    var button = form.querySelector("[data-advanced-identity-copy]");
    var label = form.querySelector("[data-advanced-identity-copy-label]");
    if (!input || !button || !label) {
      return;
    }

    function resetLabel() {
      window.setTimeout(function () {
        button.classList.remove("is-copied", "has-copy-error");
        label.textContent = "Copy value";
      }, 1800);
    }

    function copied() {
      button.classList.add("is-copied");
      button.classList.remove("has-copy-error");
      label.textContent = "Copied";
      resetLabel();
    }

    function failed() {
      button.classList.add("has-copy-error");
      label.textContent = "Copy failed";
      resetLabel();
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(input.value).then(copied, failed);
      return;
    }

    input.focus();
    input.select();
    try {
      document.execCommand("copy") ? copied() : failed();
    } catch (error) {
      failed();
    }
  }

  function initialize(form) {
    if (form.getAttribute("data-advanced-identity-ready") === "true") {
      return;
    }

    var input = inputFor(form);
    var copy = form.querySelector("[data-advanced-identity-copy]");
    var restore = form.querySelector("[data-advanced-identity-restore]");

    if (input) {
      input.addEventListener("input", function () {
        updatePresentation(form);
      });
    }

    if (copy) {
      copy.addEventListener("click", function () {
        copyValue(form);
      });
    }

    if (restore && input) {
      restore.addEventListener("click", function () {
        input.value = form.getAttribute("data-saved-value") || "";
        input.dispatchEvent(new Event("input", { bubbles: true }));
        input.focus();
      });
    }

    updatePresentation(form);
    form.setAttribute("data-advanced-identity-ready", "true");
  }

  window.run_update_advanced = function (form) {
    var submit = form.querySelector(".red-admin-advanced-save");
    var itemLabel = form.getAttribute("data-item-label") || "Advanced item";
    var requestUrl = form.getAttribute("data-submit-url") || "/admin/bin/update_advanced.php";

    if (!window.jQuery) {
      setMessage(form, "The editor could not start the save request. Reload and try again.", "error");
      return false;
    }

    if (submit) {
      submit.disabled = true;
    }
    setStatus(form, "Saving…", "saving");
    setMessage(form, "Saving " + itemLabel + "…", "pending");

    window.jQuery.ajax({
      type: "POST",
      url: requestUrl,
      data: window.jQuery(form).serialize(),
      success: function (data) {
        if (data === "yes") {
          setStatus(form, "Saved", "ready");
          setMessage(form, itemLabel + " saved.", "success");
          window.setTimeout(function () {
            window.location.reload();
          }, 700);
          return;
        }
        setStatus(form, "Save failed", "error");
        setMessage(form, "The change was not saved. Please try again.", "error");
        if (submit) {
          submit.disabled = false;
        }
      },
      error: function () {
        setStatus(form, "Save failed", "error");
        setMessage(form, "The change was not saved. Check the connection and try again.", "error");
        if (submit) {
          submit.disabled = false;
        }
      },
    });
    return false;
  };

  Array.prototype.forEach.call(
    document.querySelectorAll("[data-red-advanced-identity]"),
    initialize
  );
})(window, document);
