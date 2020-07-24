jQuery(document).ready(function ($) {
  /**
   * MAIN PROPER FORMS CONTROLLER
   */

  var ProperForms = new function (e) {

    var self = this

    self.field = ''
    self.formElement = ''
    self.formSuccessBox = ''
    self.formErrorBox = ''
    self.formWrapper = ''


    self.init = function ($formEl) {

      self.formElement = $formEl
      self.formName = $( '.pf_form__title', $formEl ).val()

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
      var fileFields = self.formElement.find('.pf_field--file')

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
      var myForm

      self.formElement.find('.pf_field--submit').on('click', function () {
        myForm = $(this).closest('.pf_form__form')
        myForm.find('.pf-required input, .pf-required textarea, .pf_field select, .pf_field ul li radio, .pf_fileupload_callback_id').focus().focusout()
      })
      // Submit form
      self.formElement.submit(function (e) {
        e.preventDefault()

        var hasErrors = 0
        // Validate all fields again on submit
        myForm.find('.pf_field input, .pf_field textarea, .pf_field select, .pf_field ul li radio, .pf_fileupload_callback_id').each(function () {
          if (PFValidator.init($(this)) === false) {
            hasErrors = 1
          }
        })

        if (hasErrors) {
          return
        }

        var i = 0

        $('.pf_field--recaptcha').each(function () {
          if ($(this).parents('.pf_form__form').attr('id') === myForm.attr('id')) {
            return false
          }
          i++
        })

        var data = {
          action: 'pf_submit_form',
          nonce_pf_submit: PF.ajaxNonce,
          recaptcha_response: grecaptcha.getResponse(i),
          form_data: {}
        }

        var fields = myForm.find(':input', $(this)).serializeArray()

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
          action: 'pf_submit_form',
          nonce_pf_submit: PF.ajaxNonce,
          data: data
        }).done(function (response) {
          if (!response.success) {
            self.onFailure()

            return
          }

          self.onSuccess( response.data, myForm.attr('id') )

        }).fail(function (response ) {
          self.onFailure( response.data, response.responseJSON.data, myForm.attr('id') )

        })
      })
    }

    // selec2 for select dropdowns
    self.selectDropdown = function () {
      var selects = self.formElement.find('select')

      selects.each(function () {
        $(this).select2({
          dropdownParent: self.formElement,
          width: 'style'
        })
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
        var $select = $(this).parents('.pf_field').find('select')
        $select.val($select.find('option:first').attr('value')).change()
        $select.select2('close')
        $(this).remove()
      })
    }

    configPF = function() {

      var form_ID = $('input[name=form_id]').val()
      var config  = PF_CONFIG[form_ID]

      return config

    }

    /**
     * After succesful submission
     */
    self.onSuccess = function (data, formID) {

      var formToHide = document.getElementById(formID);

      this.formSuccessBox = $(formToHide).siblings('.pf_form__success')
      this.formErrorBox = $(formToHide).siblings('.pf_form__errors')

      var formRedirect = configPF()

      if  ( typeof formRedirect !== 'undefined' && typeof formRedirect.redirect !== 'undefined' && formRedirect.redirect.length ) {

        this.formSuccessBox.hide()
        self.redirect(formRedirect.redirect)

      } else {

        $(formToHide).hide()
        this.formErrorBox.hide()
        this.formSuccessBox.show()
    }

      self.sendAnalyticsData()

    }

    /**

     * After submission fail
     */
    self.onFailure = function (data, captchaResponse, formID) {

      var formToHide = document.getElementById(formID);

      this.formSuccessBox = $(formToHide).siblings('.pf_form__success')
      this.formErrorBox = $(formToHide).siblings('.pf_form__errors')

      this.formSuccessBox.hide()

      if (captchaResponse !== null ) {
        this.formErrorBox.text(captchaResponse).css('color', 'red')
      }

      this.formErrorBox.show()
    }

    self.sendAnalyticsData = function() {
      window.dataLayer = window.dataLayer || [];
      window.dataLayer.push( {
        'event'    : 'FormSubmission',
        'formID'   : self.formId,
        'formTitle' : self.formName,
      } )
    }

    self.redirect = function(redirectUrl) {

    window.location.href = redirectUrl

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
    self.ajaxURL = PF.ajaxURL + '?action=pf_fileupload'

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
      self.button = $fieldEl.find('.pf_fileupload_btn')
      self.input = $fieldEl.find('input[type="file"]')
      self.nonce = $fieldEl.find('input.pf_fileupload_nonce').val()
      self.wpRestNonce = $fieldEl.closest('form').find('.pf_form__wp_rest_nonce').val()
      self.formId = $fieldEl.closest('form').find('input.pf_form__id').val()
      self.fieldId = self.input.attr('id')
      self.wpFileInput = $fieldEl.find('input.pf_fileupload_callback_id')

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

      self.parent.find('.pf_error').remove()

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
      var $error = $('<span>', { class: 'pf_error', text: data })

      $error.appendTo(self.parent)
      self.button.text(PF.strings.select_file)
    }

    self.reset = function () {
      self.parent.find('.pf_fileupload__uploaded').remove()
      self.wpFileInput.val('')
      self.input.val('')
      self.button.text(PF.strings.select_file)
      self.enable()
    }

    self.disable = function () {
      self.input.prop('disabled', true)
      self.parent.addClass('pf_disabled')
    }

    self.enable = function () {
      self.input.prop('disabled', false)
      self.parent.removeClass('pf_disabled')
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
      var removeEl = $('<span>').text(PF.strings.remove_file).addClass('pf_fileupload__remove')
      var uploadedInfo = $('<p/>').text(fileData.name).addClass('pf_fileupload__uploaded')
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
      if (!PF_CONFIG || !PF) {
        return
      }

      self.field = $fieldEl
      self.parent = $fieldEl.closest('.pf_field')
      self.formEl = $fieldEl.closest('.pf_form')
      self.validationType = self.parent.data('validate')

      self.cleanErrors()

      self.getFieldValue()

      return self.validate()

    }

    self.validate = function () {

      if ( self.field.hasClass('select2-search__field') ) {
        return true
      }

      if ( self.validationType === 'multiselect'){

        if ( self.isRequired() && self.fieldValue.length === 0 ) {
          self.hasErrors = 1
          self.outputError(PF.strings.required_error)
          return false
        }

      } else {
        // check if required fields are filled before even trying to check values
        if (self.isRequired() &&  ( !self.fieldValue || self.field.val() === '' ) ) {

          self.hasErrors = 1
          self.outputError(PF.strings.required_error)
          return false
        }
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
            'dd/mm/yy': /\d{2}\/\d{2}\/\d{4}/,
            'dd-mm-yy': /\d{2}\-\d{2}\-\d{4}/,
            'dd.mm.yy': /\d{2}\.\d{2}\.\d{4}/,
            'mm/dd/yy': /\d{2}\/\d{2}\/\d{4}/,
            'mm-dd-yy': /\d{2}\-\d{2}\-\d{4}/,
            'mm.dd.yy': /\d{2}\.\d{2}\.\d{4}/,
            'yy-mm-dd': /\d{4}\-\d{2}\-\d{2}/,
            'yy/mm/dd': /\d{4}\/\d{2}\/\d{2}/,
            'yy.mm.dd': /\d{4}\.\d{2}\.\d{2}/
          }

          var regex = formatToRegex[ self.parent.data('format') ] || /\d{2}\/\d{2}\/\d{4}/

          if (!self.fieldValue.match(regex) && ( $('.datepicker').val().length !== 0)) {

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
      if (self.field.prop('required') || self.parent.hasClass('pf-required')) {
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

      var formConfig = configPF()

      if (!formConfig.errors || !formConfig.errors[fieldId]) {
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

      return formConfig.errors[fieldId]
    }

    /**
     * Adds error classes to HTML and prints span element with custom error message
     */
    self.outputError = function (msg) {

      if ( (self.parent).hasClass('pf_field--checkboxes')
        || (self.parent).hasClass('pf_field--radios')
      ) {

      fieldVal = self.parent.attr('id')

      } else if( (self.parent).hasClass('pf_field--file') ) {

        fieldVal = self.field.attr('name')
      } else if( (self.parent).hasClass('pf_field--multiselect') ) {

        fieldVal = self.parent.attr('name')

      } else {

        fieldVal = self.field.attr('id')
      }

      var errorEl = $('<span>', {
        class: 'pf_error',
        text: self.getErrorMsg(fieldVal)
      })

      self.field.addClass('pf_error_shadow')
      self.parent.addClass('pf_has_errors')
      self.formEl.addClass('pf_has_errors')

      errorEl.appendTo(self.parent)
    }

    /**
     * Resets error messages and validator state
     */
    self.cleanErrors = function () {
      self.parent.removeClass('pf_has_errors')
      self.formEl.removeClass('pf_has_errors')
      self.field.removeClass('pf_error_shadow')
      self.parent.find('.pf_error').remove()
      self.hasErrors = 0
    }

    $('.pf_form__form').on('submit', function () {
      self.validate()
    })
  }()

  // KICK IT OFF!

  // Init main controller on every form on the page
  $('.pf_form__form').each(function () {
    ProperForms.init($(this))
  })

  // Init validator when field's value change
  $('.pf_field input, .pf_field textarea, .pf_field select, .pf_field ul li radio, .pf_fileupload_callback_id').on(' change focus', function (e) {
    var check = PFValidator.init($(this))
  })

    $('.pf_field--date').each(function() {

      var customFormat = $(this).data('format');

      $('.datepicker').datepicker({
        dateFormat: customFormat,
        changeYear: true,
        changeMonth: true,
        yearRange: '-100:+100'
       }).off('focus')
        .click(function () {
         $(this).datepicker('show');
       });
    });

// TODO add as main controller's method

  function resizeForms () {
    $('.pf_form__form select').each(function () {
      var newWidth = $(this).width()
      if (newWidth === lastWindowWidth) {
        return
      }
      lastWindowWidth = newWidth

      var $form = $(this).parents('.pf_form__form')

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
  $(window).resize(lodash.debounce(resizeForms, 100))

})
