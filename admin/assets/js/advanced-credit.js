(function (window, document) {
  "use strict";

  function selectedValue(form) {
    var selected = form.querySelector('input[name="ShortLine"]:checked');
    return selected ? selected.value : "Y";
  }

  function setStatus(form, text, state) {
    var status = form.querySelector("[data-advanced-credit-status]");
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
    var value = selectedValue(form);
    var savedValue = form.getAttribute("data-saved-value") || "Y";
    var preview = form.querySelector("[data-credit-preview]");
    var previewMessage = form.querySelector("[data-credit-preview-message]");

    Array.prototype.forEach.call(
      form.querySelectorAll("[data-credit-option]"),
      function (option) {
        var radio = option.querySelector('input[type="radio"]');
        option.classList.toggle("is-selected", !!radio && radio.checked);
      }
    );

    if (preview) {
      preview.hidden = value !== "Y";
    }
    if (previewMessage) {
      previewMessage.textContent = value === "Y"
        ? "Visible at the bottom of public pages."
        : "The public footer will remain unbranded.";
    }

    setStatus(
      form,
      value === savedValue ? "Saved value" : "Unsaved changes",
      value === savedValue ? "ready" : "changed"
    );
  }

  function initialize(form) {
    if (form.getAttribute("data-advanced-credit-ready") === "true") {
      return;
    }
    Array.prototype.forEach.call(
      form.querySelectorAll('input[name="ShortLine"]'),
      function (radio) {
        radio.addEventListener("change", function () {
          updatePresentation(form);
        });
      }
    );
    updatePresentation(form);
    form.setAttribute("data-advanced-credit-ready", "true");
  }

  window.run_update_advanced = function (form) {
    var submit = form.querySelector(".red-admin-advanced-save");
    var requestUrl = form.getAttribute("data-submit-url") || "/admin/bin/update_advanced.php";

    if (!window.jQuery) {
      setMessage(form, "The editor could not start the save request. Reload and try again.", "error");
      return false;
    }

    if (submit) {
      submit.disabled = true;
    }
    setStatus(form, "Saving…", "saving");
    setMessage(form, "Saving website credit preference…", "pending");

    window.jQuery.ajax({
      type: "POST",
      url: requestUrl,
      data: window.jQuery(form).serialize(),
      success: function (data) {
        if (data === "yes") {
          setStatus(form, "Saved", "ready");
          setMessage(form, "Website credit preference saved.", "success");
          window.setTimeout(function () {
            window.location.reload();
          }, 700);
          return;
        }
        setStatus(form, "Save failed", "error");
        setMessage(form, "The preference was not saved. Please try again.", "error");
        if (submit) {
          submit.disabled = false;
        }
      },
      error: function () {
        setStatus(form, "Save failed", "error");
        setMessage(form, "The preference was not saved. Check the connection and try again.", "error");
        if (submit) {
          submit.disabled = false;
        }
      },
    });
    return false;
  };

  Array.prototype.forEach.call(
    document.querySelectorAll("[data-red-advanced-credit]"),
    initialize
  );
})(window, document);
