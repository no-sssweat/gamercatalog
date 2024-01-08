(function (Drupal, once) {
  Drupal.behaviors.webformEmbed = {
    attach: function( context, settings ) {
      once('webform-embed', '.webform-embed', context).forEach(( webform_embed_wrapper ) => {

      });
    }
  };
}(Drupal, once));
