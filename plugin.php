<?php
/*
Plugin Name: Sass Compiler
Plugin URI: http://webmaestro.fr/sass-compiler-wordpress/
Author: Etienne Baudry
Author URI: http://webmaestro.fr
Description: Sass Compiler for Wordpress.
Version: 1.1
License: GNU General Public License
License URI: license.txt
Text Domain: wm-sass
GitHub Plugin URI: https://github.com/WebMaestroFr/wm-sass-compiler
GitHub Branch: master
*/

if ( ! class_exists( 'WM_Sass' ) ) {

require_once( plugin_dir_path( __FILE__ ) . 'libs/wm-settings/plugin.php' );

function sass_set( $variable, $value = null ) {
	// Set a Sass variable value
	$variable = sanitize_key( $variable );
	WM_Sass::$variables[$variable] = $value;
}

function sass_get( $variable ) {
	// Return a Sass variable value
	$variable = sanitize_key( $variable );
	return isset( WM_Sass::$variables[$variable] )
	 	? WM_Sass::$variables[$variable]
		: null;
}

class WM_Sass
{
	public static $variables = array(); // Sass variables

	private static $cache,   // Cache directory (writable)
		$imports = array(),  // Normalize, frameworks or other stuff to import before stylesheet
		$output = false,     // Compiled CSS file path
		$sources = array(),  // Registered variables files
		$fields = array(),   // "Variables" page fields
		$controls = array(); // "Theme Customization API" controls

	public static function init()
	{
		// Configuration
		$defaults = array(
			'variables' => array(),
			'imports'   => array(),
			'cache'     => ABSPATH . 'wp-content/cache',
			'search'    => true
		);
		$config = array_merge( $defaults, apply_filters( 'sass_configuration', $defaults ) );
		self::$sources = self::valid_files( $config, 'variables' );
		self::$imports = self::valid_files( $config, 'imports' );
		self::$cache = empty( $config['cache'] ) ? $defaults['cache'] : $config['cache'];

		// Admin init
		if ( is_admin() ) {

			// Plugin pages
			$page = create_settings_page(
				'sass_compiler',
				__( 'Sass Compiler', 'wm-sass' ),
				array(
					'parent' => 'themes.php', // Under Appearance
					// 'title' => __( 'Sass', 'wm-sass' ),
					'icon_url' => plugin_dir_url( __FILE__ ) . 'img/menu-icon.png'
				),
				array(
					'sass_compiler' => array( // "Editor" page
						'title'       => __( 'Stylesheet', 'wm-sass' ),
						'fields' => array(
							'stylesheet' => array(
								'label'       => false,
								'type'        => 'textarea',
								'description' => sprintf( __( 'From this very stylesheet, <strong>@import</strong> urls are relative to <code>%s</code>.', 'wm-sass' ), get_template_directory() ),
								'attributes'  => array(
									'placeholder' => esc_attr( __( '/* Sass stylesheet */', 'wm-sass' ) )
								)
							)
						)
					)
				),
				array(
					'description' => '<a href="http://sass-lang.com/guide" target="_blank">' . __( 'Getting started with Sass', 'wm-sass' ) . '</a> | <a href="http://webmaestro.fr/sass-compiler-wordpress/" target="_blank">' . __( 'Configure with PHP', 'wm-sass' ) . '</a>',
					'tabs'        => true,
					'submit'      => __( 'Compile', 'wm-sass' ),
					'reset'       => false,
					'updated'     => false
				)
			);

			// Parse variables from registered source files
			if ( empty( self::$sources ) ) {
				$page->add_notice( __( 'In order to edit your Sass variables from this page, you must <a href="http://webmaestro.fr/sass-compiler-wordpress/" target="_blank">register your definition file(s)</a>.', 'wm-sass' ) );
			} else {

				// "Variables" page
				$section = array(
					'title'       => __( 'Variables', 'wm-sass' ),
					'description' => empty( $config['search'] ) ? false : '<input type="search" id="variable-search" placeholder="' . __( 'Search Variable', 'wm-sass' ) . '">',
					'fields'      => array()
				);

				foreach ( self::$sources as $source ) {
					$fields = array();
					if ( $lines = file( $source ) ) {
						foreach ( $lines as $line ) {
							// Find variable definitions
							if ( preg_match( '/^\s*\$([a-zA-Z-]+)\s*:\s*(.+)(\s+!\s*default)?\s*;(\s*\/\/\s*(WP_Customize_[a-zA-Z_]+))?\s*$/', $line, $matches ) ) {
								$name = sanitize_key( $matches[1] );

								// Set variable
								self::$variables[$name] = $matches[2];

								// "Variables" page field
								$fields[$name] = array(
									'label' => "\${$name}",
									'attributes' => array( 'placeholder' => esc_attr( $matches[2] ) )
								);

								// "Theme Customization API" control
								if ( ! empty( $matches[5] ) ) {
									self::$controls[$name] = array(
										'class' => $matches[5],
										'default' => $matches[2]
									);
								}
							}
						}
					}

					if ( empty( $fields ) ) {
						$page->add_notice( sprintf( __( 'No variables were found in the registered definition file <code>%s</code>.', 'wm-sass' ), $source ), 'warning' );
					} else {
						self::$fields = array_merge( self::$fields, $fields );
					}
				};

				if ( ! empty( self::$variables ) ) {
					$page->apply_settings( array(
						'sass_variables' => array(
							'title'       => __( 'Variables', 'wm-sass' ),
							'description' => empty( $config['search'] ) ? false : '<input type="search" id="variable-search" placeholder="' . __( 'Search Variable', 'wm-sass' ) . '">',
							'fields'      => self::$fields
						)
					) );
				}
			}

			// Validate cache directory
			if ( ! is_dir( self::$cache ) && ! mkdir( self::$cache, 0755 ) ) {
				$page->add_notice( sprintf( __( 'The cache directory <code>%s</code> does not exist and cannot be created. Please create it with <code>0755</code> permissions.', 'wm-sass' ), self::$cache ), 'error' );
			} else if ( ! is_writable( self::$cache ) && ! chmod( self::$cache, 0755 ) ) {
				$page->add_notice( sprintf( __( 'The cache directory <code>%s</code> is not writable. Please apply <code>0755</code> permissions to it.', 'wm-sass' ), self::$cache ), 'error' );
			}

			update_option( 'sass_variables_defaults', self::$variables );

		} else {

			// Public init
			self::$variables = get_option( 'sass_variables_defaults', array() );

		}

		self::$variables = array_merge( self::$variables, array_filter( get_setting( 'sass_variables' ) ) );

		// Hooks
		if ( is_writable( self::$cache ) ) {
			self::$output = self::$cache . '/wm-sass-' . get_current_blog_id() . '.css';
	        add_action( 'customize_register', array( __CLASS__, 'customize_register' ) );
			add_action( 'sass_compiler_settings_updated', array( __CLASS__, 'compile' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
			add_filter( 'style_loader_src', array( __CLASS__, 'style_loader_src' ) );
		}
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_enqueue_scripts' ) );
	}

	private static function valid_files( $array, $key )
	{
		$valid = array();
		if ( ! empty( $array[$key] ) ) {
			$files = $array[$key];
			if ( ! is_array( $files ) ) {
				// Convert to array
				$files = array( (string) $files );
			}
			foreach ( $files as $file ) {
				if ( $path = self::valid_file( $file ) ) {
					$valid[] = $path;
				}
			}
		}
		return $valid;
	}

	private static function valid_file( $path )
	{
		if ( empty( $path ) ) { return false; }
		// Convert URIs to path
		$path = str_replace( trailingslashit( site_url() ), ABSPATH, $path );
		if ( strpos( $path, ABSPATH ) !== 0 ) {
			// Convert relative (from template directory) to absolute
			$path = trailingslashit( get_template_directory() ) . ltrim( $path, '/' );
		}
		if ( ! is_file( $path ) ) {
			add_action( 'admin_notices', function () use ( $path ) {
				add_settings_error( 'sass_compiler', 'file_not_found', sprintf( __( 'The file <code>%s</code> cannot be found.', 'wm-sass' ), $path ) );
			} );
		} else if ( ! is_writable( $path ) ) {
			add_action( 'admin_notices', function () use ( $path ) {
				add_settings_error( 'sass_compiler', 'file_not_writable', sprintf( __( 'The file <code>%s</code> is not writable.', 'wm-sass' ), $path ) );
			} );
		} else {
			return $path;
		}
		return false;
	}

    public static function customize_register( $wp_customize )
    {
		if ( ! empty( self::$controls ) ) {
	        $wp_customize->add_section( 'wm_sass' , array(
				'title' => __( 'Sass Variables', 'wm-sass' )
			) );
	        foreach ( self::$controls as $name => $control ) {
	        	$wp_customize->add_setting( $name, array(
					'default' => esc_js( $control['default'] )
				) );
				$wp_customize->add_control( new $control['class']( $wp_customize, $name, array(
					'label'    => "\${$name}",
					'section'  => 'wm_sass',
					'settings' => $name
				) ) );
	        }
		}
    }

	private static function getParser()
	{
		require_once( plugin_dir_path( __FILE__ ) . 'libs/scssphp/scss.inc.php' );
		$parser = new scssc();
		$parser->setImportPaths( array(
			get_stylesheet_directory(),
			get_template_directory(),
			ABSPATH
		) );
		$parser->setFormatter( 'scss_formatter_compressed' );
		$parser->setVariables( self::$variables );
		return $parser;
	}

	public static function compile()
	{
		$code = '';
		$parser = self::getParser();
		try {
			foreach ( self::$imports as $file ) {
				$parser->addImportPath( dirname( $file ) );
				$import = basename( $file, '.scss' );
				$code .= "@import '{$import}';\n";
			}
			$code .= get_setting( 'sass_compiler', 'stylesheet' );
			self::blank_sources();
			$css = $parser->compile( $code );
			self::restore_sources();
			file_put_contents( self::$output, $css );
			add_settings_error( 'sass_compiler', 'sass_compiled', __( 'Sass successfully compiled.', 'wm-sass' ), 'updated' );
		} catch ( exception $e ) {
			add_settings_error( 'sass_compiler', $e->getCode(), sprintf( __( 'Compiler result with the following error : <pre>%s</pre>', 'wm-sass' ), $e->getMessage() ) );
		}
	}
	private static function blank_sources()
	{
		foreach ( self::$sources as $i => $source ) {
			if ( is_file( $source ) && ! is_file( $source . '.restore.scss' ) && copy( $source, $source . '.restore.scss' ) ) {
				file_put_contents( $source, '' );
			}
		}
	}
	private static function restore_sources()
	{
		foreach ( self::$sources as $i => $source ) {
			if ( is_file( $source . '.restore.scss' ) && file_get_contents( $source ) === '' && copy( $source . '.restore.scss', $source ) ) {
				unlink( $source . '.restore.scss' );
			}
		}
	}

	public static function admin_enqueue_scripts( $hook_suffix )
	{
		if ( 'appearance_page_sass_compiler' === $hook_suffix ) {
			wp_enqueue_script( 'codemirror', plugin_dir_url( __FILE__ ) . 'js/codemirror.js' );
			wp_enqueue_script( 'codemirror-css', plugin_dir_url( __FILE__ ) . 'js/codemirror.css.js', array( 'codemirror' ) );
			wp_enqueue_script( 'codemirror-placeholder', plugin_dir_url( __FILE__ ) . 'js/codemirror.placeholder.js', array( 'codemirror' ) );
			wp_enqueue_script( 'sass-compiler', plugin_dir_url( __FILE__ ) . 'js/sass-compiler.js', array( 'codemirror' ) );
			wp_enqueue_style( 'codemirror', plugin_dir_url( __FILE__ ) . 'css/codemirror.css' );
			wp_enqueue_style( 'sass-compiler', plugin_dir_url( __FILE__ ) . 'css/sass-compiler.css' );
		}
	}

	public static function enqueue_scripts()
	{
		if ( ! empty( self::$imports ) || get_setting( 'sass_compiler', 'stylesheet' ) ) {
			if ( ! is_file( self::$output ) ) { self::compile(); }
			wp_enqueue_style( 'wm-sass', str_replace( ABSPATH, trailingslashit( site_url() ), self::$output ) );
		}
	}

	public static function style_loader_src( $src )
	{
		$input = strtok( $src, '?' );
    	if ( preg_match( '/\.scss$/', $input ) ) {
			if ( $file = self::valid_file( $input ) ) {
				$key = md5( $file );
				$hash = md5_file( $file );
				$output = self::$cache . '/wm-sass-' . get_current_blog_id() . '.' . $key . '.css';
				if ( ! $cache = get_option( 'sass_compiler_cache' ) ) { $cache = array(); }
				if ( empty( $cache[$key] ) || $hash !== $cache[$key] ) {
					$parser = self::getParser();
					$parser->addImportPath( dirname( $file ) );
					$code = file_get_contents( $file );
					self::blank_sources();
					$css = $parser->compile( $code );
					self::restore_sources();
					file_put_contents( $output, $css );
					$cache[$key] = $hash;
					update_option( 'sass_compiler_cache', $cache );
				}
				return str_replace( ABSPATH, trailingslashit( site_url() ), $output );
			}
			return null;
	    }
	    return $src;
	}
}
add_action( 'init', array( 'WM_Sass', 'init' ) );

}

?>
