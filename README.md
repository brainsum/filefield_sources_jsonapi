# Filefield source JSON API module
Define 'TML Remote URL textfield' filefield source.
  
## Configuration
- Set TML Rest API URI at form widget settings.
- Add TML Rest API basic auth credentials to your settings.php:

      /**
      * TML entity browser crdetials settings.
      */
      $config['filefield_sources_jsonapi']['username'] = 'USERNAME';
      $config['filefield_sources_jsonapi']['password'] = 'PASSWORD';

