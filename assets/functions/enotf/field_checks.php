<script>
    const inputElements = document.querySelectorAll('.edivi__input-check');

    function toggleInputChecked(inputElement) {
        if (inputElement.tagName === 'SELECT') {
            const selectedOption = inputElement.querySelector('option:checked');
            if (selectedOption && !selectedOption.disabled) {
                inputElement.classList.add('edivi__input-checked');
            } else {
                inputElement.classList.remove('edivi__input-checked');
            }
        } else {
            if (inputElement.value.trim() === '') {
                inputElement.classList.remove('edivi__input-checked');
            } else {
                inputElement.classList.add('edivi__input-checked');
            }
        }

        const groupContainer = inputElement.closest('.edivi__box');
        const groupHeading = groupContainer ? groupContainer.querySelector('h5.edivi__group-check') : null;

        if (groupHeading) {
            inputElement.style.borderLeft = '0';
        } else {
            inputElement.style.borderLeft = '';
        }
    }

    function checkGroupStatus() {
        const groupHeadings = document.querySelectorAll('h5.edivi__group-check');

        groupHeadings.forEach(groupHeading => {
            const groupContainer = groupHeading.closest('.edivi__box');
            if (!groupContainer) return;

            const groupInputs = groupContainer.querySelectorAll('.edivi__input-check');

            let allFilled = true;
            groupInputs.forEach(input => {
                if (input.tagName === 'SELECT') {
                    const selectedOption = input.querySelector('option:checked');
                    if (!selectedOption || selectedOption.disabled) {
                        allFilled = false;
                    }
                } else if (input.value.trim() === '') {
                    allFilled = false;
                }

                input.style.borderLeft = '0';
            });

            if (allFilled) {
                groupHeading.classList.add('edivi__group-checked');
            } else {
                groupHeading.classList.remove('edivi__group-checked');
            }
        });
    }

    inputElements.forEach(inputElement => {
        toggleInputChecked(inputElement);
        inputElement.addEventListener('input', () => {
            toggleInputChecked(inputElement);
            checkGroupStatus();
        });
    });

    document.addEventListener('DOMContentLoaded', checkGroupStatus);

    document.addEventListener('DOMContentLoaded', function() {
        const clickableBoxes = document.querySelectorAll('.edivi__box-clickable');

        clickableBoxes.forEach(function(box) {
            box.addEventListener('click', function() {
                window.location.href = this.getAttribute('data-href');
            });
        });
    });

    $(document).ready(function() {
        const zeroIsValid = [
            'patsex', 'awsicherung_neu', 'b_symptome',
            'b_auskult', 'b_beatmung', 'c_kreislauf', 'c_ekg',
            'd_bewusstsein', 'd_ex_1', 'd_pupillenw_1', 'd_pupillenw_2',
            'd_lichtreakt_1', 'd_lichtreakt_2', 'd_gcs_1', 'd_gcs_2', 'd_gcs_3', 'v_muster_k', 'v_muster_t',
            'v_muster_a', 'v_muster_al', 'v_muster_bl', 'v_muster_w', 'transportziel'
        ];

        function validateLinks() {
            $("[class*='edivi__interactbutton'] a[data-requires]").each(function() {
                const $link = $(this);
                const requirements = $link.data("requires") || $link.attr("data-requires");
                console.log("Link:", $link.text().trim(), "Requirements:", requirements);
                if (requirements && requirements !== "" && !$link.hasClass("edivi__validation-ignore")) {
                    const isValid = validateRequirements(requirements);
                    $link.removeClass("edivi__validation-green edivi__validation-red");
                    if (isValid === true) {
                        $link.addClass("edivi__validation-green");
                    } else {
                        $link.addClass("edivi__validation-red");
                    }
                    console.log("Link validated:", $link.text().trim(), "Valid:", isValid);
                }
            });
        }

        function validateRequirements(requirements) {
            if (!requirements || requirements === "") return true;
            const groups = requirements.split(',');
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
                if (!groupValid) {
                    console.log("Group invalid:", group);
                    return false;
                }
            }
            return true;
        }

        function getFieldValue(fieldName) {
            try {
                const phpData = <?= json_encode($daten ?? []) ?>;
                return phpData[fieldName];
            } catch (e) {
                console.error("Error getting field value:", e);
                return null;
            }
        }

        validateLinks();
        $(document).on('change', 'input, select, textarea', function() {
            setTimeout(validateLinks, 100);
        });
    });
</script>