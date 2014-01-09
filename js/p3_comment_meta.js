jQuery(document).ready( function() {

	jQuery(".p3-comment-moderation").click( function(e) {
		e.preventDefault();
		var comment_id = jQuery(this).attr("data-comment_id")
		var nonce = jQuery(this).attr("data-nonce")

		jQuery.ajax({
			type : "post",
			dataType : "json",
			url : p3cmetaAjax.ajaxurl,
			data : {action: "p3_comment_moderation_save", comment_id : comment_id, nonce : nonce},
			success: function(response) {
				if(response.type == "success") {
					alert("Success!")
				}
				else {
					alert("Something went wrong.")
				}
			}
		})

	})

})