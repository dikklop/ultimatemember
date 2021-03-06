<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="um-admin-metabox">
	<?php
    if ( ! empty( $object->ID ) )
        $data = get_post_meta( $object->ID, 'um_content_restriction', true );
    else
        $data = array();

    $_um_access_roles_value = array();
    if ( ! empty( $data['_um_access_roles'] ) ) {
        foreach ( $data['_um_access_roles'] as $key => $value ) {
            if ( $value )
                $_um_access_roles_value[] = $key;
        }
    }

    $fields = apply_filters( 'um_admin_access_settings_fields', array(
        array(
            'id'		    => '_um_custom_access_settings',
            'type'		    => 'checkbox',
            'name'		    => '_um_custom_access_settings',
            'label'    		=> __( 'Restrict access to this content?', 'ultimate-member' ),
            'description' 	=> __( 'Activate content restriction for this post', 'ultimate-member' ),
            'value' 		=> ! empty( $data['_um_custom_access_settings'] ) ? $data['_um_custom_access_settings'] : 0,
        ),
        array(
            'id'		=> '_um_accessible',
            'type'		=> 'select',
            'name'		=> '_um_accessible',
            'label'    		=> __( 'Who can access this content?', 'ultimate-member' ),
            'description' 	=> __( 'Activate content restriction for this post', 'ultimate-member' ),
            'value' 		=> ! empty( $data['_um_accessible'] ) ? $data['_um_accessible'] : 0,
            'options'		=> array(
                '0'         => __( 'Everyone', 'ultimate-member' ),
                '1'         => __( 'Logged out users', 'ultimate-member' ),
                '2'         => __( 'Logged in users', 'ultimate-member' ),
            ),
            'conditional'	=> array( '_um_custom_access_settings', '=', '1' )
        ),
        array(
            'id'       		=> '_um_access_roles',
            'type'     		=> 'multi_checkbox',
            'name'		    => '_um_access_roles',
            'label'    		=> __( 'Select which roles can access this content', 'ultimate-member' ),
            'description' 	=> __( 'Activate content restriction for this post', 'ultimate-member' ),
            'value' 		=> $_um_access_roles_value,
            'options'		=> UM()->roles()->get_roles( false, array( 'administrator' ) ),
            'columns'       => 3,
            'conditional'	=> array( '_um_accessible', '=', '2' )
        ),
        array(
            'id'       		=> '_um_noaccess_action',
            'type'     		=> 'select',
            'name'		    => '_um_noaccess_action',
            'label'    		=> __( 'What happens when users without access tries to view the content?', 'ultimate-member' ),
            'description' 	=> __( 'Action when users without access tries to view the content', 'ultimate-member' ),
            'value' 		=> ! empty( $data['_um_noaccess_action'] ) ? $data['_um_noaccess_action'] : 0,
            'options'		=> array(
                '0'         => __( 'Show access restricted message', 'ultimate-member' ),
                '1'         => __( 'Redirect user', 'ultimate-member' ),
            ),
            'conditional'	=> array( '_um_accessible', '!=', '0' )
        ),
        array(
            'id'       		=> '_um_restrict_by_custom_message',
            'type'     		=> 'select',
            'name'		    => '_um_restrict_by_custom_message',
            'label'    		=> __( 'Would you like to use the global default message or apply a custom message to this content?', 'ultimate-member' ),
            'description' 	=> __( 'Action when users without access tries to view the content', 'ultimate-member' ),
            'value' 		=> ! empty( $data['_um_restrict_by_custom_message'] ) ? $data['_um_restrict_by_custom_message'] : '0',
            'options'		=> array(
                '0'         => __( 'Global default message (default)', 'ultimate-member' ),
                '1'         => __( 'Custom message', 'ultimate-member' ),
            ),
            'conditional'	=> array( '_um_noaccess_action', '=', '0' )
        ),
        array(
            'id'       		=> '_um_restrict_custom_message',
            'type'     		=> 'wp_editor',
            'name'		    => '_um_restrict_custom_message',
            'label'    		=> __( 'Custom Restrict Content message', 'ultimate-member' ),
            'description' 	=> __( 'Changed global restrict message', 'ultimate-member' ),
            'value' 		=> ! empty( $data['_um_restrict_custom_message'] ) ? $data['_um_restrict_custom_message'] : '',
            'conditional'	=> array( '_um_restrict_by_custom_message', '=', '1' )
        ),
        array(
            'id'       		=> '_um_access_redirect',
            'type'     		=> 'select',
            'name'		    => '_um_access_redirect',
            'label'    		=> __( 'Where should users be redirected to?', 'ultimate-member' ),
            'description' 	=> __( 'Select redirect to page when user hasn\'t access to content', 'ultimate-member' ),
            'value' 		=> ! empty( $data['_um_access_redirect'] ) ? $data['_um_access_redirect'] : '0',
            'conditional'	=> array( '_um_noaccess_action', '=', '1' ),
            'options'		=> array(
                '0'         => __( 'Login page', 'ultimate-member' ),
                '1'         => __( 'Custom URL', 'ultimate-member' ),
            ),
        ),
        array(
            'id'       		=> '_um_access_redirect_url',
            'type'     		=> 'text',
            'name'		    => '_um_access_redirect_url',
            'label'    		=> __( 'Redirect URL', 'ultimate-member' ),
            'description' 	=> __( 'Changed global restrict message', 'ultimate-member' ),
            'value' 		=> ! empty( $data['_um_access_redirect_url'] ) ? $data['_um_access_redirect_url'] : '',
            'conditional'	=> array( '_um_access_redirect', '=', '1' )
        ),
        array(
            'id'       		=> '_um_access_hide_from_queries',
            'type'     		=> 'checkbox',
            'name'		    => '_um_access_hide_from_queries',
            'label'    		=> __( 'Hide from queries', 'ultimate-member' ),
            'description' 	=> __( 'Hide this content from archives, RSS feeds etc for users who do not have permission to view this content', 'ultimate-member' ),
            'value' 		=> ! empty( $data['_um_access_hide_from_queries'] ) ? $data['_um_access_hide_from_queries'] : '',
            'conditional'	=> array( '_um_accessible', '!=', '0' )
        )
    ), $data );

    UM()->admin_forms( array(
        'class'		=> 'um-restrict-content um-third-column',
        'prefix_id'	=> 'um_content_restriction',
        'fields' => $fields
    ) )->render_form(); ?>

</div>