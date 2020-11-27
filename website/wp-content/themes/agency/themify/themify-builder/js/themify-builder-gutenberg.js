( function( blocks, i18n, element ) {
	var el = element.createElement;
	var __ = i18n.__;

	blocks.builderUtils = {
		stateInit: false,
		number: 0,
		tempHTML: null,
		vent: _.extend({}, Backbone.Events),
		isRendered: function(){
			return document.getElementById('tb_toolbar');
		},
		saveHTML: function( props ) {
			if ( this.isRendered() ) {
				this.tempHTML = document.getElementById('themify-builder-canvas-block').innerHTML;
			}
		},
		restoreHTML: function( props ){
			if ( 'undefined' === typeof themifybuilderapp ) return;
			
			var api = themifybuilderapp;
			if ( ! _.isNull( this.tempHTML ) && ! this.isRendered() && document.getElementById('themify-builder-canvas-block') ) {
				document.getElementById('themify-builder-canvas-block').innerHTML = this.tempHTML;
				if ( ! _.isUndefined(api.Instances.Builder[0]) ) {
					
					var batch = document.getElementById('themify_builder_row_wrapper').querySelectorAll('[data-cid]');
					batch = Array.prototype.slice.call(batch);
					for (var i = 0, len = batch.length; i < len; ++i) {
					var model = api.Models.Registry.lookup(batch[i].getAttribute('data-cid'));
						if (model) {
							model.trigger('change:view', batch[i]);
						}
					}
					api.toolbar.setElement( $('#tb_toolbar') ).render();
					api.Instances.Builder[0].setElement($('#themify_builder_row_wrapper'));
					api.Instances.Builder[0].init(true);
				}
			}
		},
		manageState: function( props ) {
			if ( 'undefined' === typeof themifybuilderapp || this.stateInit ) return;
			this.stateInit = true;
			console.log('manageState_callback');
			var api = themifybuilderapp;
			api.vent.on('dom:change', function () {
				if (api.hasChanged) {
					props.setAttributes({data: blocks.builderUtils.number++ });
				}
			});
			api.vent.on('backend:switchfrontend', function(url){
				window.parent.location.href = url;
			});
		},
		saveBlock: function(){
			if ( 'undefined' === typeof themifybuilderapp ) return;
			console.log('save_callback');

			if ( themifybuilderapp.hasChanged ) {
				themifybuilderapp.Utils.saveBuilder(this.goToFrontend);
			} else {
				this.goToFrontend();
			}
		},
		goToFrontend: function(){
			if ( themifybuilderapp.redirectFrontend ) {
				themifybuilderapp.vent.trigger('backend:switchfrontend', themifybuilderapp.redirectFrontend);
			}
		}
	};

	blocks.registerBlockType( 'themify-builder/canvas', {
		title: 'Themify Builder',
		icon: 'layout',
		category: 'layout',
		useOnce: true,
		attributes: {
			data: {
				source: 'meta'
			}
		},
		edit: function( props ) {
			console.log('edit');
			blocks.builderUtils.vent.trigger('edit', props);
			
			return el('div',{ id: 'themify-builder-canvas-block'}, 'placeholder builder' );
		},
		save: function() {
			console.log('save');
			return null; // render with PHP
		}
	} );

	var render_block = _.debounce(function( props ){
			blocks.builderUtils.saveHTML( props );
			blocks.builderUtils.restoreHTML( props );

			blocks.builderUtils.manageState( props );
		}, 800),
		save_block = _.debounce(function(){
			blocks.builderUtils.saveBlock();
		},800);

	blocks.builderUtils.vent.on('edit', render_block);
	blocks.builderUtils.vent.on('save', save_block);

	jQuery( document ).ajaxComplete(function( event, xhr, settings ) {
		var url  = settings.url,
			callbackUrl = 'post.php?post=' + themifyBuilder.post_ID + '&action=edit&classic-editor=1&meta_box=1';
		if ( 'POST' === settings.type && url.indexOf(callbackUrl) !== -1 ) {
			blocks.builderUtils.vent.trigger('save');
		}
	});

} )(
	window.wp.blocks,
	window.wp.i18n,
	window.wp.element
);