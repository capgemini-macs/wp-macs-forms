jQuery(document).ready(function ($) {
  /**
   * MAIN PROPER FORMS CONTROLLER
   */
  var ProperForms = new function () {
    var self = this

    self.formElement = ''
    self.formSuccessBox = ''
    self.formErrorBox = ''

    self.init = function ($formEl) {
      self.formElement = $formEl
      self.formSuccessBox = $formEl.closest('.mf_forms__container').find('.mf_form__success')
      self.formErrorBox = $formEl.closest('.mf_forms__container').find('.mf_form__errors')
      self.bindFileUploads()
      self.bindSubmit()

      if (typeof jQuery('select2') !== 'undefined') {
        self.selectDropdown()
      }
    }

    /**
     * Process file uploads asynchronously
     * The file should be uploaded immediately after being selected by user and store as encrypted blob
     * It will be marked as draft in WP and deleted if the submission is not sent successfully
     * After successful form submission file post will get published and binded to submission post.
     */
    self.bindFileUploads = function () {
      var fileFields = self.formElement.find('.mf_field--file')

      fileFields.each(function () {
        var upload = new PFFileUpload()
        upload.init($(this))
      })
    }

    /**
     * Proccess form on submit
     */
    self.bindSubmit = function () {
      // add error validation after click submit button
      $('.mf_field--submit').on('click', function () {
        $('.mf-required input, .mf-required textarea, .mf_field select, .mf_fileupload_callback_id').focus().focusout()
      })

      // Submit form
      self.formElement.submit(function (e) {
        e.preventDefault()

        var hasErrors = 0
        // Validate all fields again on submit
        $('.mf_field input, .mf_field textarea, .mf_field select, .mf_fileupload_callback_id').each(function () {
          if (PFValidator.init($(this)) === false) {
            hasErrors = 1
          }
        })

        if (hasErrors) {
          return
        }

        var data = {
          action: 'mf_submit_form',
          nonce_mf_submit: PF.ajaxNonce,
          recaptcha_response: grecaptcha.getResponse(),
          form_data: {}
        }

        var fields = $(':input', $(this)).serializeArray()

        $.each(fields, function (i, field) {
          // skip undefined
          if (!field.name) {
            return
          }

          // treat multiple inputs with same names[] as array of values, similar to PHP
          if (field.name.slice(-2) === '[]') {
            var realName = field.name.slice(0, -2)

            if (Array.isArray(data.form_data[ realName ])) {
              data.form_data[ realName ].push(field.value)
            } else {
              data.form_data[ realName ] = [ field.value ]
            }
          } else { // singular values
            data.form_data[ field.name ] = field.value
          }
        })

        data.form_data = JSON.stringify(data.form_data)

        $.ajax({
          url: PF.ajaxURL,
          method: 'post',
          action: 'mf_submit_form',
          nonce_mf_submit: PF.ajaxNonce,
          data: data
        }).done(function (response) {
          if (!response.success) {
            self.onFailure()
            return
          }

          self.onSuccess()
        }).fail(function () {
          self.onFailure()
        })
      })
    }

    // selec2 for select dropdowns
    self.selectDropdown = function () {
      var selects = self.formElement.find('select')

      selects.each(function () {
        $(this).select2({ dropdownParent: self.formElement })
      })

      selects.attr('aria-hidden', false)
      $('.select2').attr('aria-hidden', true)

      selects.on('select2:open', function (e) {
        iconSearch = $('<i />', { 'class': 'icon-ios-search-strong', 'aria-hidden': 'true' })
        $('.select2-search__field', self.formElement).attr('placeholder', 'Search')
        $('.select2-search--dropdown', self.formElement).append(iconSearch)
      })

      self.formElement.on('click', '.select-clear', function (e) {
        e.preventDefault()
        var $select = $(this).parents('.mf_field').find('select')
        $select.val($select.find('option:first').attr('value')).change()
        $select.select2('close')
        $(this).remove()
      })
    }

    /**
     * After succesful submission
     */
    self.onSuccess = function () {
      self.formElement.hide()
      self.formErrorBox.hide()
      self.formSuccessBox.show()
    }

    /**

     * After submission fail
     */
    self.onFailure = function () {
      self.formSuccessBox.hide()
      self.formErrorBox.show()
    }
  }()

  /**
   * AJAX FILE UPLOAD FIELDS CONTROLLER
   */
  function PFFileUpload () {
    var self = this

    // Field wrapper element
    self.parent = ''

    // File input to proccess
    self.input = ''

    // WP Ajax url with callback action query string
    self.ajaxURL = PF.ajaxURL + '?action=mf_fileupload'

    // Parent Proper Form Form ID - to retrieve field settings from
    self.formId = ''

    // Proper Forms field ID
    self.fieldId = ''

    // WP Nonce
    self.nonce = ''

    // Hidden input to update with file ID after successful upload to WP
    self.wpFileInput = ''

    self.init = function ($fieldEl) {
      // Populate properties
      self.parent = $fieldEl
      self.button = $fieldEl.find('.mf_fileupload_btn')
      self.input = $fieldEl.find('input[type="file"]')
      self.nonce = $fieldEl.find('input.mf_fileupload_nonce').val()
      self.wpRestNonce = $fieldEl.closest('form').find('.mf_form__wp_rest_nonce').val()
      self.formId = $fieldEl.closest('form').find('input.mf_form__id').val()
      self.fieldId = self.input.attr('id')
      self.wpFileInput = $fieldEl.find('input.mf_fileupload_callback_id')

      // Kick it off
      self.bindUploadClicks()
    }

    self.bindUploadClicks = function () {
      self.button.off().on('click', function (e) {
        e.preventDefault()
        self.input.click()
      })

      self.input.on('change', function (e) {
        // no file selected, do nothing
        if (!self.input[0] || !self.input[0].files || !self.input[0].files.length) {
          return
        }

        self.prepare()
      })
    }

    self.prepare = function () {
      var file = self.input[0].files
      var uploadData = new FormData()

      self.parent.find('.mf_error').remove()

      uploadData.append('form_id', self.formId)
      uploadData.append('field_id', self.fieldId)
      uploadData.append('nonce', self.nonce)

      $.each(file, function (key, value) {
        uploadData.append('file_' + self.fieldId, value)
      })

      self.uploadFile(uploadData)
    }

    self.uploadFile = function (uploadData) {
      $.ajax({
        url: self.ajaxURL,
        type: 'POST',
        data: uploadData,
        cache: false,
        dataType: 'json',
        processData: false,
        contentType: false,
        beforeSend: function (jqXhr) {
          self.button.text(PF.strings.uploading)
        },
        success: function (result, textStatus, jqXHR) {
          if (!result.data || !result.success || !result.data.file_post_id || !result.data.file_data) {
            self.fail(result.data)
            return
          }
          self.success(result.data)
        }
      })
    }

    self.fail = function (data) {
      var $error = $('<span>', { class: 'mf_error', text: data })
      $error.appendTo(self.parent)
      self.button.text(PF.strings.select_file)
    }

    self.reset = function () {
      self.parent.find('.mf_fileupload__uploaded').remove()
      self.wpFileInput.val('')
      self.input.val('')
      self.button.text(PF.strings.select_file)
      self.enable()
    }

    self.disable = function () {
      self.input.prop('disabled', true)
      self.parent.addClass('mf_disabled')
    }

    self.enable = function () {
      self.input.prop('disabled', false)
      self.parent.removeClass('mf_disabled')
    }

    /**
     * Adds WP file post ID back to the form
     * Shows uploaded file name to user
     * Disables the field until the file is removed
     */
    self.success = function (result) {
      self.wpFileInput.val(result.file_post_id)
      self.appendUploaded(result.file_data)
      self.button.text(PF.strings.file_selected)
      self.disable()
    }

    /**
     * Appends HTML with uploaded file name and removal link
     */
    self.appendUploaded = function (fileData) {
      var removeEl = $('<span>').text(PF.strings.remove_file).addClass('mf_fileupload__remove')
      var uploadedInfo = $('<p/>').text(fileData.name).addClass('mf_fileupload__uploaded')
      removeEl.appendTo(uploadedInfo)
      uploadedInfo.appendTo(self.parent)

      removeEl.on('click', function () {
        self.reset()
      })
    }
  }

  /**
   * PROPER FORMS VALIDATOR
   */
  var PFValidator = new function () {
    var self = this

    self.field = ''
    self.parent = ''
    self.formEl = ''
    self.fieldType = ''
    self.fieldValue = ''
    self.hasErrors = 0

    self.init = function ($fieldEl) {
      // Bail out early if globals from localized scripts are not present
      if (!PF_ERR || !PF) {
        return
      }

      self.field = $fieldEl
      self.parent = $fieldEl.closest('.mf_field')
      self.formEl = $fieldEl.closest('.mf_form')
      self.validationType = self.parent.data('validate')

      self.cleanErrors()

      self.getFieldValue()

      return self.validate()
    }

    self.validate = function () {
      // check if required fields are filled before even trying to check values
      if (self.isRequired() && !self.fieldValue) {
        self.hasErrors = 1
        self.outputError(PF.strings.required_error)
        return false
      }

      // validate data based on validation type provided in HTML's data attribute
      var errorMsg = ''
      switch (self.validationType) {
        case 'email' :
          var regex = /[a-z0-9\._%+!$&*=^|~#%'`?{}/\-]+@([a-z0-9\-]+\.){1,}([a-z]{2,16})/

          if (!self.fieldValue.match(regex)) {
            self.hasErrors = 1
            errorMsg = PF.strings.email_error
          }
          break

        case 'date' :

          var formatToRegex = {
            'DD/MM/YYYY': /\d{2}\/\d{2}\/\d{4}/,
            'DD-MM-YYYY': /\d{2}\-\d{2}\-\d{4}/,
            'DD.MM.YYYY': /\d{2}\.\d{2}\.\d{4}/,
            'MM/DD/YYYY': /\d{2}\/\d{2}\/\d{4}/,
            'MM-DD-YYYY': /\d{2}\-\d{2}\-\d{4}/,
            'MM.DD.YYYY': /\d{2}\.\d{2}\.\d{4}/,
            'YYYY-MM-DD': /\d{4}\-\d{2}\-\d{2}/,
            'YYYY/MM/DD': /\d{4}\/\d{2}\/\d{2}/,
            'YYYY.MM.DD': /\d{4}\.\d{2}\.\d{2}/
          }
          var regex = formatToRegex[ self.parent.data('format') ] || /\d{2}\/\d{2}\/\d{4}/

          if (!self.fieldValue.match(regex)) {
            self.hasErrors = 1
            errorMsg = PF.strings.date_format_error
          }
          break
      }

      if (self.hasErrors) {
        self.outputError(self.getErrorMsg(self.field.attr('id'), errorMsg))
        return false
      }

      return true
    }

    /**
     * Gets current value from field or set of fields according to field type
     */
    self.getFieldValue = function () {
      switch (self.validationType) {
        case 'checkboxes':
          var vals = []
          self.parent.find('input:checked').each(function () {
            vals.push($(this).val())
          })
          self.fieldValue = vals.join(',')
          break
        case 'radio':
          self.fieldValue = self.parent.find('input:checked').first().val()
          break
        default:
          self.fieldValue = self.field.val()
          break
      }
    }

    /**
     * Checks if field element is marked as required in HTML
     */
    self.isRequired = function () {
      if (self.field.prop('required') || self.parent.hasClass('mf-required')) {
        return true
      }

      return false
    }

    /**
     * Gets error message, customized if possible. Strings are passed through wp_localize_script in shortcode callback.
     *
     * 1. Looks for field's custom message if set in form WP admin screen (passed in PF_ERR global),
     * 2. First fallback is a string provided as function param
     * 3. Second fallback: generic localized string in PF global,
     * 3. Ultimate fallback: hardcoded english string. Lo siento pero no comprendo, señor.
     */
    self.getErrorMsg = function (fieldId, defaultMsg) {
      if (!PF_ERR || !PF_ERR[fieldId]) {
        // Fallback: return msg probivided as param
        if (defaultMsg) {
          return defaultMsg
        }

        // Fallback: return hardcoded value if nothing else is present
        if (!PF || !PF.strings || !PF.strings.default_error) {
          return 'This field\'s value is invalid!' // Default hardcoded message, displayed only if wp_localize_script() fails
        }

        // Return localized default message if present
        return PF.strings.default_error
      }

      return PF_ERR[fieldId]
    }

    /**
     * Adds error classes to HTML and prints span element with custom error message
     */
    self.outputError = function (msg) {
      var errorEl = $('<span>', {
        class: 'mf_error',
        text: msg
      })

      self.field.addClass('mf_error_shadow')
      self.parent.addClass('mf_has_errors')
      self.formEl.addClass('mf_has_errors')

      errorEl.appendTo(self.parent)
    }

    /**
     * Resets error messages and validator state
     */
    self.cleanErrors = function () {
      self.parent.removeClass('mf_has_errors')
      self.formEl.removeClass('mf_has_errors')
      self.field.removeClass('mf_error_shadow')
      self.parent.find('.mf_error').remove()
      self.hasErrors = 0
    }

    $('.mf_form__form').on('submit', function () {
      self.validate()
    })
  }()

  // KICK IT OFF!

  // Init main controller on every form on the page
  $('.mf_form__form').each(function () {
    ProperForms.init($(this))
  })

  // Init validator when field's value change
  $('.mf_field input, .mf_field textarea, .mf_field select, .mf_fileupload_callback_id').on('blur change', function () {
    var check = PFValidator.init($(this))
  })
})

// TODO add as main controller's method
function resizeForms () {
  $('.mf_form__form select').each(function () {
    var newWidth = $(this).width()
    if (newWidth === lastWindowWidth) {
      return
    }
    lastWindowWidth = newWidth

    var $form = $(this).parents('.mf_form__form')

    $(this).select2('destroy')
    $(this).width($(this).parent().width())
    $(this).select2({ dropdownParent: $form })

    $('select', $form).attr('aria-hidden', false)
    $('.select2', $form).attr('aria-hidden', true)
  })
  $('.popup__overlay').find('select').each(function () {
    $(this).select2()
  })
}
var lastWindowWidth = $(window).width()
$(window).resize(_.debounce(resizeForms, 100))
