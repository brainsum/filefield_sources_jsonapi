# Filefield source JSON API module
Define 'JSON API remote URL' filefield source.
  
## Configuration
- Enable 'JSON API remote URL' on form display for image field widget.
- Configure JSON API form widget settings.
- Add TML Rest API basic auth credentials to your settings.php:

      /**
      * TML entity browser credentials settings.
      */
      $config['filefield_sources_jsonapi']['username'] = 'USERNAME';
      $config['filefield_sources_jsonapi']['password'] = 'PASSWORD';
      
## Widget settings
- JSON Api URL
   - request URL, e.g. example.com/jsonapi/media/image
- Params
  - JSON query params per line in key|value format for getting all needed data.
- URL attribute path
- Thumbnail URL attribute path
  - Displayed in modal browser. On empty the 'URL attribute path' will be used.
- Alt attribute path
- Title attribute path
- Sorting option list
  - Option list per line in key|label format.
- Search filter attribute name
- Items to display
  - Item number per page.
  
## Info, requirements
URLs (URL, Thumbnail URL) must be relative to the remote server, no contains
domain/base url. Base url is parsed from 'JSON API URL'.

## Restrictions
Widget/browser doesn't support multiple selecting. This means: You can use with
more than 1 cardinality, but you can select remote images by one.

## Examples
#### 1. Getting files from media image entities, field_image field

We have media image entities ('image' bundle). Image (file) is stored in
field_image (core image field type). We would like to get all image urls for
published media image, searching in media name, sorting by media name
(ascending/alphabetic) and by created date (descending).

    - Api URL: example.com/media/image
    - Params:
        - include|field_image
        - fields[file--file]|url
        - fields[media--image]|name,field_image
        - filter[statusFilter][condition][path]|status
        - filter[statusFilter][condition][operator]|=
        - filter[statusFilter][condition][value]|1
    - URL attribute path: data->relationships->field_image->included->attributes->url
    - Thumbnail URL attribute path:
    - Alt attribute path: data->relationships->field_image->data->meta->alt
    - Title attribute path: data->attributes->name
    - Sorting option list:
      - -created|Newest first
      - name|By name
    - Search filter attribute name: field_category.name

#### 2. Getting images from managed files:
We would like to get all image (drupal managed files) file urls, searching in
file name, sorting by created date (descending).

    - Api URL: example.com/file/file
    - Params:
        - fields[file--file]|filename,url
        - filter[mimeFilter][condition][path]|filemime
        - filter[mimeFilter][condition][operator]|CONTAINS
        - filter[mimeFilter][condition][value]|image/
    - URL attribute path: data->attributes->url
    - Thumbnail URL attribute path:
    - Alt attribute path: data->attributes->filename
    - Title attribute path: data->attributes->filename
    - Sorting option list:
        - -created|Newest first
    - Search filter attribute name: filename