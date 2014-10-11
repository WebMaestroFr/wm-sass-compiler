=== Plugin Name ===
Contributors: WebMaestro.Fr
Donate link: http://webmaestro.fr/sass-compiler-wordpress/
Tags: SASS, compiler
Requires at least: 4.0
Tested up to: 4.0
Stable tag: 1.0
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

SASS compiler for WordPress. Allows you to write and compile SASS, and to edit style variables straight into your WordPress dashboard.

== Description ==

Write SASS, edit your variables and compile your stylesheet from your dashboard.

[Read the documentation](http://webmaestro.fr/sass-compiler-wordpress/)

  - Register and enqueue your SASS sheets the same way you would do for your CSS.

    ```
    wp_enqueue_style( 'my-sass-handle', 'http://example.com/css/mystyle.scss', $deps, $ver, $media );
    ```

  - Configure the plugin with the `sass_configuration` filter.

    Configuration of the plugin is optional, but you should at least register your variables if you are using a CSS framework.

  - Set a SASS variable value

    ```
    sass_set( $variable, $value );
    ```

  - Get a SASS variable value

    ```
    sass_get( $variable );
    ```

You will most likely use these functions in your theme's `functions.php`.

The plugin uses [the scssphp Compiler](http://leafo.net/scssphp/).

== Installation ==

1. Upload `sass-compiler` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Find the plugin's pages under the new 'SASS' menu

== Frequently Asked Questions ==

= No question asked =

No answer to give.

== Screenshots ==

1. The 'Compiler' page
2. The 'Variables' page after configuration

== Changelog ==

= 1.0 =
* Initial commit

== Upgrade Notice ==

= 1.0 =
* Initial commit
