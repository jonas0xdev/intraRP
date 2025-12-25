"use strict";

// Global state
var truppTimers = {
  1: {
    running: false,
    startTime: null,
    elapsedSeconds: 0,
    interval: null
  },
  2: {
    running: false,
    startTime: null,
    elapsedSeconds: 0,
    interval: null
  },
  3: {
    running: false,
    startTime: null,
    elapsedSeconds: 0,
    interval: null
  }
}; // Constants

var MAX_TIME_SECONDS = 60 * 60; // 60 minutes

var CIRCLE_CIRCUMFERENCE = 471.24; // 2 * PI * 75 (neuer Radius)
// Initialize on load

document.addEventListener("DOMContentLoaded", function () {
  updateCurrentTime();
  setInterval(updateCurrentTime, 1000); // Set today's date as default in DD.MM.YYYY format

  var today = new Date();
  var day = String(today.getDate()).padStart(2, "0");
  var month = String(today.getMonth() + 1).padStart(2, "0");
  var year = today.getFullYear();
  var missionDateField = document.getElementById("missionDate");

  if (missionDateField) {
    missionDateField.value = "".concat(day, ".").concat(month, ".").concat(year);
  } // Initialize timer displays


  for (var i = 1; i <= 3; i++) {
    updateTruppDisplay(i);
  }
}); // Update current time display

function updateCurrentTime() {
  var now = new Date();
  var hours = String(now.getHours()).padStart(2, "0");
  var minutes = String(now.getMinutes()).padStart(2, "0");
  var timeElement = document.getElementById("currentTime");

  if (timeElement) {
    timeElement.textContent = "".concat(hours, ":").concat(minutes);
  }
} // Format seconds to MM:SS


function formatTime(seconds) {
  var mins = Math.floor(seconds / 60);
  var secs = seconds % 60;
  return "".concat(String(mins).padStart(2, "0"), ":").concat(String(secs).padStart(2, "0"));
} // Update trupp timer display


function updateTruppDisplay(truppNum) {
  var timer = truppTimers[truppNum];
  var timeElement = document.getElementById("trupp".concat(truppNum, "Time"));
  var progressCircle = document.getElementById("trupp".concat(truppNum, "ProgressCircle"));
  var handElement = document.getElementById("trupp".concat(truppNum, "Hand"));
  var progressBar = document.getElementById("trupp".concat(truppNum, "ProgressBar"));
  var percentElement = document.getElementById("trupp".concat(truppNum, "Percent"));

  if (timeElement) {
    timeElement.textContent = formatTime(timer.elapsedSeconds); // Update warning states

    timeElement.classList.remove("text-warning", "text-danger");

    if (timer.elapsedSeconds >= 3000) {
      // 50 minutes
      timeElement.classList.add("text-danger");
    } else if (timer.elapsedSeconds >= 2400) {
      // 40 minutes
      timeElement.classList.add("text-warning");
    }
  } // Calculate percentage


  var percentage = timer.elapsedSeconds / MAX_TIME_SECONDS;
  var percentValue = Math.round(percentage * 100); // Update SVG circle progress

  if (progressCircle) {
    var offset = CIRCLE_CIRCUMFERENCE * (1 - percentage);
    progressCircle.style.strokeDashoffset = offset; // Change color based on time

    if (timer.elapsedSeconds >= 3000) {
      progressCircle.style.stroke = "#dc3545"; // danger
    } else if (timer.elapsedSeconds >= 2400) {
      progressCircle.style.stroke = "#ffc107"; // warning
    } else {
      progressCircle.style.stroke = "#d10000"; // main color
    }
  } // Update clock hand (rotates 360 degrees over 60 minutes)


  if (handElement) {
    var degrees = timer.elapsedSeconds / MAX_TIME_SECONDS * 360;
    handElement.style.transform = "rotate(".concat(degrees, "deg)"); // Change hand color based on time

    if (timer.elapsedSeconds >= 3000) {
      handElement.style.stroke = "#dc3545"; // danger
    } else if (timer.elapsedSeconds >= 2400) {
      handElement.style.stroke = "#ffc107"; // warning
    } else {
      handElement.style.stroke = "#d10000"; // main color
    }
  } // Update progress bar


  if (progressBar) {
    progressBar.style.width = percentValue + "%";
    progressBar.setAttribute("aria-valuenow", percentValue); // Change color based on time

    progressBar.classList.remove("bg-danger", "bg-warning");

    if (timer.elapsedSeconds >= 3000) {
      progressBar.classList.add("bg-danger");
    } else if (timer.elapsedSeconds >= 2400) {
      progressBar.classList.add("bg-warning");
    } else {
      progressBar.classList.add("bg-danger");
    }
  } // Update percentage text


  if (percentElement) {
    percentElement.textContent = percentValue + "%";
  }
} // Start trupp timer


function startTrupp(truppNum) {
  var timer = truppTimers[truppNum];

  if (timer.running) {
    console.log("Trupp ".concat(truppNum, " is already running"));
    return;
  } // Set start time if not already set


  var startTimeInput = document.getElementById("trupp".concat(truppNum, "StartTime"));

  if (startTimeInput && !startTimeInput.value) {
    var now = new Date();
    var hours = String(now.getHours()).padStart(2, "0");
    var minutes = String(now.getMinutes()).padStart(2, "0");
    startTimeInput.value = "".concat(hours, ":").concat(minutes);
  }

  timer.running = true;
  timer.startTime = Date.now() - timer.elapsedSeconds * 1000;
  timer.interval = setInterval(function () {
    if (timer.running) {
      var elapsed = Math.floor((Date.now() - timer.startTime) / 1000);
      timer.elapsedSeconds = Math.min(elapsed, MAX_TIME_SECONDS);
      updateTruppDisplay(truppNum); // Auto-stop at max time

      if (timer.elapsedSeconds >= MAX_TIME_SECONDS) {
        stopTrupp(truppNum);
      }
    }
  }, 1000);
  console.log("Trupp ".concat(truppNum, " started"));
} // Stop trupp timer


function stopTrupp(truppNum) {
  var timer = truppTimers[truppNum];

  if (!timer.running) {
    console.log("Trupp ".concat(truppNum, " is not running"));
    return;
  }

  timer.running = false;

  if (timer.interval) {
    clearInterval(timer.interval);
    timer.interval = null;
  } // Set end time if not already set


  var endTimeInput = document.getElementById("trupp".concat(truppNum, "End"));

  if (endTimeInput && !endTimeInput.value) {
    var now = new Date();
    var hours = String(now.getHours()).padStart(2, "0");
    var minutes = String(now.getMinutes()).padStart(2, "0");
    endTimeInput.value = "".concat(hours, ":").concat(minutes);
  }

  console.log("Trupp ".concat(truppNum, " stopped at ").concat(formatTime(timer.elapsedSeconds)));
} // Reset trupp timer


function resetTrupp(truppNum) {
  stopTrupp(truppNum);
  var timer = truppTimers[truppNum];
  timer.elapsedSeconds = 0;
  timer.startTime = null;
  updateTruppDisplay(truppNum);
} // Get trupp data


function getTruppData(truppNum) {
  var getFieldValue = function getFieldValue(id) {
    var element = document.getElementById(id);
    return element ? element.value : "";
  };

  return {
    truppNumber: truppNum,
    elapsedTime: truppTimers[truppNum].elapsedSeconds,
    tf: getFieldValue("trupp".concat(truppNum, "TF")),
    tm1: getFieldValue("trupp".concat(truppNum, "TM1")),
    tm2: getFieldValue("trupp".concat(truppNum, "TM2")),
    startPressure: getFieldValue("trupp".concat(truppNum, "StartPressure")),
    startTime: getFieldValue("trupp".concat(truppNum, "StartTime")),
    mission: getFieldValue("trupp".concat(truppNum, "Mission")),
    check1: getFieldValue("trupp".concat(truppNum, "Check1")),
    check2: getFieldValue("trupp".concat(truppNum, "Check2")),
    objective: getFieldValue("trupp".concat(truppNum, "Objective")),
    retreat: getFieldValue("trupp".concat(truppNum, "Retreat")),
    end: getFieldValue("trupp".concat(truppNum, "End")),
    remarks: getFieldValue("trupp".concat(truppNum, "Remarks"))
  };
} // Get all data


function getAllData() {
  var getFieldValue = function getFieldValue(id) {
    var element = document.getElementById(id);
    return element ? element.value : "";
  };

  return {
    missionNumber: getFieldValue("missionNumber"),
    missionLocation: getFieldValue("missionLocation"),
    missionDate: getFieldValue("missionDate"),
    supervisor: getFieldValue("supervisor"),
    trupp1: getTruppData(1),
    trupp2: getTruppData(2),
    trupp3: getTruppData(3),
    timestamp: new Date().toISOString()
  };
} // Clear all data


function clearAll() {
  showConfirm("Möchten Sie wirklich alle Daten löschen?", {
    title: "Daten löschen",
    danger: true,
    confirmText: "Löschen",
    cancelText: "Abbrechen"
  }).then(function (confirmed) {
    if (!confirmed) {
      return;
    } // Stop all timers


    for (var i = 1; i <= 3; i++) {
      resetTrupp(i);
    } // Clear all inputs except mission date and pre-filled fields


    var inputs = document.querySelectorAll('#asuForm input[type="text"]:not(#missionNumber):not(#missionLocation), #asuForm input[type="number"], #asuForm input[type="time"], #asuForm textarea, #asuForm select');
    inputs.forEach(function (input) {
      input.value = "";
    });
    console.log("All data cleared");
  });
} // Send data to server


function sendData() {
  var data = getAllData(); // Validate required fields

  if (!data.missionNumber || !data.missionLocation || !data.missionDate || !data.supervisor) {
    showAlert("Bitte füllen Sie alle Pflichtfelder aus: Einsatznummer, Einsatzort, Einsatzdatum, Überwacher", {
      title: "Fehlende Pflichtfelder",
      type: "warning"
    });
    return;
  } // Check if at least one trupp has data


  var hasTruppData = false;

  for (var i = 1; i <= 3; i++) {
    var truppData = data["trupp".concat(i)];

    if (truppData.tf && truppData.tm1) {
      hasTruppData = true;
      break;
    }
  }

  if (!hasTruppData) {
    showAlert("Bitte geben Sie mindestens für einen Trupp die Pflichtfelder (TF und TM1) an!", {
      title: "Fehlende Trupp-Daten",
      type: "warning"
    });
    return;
  }

  console.log("Sending ASU data:", data); // Get incident ID from hidden field (if exists) or URL parameter

  var incidentId = null;
  var incidentIdField = document.getElementById("incidentId");

  if (incidentIdField) {
    incidentId = incidentIdField.value;
  } else {
    // Fallback to URL parameter for backwards compatibility
    var urlParams = new URLSearchParams(window.location.search);
    incidentId = urlParams.get("id");
  } // Send to backend


  fetch(basePath + "einsatz/actions.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded"
    },
    body: new URLSearchParams({
      action: "add_asu",
      incident_id: incidentId || "",
      asu_data: JSON.stringify(data)
    })
  }).then(function (response) {
    return response.text();
  }).then(function (result) {
    console.log("ASU data saved:", result); // Show success message

    showAlert("Das ASU-Protokoll wurde erfolgreich gespeichert.", {
      title: "Erfolgreich gespeichert",
      type: "success"
    }); // Reload the page to show the new protocol in the list

    setTimeout(function () {
      window.location.reload();
    }, 1500);
  })["catch"](function (error) {
    console.error("Error sending ASU data:", error);
    showAlert("Fehler beim Senden der Daten!", {
      title: "Fehler",
      type: "error"
    });
  });
} // View ASU Protocol


function viewASUProtocol(data) {
  var modal = new bootstrap.Modal(document.getElementById("viewASUModal"));
  var viewContainer = document.getElementById("asuProtocolView");
  var html = "\n    <div class=\"row mb-3\">\n      <div class=\"col-md-3\">\n        <strong>Einsatznummer:</strong><br>\n        ".concat(escapeHtml(data.missionNumber || "-"), "\n      </div>\n      <div class=\"col-md-3\">\n        <strong>Einsatzort:</strong><br>\n        ").concat(escapeHtml(data.missionLocation || "-"), "\n      </div>\n      <div class=\"col-md-3\">\n        <strong>Datum:</strong><br>\n        ").concat(escapeHtml(data.missionDate || "-"), "\n      </div>\n      <div class=\"col-md-3\">\n        <strong>\xDCberwacher:</strong><br>\n        ").concat(escapeHtml(data.supervisor || "-"), "\n      </div>\n    </div>\n    <hr>\n  "); // Display each trupp

  for (var i = 1; i <= 3; i++) {
    var trupp = data["trupp".concat(i)];
    if (!trupp || !trupp.tf) continue;
    var truppName = i === 3 ? "Sicherheitstrupp" : "".concat(i, ". Trupp");
    html += "\n      <div class=\"card mb-3\">\n        <div class=\"card-header bg-dark\">\n          <h5 class=\"mb-0\">".concat(truppName, "</h5>\n        </div>\n        <div class=\"card-body\">\n          <div class=\"row mb-2\">\n            <div class=\"col-md-4\"><strong>Truppf\xFChrer:</strong> ").concat(escapeHtml(trupp.tf || "-"), "</div>\n            <div class=\"col-md-4\"><strong>Truppmann 1:</strong> ").concat(escapeHtml(trupp.tm1 || "-"), "</div>\n            <div class=\"col-md-4\"><strong>Truppmann 2:</strong> ").concat(escapeHtml(trupp.tm2 || "-"), "</div>\n          </div>\n          <div class=\"row mb-2\">\n            <div class=\"col-md-4\"><strong>Anfangsdruck:</strong> ").concat(escapeHtml(trupp.startPressure || "-"), " bar</div>\n            <div class=\"col-md-4\"><strong>Einsatzbeginn:</strong> ").concat(escapeHtml(trupp.startTime || "-"), "</div>\n            <div class=\"col-md-4\"><strong>Auftrag:</strong> ").concat(escapeHtml(trupp.mission || "-"), "</div>\n          </div>\n          <div class=\"row mb-2\">\n            <div class=\"col-md-6\"><strong>1. Kontrolle:</strong> ").concat(escapeHtml(trupp.check1 || "-"), "</div>\n            <div class=\"col-md-6\"><strong>2. Kontrolle:</strong> ").concat(escapeHtml(trupp.check2 || "-"), "</div>\n          </div>\n          <div class=\"row mb-2\">\n            <div class=\"col-md-4\"><strong>Einsatzziel:</strong> ").concat(escapeHtml(trupp.objective || "-"), "</div>\n            <div class=\"col-md-4\"><strong>R\xFCckzug:</strong> ").concat(escapeHtml(trupp.retreat || "-"), "</div>\n            <div class=\"col-md-4\"><strong>Einsatzende:</strong> ").concat(escapeHtml(trupp.end || "-"), "</div>\n          </div>\n          <div class=\"row mb-2\">\n            <div class=\"col-md-4\"><strong>Einsatzzeit:</strong> ").concat(formatTime(trupp.elapsedTime || 0), "</div>\n          </div>\n          ").concat(trupp.remarks ? "\n          <div class=\"row\">\n            <div class=\"col-12\">\n              <strong>Bemerkungen:</strong><br>\n              ".concat(escapeHtml(trupp.remarks), "\n            </div>\n          </div>\n          ") : "", "\n        </div>\n      </div>\n    ");
  }

  viewContainer.innerHTML = html;
  modal.show();
} // Delete ASU Protocol


function deleteASUProtocol(asuId) {
  showConfirm("Möchten Sie dieses ASU-Protokoll wirklich löschen?", {
    title: "Protokoll löschen",
    danger: true,
    confirmText: "Löschen",
    cancelText: "Abbrechen"
  }).then(function (confirmed) {
    if (!confirmed) {
      return;
    }

    var urlParams = new URLSearchParams(window.location.search);
    var incidentId = urlParams.get("id");
    fetch(basePath + "einsatz/actions.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded"
      },
      body: new URLSearchParams({
        action: "delete_asu",
        incident_id: incidentId,
        asu_id: asuId,
        return_tab: "asu"
      })
    }).then(function (response) {
      if (response.redirected) {
        window.location.href = response.url;
      }
    })["catch"](function (error) {
      console.error("Error deleting ASU protocol:", error);
      showAlert("Fehler beim Löschen des Protokolls!", {
        title: "Fehler",
        type: "error"
      });
    });
  });
} // Helper function to escape HTML


function escapeHtml(text) {
  if (!text) return "-";
  var map = {
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#039;"
  };
  return text.toString().replace(/[&<>"']/g, function (m) {
    return map[m];
  });
}