class SemontoHealthMonitorServerMonitoringForm {
  formElement = null
  enableCMSUpdates = null
  enableCMSUpdatesNonCritical = null

  checkForInvalidForms () {
    const invalidInputs = Array.from(this.formElement.querySelectorAll('.semonto-health-monitor__test-input--error'))

    if (invalidInputs.length > 0) {
      invalidInputs[0].scrollIntoView({ block: 'center' })
    }
  }

  handleCMSUpdatesChange () {
    if (this.enableCMSUpdates.checked) {
      this.enableCMSUpdatesNonCritical.disabled = false
    } else {
      this.enableCMSUpdatesNonCritical.disabled = true
    }
  }

  constructor(formElement) {
    this.formElement = formElement

    this.enableCMSUpdates = this.formElement.querySelector('input[name="tests[CraftCMSUpdates][enabled]"')
    this.enableCMSUpdatesNonCritical = this.formElement.querySelector('input[name="tests[CraftCMSUpdates][config][alert_non_critical_updates]"]')

    this.enableCMSUpdates.addEventListener('change', () => this.handleCMSUpdatesChange())

    this.checkForInvalidForms()
  }
}

const formElement = document.querySelector(".semonto-health-monitor__settings-form");
if (formElement) {
  new SemontoHealthMonitorServerMonitoringForm(formElement);
}

function confirmReset() {
  return confirm("Are you sure you want to reset to the default values?");
}
