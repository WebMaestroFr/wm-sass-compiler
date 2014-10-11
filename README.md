A SASS compiler for WordPress ! This is a plugin that will allow you to write and compile SASS, and to edit style variables straight into your WordPress dashboard.

It uses [the scssphp Compiler](http://leafo.net/scssphp/).

## Installation

1. Download the last release
2. Unzip it into your wp-content/plugins directory
3. Activate the plugin in WordPress

## Documentation

[Read the documentation](http://webmaestro.fr/sass-compiler-wordpress/)

## How to use

- Register and enqueue your SASS sheets the same way you would do for your CSS.
  ```php
  wp_enqueue_style( 'my-sass-handle', 'http://example.com/css/mystyle.scss', $deps, $ver, $media );
  ```

- Configure the plugin with the `sass_configuration` filter.
  ```php
  add_filter( 'sass_configuration', 'my_sass_config' );
  function my_sass_config( $config ) {
    $variables = array( 'sass/_variables.scss' );
    $imports = array(
      'sass/bootstrap.scss',
      'sass/_theme.scss'
    );
    return array_merge_recursive( $config, array(
      'variables' => $variables,
      'imports'   => $imports
    ) );
  }
  ```
  Configuration of the plugin is optional, but you should at least register your variables if you are using a CSS framework.

- Set a SASS variable value
  ```php
  sass_set( $variable, $value );
  ```

- Get a SASS variable value
  ```php
  sass_get( $variable );
  ```

## Contributors

Contributors are more than welcome !

## License

[WTFPL](http://www.wtfpl.net/) – Do What the Fuck You Want to Public License
