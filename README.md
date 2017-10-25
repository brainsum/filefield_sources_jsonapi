# File field source JSON API module
Define 'JSON API remote URL' file field source.
  
## Configuration
- Enable 'JSON API remote URL' on form display for image field widget.
- Configure JSON API form widget settings.
- Add TML Rest API basic auth credentials to your settings.php:

      /**
       * Filefield sources JSON API browser credentials settings.
       */
      $config['filefield_sources_jsonapi']['username'] = 'USERNAME';
      $config['filefield_sources_jsonapi']['password'] = 'PASSWORD';
      
## Widget settings
- JSON Api URL
   - request URL, e.g. example.com/jsonapi/media/image
- Params
  - JSON query params per line in key|value format for getting/filtering all
  needed data.
- URL attribute path
  - This is used as remote image url.
- Thumbnail URL attribute path
  - Displayed in modal browser. On empty the 'URL attribute path' will be used.
- Alt attribute path
  - If alt field is enabled on the image field, this value will be set as
  default value after selection.
- Title attribute path
  - Displayed in the lister under image. If title field is enabled on the image
  field, this value will be set as default value after selection.
- Sorting option list
  - Option list per line in key|label format.
- Search filter attribute name
- Items to display
  - Item number per page.
- Modal window width
  - Modal window initial width.
- Modal window height
  - Modal window initial height.
  
## Info, requirements
- URLs (URL, Thumbnail URL) must be relative to the remote server, no contains
domain/base url. Base url is parsed from 'JSON API URL'.
- Sorting: You can add multiple sorting, e.g. 

      name,-created|Name

- Attribute path to 'data' property:
  - If the needed information is in the 'data' property of the response, e.g.:
  

      data->attribute->title

- Attribute path to 'included' property:
   - If the needed information is coming from relationship, e.g.: from
  field_image field, Than you have to include it as request params:
  
  
      include|field_image
   
   and getting data (filename from referenced image):
  

      data->relationships->field_image->included->attributes->filename

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
    
#### 3. Sorting

Sorting by created date (DESC) and name together, using 'Newest first' label:

    -created,name|Newest first
    
#### 4. Searching in media image bundle and in taxonomy term

First, we have to include referenced taxonomy using include param:

    include|field_category

Now we can add it to search field:

    name,field_category

Multiple fields are grouped with 'OR' conjunction. 
