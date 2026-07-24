(function (window, document) {
  "use strict";

  function editorFor(form) {
    return form.querySelector("[data-advanced-source]");
  }

  function setStatus(form, text, state) {
    var status = form.querySelector("[data-advanced-editor-status]");
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

  function updateCounts(form) {
    var source = editorFor(form);
    if (!source) {
      return;
    }
    var value = source.value;
    var lineCount = value === "" ? 0 : value.split(/\r\n|\r|\n/).length;
    var lineOutput = form.querySelector("[data-advanced-line-count]");
    var characterOutput = form.querySelector("[data-advanced-character-count]");
    if (lineOutput) {
      lineOutput.textContent = String(lineCount);
    }
    if (characterOutput) {
      characterOutput.textContent = String(value.length);
    }
  }

  function copySource(form) {
    var source = editorFor(form);
    var button = form.querySelector("[data-advanced-copy]");
    var label = form.querySelector("[data-advanced-copy-label]");
    if (!source || !button || !label) {
      return;
    }

    function copied() {
      button.classList.add("is-copied");
      button.classList.remove("has-copy-error");
      label.textContent = "Copied";
      window.setTimeout(function () {
        button.classList.remove("is-copied");
        label.textContent = "Copy source";
      }, 1800);
    }

    function failed() {
      button.classList.add("has-copy-error");
      label.textContent = "Copy failed";
      window.setTimeout(function () {
        button.classList.remove("has-copy-error");
        label.textContent = "Copy source";
      }, 2200);
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(source.value).then(copied, failed);
      return;
    }

    source.focus();
    source.select();
    try {
      document.execCommand("copy") ? copied() : failed();
    } catch (error) {
      failed();
    }
  }

  function initialize(form) {
    if (form.getAttribute("data-advanced-editor-ready") === "true") {
      return;
    }

    var source = editorFor(form);
    var copy = form.querySelector("[data-advanced-copy]");
    var initialValue = source ? source.value : "";

    if (source) {
      source.addEventListener("input", function () {
        updateCounts(form);
        setStatus(
          form,
          source.value === initialValue ? "Saved source" : "Unsaved changes",
          source.value === initialValue ? "ready" : "changed"
        );
      });

      source.addEventListener("keydown", function (event) {
        if (event.key !== "Tab") {
          return;
        }
        event.preventDefault();
        var start = source.selectionStart;
        var end = source.selectionEnd;
        source.setRangeText("  ", start, end, "end");
        source.dispatchEvent(new Event("input", { bubbles: true }));
      });
    }

    if (copy) {
      copy.addEventListener("click", function () {
        copySource(form);
      });
    }

    updateCounts(form);
    form.setAttribute("data-advanced-editor-ready", "true");
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
        if (data === "stale") {
          setStatus(form, "Reopen required", "warning");
          setMessage(
            form,
            "The active theme or stylesheet changed. Reopen Website CSS before saving.",
            "warning"
          );
        } else {
          setStatus(form, "Save failed", "error");
          setMessage(form, "The change was not saved. Please try again.", "error");
        }
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
    document.querySelectorAll("[data-red-advanced-editor]"),
    initialize
  );
})(window, document);
