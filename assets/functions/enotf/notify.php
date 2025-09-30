<div id="toast-container" style="position: fixed; bottom: 20px; right: 20px; z-index: 9999;"></div>
<script>
    $(document).ready(function() {
        const enr = <?= json_encode($enr) ?>;
        const inputElements = $(
            "form[name='form'] input:not([readonly]):not([disabled]):not(.medikament-field-ignore):not([data-ignore-autosave]), " +
            "form[name='form'] select:not([readonly]):not([disabled]):not(.medikament-field-ignore):not([data-ignore-autosave]), " +
            "form[name='form'] textarea:not([readonly]):not([disabled]):not(.medikament-field-ignore):not([data-ignore-autosave])"
        );
        const activeRequests = {};

        const zeroIsValid = [
            'patsex', 'awsicherung_neu', 'b_symptome',
            'b_auskult', 'b_beatmung', 'c_kreislauf', 'c_ekg', 'c_zugang',
            'd_bewusstsein', 'd_ex_1', 'd_pupillenw_1', 'd_pupillenw_2',
            'd_lichtreakt_1', 'd_lichtreakt_2', 'd_gcs_1', 'd_gcs_2', 'd_gcs_3', 'v_muster_k', 'v_muster_t',
            'v_muster_a', 'v_muster_al', 'v_muster_bl', 'v_muster_w', 'transportziel', 'medis'
        ];

        inputElements.each(function() {
            $(this).data('original-value', $(this).val());
        });

        function updateNavFillStates(data) {
            $("#edivi__nidanav a[data-requires]").each(function() {
                const $link = $(this);
                const requiresRaw = $link[0].dataset.requires;
                if (!requiresRaw) return;

                const groups = requiresRaw.split(",");
                let filledGroups = 0;

                groups.forEach(group => {
                    const options = group.split("|").map(key => key.trim());
                    const isGroupFilled = options.some(field => {
                        const val = data[field];
                        return (
                            val !== null &&
                            typeof val !== "undefined" &&
                            (val !== "" && (val !== 0 || zeroIsValid.includes(field)))
                        );
                    });
                    if (isGroupFilled) filledGroups++;
                });

                const totalGroups = groups.length;
                const isFullyFilled = filledGroups === totalGroups;
                const isPartiallyFilled = filledGroups > 0 && filledGroups < totalGroups;

                $link
                    .toggleClass("edivi__nidanav-filled", isFullyFilled)
                    .toggleClass("edivi__nidanav-partfilled", isPartiallyFilled)
                    .toggleClass("edivi__nidanav-unfilled", filledGroups === 0);
            });
        }

        function validateLinks() {
            $("[class*='edivi__interactbutton'] a[data-requires]").each(function() {
                const $link = $(this);
                const requirements = $link.data("requires") || $link.attr("data-requires");
                console.log("Link:", $link.text().trim(), "Requirements:", requirements);

                if (requirements && requirements !== "" && !$link.hasClass("edivi__validation-ignore")) {
                    const validationResult = validateRequirements(requirements);

                    $link.removeClass("edivi__validation-green edivi__validation-red edivi__validation-yellow");

                    if (validationResult === true) {
                        $link.addClass("edivi__validation-green");
                    } else if (validationResult === 'partial') {
                        $link.addClass("edivi__validation-yellow");
                    } else {
                        $link.addClass("edivi__validation-red");
                    }

                    console.log("Link validated:", $link.text().trim(), "Status:", validationResult);
                }
            });
        }

        function validateRequirements(requirements) {
            if (!requirements || requirements === "") return true;

            const groups = requirements.split(',');
            let allGroupsValid = true;
            let anyGroupValid = false;

            for (let group of groups) {
                const fields = group.split('|');
                let groupValid = false;

                for (let field of fields) {
                    const fieldName = field.trim();
                    const fieldValue = getFieldValue(fieldName);
                    console.log("Checking field:", fieldName, "Value:", fieldValue);

                    if (fieldValue !== null &&
                        fieldValue !== undefined &&
                        fieldValue !== '' &&
                        (fieldValue !== 0 || zeroIsValid.includes(fieldName)) &&
                        (fieldValue !== '0' || zeroIsValid.includes(fieldName))) {
                        groupValid = true;
                        break;
                    }
                }

                if (groupValid) {
                    anyGroupValid = true;
                } else {
                    allGroupsValid = false;
                }

                console.log("Group:", group, "Valid:", groupValid);
            }

            if (allGroupsValid) {
                return true;
            } else if (anyGroupValid) {
                return 'partial';
            } else {
                return false;
            }
        }

        function getFieldValue(fieldName) {
            try {
                return window.__dynamicDaten[fieldName];
            } catch (e) {
                console.error("Error getting field value:", e);
                return null;
            }
        }

        window.__dynamicDaten = <?= json_encode($daten) ?>;
        updateNavFillStates(window.__dynamicDaten);
        validateLinks();

        function showToast(message, type = 'success') {
            var bgColor = (type === 'success') ? '#28a745' : '#dc3545';
            var toast = $('<div></div>').text(message).css({
                'border-left': '4px solid ' + bgColor,
                'background-color': '#333',
                'color': '#fff',
                'padding': '10px 20px',
                'margin-top': '10px',
                'border-radius': '0',
                'box-shadow': '0 2px 6px rgba(0, 0, 0, 0.2)',
                'font-family': 'Arial, sans-serif',
                'font-size': '14px',
                'opacity': '0.95'
            });
            $('#toast-container').append(toast);
            setTimeout(function() {
                toast.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 4000);
        }

        window.showToast = showToast;

        $('input.btn-check[type="checkbox"]').on('change', function() {
            const clicked = $(this);
            const clickedId = clicked.attr('id');
            const base = clickedId.split('_')[0];
            const group = $('input.btn-check[type="checkbox"]').filter(function() {
                return $(this).attr('id')?.startsWith(base + '_');
            });

            group.each(function() {
                const $box = $(this);
                if ($box[0] !== clicked[0]) {
                    if ($box.is(':checked')) {
                        $box.prop('checked', false).trigger('change');
                    }
                }
            });

            clicked.trigger('blur');
        });

        inputElements.off('change blur').on('change blur', function(e) {
            const $this = $(this);
            const fieldName = $this.attr('name');
            const elementId = $this.attr('id');

            if (elementId === 'c_zugang-0') {
                return;
            }

            if ($this.hasClass('zugang-checkbox')) {
                return;
            }

            let currentValue;

            if ($this.is(':radio')) {
                currentValue = $('input[name="' + fieldName + '"]:checked').val();
            } else if ($this.is(':checkbox')) {
                currentValue = $this.is(':checked') ? 1 : 0;
            } else {
                currentValue = $this.val();
            }

            if ($this.is(':radio')) {
                const savedValue = window.__dynamicDaten[fieldName];
                console.log('Radio check:', fieldName, 'currentValue:', currentValue, 'savedValue:', savedValue, 'types:', typeof currentValue, typeof savedValue);

                if (String(currentValue) === String(savedValue)) {
                    console.log('Radio value unchanged, skipping save');
                    return;
                }
            } else {
                const originalValue = $this.data('original-value');
                if (!$this.hasClass('btn-check') && currentValue == originalValue) return;
            }

            if (!activeRequests[fieldName]) {
                activeRequests[fieldName] = true;

                let labelText = $('label[for="' + $this.attr('id') + '"]').text().trim();
                if (!labelText) {
                    const firstInput = $('input[name="' + fieldName + '"]').first();
                    labelText = $('label[for="' + firstInput.attr('id') + '"]').text().trim();
                }
                if (!labelText) {
                    labelText = fieldName;
                }

                console.log('Saving field:', fieldName, 'value:', currentValue);

                $.ajax({
                    url: '<?= BASE_PATH ?>assets/functions/save_fields.php',
                    type: 'POST',
                    data: {
                        enr: enr,
                        field: fieldName,
                        value: currentValue
                    },
                    success: function(response) {
                        showToast("✔️ '" + labelText + "' gespeichert.", 'success');

                        $('input[name="' + fieldName + '"]').data('original-value', currentValue);

                        window.__dynamicDaten[fieldName] = currentValue;
                        console.log('Updated __dynamicDaten[' + fieldName + ']:', currentValue);

                        updateNavFillStates(window.__dynamicDaten);
                        validateLinks();
                    },
                    error: function() {
                        showToast("❌ Fehler beim Speichern von '" + labelText + "'", 'error');
                    },
                    complete: function() {
                        activeRequests[fieldName] = false;
                    }
                });
            }
        });

        $(document).on('change', 'input, select, textarea', function() {
            setTimeout(function() {
                validateLinks();
            }, 50);
        });

        $('#final').on('click', function(e) {
            e.preventDefault();

            const plausibilityContent = document.getElementById('plausibility');
            if (plausibilityContent && plausibilityContent.innerText.trim().length > 0) {
                showToast("❌ Abschluss nicht möglich: Plausibilitätsprüfung nicht bestanden!", 'error');
                return;
            }

            const pfname = <?= json_encode($daten['pfname']) ?>;
            if (!pfname || pfname.trim() === "") {
                showToast("❌ Kein Protokollant angegeben!", 'error');
                return;
            }

            $(this).prop('disabled', true);

            $.ajax({
                url: '<?= BASE_PATH ?>assets/functions/save_fields.php',
                type: 'POST',
                data: {
                    enr: enr,
                    field: 'freigeber',
                    value: pfname
                },
                success: function(response) {
                    if (response.includes("erfolgreich")) {
                        window.location.href = "<?= BASE_PATH ?>enotf/protokoll/index.php?enr=" + enr;
                    } else {
                        showToast("❌ " + response, 'error');
                        $('#final').prop('disabled', false);
                    }
                },
                error: function() {
                    showToast("❌ Fehler beim Abschließen.", 'error');
                    $('#final').prop('disabled', false);
                }
            });
        });
    });
</script>