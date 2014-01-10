jQuery(document).ready( function() {

	jQuery(".p3-comment-moderation").click( function(e) {
		e.preventDefault();
		var comment_id = jQuery(this).attr("data-comment_id")
		var nonce = jQuery(this).attr("data-nonce")
		var p3moderation = jQuery(this).attr("data-p3moderation")
		//console.log( nonce, comment_id, p3moderation )

		jQuery("#comment-".concat(comment_id)).fadeOut( function() { 
			jQuery(this).remove(); 
		})

		jQuery.ajax({
			type : "post",
			dataType : "json",
			url : p3cmetaAjax.ajaxurl,
			data : {
				action : "p3_comment_moderation_save",
				p3moderation : p3moderation,
				comment_id : comment_id,
				nonce : nonce
			},
			success: function(response) {
				if(response.type == "success") {
					//alert("Success!")
					//console.log ("success")
				}
				else {
					alert("Something went wrong and the comment couldn't be moderated. Please reload the page and try again! If this doesn't resolve the problem please contact the administrator")
					//console.log ("error")
				}
			}
		})

	})

})