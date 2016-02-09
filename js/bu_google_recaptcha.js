function buRenderAllCAPTCHAs(){
	(function($){
		$.each( $('.g-recaptcha:empty'), function(i, e){
			grecaptcha.render( e, {
				'sitekey' : $(e).data('sitekey')
			});
		});
	})(jQuery);
}
function buGoogleCAPTCHACallback(){
	buRenderAllCAPTCHAs();
	jQuery( 'iframe[name^="gform_ajax"]' ).bind( 'load', buRenderAllCAPTCHAs );
}