<?php

/*Get the thumbnail from video & attach to video post*/
function get_attachment_url_by_slug( $slug ) {
    $args = array(
        'post_type' => 'attachment',
        'name' => $slug,
        'posts_per_page' => 1,
        //'post_status' => 'inherit',
    );
    $_header = get_posts( $args );
    $header = $_header ? array_pop($_header) : null;
    return $header ? wp_get_attachment_url($header->ID) : '';
}

/*Get the Thumbnail of the embeded YouTube video*/

add_action('save_post','get_post_before_save',10,2);
function get_post_before_save($post_id,$post) {
	$post_type = get_post_type($post_id);
	// If the Post type for the post is video
	// Replace video with the post type you need to save to
	if ($post_type == 'video') {
		// Replace this with correct YouTube URL field name
		$youtube_embed = get_field('embed_video', $post_id);
		if ($youtube_embed) {
			
			// oEmbed returns this
			// <iframe title="PE With Joe 2021 | Monday 1st Feb" width="640" height="360" src="https://www.youtube.com/embed/yOvqLXv88L4?feature=oembed" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
			
			preg_match('/src="(.+?)"/', $youtube_embed, $matches);
			$src = $matches[1];
			
			// Get the youtube embed & get everything after the =
			// e.g. https://www.youtube.com/embed/yOvqLXv88L4?feature=oembed - we need yOvqLXv88L4
			$pos = strrpos($src, '/');
			$id = $pos === false ? $src : substr($src, $pos + 1);
			
			// This still leaves yOvqLXv88L4?feature=oembed - we only need yOvqLXv88L4
			$arr = explode("?", $id, 2);
			$yt_id = $arr[0];
			
			// This gets yOvqLXv88L4
			// Now we need to create the YouTube thumbnail image url
			$yt_img_url = 'https://img.youtube.com/vi/' . $yt_id . '/0.jpg';
			
			if ($yt_img_url) {

					// We need the image file name
					$yt_img_url_short = basename($yt_img_url);
					
					// Get the post name
					$title = get_the_title($post_id);
					$post_name = urlencode($title);
				
					$yt_img_url_short = $title . '-' . $yt_id . '-' . $yt_img_url_short;
					// We need to check whether the file already exists within the media library
					// We get the directory that stores media files
					$upload_dir = wp_upload_dir();

					$attachment_url_full = get_attachment_url_by_slug($yt_img_url_short);

					// If an attachement url wasn't found, lets create the image within the media library
					if (!$attachment_url_full) {

							// Get Featured Image
							$image_url        = $yt_img_url; // Define the image URL here
							$image_name       = $yt_img_url_short;
							$image_data       = file_get_contents($image_url); // Get image data
							$unique_file_name = wp_unique_filename( $upload_dir['path'], $image_name ); // Generate unique name
							$filename         = basename( $unique_file_name ); // Create image file name

							// Check folder permission and define file location
							if( wp_mkdir_p( $upload_dir['path'] ) ) {
									$file = $upload_dir['path'] . '/' . $filename;
							} else {
									$file = $upload_dir['basedir'] . '/' . $filename;
							}

							// Create the image file on the server
							file_put_contents( $file, $image_data );

							// Check image file type
							$wp_filetype = wp_check_filetype( $filename, null );

							// Set attachment data
							$attachment = array(
									'post_mime_type' => $wp_filetype['type'],
									'post_title'     => sanitize_file_name( $filename ),
									'post_content'   => '',
									'post_status'    => 'inherit'
							);

							// Create the attachment
							$attach_id = wp_insert_attachment( $attachment, $file, $post_id );

							// Include image.php
							require_once(ABSPATH . 'wp-admin/includes/image.php');

							// Define attachment metadata
							$attach_data = wp_generate_attachment_metadata( $attach_id, $file );

							// Assign metadata to attachment
							wp_update_attachment_metadata( $attach_id, $attach_data );

							// And finally assign featured image to post
							set_post_thumbnail( $post_id, $attach_id );

					} 

					else {
							// If we're here it means the file already exists in the media library
							// Get attachment ID from the media item URL that already exists 
							$attach_id = attachment_url_to_postid( $attachment_url_full );

							// Get the ID of the featured image already attached to the post
							$featured_image_id = get_post_thumbnail_id($post_id);

							// If the featured image is not the same as the new attachment ID,
							// set the new attachement as the featured image
							// Otherwise do nothing
							if ($featured_image_id != $attach_id) {
									// Assign new featured image to post
									set_post_thumbnail( $post_id, $attach_id );
							} else {
							}

					}

			} else {}
		} else {}
	}
}