<?php
class Themify_Builder_Gutenberg {
	private $builder;

	public $block_patterns = '<!-- wp:themify-builder/canvas /-->';

	public function __construct( $builder ) {
		$this->builder = $builder;
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_scripts') );
		add_action( 'init', array( $this, 'init') );
		add_filter( 'themify_defer_js_exclude', array( $this, 'exclude_defer_script' ) );

		$post_types = $this->builder->builder_post_types_support();
		foreach( $post_types as $type ) {
			add_filter( 'rest_prepare_' . $type, array( $this, 'enable_block_existing_content'), 10, 3 );
		}
		add_filter( 'admin_body_class', array( $this, 'admin_body_class') );
	}

	public function init() {
		if ( function_exists( 'register_block_type' ) ) {
			wp_register_style(
				'themify-builder-style',
				themify_enque(THEMIFY_BUILDER_URI . '/css/themify-builder-style.css'),
				array( 'wp-blocks' )
			);

			register_block_type( 'themify-builder/canvas', array(
				'render_callback' => array( $this, 'render_builder_block'),
				'style' => 'themify-builder-style'
			) );
		}

		$post_type_object = get_post_type_object( 'page' );
		$post_type_object->template = array(
			array( 'themify-builder/canvas' )
		);
	}

	public function enqueue_editor_scripts() {
		wp_enqueue_script( 
			'themify-builder-gutenberg-block', 
			themify_enque(THEMIFY_BUILDER_URI . '/js/themify-builder-gutenberg.js'), 
			array( 'wp-blocks', 'wp-i18n', 'wp-element', 'backbone' )
		);
	}

	public function render_builder_block( $attributes ) {

		if ( Themify_Builder_Model::is_front_builder_activate() ) {
			return '<!--themify-builder:block-->';
		} else {
			remove_filter('the_content', array($this->builder, 'builder_show_on_front'), 11);
		}

		$content = '';
		$post_id = get_the_ID();
		$builder_data = $this->builder->get_builder_data($post_id);

		do_action('themify_builder_before_template_content_render');

		if ( !is_array($builder_data) ) {
			$builder_data = array();
		}
		Themify_Builder_Component_Base::$post_id = $post_id;
		$template = $this->builder->in_the_loop ? 'builder-output-in-the-loop.php' : 'builder-output.php';
		$builder_output = Themify_Builder_Component_Base::retrieve_template($template, array('builder_output' => $builder_data, 'builder_id' => $post_id), '', '', false);
		
		$content .= $builder_output;

		return $content;
	}

	public function exclude_defer_script( $handles ) {
		$excludes = array( 'themify-builder-gutenberg-block' );
		$handles = array_merge( $handles, $excludes );
		return $handles;
	}

	/**
	 * Enable builder block on existing content data.
	 * 
	 * @param object $data 
	 * @param object $post 
	 * @param array $context 
	 * @return object
	 */
	public function enable_block_existing_content( $data, $post, $context ) {
		global $ThemifyBuilder_Data_Manager;

		if ( ! empty( $data->data['content']['raw'] ) && 'edit' === $context['context'] && ! preg_match( '<!-- wp:themify-builder\/canvas \/-->', $data->data['content']['raw'] ) ) {
			$data->data['content']['raw'] = $data->data['content']['raw'] . ' ' . $this->block_patterns;
		}

		// Remove static content
		if ( ! empty( $data->data['content']['raw'] ) && 'edit' === $context['context'] ) {
			$data->data['content']['raw'] = $ThemifyBuilder_Data_Manager->update_static_content_string( '', $data->data['content']['raw'] );
			//$data->data['content']['raw'] = str_replace('<p></p>', '', $data->data['content']['raw'] );
		}
		return $data;
	}

	/**
	 * Added body class
	 * @param string $classes 
	 * @return string
	 */
	public function admin_body_class( $classes ) {
		$classes .= 'themify-gutenberg-editor';
		return $classes;
	}
}
new Themify_Builder_Gutenberg( $this );