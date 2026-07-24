(function (window, document) {
  "use strict";

  function copyText(value) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(value);
    }

    return new Promise(function (resolve, reject) {
      var helper = document.createElement("textarea");
      helper.value = value;
      helper.setAttribute("readonly", "");
      helper.style.position = "fixed";
      helper.style.opacity = "0";
      document.body.appendChild(helper);
      helper.select();
      try {
        document.execCommand("copy") ? resolve() : reject(new Error("Copy failed."));
      } catch (error) {
        reject(error);
      }
      document.body.removeChild(helper);
    });
  }

  function setMessage(root, text, tone) {
    var message = root.querySelector("[data-logo-message]");
    if (!message) {
      return;
    }
    message.textContent = text;
    message.setAttribute("data-tone", tone || "neutral");
  }

  function setProgress(root, visible, value, label) {
    var wrapper = root.querySelector("[data-logo-progress]");
    var bar = root.querySelector("[data-logo-progress-bar]");
    var text = root.querySelector("[data-logo-progress-label]");
    if (!wrapper || !bar || !text) {
      return;
    }
    wrapper.hidden = !visible;
    bar.value = Math.max(0, Math.min(100, value || 0));
    text.textContent = label || "Uploading logo…";
  }

  function validateFile(root, file) {
    var maximum = parseInt(root.getAttribute("data-max-bytes"), 10) || 2097152;
    var extension = (file.name.split(".").pop() || "").toLowerCase();
    if (["png", "jpg", "jpeg"].indexOf(extension) === -1) {
      return "Choose a PNG, JPG, or JPEG image.";
    }
    if (["image/png", "image/jpeg"].indexOf(file.type) === -1) {
      return "The selected file is not a supported raster image.";
    }
    if (!file.size || file.size > maximum) {
      return "Choose an image smaller than 2 MB.";
    }
    return "";
  }

  function uploadFile(root, file) {
    var error = validateFile(root, file);
    if (error) {
      setMessage(root, error, "error");
      return;
    }

    var url = root.getAttribute("data-upload-url");
    var recordId = parseInt(root.getAttribute("data-record-id"), 10) || 0;
    var body = new FormData();
    var request = new XMLHttpRequest();
    body.append("pic", file);

    setMessage(root, "Uploading " + file.name + "…", "working");
    setProgress(root, true, 0, "Uploading " + file.name + "…");

    request.open("POST", url, true);
    request.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    request.upload.addEventListener("progress", function (event) {
      if (!event.lengthComputable) {
        return;
      }
      setProgress(root, true, Math.round((event.loaded / event.total) * 100), "Uploading " + file.name + "…");
    });
    request.addEventListener("load", function () {
      var response = {};
      try {
        response = JSON.parse(request.responseText || "{}");
      } catch (parseError) {
        response = {};
      }

      if (request.status < 200 || request.status >= 300 || !response.stored_name) {
        setProgress(root, false, 0, "");
        setMessage(root, response.status || "The logo could not be uploaded.", "error");
        return;
      }

      setProgress(root, true, 100, "Upload complete");
      setMessage(root, "Logo saved. Refreshing the editor…", "success");
      window.setTimeout(function () {
        if (typeof window.edit_advanced === "function" && recordId > 0) {
          window.edit_advanced(recordId);
          return;
        }
        window.location.reload();
      }, 700);
    });
    request.addEventListener("error", function () {
      setProgress(root, false, 0, "");
      setMessage(root, "The upload was interrupted. Please try again.", "error");
    });
    request.send(body);
  }

  function initialize(root) {
    if (!root || root.getAttribute("data-logo-ready") === "true") {
      return;
    }
    root.setAttribute("data-logo-ready", "true");

    var input = root.querySelector("[data-logo-file]");
    var dropzone = root.querySelector("[data-logo-dropzone]");
    var copyButton = root.querySelector("[data-logo-copy]");

    if (input) {
      input.addEventListener("change", function () {
        if (input.files && input.files[0]) {
          uploadFile(root, input.files[0]);
        }
        input.value = "";
      });
    }

    if (dropzone) {
      ["dragenter", "dragover"].forEach(function (eventName) {
        dropzone.addEventListener(eventName, function (event) {
          event.preventDefault();
          dropzone.setAttribute("data-dragging", "true");
        });
      });
      ["dragleave", "drop"].forEach(function (eventName) {
        dropzone.addEventListener(eventName, function (event) {
          event.preventDefault();
          dropzone.removeAttribute("data-dragging");
        });
      });
      dropzone.addEventListener("drop", function (event) {
        var files = event.dataTransfer ? event.dataTransfer.files : null;
        if (files && files[0]) {
          uploadFile(root, files[0]);
        }
      });
    }

    if (copyButton) {
      copyButton.addEventListener("click", function () {
        var path = copyButton.getAttribute("data-copy-value") || "";
        var label = copyButton.querySelector("[data-logo-copy-label]");
        copyText(window.location.origin + path).then(function () {
          if (label) {
            label.textContent = "Copied";
            window.setTimeout(function () {
              label.textContent = "Copy image URL";
            }, 1600);
          }
        }).catch(function () {
          setMessage(root, "Copy failed. Select the image URL manually.", "error");
        });
      });
    }
  }

  document.querySelectorAll("[data-red-advanced-logo]").forEach(initialize);
})(window, document);
