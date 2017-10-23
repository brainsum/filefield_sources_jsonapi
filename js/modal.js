(function ($, Drupal) {

  'use strict';
  Drupal.behaviors.mediaBox = {
    attach: function (context, settings) {
      var $insert_button = $("#filefield_filesources_jsonapi_action input[name='insert_selected']");

      $('.form-type-checkbox + label').each(function(){
        $(this).find('img').wrapAll('<div class="form-image" />').wrapAll('<div class="image" />');
      });
      $('input.form-checkbox').on('click', function() {
        var $parent = $(this).closest('.form-type-checkbox');
        $insert_button.mousedown();
        if ($(this).is(':checked')) {
          $parent.addClass('checked');
        }
        else {
          $parent.removeClass('checked');
        }
      });
    }
  };

})(jQuery, Drupal);
