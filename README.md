#p3wp-comments

Comment moderation system for Planet3.0

The theme template must be updated to show custom comment statuses for example if you want the main comment list to exclude shadow comments replace the original `get_comments()` query with this:

    get_comments( array( 
        'order' => 'ASC',
        'post_id' => get_the_ID(),
        'status' => 'approve',
        'meta_query' => array(
            'relation' => 'OR',
            array( // Select comments that don't have the 'shadow' p3_comment_status meta
                'key' => 'p3_comment_status',
                'value' => 'shadow',
                'compare' => '!='
            ),
            array( // Select comments that don't have the p3_comment_status set
                'key' => 'p3_comment_status',
                'compare' => 'NOT EXISTS',
                'value' => ''
            )
        )
    ) );

To display just the shadow comments add this `get_comments()` query:

    get_comments( array( 
        'order' => 'ASC',
        'post_id' => get_the_ID(),
        'status' => 'approve',
        'meta_query' => array(
            array( // Select comments that don't have the 'shadow' p3_comment_status meta
                'key' => 'p3_comment_status',
                'value' => 'shadow',
                'compare' => '='
            )
        )
    ) );

To dispalay pending comments requiering moderation add this 'get_comments` query:

    get_comments( array( 
        'order' => 'ASC',
        'post_id' => get_the_ID(),
        'status' => 'hold'
    ) );

And finally the conditional used to check if pending comments should be displayed is:

    if ( function_exists('p3_comment_moderation_show') && p3_comment_moderation_show() )