<?php
/**
 * WeCodeArt Framework.
 *
 * WARNING: This file is part of the core WeCodeArt Framework. DO NOT edit this file under any circumstances.
 * Please do all modifications in the form of a child theme.
 *
 * @package 	WeCodeArt Framework
 * @subpackage 	Support\Modules\ScrollTop
 * @copyright   Copyright (c) 2024, WeCodeArt Framework
 * @since 		6.3.0
 * @version		6.3.0
 */

namespace WeCodeArt\Support\Modules;

defined( 'ABSPATH' ) || exit;

use WeCodeArt\Singleton;
use WeCodeArt\Integration;
use WeCodeArt\Config\Traits\Asset;
use WeCodeArt\Conditional\Traits\No_Conditionals;
use function WeCodeArt\Functions\toJSON;
use function WeCodeArt\Functions\get_prop;

/**
 * The ScrollTop object.
 */
final class ScrollTop implements Integration {

    use Asset;
    use Singleton;
	use No_Conditionals;

	const CONTEXT = 'wca-scrolltop';

    /**
	 * The config of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      mixed    $config    The config of the plugin.
	 */
	protected $config;

	/**
	 * Send to Constructor
	 */
	public function init() {
        $this->config = wp_parse_args( wecodeart_option( 'scrolltop' ), self::get_defaults() );
	}

	/**
	 * Hooks
	 */
	public function register_hooks() {
		add_action( 'admin_enqueue_scripts',	[ $this, 'admin_assets' ] );
		add_action( 'wp_enqueue_scripts',       [ $this, 'assets' ], 0 );
		add_action( 'wp_footer',                [ $this, 'markup' ], 0 );
		add_action( 'admin_init',				[ $this, 'insert_defaults'	] );
	}

	/**
	 * Markup
	 *
	 * @return 	void
	 */
	public function markup() {
		$is_interactive = function_exists( 'wp_enqueue_script_module' );
		$scrollOffset	= intval( get_prop( $this->config, [ 'scroll', 'offset' ], 0 ) );
		$scrollDuration	= intval( get_prop( $this->config, [ 'scroll', 'duration' ], 400 ) );
		$elOffset		= intval( get_prop( $this->config, [ 'element', 'offset' ], 0 ) );
		$elSelector		= get_prop( $this->config, [ 'element', 'selector' ], 'html' );

		wecodeart( 'markup' )->SVG::add( 'scrolltop', get_prop( $this->config, 'icon' ) );

		$classes = wp_parse_args(
			explode( ' ', get_prop( $this->config, 'classes', '' ) ),
			[ 'wp-element-button', 'wp-element-button--scrolltop' ]
		);

		$inline_css = 'transition:var(--wp--transition);';
		$inline_css .= 'opacity:' . ( $scrollOffset === 0 ? '1' : '0' ) . ';';
		$inline_css .= 'pointer-events:' . ( $scrollOffset === 0 ? 'all' : 'none' );

		$attributes = [];

		if( $is_interactive ) {
			$attributes = wp_parse_args( [
				'data-wp-context' 			=> toJSON( array_filter( [
					'showOffset'=> $scrollOffset,
					'duration' 	=> $scrollDuration,
					'target' 	=> $elSelector,
					'offset'	=> $elOffset
				] ) ),
				'data-wp-interactive' 		=> 'wecodeart/scrolltop',
				'data-wp-on--click'			=> 'actions.animateScoll',
				'data-wp-watch'				=> 'callbacks.visibility',
				'data-wp-init--validate'	=> 'callbacks.validateConfig',
				'data-wp-init--scroll'		=> 'actions.onScroll',
				'data-wp-on-window--scroll'	=> 'actions.onScroll',
			], $attributes );
		}

		$attributes = wp_parse_args( [
			'class'			=> join( ' ', array_filter( $classes ) ),
			'type'			=> 'button',
			'aria-label' 	=> esc_html__( 'Scroll to top', 'wca-scrolltop' ),
			'style'			=> $inline_css,
		], $attributes );

		wecodeart_input( 'button', [
			'label' => wecodeart( 'markup' )->SVG::compile( 'scrolltop' ),
			'attrs' => $attributes
		] );

		if( $is_interactive ) {
			\wp_interactivity_state( 'wecodeart/scrolltop', apply_filters( 'wecodeart/filter/interactive/state/scrolltop', [
				'showOffset'=> 100,
				'duration'	=> 400,
				'target'	=> '',
				'offset'	=> 0,
			] ) );
				
			\wp_interactivity_config( 'wecodeart/scrolltop', [
				'showOffset'=> '(null|number)',
				'duration'	=> '(null|number)',
				'target' 	=> '(null|element|string)',
				'offset' 	=> '(null|number)',
			] );

			return;
		}

		$inline_js = <<<JS
			const ScrollTop = {
				el: document.querySelector('.wp-element-button--scrolltop'),
				target: document.querySelector('$elSelector'),
				to: 0,
				fps: 60,
				start: 0,
				change: 0,
				duration: 0,
				elapsedTime: 0,

				init: function() {
					const scrollListener = () => window.scrollY >= $scrollOffset ? this.fade(true) : this.fade();
					const clickListener = () => this.animate((this.target ? this.target.offsetTop : 0) - $elOffset, $scrollDuration);

					scrollListener();

					window.addEventListener('scroll', scrollListener);

					this.el.addEventListener('click', clickListener, true);
				},

				animate: function(to, duration) {
					this.to = to;
					this.duration = duration;
					this.start = document.documentElement.scrollTop || document.body.scrollTop;
					this.change = this.to - this.start;
					this.startTime = performance.now();

					console.log(this.change)
					console.log(this.startTime)

					return window.requestAnimationFrame(this.loop.bind(this));
				},

				loop: function(timestamp) {
					const elapsedTime = timestamp - this.startTime;
					const position = this.easing(elapsedTime, this.start, this.change, this.duration);

					document.documentElement.scrollTop = position;
					document.body.scrollTop = position;

					if (elapsedTime < this.duration) {
						window.requestAnimationFrame(this.loop.bind(this));
					}
				},

				easing: function(currentTime, start, change, duration) {
					currentTime /= duration;
					return -change * currentTime * (currentTime - 2) + start;
				},

				fade: function(dir) {
					this.el.classList[dir ? 'add' : 'remove']('wp-element-button--appear');
					this.el.style.opacity = dir ? 'var(--wp--opacity)' : 0;
					this.el.style.pointerEvents = dir ? 'all' : 'none';
				}
			};

			ScrollTop.init();
		JS;
		
		printf( '<script id="wecodeart-scrolltop-js">%s</script>', $inline_js );
	}

	/**
	 * Assets
	 *
	 * @return 	void
	 */
	public function assets() {
		// Utilities classes
		$classes = get_prop( $this->config, 'classes', '' );
		if( $classes ) {
			wecodeart( 'styles' )->Utilities->load( explode( ' ', $classes ) );
		}

		// Styles
		$styles = get_prop( $this->config, 'style', [] );

		// Misc
		\WP_Style_Engine::store_css_rule( self::CONTEXT, '.wp-element-button--scrolltop', [
			'--wp--opacity'		=> get_prop( $styles, 'opacity', 100 ) / 100,
			'--wp--transition'	=> 'opacity .3s ease-in-out',
			'position' 			=> 'fixed',
			'opacity' 			=> 0,
			'z-index'			=> 5,
			'transition'		=> 'opacity .3s ease-in-out',
			'pointer-events' 	=> 'none',
		] );

		// Position
		$positions = wp_array_slice_assoc( $styles, [ 'top', 'bottom', 'left', 'right' ] );
		$positions = array_map( fn( $i ) => $i . 'px', $positions );
		unset( $positions[ get_prop( $this->config, 'position', 'right' ) === 'left' ? 'right' : 'left' ] );
		\WP_Style_Engine::store_css_rule( self::CONTEXT, '.wp-element-button--scrolltop', $positions );

		// Dimensions
		$dimensions = wp_array_slice_assoc( $styles, [ 'width', 'height' ] );
		$dimensions = array_map( fn( $i ) => $i . 'px', $dimensions );
		\WP_Style_Engine::store_css_rule( self::CONTEXT, '.wp-element-button--scrolltop', $dimensions );

		// Colors
		$colors = wp_array_slice_assoc( $styles, [ 'backgroundColor', 'color' ] );
		$rules['color'] = [
			'background' 	=> get_prop( $styles, 'backgroundColor' ),
			'text'			=> get_prop( $styles, 'color' )
		];

		// Border
		$rules['border'] = wp_parse_args( [
			'radius' => get_prop( $styles, 'borderRadius', '0' ) . 'px'
		], get_prop( $styles, 'border' ) );

		// Spacing
		$rules['spacing']['padding'] = get_prop( $styles, 'padding', '0' ) . 'px';

		// SVG Fix
		\WP_Style_Engine::store_css_rule( self::CONTEXT, '.wp-element-button--scrolltop svg', [
			'height' => 'initial'
		] );

		// Hover
		\WP_Style_Engine::store_css_rule( self::CONTEXT, '.wp-element-button--scrolltop:is(:hover,:active,:focus)', [
			'--wp--opacity' => 1
		] );

		// Load Styles
		wp_style_engine_get_styles( $rules, [
			'selector' 	=> '.wp-element-button--scrolltop',
			'context'	=> self::CONTEXT
		] );

		if( function_exists( 'wp_enqueue_script_module' ) ) {
			\wp_register_script_module(
				'@wecodeart/scrolltop',
				$this->get_asset( 'js', 'scrolltop' ),
				[ '@wordpress/interactivity' ],
				wecodeart( 'version' )
			);
	
			\wp_enqueue_script_module( '@wecodeart/scrolltop' );
		}
	}

    /**
	 * Admin assets.
	 *
	 * @return void
	 */
	public function admin_assets() {
		if( ! wecodeart_if( 'is_theme_admin' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		wp_register_script( 
			$this->make_handle(),
			$this->get_asset( 'js', 'admin' ),
			[ 'wecodeart-admin', 'wp-block-editor' ],
			wecodeart( 'version' ),
			true 
		);

		wp_enqueue_script( $this->make_handle() );

		wp_set_script_translations( $this->make_handle(), 'wecodeart', wecodeart_config( 'directories' )['languages'] );
	}

	/**
	 * Get file.
	 *
	 * @return string
	 */
	public function get_asset( string $type, string $name ): string {
		$file_path = wecodeart_if( 'is_dev_mode' ) ? 'unminified' : 'minified';
		$file_name = wecodeart_if( 'is_dev_mode' ) ? $name . '.' . $type :  $name . '.min.' . $type;
		$file_path = wecodeart_config( 'paths' )['uri'] . '/inc/support/modules/scrolltop/assets/' . $file_path . '/' . $type . '/' . $file_name;

		return esc_url( $file_path );
	}

	/**
	 * Insert defaults.
	 *
	 * @return 	void
	 */
	public function insert_defaults() {
		if( ! wecodeart_option( 'scrolltop' ) ) {
			wecodeart_option( [
				'scrolltop' => self::get_defaults()
			] );
		}
	}

    /**
	 * Get defaults.
	 *
	 * @return 	array
	 */
	public static function get_defaults(): array {
		return [
			'position' 	=> 'right',
			'action'	=> 'top',
			'icon'		=> [
				'viewBox' 	=> '0 0 16 16',
				'paths'		=> [
					'M15 2a1 1 0 0 0-1-1H2a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V2zM0 2a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V2zm8.5 9.5a.5.5 0 0 1-1 0V5.707L5.354 7.854a.5.5 0 1 1-.708-.708l3-3a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 5.707V11.5z'
				]
			],
			'scroll'	=> [
				'offset' 	=> 100,
				'duration'	=> 400,
			],
			'style'	=> [
				'left'		=> 30,
				'right'		=> 30,
				'bottom'	=> 20,
				'width'		=> 45,
				'height'	=> 45,
				'padding'	=> 3,
				'opacity'	=> 100,
				'color'		=> '#ffffff',
				'backgroundColor'	=> '#0a86ff',
				'borderRadius' 		=> 7,
				'border'	=> [
					'color'	=> '#ffffff',
					'style'	=> 'solid',
					'width'	=> '1px',
				],
			]
		];
	}
}