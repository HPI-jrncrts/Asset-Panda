<?php
/**
 * REST routes (namespace ap-template-library/v1). All of them need a logged-in user who
 * can edit pages; WordPress's cookie authentication requires the wp_rest nonce
 * (X-WP-Nonce), which wp.apiFetch sends.
 *
 *   POST   /draft     { template, page, blocks }  -> creates a draft, returns its links
 *   POST   /progress  { template, state }         -> saves builder progress for the user
 *   DELETE /progress  ?template=<key>             -> discards it
 *
 * @package APTemplateLibrary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'ap_tl_register_routes' );

/** Registers the routes. */
function ap_tl_register_routes() {
	$template_arg = array(
		'type'              => 'string',
		'required'          => true,
		'sanitize_callback' => 'sanitize_key',
	);

	register_rest_route(
		AP_TL_REST_NS,
		'/draft',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'ap_tl_rest_draft',
			'permission_callback' => 'ap_tl_rest_permission',
			'args'                => array(
				'template' => $template_arg,
				'page'     => array(
					'type'     => 'object',
					'required' => true,
				),
				'blocks'   => array(
					'type'     => 'array',
					'required' => true,
				),
			),
		)
	);

	register_rest_route(
		AP_TL_REST_NS,
		'/progress',
		array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'ap_tl_rest_save_progress',
				'permission_callback' => 'ap_tl_rest_permission',
				'args'                => array(
					'template' => $template_arg,
					'state'    => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => 'ap_tl_rest_delete_progress',
				'permission_callback' => 'ap_tl_rest_permission',
				'args'                => array( 'template' => $template_arg ),
			),
		)
	);
}

/**
 * Every route: logged in and able to edit pages.
 *
 * @return bool|WP_Error
 */
function ap_tl_rest_permission() {
	if ( current_user_can( 'edit_pages' ) ) {
		return true;
	}
	return new WP_Error( 'rest_forbidden', __( 'You are not allowed to use the Template Library.', 'ap-template-library' ), array( 'status' => rest_authorization_required_code() ) );
}

/**
 * POST /draft.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function ap_tl_rest_draft( WP_REST_Request $request ) {
	try {
		$result = ap_tl_create_draft(
			(string) $request->get_param( 'template' ),
			array(
				'page'   => (array) $request->get_param( 'page' ),
				'blocks' => $request->get_param( 'blocks' ),
			)
		);
	} catch ( Throwable $e ) {
		error_log( '[ap-template-library] draft: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		return new WP_Error( 'ap_tl_error', __( 'The draft could not be created. Nothing was saved.', 'ap-template-library' ), array( 'status' => 500 ) );
	}
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	return new WP_REST_Response( $result, 201 );
}

/**
 * POST /progress.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function ap_tl_rest_save_progress( WP_REST_Request $request ) {
	$key = (string) $request->get_param( 'template' );
	if ( ! ap_tl_get_template( $key ) ) {
		return new WP_Error( 'ap_tl_unknown_template', __( 'That template no longer exists.', 'ap-template-library' ), array( 'status' => 404 ) );
	}
	$result = ap_tl_save_progress( get_current_user_id(), $key, $request->get_param( 'state' ) );
	if ( is_wp_error( $result ) ) {
		return $result;
	}
	return new WP_REST_Response( $result, 200 );
}

/**
 * DELETE /progress.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function ap_tl_rest_delete_progress( WP_REST_Request $request ) {
	ap_tl_delete_progress( get_current_user_id(), (string) $request->get_param( 'template' ) );
	return new WP_REST_Response( array( 'deleted' => true ), 200 );
}
