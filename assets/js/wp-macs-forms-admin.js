jQuery(document).ready(function ($) {
  var PfController = new function () {
    var self = this

    self.init = function () {

			// jquery ui sortable and draggable
      $('.sortable').sortable({
        revert: 120,
        placeholder: 'sortable-placeholder'
      })

      $('.draggable').draggable({
        connectToSortable: '.sortable',
        helper: 'clone',
        revert: 'invalid',
        distance: 8,
        revertDuration: 100,
        stop: function (event, ui) {
					// modify only elements moved to our builder
          if (ui.helper.parents('.mf_list--canvas').length) {
            ui.helper.addClass('mf_list__item--landed').css({ 'width': '', 'height': '' })
            self.bindClicksAfterLanding(ui.helper)
            self.setFieldId(ui.helper)
            self.flagAsUsed(ui.helper)
          }
        }
      })

			//bind clicks to modules preloaded from db
      $('.mf_list__item--landed').each(function () {
        self.bindClicksAfterLanding($(this))
      })

      self.getFormsOnSubmit();

    }

		/**
		 * add hidden input to send used module slug on poste save.
		 */
    self.flagAsUsed = function (parent) {

          var config_panel = parent.find( '.mf_list__config_panel' ).first();

          if ( ! config_panel ) {
            return;
          }

          var fieldIdInput = $('<input />', {
            'type': 'hidden',
            'name': 'mf_field_id',
            'value': parent.data('field-id')
          })

          config_panel.append(fieldIdInput)
    }

		/**
		 * Add listeners to component's menu buttons
		 */
    self.bindClicksAfterLanding = function (parent) {
      var removeBtn = parent.find('.mf_btn--remove'),
			    configBtn = parent.find('.mf_btn--config'),
			    configPanel = parent.find('.mf_list__config_panel')

      // Component's delete btn action
      removeBtn.off().on('click', function (e) {
        e.preventDefault()
        parent.remove()
      })

      // Component's config panel show/hide
      configBtn.off().on('click', function (e) {
        e.preventDefault()
        $(this).toggleClass('opened')
        configPanel.toggleClass('opened')
      })

      // Option tables
      if ( parent.find('.mf_key_value_table').length ) {
        var table = new PfOptionTable(parent)
        table.init()
      }
    }

    self.setFieldId = function( element ) {
      element.data( 'field-id', self.createFieldId() )
    }

    self.createFieldId = function() {
      var random = Math.floor(Math.random() * 10000),
          postId = $('#post_ID').val()

          self.newFieldId = '_mf_' + parseInt(postId, 10) + '_' + random

          // try again if id is in use already in this form
          $('.mf_list--canvas .mf_list__item').each( function() {
            if ( $(this).data('field-id') && self.newFieldId === $(this).data('field-id').toString() ) {
              self.newFieldId = self.createFieldId()
              return false;
            }
          } )
          return self.newFieldId;
    }

    self.getFormsOnSubmit = function() {
      $('#post').submit( function( e ) {
        $( '.mf_list__item--landed .mf_list__config_panel' ).each( function() {

          // get only fields with name attributes
          var inputs = $(this).find( 'input, textarea, select' ).filter( function( index, item ) {


            return $( item ).attr( 'name' )
          } );

          var fieldDataInput = $('<input />', {
            'type': 'hidden',
            'name': 'mf_fields[]',
            'value': JSON.stringify( inputs.serializeArray() )
          })

          $(this).append(fieldDataInput)
        } );

      } )
    }
  }()

  function PfOptionTable(parent) {

    var self = this

    self.parent = parent

    self.table = ''

    self.tableInput = ''

    self.values = []

    self.tableRowTemplate = '<tr><td><input type="text" /></td><td><input type="text" /></td><td><button class="mf_delete_row" aria-label="' + PF.string_delete_row + '"><span class="dashicons dashicons-no"></span></button></td></tr>'

    /**
     * Initialize Option Table
     */
    self.init = function() {
      self.table      = self.parent.find( '.mf_key_value_table tbody' ).first()
      self.tableInput = self.parent.find('input[name=options]').first()

      // Add listeners
      self.bindRowClicks()
      self.bindDeleteRowBtn()
      self.bindInputChange()

      // populate intput initially
      self.populateInput()
    }

    self.bindInputChange = function(){
      self.table.find( 'input' ).off().on( 'change', function(){
        self.populateInput()
      } )
    }

    /**
     * Handles a click on new row button
     */
    self.bindRowClicks = function() {
      var newRowBtn = self.parent.find( '.mf_table_add_row' ).first()

      newRowBtn.off().on('click', function(e) {
        e.preventDefault()
        self.table.append( $( self.tableRowTemplate ) )
        self.bindDeleteRowBtn()
        self.bindInputChange()
      } )
    }

    /**
     * Handles a click on delete button element
     */
    self.bindDeleteRowBtn = function() {
      self.parent.find( '.mf_delete_row' ).off().on('click', function(e) {
        e.preventDefault()
        $(this).closest( 'tr' ).remove()

        // refresh values
        self.populateInput()
      })
    }

    /**
     * Gets options from options table rows and translate it into following format:
     *
     * option_value|option_label~option_2_value|option_2_label
     *
     * We can't use stringified JSON here because this string is part of a
     * meta field that will be strigified later
     *
     * @param {Object} parent
     *
     * @return {string}
     */
    self.getValues = function() {
      var tableRows = self.parent.find( '.mf_key_value_table tr' ),
          values    = []

      tableRows.each( function() {
        var inputs = $(this).find('input');
        if ( 2 === inputs.length ) {
          values.push( $( inputs[1] ).val() + '|' + $( inputs[0] ).val() )
        }
      } )
      this.values = values.join('~')
      return this.values
    }

    /**
     * Collects data and updates hidden options field
     */
    self.populateInput = function(){
      var vals = self.getValues()

      self.tableInput.val( vals.toString() )
    }
  }

  // Initialize Proper Forms UI
  PfController.init()
})
