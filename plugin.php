<?php
/*
Plugin Name: WebMaestro Sass
Plugin URI: http://webmaestro.fr/sass-compiler-wordpress/
Author: Etienne Baudry
Author URI: http://webmaestro.fr
Description: Sass Compiler for Wordpress. Enqueue .scss, edit variables from dashboard, compile if modifications occured.
Version: 2.0
License: GNU General Public License
License URI: license.txt
Text Domain: wm-sass
*/


if ( ! class_exists( 'WM_Sass' ) ) {

    // USER FUNCTIONS

    function wm_sass_variables( array $variables )
    {
        WM_Sass::$variables = array_merge( WM_Sass::$variables, array_filter( $variables ) );
    }


    // PLUGIN CLASS

    class WM_Sass {

        public static $variables = array();


        // WORDPRESS ACTIONS

    	public static function init()
    	{
            if ( ! class_exists( 'WM_Settings' ) ) {
                require_once( __DIR__ . "/libraries/wm-settings/plugin.php" );
            }
            if ( ! is_writable( __DIR__ . "/compiled" ) ) {
                $message = sprintf( __( 'The directory <code>%s</code> must be writable. Please restore correct permissions (<code>0755</code>).', 'wm-sass' ) , __DIR__ . "/compiled" );
                WM_Settings::add_alert( $message, 'error', __( 'WebMaestro Sass', 'wm-sass' ) );
    		} else {
                // Listen for .scss only if "compiled" directory is writable
                add_filter( 'style_loader_tag', array( __CLASS__, 'style_loader_tag' ), null, 3 );
            }
    	}


        // WORDPRESS FILTERS

    	public static function style_loader_tag( $html, $handle, $href )
    	{
            // Input file URL
    		$input_url = strtok( $href, '?' );
            // Check if input is Sass
        	if ( preg_match( '/\.scss$/', $input_url ) ) {
                // Input file path
    			$input = str_replace( site_url(), ABSPATH, $input_url );
                // Replace with output file URL
                return str_replace( $input_url, self::get_output( $input, true ), $html );
    		}
    		return $html;
    	}


        // USER METHODS

        public static function get_output( $input, $url = false )
        {
            // Unique blog + input ID
            $hash = get_current_blog_id() . md5( $input );
            // Compiled CSS path
            $output = __DIR__ . "/compiled/{$hash}.css";

             // Check if compiling is needed
            if ( ! is_file( $output )
                || ( current_user_can( 'edit_themes' ) && ! self::compare_filemtimes( get_transient( "wm_sass_{$hash}" ) ) )
            ) {
                // Compile
                $compiler = self::get_compiler();
                $compiler->addImportPath( dirname( $input ) );
                $scss = file_get_contents( $input );
                try {
                    file_put_contents( $output, $compiler->compile( $scss ) );
                    set_transient( "wm_sass_{$hash}", array_merge( array(
                        $input => filemtime( $input )
                    ), $compiler->getParsedFiles() ) );
                } catch ( Exception $error ) {
                    WM_Settings::add_alert( $error->getMessage(), 'error', __( 'WebMaestro Sass', 'wm-sass' ) );
                }
            }
            // Return compiled CSS path/URL
            return $url ? plugins_url( "compiled/{$hash}.css", __FILE__ ) : $output;
        }
        private static function compare_filemtimes( array $files )
        {
            return $files && $files === array_combine( array_keys( $files ), array_map( 'filemtime', array_keys( $files ) ) );
        }

        // Get Scss PHP compiler instance
        public static function get_compiler()
        {
            // Instanciate compiler
            require_once( __DIR__ . "/libraries/scssphp/scss.inc.php" );
            $scss = new Leafo\ScssPhp\Compiler;
            // Configure compiler
            $scss->setImportPaths( ABSPATH );
            $scss->setFormatter( 'Leafo\ScssPhp\Formatter\Crunched' );
            $scss->setNumberPrecision( 2 );
            $scss->setVariables( array_merge( array(
                'site-url' => wp_make_link_relative( site_url() )
            ), self::$variables ) );
            return $scss;
        }
    }


    // ACTIONS

    add_action( 'init', array( 'WM_Sass', 'init' ) );
}

?>
