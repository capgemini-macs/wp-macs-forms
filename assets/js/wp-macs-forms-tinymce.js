(function() {
	tinymce.PluginManager.add('macs_form', function( editor, url ) {
		
		var sh_tag = 'macs_form';

		var ajaxList = get_forms_list()

		function get_forms_list() {
			var data = {
				'action': 'populate_forms',
			};

			var list = []

			jQuery.post(ajaxurl, data, function( response ) {
				var forms = response.data
				
				for (var key in forms) {
					pair = {
						text: forms[key],
						value: key
					}
    				list.push(pair)
    			}
			});
			return list	
		}
		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		

		/**
		 * Return assets path
		 */
		function assetsUrl(url) {
			var aUrl, i, l, sUrl = '';

			aUrl = url.split( '/' );
			for ( i = 0, l = aUrl.length - 1; i < l; i++ ) {
				sUrl += aUrl[ i ] + '/';
			}
			return sUrl;
		}

		/**
		 * Add TinyMCE popup for inserting shortcode attributes
		 */
		editor.addCommand('cg_button_popup', function(ui, vals) {
			
			//setup defaults
			var form_id   = '';

			if ( vals.form_id ) {
				form_id = vals.form_id;
			}

			editor.windowManager.open( {
				title: 'Add Form',
				body: [
					{
						type: 'listbox',
						name: 'form_id',
						label: 'Select form',
						values : ajaxList,
						value: form_id,
						tooltip: 'Select form from the list'
					}
				],
				onsubmit: function( e ) {
					var form_id       = typeof e.data.form_id != 'undefined' ? parseInt( e.data.form_id.toString() ) : ''
					var shortcode_str = '[' + sh_tag.toString() + ' id=' + form_id + ']';

					editor.insertContent( shortcode_str );
				}
			});
		  });

		/**
		 * Add button for shortcode
		 */
		editor.addButton('macs_form', {
			text: 'Form',
			tooltip: 'Add Form',
			onpostrender: function() {
				this.$el.addClass('cg-shortcodes-btn')
			},
			onclick: function() {
				editor.execCommand('cg_button_popup','',{
					form_id : '',
				});
			}
		});
	});
})();