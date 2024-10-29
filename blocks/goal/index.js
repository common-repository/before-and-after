( 
	function( wp ) {
	/**
	 * Registers a new block provided a unique name and an object defining its behavior.
	 * @see https://github.com/WordPress/gutenberg/tree/master/blocks#api
	 */
	var registerBlockType = wp.blocks.registerBlockType;
	/**
	 * Returns a new element of given type. Element is an abstraction layer atop React.
	 * @see https://github.com/WordPress/gutenberg/tree/master/element#element
	 */
	var el = wp.element.createElement;
	/**
	 * Retrieves the translation of text.
	 * @see https://github.com/WordPress/gutenberg/tree/master/i18n#api
	 */
	var __ = wp.i18n.__;
	
	var build_post_options = function(posts) {
		var opts = [
			{
				label: __('Select a Goal...'),
				value: ''
			}
		];

		// build list of options from goals
		for( var i in posts ) {
			post = posts[i];
			opts.push( 
			{
				label: post.title.rendered || __('(no title)'),
				value: post.id
			});
		}
		return opts;
	};
	
	var build_category_options = function(categories) {
		var opts = [
			{
				label: 'All Categories',
				value: ''
			}
		];

		// build list of options from goals
		for( var i in categories ) {
			cat = categories[i];
			opts.push( 
			{
				label: cat.name,
				value: cat.id
			});
		}
		return opts;
	};	

	var extract_label_from_options = function (opts, val) {
		var label = '';
		for (j in opts) {
			if ( opts[j].value == val ) {
				label = opts[j].label;
				break;
			}										
		}
		return label;
	};
	
	var checkbox_control = function (label, checked, onChangeFn) {
		// add checkboxes for which fields to display
		var controlOptions = {
			checked: checked,
			label: label,
			value: '1',
			onChange: onChangeFn,
		};	
		return el(  wp.components.CheckboxControl, controlOptions );
	};
	
	var generate_post_output = function(id, post, props) {
		var post_elements = [],
			post_output,
			//show_photo = typeof(last_show_photo[id]) != 'undefined' ? last_show_photo[id] : true
			show_photo = props.attributes.show_photo,
			div_class = '';

		post_elements.push( el('p', {}, el('strong', {}, post.title.rendered)) );
		
		if ( props.attributes.show_title && post.metadata.title ) {
			post_elements.push( el('p', {}, post.metadata.title) );
		}
		
		if ( show_photo && post.featured_image_src ) {
			post_elements.push( el('img', { src: post.featured_image_src, className: 'before-and-after-block-featured-image' }) );
			div_class = 'has_photo';
		} else {
			div_class = 'no_photo';
		}
		
		post_output = el('div', { className: div_class }, post_elements);
		return post_output;
	};
	
	var last_fetched_id = 0;
	var last_show_photo = [];
	var post_cache = [];
	var cache_expiration = 5000; // 5s

	// swap_horiz
	var iconGroup = [];
	iconGroup.push(	el(
			'path',
			{ d: "M6.99 11L3 15l3.99 4v-3H14v-2H6.99v-3zM21 9l-3.99-4v3H10v2h7.01v3L21 9z"}
		)
	);
	iconGroup.push(	el(
			'path',
			{ d: "M0 0h24v24H0z", fill: 'none' }
		)
	);
	
	var iconEl = el(
		'svg', 
		{ width: 24, height: 24 },
		iconGroup
	);

	/**
	 * Every block starts by registering a new block type definition.
	 * @see https://wordpress.org/gutenberg/handbook/block-api/
	 */
	registerBlockType( 'before-and-after/goal', {
		/**
		 * This is the display title for your block, which can be translated with `i18n` functions.
		 * The block inserter will show this name.
		 */
		title: __( 'Goal' ),

		/**
		 * Blocks are grouped into categories to help users browse and discover them.
		 * The categories provided by core are `common`, `embed`, `formatting`, `layout` and `widgets`.
		 */
		category: 'before-and-after',

		/**
		 * Optional block extended support features.
		 */
		supports: {
			// Removes support for an HTML mode.
			html: false,
		},

		/**
		 * The edit function describes the structure of your block in the context of the editor.
		 * This represents what the editor will render when the block is used.
		 * @see https://wordpress.org/gutenberg/handbook/block-edit-save/#edit
		 *
		 * @param {Object} [props] Properties passed from the editor.
		 * @return {Element}       Element to render.
		 */
		edit: wp.data.withSelect( function( select ) {
					return {
						posts: select( 'core' ).getEntityRecords( 'postType', 'b_a_goal', {
							orderBy: 'title',
							order: 'asc',						
							per_page: -1
						} )
					};
				} ) ( function( props ) {
						var retval = [];
						var inspector_controls = [],
							id = props.attributes.id || '',
							goal_title = props.attributes.goal_title || '',
							focus = props.isSelected;
							
						var primary_fields = [];
						
						// add <select> to choose the Goal
						var opts = build_post_options(props.posts);
						var controlOptions = {
							label: __('Goal:'),
							value: id,
							onChange: function( newVal ) {
								goal_title = newVal
											 ? extract_label_from_options(opts, newVal)
											 : '';
								props.setAttributes({
									id: newVal,
									goal_title: goal_title
								});
							},
							options: opts,
						};
					
						primary_fields.push(
							el(  wp.components.SelectControl, controlOptions )
						);

						inspector_controls.push(							
							el (
								wp.components.PanelBody,
								{
									title: __('Goal'),
									className: 'gp-panel-body',
									initialOpen: true,
								},
								primary_fields
							)
						);
						
						retval.push(
							el( wp.editor.InspectorControls, {}, inspector_controls ) 
						);


						// show a box in the editor representing the block
						var inner_fields = [];
						inner_fields.push( el('h3', { className: 'block-heading' }, 'Before And After - Goal') );
						if (goal_title) {
							inner_fields.push( el('blockquote', { className: 'goal-placeholder' }, goal_title ) );
						}
						else {
							inner_fields.push( el('p', { className: 'goal-placeholder' }, __('Please select a ') + 'Goal' ) );
						}
						retval.push( el('div', {'className': 'before-and-after-editor-not-selected'}, inner_fields ) );
					
				return el( 'div', { className: 'before-and-after-goal-editor'}, retval );
			} ),

		/**
		 * The save function defines the way in which the different attributes should be combined
		 * into the final markup, which is then serialized by Gutenberg into `post_content`.
		 * @see https://wordpress.org/gutenberg/handbook/block-edit-save/#save
		 *
		 * @return {Element}       Element to render.
		 */
		save: function() {
			return null;
		},
		attributes: {
			id: {
				type: 'string',
			},
			goal_title: {
				type: 'string',
			},
		},
		icon: iconEl,
	} );
} )(
	window.wp
);
