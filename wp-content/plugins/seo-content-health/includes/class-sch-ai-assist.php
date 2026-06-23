<?php
/**
 * Optional AI assist. Entirely opt-in: only runs if the site admin
 * pastes an API key into Settings. Without a key, the plugin falls
 * back to the rule-based suggestions in SCH_Image_Alt and works fine.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SCH_AI_Assist {

	const API_URL = 'https://api.anthropic.com/v1/messages';

	/**
	 * Ask the AI for a short, descriptive alt text suggestion for an
	 * attachment, using its filename + surrounding post title as context.
	 */
	public static function suggest_alt_text( $attachment_id ) {
		$opts = sch_get_options();
		if ( empty( $opts['ai_api_key'] ) ) {
			return '';
		}

		$file       = get_attached_file( $attachment_id );
		$filename   = $file ? basename( $file ) : '';
		$parent_id  = wp_get_post_parent_id( $attachment_id );
		$context    = $parent_id ? get_the_title( $parent_id ) : '';

		$prompt = sprintf(
			"Suggest a concise, descriptive HTML alt attribute (max 12 words, no quotes) for an image named \"%s\" used in an article titled \"%s\". Reply with only the alt text, nothing else.",
			$filename,
			$context
		);

		$response = self::call_api( $prompt );

		return $response ? trim( $response, " \t\n\r\0\x0B\"'" ) : '';
	}

	/**
	 * Ask the AI for an SEO title + meta description for a post.
	 * Returns array( 'title' => ..., 'description' => ... ) or false.
	 */
	public static function suggest_meta( $post_id ) {
		$opts = sch_get_options();
		if ( empty( $opts['ai_api_key'] ) ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		$excerpt = wp_trim_words( wp_strip_all_tags( $post->post_content ), 120 );

		$prompt = sprintf(
			"Write an SEO title (%d-%d characters) and a meta description (%d-%d characters) for this article titled \"%s\". Content summary: %s\n\nReply strictly as JSON: {\"title\": \"...\", \"description\": \"...\"}",
			$opts['title_min'], $opts['title_max'],
			$opts['desc_min'], $opts['desc_max'],
			$post->post_title,
			$excerpt
		);

		$raw = self::call_api( $prompt );
		if ( ! $raw ) {
			return false;
		}

		$clean = preg_replace( '/```json|```/', '', $raw );
		$data  = json_decode( trim( $clean ), true );

		if ( ! is_array( $data ) || empty( $data['title'] ) ) {
			return false;
		}

		return array(
			'title'       => sanitize_text_field( $data['title'] ),
			'description' => sanitize_text_field( $data['description'] ?? '' ),
		);
	}

	/**
	 * Low-level call to the Anthropic Messages API.
	 */
	private static function call_api( $prompt ) {
		$opts = sch_get_options();

		$response = wp_remote_post( self::API_URL, array(
			'timeout' => 20,
			'headers' => array(
				'Content-Type'      => 'application/json',
				'x-api-key'         => $opts['ai_api_key'],
				'anthropic-version' => '2023-06-01',
			),
			'body' => wp_json_encode( array(
				'model'      => 'claude-sonnet-4-6',
				'max_tokens' => 200,
				'messages'   => array(
					array( 'role' => 'user', 'content' => $prompt ),
				),
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['content'][0]['text'] ) ) {
			return '';
		}

		return $body['content'][0]['text'];
	}
}
