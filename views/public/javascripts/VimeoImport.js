jQuery(window).load(function() {
  var vimeoImportedThumb = jQuery("div#moving-image-item-type-metadata-imported-thumbnail div.element-text p").html();
  vimeoImportedThumb = vimeoImportedThumb || jQuery("div#moving-image-item-type-metadata-imported-thumbnail div.element-text").html();
  jQuery('a img[title="'+vimeoImportedThumb+'"]').hide();
  var adminImageDiv =   jQuery('a img[title="'+vimeoImportedThumb+'"]').parents("div.admin-thumb.panel");
  if(vimeoImportedThumb && adminImageDiv.children().length == 1)
    adminImageDiv.hide();
});
