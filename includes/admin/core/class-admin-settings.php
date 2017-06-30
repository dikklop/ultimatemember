<?php
namespace um\admin\core;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Admin_Settings' ) ) {
    class Admin_Settings {

        var $settings_structure;
        var $previous_licenses;

        function __construct() {
            //init settings structure
            add_action( 'admin_init', array( &$this, 'init_variables' ), 9 );

            //admin menu
            add_action( 'admin_menu', array( &$this, 'primary_admin_menu' ), 0 );

            //settings structure handlers
            add_action( 'um_settings_page_before_email__content', array( $this, 'settings_before_email_tab' ) );
            add_filter( 'um_settings_section_email__content', array( $this, 'settings_email_tab' ), 10, 1 );

            //enqueue wp_media for profiles tab
            add_action( 'um_settings_page_appearance__before_section', array( $this, 'settings_appearance_profile_tab' ) );

            //custom content for licenses tab
            add_filter( 'um_settings_section_licenses__content', array( $this, 'settings_licenses_tab' ), 10, 2 );


            add_filter( 'um_settings_structure', array( $this, 'sorting_licenses_options' ), 9999, 1 );


            //save handlers
            add_action( 'admin_init', array( $this, 'save_settings_handler' ), 10 );

            //save pages options
            add_action( 'um_settings_save', array( $this, 'on_settings_save' ) );


            //save licenses options
            add_action( 'um_settings_before_save', array( $this, 'before_licenses_save' ) );
            add_action( 'um_settings_save', array( $this, 'licenses_save' ) );


            //invalid licenses notice
            add_action( 'admin_notices', array( $this, 'check_wrong_licenses' ) );
        }


        function init_variables() {
            $general_pages_fields = array(
                array(
                    'id'       		=> 'pages_settings',
                    'type'     		=> 'hidden',
                    'default'       => true,
                    'is_option'     => false
                )
            );

            $core_pages = UM()->config()->core_pages;
            foreach ( $core_pages as $page_s => $page ) {
                $have_pages = UM()->query()->wp_pages();
                $page_id = apply_filters( 'um_core_page_id_filter', 'core_' . $page_s );

                $page_title = ! empty( $page['title'] ) ? $page['title'] : '';

                if ( 'reached_maximum_limit' == $have_pages ) {
                    $general_pages_fields[] = array(
                        'id'       		=> $page_id,
                        'type'     		=> 'text',
                        'label'    		=> sprintf( __( '%s page', 'ultimatemember' ), $page_title ),
                        'placeholder' 	=> __('Add page ID','ultimatemember'),
                        'compiler' 		=> true,
                    );
                } else {
                    $general_pages_fields[] = array(
                        'id'       		=> $page_id,
                        'type'     		=> 'selectbox',
                        'label'    		=> sprintf( __( '%s page', 'ultimatemember' ), $page_title ),
                        'options' 		=> UM()->query()->wp_pages(),
                        'placeholder' 	=> __('Choose a page...','ultimatemember'),
                        'compiler' 		=> true,
                    );
                }
            }



            $appearances_profile_menu_fields = array(
                array(
                    'id'       		=> 'profile_menu',
                    'type'     		=> 'checkbox',
                    'label'    		=> __('Enable profile menu','ultimatemember'),
                )
            );

            $tabs = UM()->profile()->tabs_primary();
            foreach( $tabs as $id => $tab ) {
                $appearances_profile_menu_fields = array_merge( $appearances_profile_menu_fields, array(
                    array(
                        'id'       		=> 'profile_tab_' . $id,
                        'type'     		=> 'checkbox',
                        'label'    		=> sprintf(__('%s Tab','ultimatemember'), $tab ),
                        'conditional'		=> array( 'profile_menu', '=', 1 ),
                    ),
                    array(
                        'id'       		=> 'profile_tab_' . $id . '_privacy',
                        'type'     		=> 'selectbox',
                        'label'    		=> sprintf( __( 'Who can see %s Tab?','ultimatemember' ), $tab ),
                        'description' 	=> __( 'Select which users can view this tab.','ultimatemember' ),
                        'options' 		=> UM()->profile()->tabs_privacy(),
                        'conditional'		=> array( 'profile_tab_' . $id, '=', 1 ),
                    ),
                    array(
                        'id'       		=> 'profile_tab_' . $id . '_roles',
                        'type'     		=> 'selectbox',
                        'multi'         => true,
                        'label'    		=> __( 'Allowed roles','ultimatemember' ),
                        'description' 	=> __( 'Select the the user roles allowed to view this tab.','ultimatemember' ),
                        'options' 		=> UM()->roles()->get_roles(),
                        'placeholder' 	=> __( 'Choose user roles...','ultimatemember' ),
                        'conditional'		=> array( 'profile_tab_' . $id . '_privacy', '=', 4 ),
                    )
                ) );
            }

            $appearances_profile_menu_fields = array_merge( $appearances_profile_menu_fields, array(
                array(
                    'id'       		=> 'profile_menu_default_tab',
                    'type'     		=> 'selectbox',
                    'label'    		=> __( 'Profile menu default tab','ultimatemember' ),
                    'description' 	=> __( 'This will be the default tab on user profile page','ultimatemember' ),
                    'options' 		=> UM()->profile()->tabs_enabled(),
                    'conditional'		=> array( 'profile_menu', '=', 1 ),
                ),
                array(
                    'id'       		=> 'profile_menu_icons',
                    'type'     		=> 'checkbox',
                    'label'    		=> __('Enable menu icons in desktop view','ultimatemember'),
                    'conditional'		=> array( 'profile_menu', '=', 1 ),
                )
            ) );


            $all_post_types = get_post_types( array( 'public' => true ) );

            $all_taxonomies = get_taxonomies( array( 'public' => true ) );
            $exclude_taxonomies = array(
                'nav_menu',
                'link_category',
                'post_format',
                'um_user_tag',
                'um_hashtag',
            );

            foreach ( $all_taxonomies as $key => $taxonomy ) {
                if( in_array( $key , $exclude_taxonomies ) )
                    unset( $all_taxonomies[$key] );
            }

            $this->settings_structure = apply_filters( 'um_settings_structure', array(
                ''              => array(
                    'title'       => __( 'General', 'ultimatemember' ),
                    'sections'    => array(
                        ''          => array(
                            'title'     => __( 'Pages', 'ultimatemember' ),
                            'fields'    => $general_pages_fields
                        ),
                        'users'     => array(
                            'title'     => __( 'Users', 'ultimatemember' ),
                            'fields'    => array(
                                array(
                                    'id'       		=> 'default_role',
                                    'type'     		=> 'selectbox',
                                    'label'    		=> __( 'Default New User Role','ultimatemember' ),
                                    'description' 	=> __( 'Select the default role that will be assigned to user after registration If you did not specify custom role settings per form.','ultimatemember' ),
                                    'options' 		=> UM()->roles()->get_roles(),
                                    'placeholder' 	=> __('Choose user role...','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'permalink_base',
                                    'type'     		=> 'selectbox',
                                    'label'    		=> __( 'Profile Permalink Base','ultimatemember' ),
                                    'description' 	=> __( 'Here you can control the permalink structure of the user profile URL globally e.g. ' . trailingslashit( um_get_core_page('user') ) . '<strong>username</strong>/','ultimatemember' ),
                                    'options' 		=> array(
                                        'user_login' 		=> __('Username','ultimatemember'),
                                        'name' 				=> __('First and Last Name with \'.\'','ultimatemember'),
                                        'name_dash' 		=> __('First and Last Name with \'-\'','ultimatemember'),
                                        'name_plus' 		=> __('First and Last Name with \'+\'','ultimatemember'),
                                        'user_id' 			=> __('User ID','ultimatemember'),
                                    ),
                                    'placeholder' 	=> __('Select...','ultimatemember')
                                ),
                                array(
                                    'id'       		=> 'display_name',
                                    'type'     		=> 'selectbox',
                                    'label'    		=> __( 'User Display Name','ultimatemember' ),
                                    'description' 	=> __( 'This is the name that will be displayed for users on the front end of your site. Default setting uses first/last name as display name if it exists','ultimatemember' ),
                                    'options' 		=> array(
                                        'default'			=> __('Default WP Display Name','ultimatemember'),
                                        'nickname'			=> __('Nickname','ultimatemember'),
                                        'username' 			=> __('Username','ultimatemember'),
                                        'full_name' 		=> __('First name & last name','ultimatemember'),
                                        'sur_name' 			=> __('Last name & first name','ultimatemember'),
                                        'initial_name'		=> __('First name & first initial of last name','ultimatemember'),
                                        'initial_name_f'	=> __('First initial of first name & last name','ultimatemember'),
                                        'first_name'		=> __('First name only','ultimatemember'),
                                        'field' 			=> __('Custom field(s)','ultimatemember'),
                                    ),
                                    'placeholder' 	=> __('Select...')
                                ),
                                array(
                                    'id'       		=> 'display_name_field',
                                    'type'     		=> 'text',
                                    'label'   		=> __( 'Display Name Custom Field(s)','ultimatemember' ),
                                    'description' 	=> __('Specify the custom field meta key or custom fields seperated by comma that you want to use to display users name on the frontend of your site','ultimatemember'),
                                    'conditional'   => array( 'display_name', '=', 'field' ),
                                ),
                                array(
                                    'id'       		=> 'author_redirect',
                                    'type'     		=> 'checkbox',
                                    'label'   		=> __( 'Automatically redirect author page to their profile?','ultimatemember'),
                                    'description' 	=> __('If enabled, author pages will automatically redirect to the user\'s profile page','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'members_page',
                                    'type'     		=> 'checkbox',
                                    'label'   		=> __( 'Enable Members Directory','ultimatemember' ),
                                    'description' 	=> __('Control whether to enable or disable member directories on this site','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'use_gravatars',
                                    'type'     		=> 'checkbox',
                                    'label'   		=> __( 'Use Gravatars?','ultimatemember' ),
                                    'description' 	=> __('Do you want to use gravatars instead of the default plugin profile photo (If the user did not upload a custom profile photo / avatar)','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'use_um_gravatar_default_builtin_image',
                                    'type'     		=> 'selectbox',
                                    'label'    		=> __( 'Use Gravatar builtin image','ultimatemember' ),
                                    'description' 	=> __( 'Gravatar has a number of built in options which you can also use as defaults','ultimatemember' ),
                                    'options' 		=> array(
                                        'default'		=> __('Default','ultimatemember'),
                                        '404'			=> __('404 ( File Not Found response )','ultimatemember'),
                                        'mm'			=> __('Mystery Man','ultimatemember'),
                                        'identicon'		=> __('Identicon','ultimatemember'),
                                        'monsterid'		=> __('Monsterid','ultimatemember'),
                                        'wavatar'		=> __('Wavatar','ultimatemember'),
                                        'retro'			=> __('Retro','ultimatemember'),
                                        'blank'			=> __('Blank ( a transparent PNG image )','ultimatemember'),
                                    ),
                                    'conditional'		=> array( 'use_gravatars', '=', 1 ),
                                ),
                                array(
                                    'id'       		=> 'use_um_gravatar_default_image',
                                    'type'     		=> 'checkbox',
                                    'label'   		=> __( 'Use Default plugin avatar as Gravatar\'s Default avatar','ultimatemember' ),
                                    'description' 	=> __('Do you want to use the plugin default avatar instead of the gravatar default photo (If the user did not upload a custom profile photo / avatar)','ultimatemember'),
                                    'conditional'		=> array( 'use_um_gravatar_default_builtin_image', '=', 'default' ),
                                ),
                                array(
                                    'id'       		=> 'reset_require_strongpass',
                                    'type'     		=> 'checkbox',
                                    'label'   		=> __( 'Require a strong password? (when user resets password only)','ultimatemember' ),
                                    'description' 	=> __('Enable or disable a strong password rules on password reset and change procedure','ultimatemember'),
                                )
                            )
                        ),
                        'account'   => array(
                            'title'     => __( 'Account', 'ultimatemember' ),
                            'fields'    => array(
                                array(
                                    'id'       		=> 'account_tab_password',
                                    'type'     		=> 'checkbox',
                                    'label'   		=> __( 'Password Account Tab','ultimatemember' ),
                                    'description' 	=> 'Enable/disable the Password account tab in account page',
                                ),
                                array(
                                    'id'       		=> 'account_tab_privacy',
                                    'type'     		=> 'checkbox',
                                    'label'   		=> __( 'Privacy Account Tab','ultimatemember' ),
                                    'description' 	=> __('Enable/disable the Privacy account tab in account page','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'account_tab_notifications',
                                    'type'     		=> 'checkbox',
                                    'label'   		=> __( 'Notifications Account Tab','ultimatemember' ),
                                    'description' 	=> __('Enable/disable the Notifications account tab in account page','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'account_tab_delete',
                                    'type'     		=> 'checkbox',
                                    'label'   		=> __( 'Delete Account Tab','ultimatemember' ),
                                    'description' 	=> __('Enable/disable the Delete account tab in account page','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'delete_account_text',
                                    'type'    		=> 'textarea', // bug with wp 4.4? should be editor
                                    'label'    		=> __( 'Account Deletion Custom Text','ultimatemember' ),
                                    'description' 	=> __('This is custom text that will be displayed to users before they delete their accounts from your site','ultimatemember'),
                                    'args'     		=> array(
                                        'textarea_rows'    => 6
                                    ),
                                ),
                                array(
                                    'id'       		=> 'account_name',
                                    'type'     		=> 'checkbox',
                                    'label'   		=> __( 'Add a First & Last Name fields','ultimatemember' ),
                                    'description' 	=> __('Whether to enable these fields on the user account page by default or hide them.','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'account_name_disable',
                                    'type'     		=> 'checkbox',
                                    'label'   		=> __( 'Disable First & Last Name fields','ultimatemember' ),
                                    'description' 	=> __('Whether to allow users changing their first and last name in account page.','ultimatemember'),
                                    'conditional'		=> array( 'account_name', '=', '1' ),
                                ),
                                array(
                                    'id'       		=> 'account_name_require',
                                    'type'     		=> 'checkbox',
                                    'label'   		=> __( 'Require First & Last Name','ultimatemember' ),
                                    'description' 	=> __('Require first and last name?','ultimatemember'),
                                    'conditional'		=> array( 'account_name', '=', '1' ),
                                ),
                                array(
                                    'id'       		=> 'account_email',
                                    'type'     		=> 'checkbox',
                                    'label'   		=> __( 'Allow users to change e-mail','ultimatemember' ),
                                    'description' 	=> __('Whether to allow users changing their email in account page.','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'account_hide_in_directory',
                                    'type'     		=> 'checkbox',
                                    'label'   		=> __( 'Allow users to hide their profiles from directory','ultimatemember' ),
                                    'description' 	=> __('Whether to allow users changing their profile visibility from member directory in account page.','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'account_require_strongpass',
                                    'type'     		=> 'checkbox',
                                    'label'   		=> __( 'Require a strong password?','ultimatemember' ),
                                    'description' 	=> __('Enable or disable a strong password rules on account page / change password tab','ultimatemember'),
                                )
                            )
                        ),
                        'uploads'   => array(
                            'title'     => __( 'Uploads', 'ultimatemember' ),
                            'fields'    => array(
                                array(
                                    'id'       		=> 'photo_thumb_sizes',
                                    'type'     		=> 'multi-text',
                                    'label'    		=> __( 'Profile Photo Thumbnail Sizes (px)','ultimatemember' ),
                                    'description' 	=> __( 'Here you can define which thumbnail sizes will be created for each profile photo upload.','ultimatemember' ),
                                    'validate' 		=> 'numeric',
                                    'add_text'		=> __('Add New Size','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'cover_thumb_sizes',
                                    'type'     		=> 'multi-text',
                                    'label'    		=> __( 'Cover Photo Thumbnail Sizes (px)','ultimatemember' ),
                                    'description' 	=> __( 'Here you can define which thumbnail sizes will be created for each cover photo upload.','ultimatemember' ),
                                    'validate' 		=> 'numeric',
                                    'add_text'		=> __('Add New Size','ultimatemember'),
                                )
                            )
                        )
                    )
                ),
                'access'        => array(
                    'title'       => __( 'Access', 'ultimatemember' ),
                    'sections'    => array(
                        ''      => array(
                            'title'     => __( 'Restriction Content', 'ultimatemember' ),
                            'fields'    => array(
                                array(
                                    'id'       		=> 'accessible',
                                    'type'     		=> 'selectbox',
                                    'label'   		=> __( 'Global Site Access','ultimatemember' ),
                                    'description' 	=> __('Globally control the access of your site, you can have seperate restrict options per post/page by editing the desired item.','ultimatemember'),
                                    'options' 		=> array(
                                        0 		=> 'Site accessible to Everyone',
                                        2 		=> 'Site accessible to Logged In Users'
                                    )
                                ),
                                array(
                                    'id'       		=> 'access_redirect',
                                    'type'     		=> 'text',
                                    'label'   		=> __( 'Custom Redirect URL','ultimatemember' ),
                                    'description' 	=> __('A logged out user will be redirected to this url If he is not permitted to access the site','ultimatemember'),
                                    'conditional'		=> array( 'accessible', '=', 2 ),
                                ),
                                array(
                                    'id'       		=> 'access_exclude_uris',
                                    'type'     		=> 'multi-text',
                                    'label'    		=> __( 'Exclude the following URLs','ultimatemember' ),
                                    'description' 	=> __( 'Here you can exclude URLs beside the redirect URI to be accessible to everyone','ultimatemember' ),
                                    'add_text'		=> __('Add New URL','ultimatemember'),
                                    'conditional'		=> array( 'accessible', '=', 2 ),
                                ),
                                array(
                                    'id'       		=> 'home_page_accessible',
                                    'type'     		=> 'checkbox',
                                    'label'   		=> __( 'Allow Homepage to be accessible','ultimatemember' ),
                                    'conditional'		=> array( 'accessible', '=', 2 ),
                                ),
                                array(
                                    'id'       		=> 'category_page_accessible',
                                    'type'     		=> 'checkbox',
                                    'label'   		=> __( 'Allow Category pages to be accessible','ultimatemember' ),
                                    'conditional'		=> array( 'accessible', '=', 2 ),
                                ),
                                array(
                                    'id'       		=> 'restricted_access_message',
                                    'type'     		=> 'wp_editor',
                                    'label'   		=> __( 'Restricted Access Message','ultimatemember' ),
                                    'description'   => __( 'This is the message shown to users that do not have permission to view the content','ultimatemember' ),
                                ),
                                array(
                                    'id'       		=> 'restricted_access_post_metabox',
                                    'type'     		=> 'multi-checkbox',
                                    'label'   		=> __( 'Restricted Access to Posts','ultimatemember' ),
                                    'description'   => __( 'Restriction content of the current Posts','ultimatemember' ),
                                    'options'       => $all_post_types,
                                    'columns'       => 3
                                ),
                                array(
                                    'id'       		=> 'restricted_access_taxonomy_metabox',
                                    'type'     		=> 'multi-checkbox',
                                    'label'   		=> __( 'Restricted Access to Taxonomies','ultimatemember' ),
                                    'description'   => __( 'Restriction content of the current Taxonomies','ultimatemember' ),
                                    'options'       => $all_taxonomies,
                                    'columns'       => 3
                                ),
                            )
                        ),
                        'other' => array(
                            'title'     => __( 'Other', 'ultimatemember' ),
                            'fields'      => array(
                                array(
                                    'id'       		=> 'enable_reset_password_limit',
                                    'type'     		=> 'checkbox',
                                    'label'   		=> __( 'Enable the Reset Password Limit?','ultimatemember' ),
                                ),
                                array(
                                    'id'       		=> 'reset_password_limit_number',
                                    'type'     		=> 'text',
                                    'label'   		=> __( 'Reset Password Limit','ultimatemember' ),
                                    'description' 	=> __('Set the maximum reset password limit. If reached the maximum limit, user will be locked from using this.','ultimatemember'),
                                    'validate'		=> 'numeric',
                                    'conditional'   => array('enable_reset_password_limit','=',1),
                                    'size'          => 'um-small-field'
                                ),
                                array(
                                    'id'       		=> 'blocked_emails',
                                    'type'     		=> 'textarea',
                                    'label'    		=> __( 'Blocked Email Addresses','ultimatemember' ),
                                    'description'	=> __('This will block the specified e-mail addresses from being able to sign up or sign in to your site. To block an entire domain, use something like *@domain.com','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'blocked_words',
                                    'type'     		=> 'textarea',
                                    'label'    		=> __( 'Blacklist Words','ultimatemember' ),
                                    'description'	=> __('This option lets you specify blacklist of words to prevent anyone from signing up with such a word as their username','ultimatemember'),
                                )
                            )
                        ),
                    )
                ),
                'email'         => array(
                    'title'       => __( 'Email', 'ultimatemember' ),
                    'fields'      => array(
                        array(
                            'id'            => 'admin_email',
                            'type'          => 'text',
                            'label'         => __( 'Admin E-mail Address', 'ultimatemember' ),
                            'description'   => __( 'e.g. admin@companyname.com','ultimatemember' ),
                        ),
                        array(
                            'id'            => 'mail_from',
                            'type'          => 'text',
                            'label'         => __( 'Mail appears from','ultimatemember' ),
                            'description' 	=> __( 'e.g. Site Name','ultimatemember' ),
                        ),
                        array(
                            'id'            => 'mail_from_addr',
                            'type'          => 'text',
                            'label'         => __( 'Mail appears from address','ultimatemember' ),
                            'description'   => __( 'e.g. admin@companyname.com','ultimatemember' ),
                        ),
                        array(
                            'id'            => 'email_html',
                            'type'          => 'checkbox',
                            'label'         => __( 'Use HTML for E-mails?','ultimatemember' ),
                            'description'   => __('If you enable HTML for e-mails, you can customize the HTML e-mail templates found in <strong>templates/email</strong> folder.','ultimatemember'),
                        )
                    )
                ),
                'appearance'    => array(
                    'title'       => __( 'Appearance', 'ultimatemember' ),
                    'sections'    => array(
                        ''                  => array(
                            'title'     => __( 'Profile', 'ultimatemember' ),
                            'fields'    => array(
                                array(
                                    'id'       		=> 'profile_template',
                                    'type'     		=> 'selectbox',
                                    'label'    		=> __( 'Profile Default Template','ultimatemember' ),
                                    'description' 	=> __( 'This will be the default template to output profile','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('profile_template'),
                                    'options' 		=> UM()->shortcodes()->get_templates( 'profile' ),
                                ),
                                array(
                                    'id'      		=> 'profile_max_width',
                                    'type'     		=> 'text',
                                    'label'    		=> __( 'Profile Maximum Width','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('profile_max_width'),
                                    'description' 	=> 'The maximum width this shortcode can take from the page width',
                                ),

                                array(
                                    'id'      		=> 'profile_area_max_width',
                                    'type'     		=> 'text',
                                    'label'    		=> __( 'Profile Area Maximum Width','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('profile_area_max_width'),
                                    'description' 	=> __('The maximum width of the profile area inside profile (below profile header)','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'profile_icons',
                                    'type'     		=> 'selectbox',
                                    'label'    		=> __( 'Profile Field Icons' ),
                                    'description' 	=> __( 'This is applicable for edit mode only','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('profile_icons'),
                                    'options' 		=> array(
                                        'field' 			=> __('Show inside text field','ultimatemember'),
                                        'label' 			=> __('Show with label','ultimatemember'),
                                        'off' 				=> __('Turn off','ultimatemember'),
                                    ),
                                ),
                                array(
                                    'id'      		=> 'profile_primary_btn_word',
                                    'type'     		=> 'text',
                                    'label'    		=> __( 'Profile Primary Button Text','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('profile_primary_btn_word'),
                                    'description' 	=> __('The text that is used for updating profile button','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'profile_secondary_btn',
                                    'type'     		=> 'checkbox',
                                    'label'    		=> __( 'Profile Secondary Button','ultimatemember' ),
                                    'default' 		=> um_get_metadefault('profile_secondary_btn'),
                                    'description' 	=> __('Switch on/off the secondary button display in the form','ultimatemember'),
                                ),
                                array(
                                    'id'      		=> 'profile_secondary_btn_word',
                                    'type'     		=> 'text',
                                    'label'    		=> __( 'Profile Secondary Button Text','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('profile_secondary_btn_word'),
                                    'description' 	=> __('The text that is used for cancelling update profile button','ultimatemember'),
                                    'conditional'		=> array( 'profile_secondary_btn', '=', 1 ),
                                ),
                                array(
                                    'id'      			=> 'default_avatar',
                                    'type'     			=> 'media',
                                    'label'    			=> __('Default Profile Photo', 'ultimatemember'),
                                    'description'     	=> __('You can change the default profile picture globally here. Please make sure that the photo is 300x300px.', 'ultimatemember'),
                                    'upload_frame_title'=> __('Select Default Profile Photo', 'ultimatemember'),
                                    'default'  			=> array(
                                        'url'		=> um_url . 'assets/img/default_avatar.jpg',
                                    ),
                                ),
                                array(
                                    'id'      			=> 'default_cover',
                                    'type'     			=> 'media',
                                    'url'				=> true,
                                    'preview'			=> false,
                                    'label'    			=> __('Default Cover Photo', 'ultimatemember'),
                                    'description'     	=> __('You can change the default cover photo globally here. Please make sure that the default cover is large enough and respects the ratio you are using for cover photos.', 'ultimatemember'),
                                    'upload_frame_title'=> __('Select Default Cover Photo', 'ultimatemember'),
                                ),
                                array(
                                    'id'      		=> 'profile_photosize',
                                    'type'     		=> 'text',
                                    'label'    		=> __( 'Profile Photo Size','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('profile_photosize'),
                                    'description' 	=> __('The global default of profile photo size. This can be overridden by individual form settings','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'profile_cover_enabled',
                                    'type'     		=> 'checkbox',
                                    'label'    		=> __( 'Profile Cover Photos','ultimatemember' ),
                                    'default' 		=> 1,
                                    'description' 	=> __('Switch on/off the profile cover photos','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'profile_cover_ratio',
                                    'type'     		=> 'selectbox',
                                    'label'    		=> __( 'Profile Cover Ratio','ultimatemember' ),
                                    'description' 	=> __( 'Choose global ratio for cover photos of profiles','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('profile_cover_ratio'),
                                    'options' 		=> array(
                                        '1.6:1' 			=> '1.6:1',
                                        '2.7:1' 			=> '2.7:1',
                                        '2.2:1' 			=> '2.2:1',
                                        '3.2:1' 			=> '3.2:1',
                                    ),
                                    'conditional'		=> array( 'profile_cover_enabled', '=', 1 ),
                                ),
                                array(
                                    'id'       		=> 'profile_show_metaicon',
                                    'type'     		=> 'checkbox',
                                    'label'    		=> __( 'Profile Header Meta Text Icon','ultimatemember' ),
                                    'default' 		=> 0,
                                    'description' 	=> __('Display field icons for related user meta fields in header or not','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'profile_show_name',
                                    'type'     		=> 'checkbox',
                                    'label'    		=> __( 'Show display name in profile header','ultimatemember' ),
                                    'default' 		=> um_get_metadefault('profile_show_name'),
                                    'description' 	=> __('Switch on/off the user name on profile header','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'profile_show_social_links',
                                    'type'     		=> 'checkbox',
                                    'label'    		=> __( 'Show social links in profile header','ultimatemember' ),
                                    'default' 		=> um_get_metadefault('profile_show_social_links'),
                                    'description' 	=> __('Switch on/off the social links on profile header','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'profile_show_bio',
                                    'type'     		=> 'checkbox',
                                    'label'    		=> __( 'Show user description in header','ultimatemember' ),
                                    'default' 		=> um_get_metadefault('profile_show_bio'),
                                    'description' 	=> __('Switch on/off the user description on profile header','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'profile_show_html_bio',
                                    'type'     		=> 'checkbox',
                                    'label'    		=> __( 'Enable html support for user description','ultimatemember' ),
                                    'default' 		=> um_get_metadefault('profile_show_html_bio'),
                                    'description' 	=> __('Switch on/off to enable/disable support for html tags on user description.','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'profile_bio_maxchars',
                                    'type'     		=> 'text',
                                    'label'    		=> __( 'User description maximum chars','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('profile_bio_maxchars'),
                                    'description' 	=> __('Maximum number of characters to allow in user description field in header.','ultimatemember'),
                                    'conditional'		=> array( 'profile_show_bio', '=', 1 ),
                                ),
                                array(
                                    'id'       		=> 'profile_header_menu',
                                    'type'     		=> 'selectbox',
                                    'label'    		=> __( 'Profile Header Menu Position','ultimatemember' ),
                                    'default' 		=> um_get_metadefault('profile_header_menu'),
                                    'description' 	=> __('For incompatible themes, please make the menu open from left instead of bottom by default.','ultimatemember'),
                                    'options' 		=> array(
                                        'bc' 		=> 'Bottom of Icon',
                                        'lc' 		=> 'Left of Icon',
                                    ),
                                ),
                                array(
                                    'id'       		=> 'profile_empty_text',
                                    'type'     		=> 'checkbox',
                                    'label'    		=> __( 'Show a custom message if profile is empty','ultimatemember' ),
                                    'default' 		=> um_get_metadefault('profile_empty_text'),
                                    'description' 	=> __('Switch on/off the custom message that appears when the profile is empty','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'profile_empty_text_emo',
                                    'type'     		=> 'checkbox',
                                    'label'    		=> __( 'Show the emoticon','ultimatemember' ),
                                    'default' 		=> um_get_metadefault('profile_empty_text_emo'),
                                    'description' 	=> __('Switch on/off the emoticon (sad face) that appears above the message','ultimatemember'),
                                    'conditional'		=> array( 'profile_empty_text', '=', 1 ),
                                )
                            )
                        ),
                        'profile_menu'      => array(
                            'title'     => __( 'Profile Menu', 'ultimatemember' ),
                            'fields'    => $appearances_profile_menu_fields
                        ),
                        'registration_form' => array(
                            'title'     => __( 'Registration Form', 'ultimatemember' ),
                            'fields'    => array(
                                array(
                                    'id'       		=> 'register_template',
                                    'type'     		=> 'selectbox',
                                    'label'    		=> __( 'Registration Default Template','ultimatemember' ),
                                    'description' 	=> __( 'This will be the default template to output registration' ),
                                    'default'  		=> um_get_metadefault('register_template'),
                                    'options' 		=> UM()->shortcodes()->get_templates( 'register' ),
                                ),
                                array(
                                    'id'      		=> 'register_max_width',
                                    'type'     		=> 'text',
                                    'label'    		=> __( 'Registration Maximum Width','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('register_max_width'),
                                    'description' 	=> __('The maximum width this shortcode can take from the page width','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'register_align',
                                    'type'     		=> 'selectbox',
                                    'label'    		=> __( 'Registration Shortcode Alignment','ultimatemember' ),
                                    'description' 	=> __( 'The shortcode is centered by default unless you specify otherwise here','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('register_align'),
                                    'options' 		=> array(
                                        'center' 			=> __('Centered'),
                                        'left' 				=> __('Left aligned'),
                                        'right' 			=> __('Right aligned'),
                                    ),
                                ),
                                array(
                                    'id'       		=> 'register_icons',
                                    'type'     		=> 'selectbox',
                                    'label'    		=> __( 'Registration Field Icons','ultimatemember' ),
                                    'description' 	=> __( 'This controls the display of field icons in the registration form','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('register_icons'),
                                    'options' 		=> array(
                                        'field' 			=> __('Show inside text field'),
                                        'label' 			=> __('Show with label'),
                                        'off' 				=> __('Turn off'),
                                    ),
                                ),
                                array(
                                    'id'      		=> 'register_primary_btn_word',
                                    'type'     		=> 'text',
                                    'label'    		=> __( 'Registration Primary Button Text','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('register_primary_btn_word'),
                                    'description' 	   		=> __('The text that is used for primary button text','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'register_secondary_btn',
                                    'type'     		=> 'checkbox',
                                    'label'    		=> __( 'Registration Secondary Button','ultimatemember' ),
                                    'default' 		=> 1,
                                    'description' 	=> __('Switch on/off the secondary button display in the form','ultimatemember'),
                                ),
                                array(
                                    'id'      		=> 'register_secondary_btn_word',
                                    'type'     		=> 'text',
                                    'label'    		=> __( 'Registration Secondary Button Text','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('register_secondary_btn_word'),
                                    'description' 	=> __('The text that is used for the secondary button text','ultimatemember'),
                                    'conditional'		=> array( 'register_secondary_btn', '=', 1 ),
                                ),
                                array(
                                    'id'      		=> 'register_secondary_btn_url',
                                    'type'     		=> 'text',
                                    'label'    		=> __( 'Registration Secondary Button URL','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('register_secondary_btn_url'),
                                    'description' 	=> __('You can replace default link for this button by entering custom URL','ultimatemember'),
                                    'conditional'		=> array( 'register_secondary_btn', '=', 1 ),
                                ),
                                array(
                                    'id'       		=> 'register_role',
                                    'type'     		=> 'selectbox',
                                    'label'    		=> __( 'Registration Default Role','ultimatemember' ),
                                    'description' 	=> __( 'This will be the default role assigned to users registering thru registration form','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('register_role'),
                                    'options' 		=> UM()->roles()->get_roles( $add_default = 'Default' ),
                                )
                            )
                        ),
                        'login_form'        => array(
                            'title'     => __( 'Login Form', 'ultimatemember' ),
                            'fields'    => array(
                                array(
                                    'id'       		=> 'login_template',
                                    'type'     		=> 'selectbox',
                                    'label'    		=> __( 'Login Default Template','ultimatemember' ),
                                    'description' 	=> __( 'This will be the default template to output login','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('login_template'),
                                    'options' 		=> UM()->shortcodes()->get_templates( 'login' ),
                                ),
                                array(
                                    'id'      		=> 'login_max_width',
                                    'type'     		=> 'text',
                                    'label'    		=> __( 'Login Maximum Width','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('login_max_width'),
                                    'description' 	=> __('The maximum width this shortcode can take from the page width','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'login_align',
                                    'type'     		=> 'selectbox',
                                    'label'    		=> __( 'Login Shortcode Alignment','ultimatemember' ),
                                    'description' 	=> __( 'The shortcode is centered by default unless you specify otherwise here','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('login_align'),
                                    'options' 		=> array(
                                        'center' 			=> __('Centered','ultimatemember'),
                                        'left' 				=> __('Left aligned','ultimatemember'),
                                        'right' 			=> __('Right aligned','ultimatemember'),
                                    ),
                                ),
                                array(
                                    'id'       		=> 'login_icons',
                                    'type'     		=> 'selectbox',
                                    'label'    		=> __( 'Login Field Icons','ultimatemember' ),
                                    'description' 	=> __( 'This controls the display of field icons in the login form','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('login_icons'),
                                    'options' 		=> array(
                                        'field' 			=> __('Show inside text field','ultimatemember'),
                                        'label' 			=> __('Show with label','ultimatemember'),
                                        'off' 				=> __('Turn off','ultimatemember'),
                                    ),
                                ),
                                array(
                                    'id'      		=> 'login_primary_btn_word',
                                    'type'     		=> 'text',
                                    'label'    		=> __( 'Login Primary Button Text','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('login_primary_btn_word'),
                                    'description' 	=> __('The text that is used for primary button text','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'login_secondary_btn',
                                    'type'     		=> 'checkbox',
                                    'label'    		=> __( 'Login Secondary Button','ultimatemember' ),
                                    'default' 		=> 1,
                                    'description' 	=> __('Switch on/off the secondary button display in the form','ultimatemember'),
                                ),
                                array(
                                    'id'      		=> 'login_secondary_btn_word',
                                    'type'     		=> 'text',
                                    'label'    		=> __( 'Login Secondary Button Text','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('login_secondary_btn_word'),
                                    'description' 	=> __('The text that is used for the secondary button text','ultimatemember'),
                                    'conditional'		=> array( 'login_secondary_btn', '=', 1 ),
                                ),
                                array(
                                    'id'      		=> 'login_secondary_btn_url',
                                    'type'     		=> 'text',
                                    'label'    		=> __( 'Login Secondary Button URL','ultimatemember' ),
                                    'default'  		=> um_get_metadefault('login_secondary_btn_url'),
                                    'description' 	=> __('You can replace default link for this button by entering custom URL','ultimatemember'),
                                    'conditional'		=> array( 'login_secondary_btn', '=', 1 ),
                                ),
                                array(
                                    'id'       		=> 'login_forgot_pass_link',
                                    'type'     		=> 'checkbox',
                                    'label'    		=> __( 'Login Forgot Password Link','ultimatemember' ),
                                    'default' 		=> 1,
                                    'description' 	=> __('Switch on/off the forgot password link in login form','ultimatemember'),
                                ),
                                array(
                                    'id'       		=> 'login_show_rememberme',
                                    'type'     		=> 'checkbox',
                                    'label'    		=> __( 'Show "Remember Me"','ultimatemember' ),
                                    'default' 		=> 1,
                                    'description' 	=> __('Allow users to choose If they want to stay signed in even after closing the browser. If you do not show this option, the default will be to not remember login session.','ultimatemember'),
                                )
                            )
                        )
                    )
                ),
                'extensions'    => array(
                    'title'       => __( 'Extensions', 'ultimatemember' )
                ),
                'licenses'      => array(
                    'title'       => __( 'Licenses', 'ultimatemember' ),
                    'fields'      => array(
                        array(
                            'id'       		=> 'licenses_settings',
                            'type'     		=> 'hidden',
                            'default'       => true,
                            'is_option'     => false
                        )
                    )
                ),
                'misc'          => array(
                    'title'       => __( 'Misc', 'ultimatemember' ),
                    'fields'      => array(
                        array(
                            'id'       		=> 'form_asterisk',
                            'type'     		=> 'checkbox',
                            'label'    		=> __( 'Show an asterisk for required fields','ultimatemember' ),
                        ),
                        array(
                            'id'      		=> 'profile_title',
                            'type'     		=> 'text',
                            'label'    		=> __('User Profile Title','ultimatemember'),
                            'description' 	=> __('This is the title that is displayed on a specific user profile','ultimatemember'),
                        ),
                        array(
                            'id'       		=> 'profile_desc',
                            'type'     		=> 'textarea',
                            'label'    		=> __( 'User Profile Dynamic Meta Description','ultimatemember' ),
                            'description'	=> __('This will be used in the meta description that is available for search-engines.','ultimatemember')
                        ),
                        array(
                            'id'       		=> 'allow_tracking',
                            'type'     		=> 'checkbox',
                            'label'   		=> __( 'Allow Tracking','ultimatemember' ),
                        ),
                        array(
                            'id'       		=> 'uninstall_on_delete',
                            'type'     		=> 'checkbox',
                            'label'   		=> __( 'Remove Data on Uninstall?', 'ultimatemember' ),
                            'description'	=> __( 'Check this box if you would like Ultimate Member to completely remove all of its data when the plugin/extensions are deleted.', 'ultimatemember' )
                        )
                    )
                )
            ) );

        }


        function sorting_licenses_options( $settings ) {
            //sorting  licenses
            if ( empty( $settings['licenses']['fields'] ) )
                return $settings;
            $licenses = $settings['licenses']['fields'];
            @uasort( $licenses, create_function( '$a,$b', 'return strnatcasecmp($a["label"],$b["label"]);' ) );
            $settings['licenses']['fields'] = $licenses;


            //sorting extensions
            if ( empty( $settings['extensions']['sections'] ) )
                return $settings;

            $extensions = $settings['extensions']['sections'];
            @uasort( $extensions, create_function( '$a,$b', 'return strnatcasecmp($a["title"],$b["title"]);' ) );

            $keys = array_keys( $extensions );
            if ( $keys[0] != "" ) {
                $new_key = strtolower( str_replace( " ", "_", $extensions[""]['title'] ) );
                $temp = $extensions[""];
                $extensions[$new_key] = $temp;
                $extensions[""] = $extensions[$keys[0]];
                unset( $extensions[$keys[0]] );
                @uasort( $extensions, create_function( '$a,$b', 'return strnatcasecmp($a["title"],$b["title"]);' ) );
            }

            $settings['extensions']['sections'] = $extensions;

            return $settings;
        }


        function get_section_fields( $tab, $section ) {

            if ( empty( $this->settings_structure[$tab] ) )
                return array();

            if ( ! empty( $this->settings_structure[$tab]['sections'][$section]['fields'] ) ) {
                return $this->settings_structure[$tab]['sections'][$section]['fields'];
            } elseif ( ! empty( $this->settings_structure[$tab]['fields'] ) ) {
                return $this->settings_structure[$tab]['fields'];
            }

            return array();
        }


        /***
         ***	@setup admin menu
         ***/
        function primary_admin_menu() {
            add_submenu_page( 'ultimatemember', __( 'Settings', 'ultimatemember' ), __( 'Settings', 'ultimatemember' ), 'manage_options', 'um_options', array( &$this, 'settings_page' ) );
        }


        function settings_page() {
            $current_tab = empty( $_GET['tab'] ) ? '' : urldecode( $_GET['tab'] );
            $current_subtab = empty( $_GET['section'] ) ? '' : urldecode( $_GET['section'] );

            $settings_struct = $this->settings_structure[$current_tab];

            //remove not option hidden fields
            if ( ! empty( $settings_struct['fields'] ) ) {
                foreach ( $settings_struct['fields'] as $field_key=>$field_options ) {

                    if ( isset( $field_options['is_option'] ) && $field_options['is_option'] === false )
                        unset( $settings_struct['fields'][$field_key] );

                }
            }

            if ( empty( $settings_struct['fields'] ) && empty( $settings_struct['sections'] ) )
                um_js_redirect( add_query_arg( array( 'page' => 'um_options' ), admin_url( 'admin.php' ) ) );

            if ( ! empty( $settings_struct['sections'] ) ) {
                if ( empty( $settings_struct['sections'][$current_subtab] ) )
                    um_js_redirect( add_query_arg( array( 'page' => 'um_options', 'tab' => $current_tab ), admin_url( 'admin.php' ) ) );
            }

            echo $this->generate_tabs_menu() . $this->generate_subtabs_menu( $current_tab );

            do_action( "um_settings_page_before_" . $current_tab . "_" . $current_subtab . "_content" );

            if ( 'licenses' == $current_tab ) {
                do_action( "um_settings_page_" . $current_tab . "_" . $current_subtab . "_before_section" );

                $section_fields = $this->get_section_fields( $current_tab, $current_subtab );
                echo apply_filters( 'um_settings_section_' . $current_tab . '_' . $current_subtab . '_content', $this->render_settings_section( $section_fields ), $section_fields );

            } else { ?>

                <form method="post" action="" name="um-settings-form" id="um-settings-form">
                    <input type="hidden" value="save" name="um-settings-action" />

                    <?php do_action( "um_settings_page_" . $current_tab . "_" . $current_subtab . "_before_section" );

                    $section_fields = $this->get_section_fields( $current_tab, $current_subtab );
                    echo apply_filters( 'um_settings_section_' . $current_tab . '_' . $current_subtab . '_content', $this->render_settings_section( $section_fields ), $section_fields );
                    ?>

                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e( 'Save Changes', 'ultimatemember' ) ?>" />
                    </p>
                </form>

            <?php }
        }



        /**
         * Generate pages tabs
         *
         * @param string $page
         * @return string
         */
        function generate_tabs_menu( $page = 'settings' ) {

            $tabs = '<h2 class="nav-tab-wrapper um-nav-tab-wrapper">';

            switch( $page ) {
                case 'settings':
                    $menu_tabs = array();
                    foreach ( $this->settings_structure as $slug => $tab ) {
                        if ( ! empty( $tab['fields'] ) ) {
                            foreach ( $tab['fields'] as $field_key=>$field_options ) {
                                if ( isset( $field_options['is_option'] ) && $field_options['is_option'] === false ) {
                                    unset( $tab['fields'][$field_key] );
                                }
                            }
                        }

                        if ( ! empty( $tab['fields'] ) || ! empty( $tab['sections'] ) )
                            $menu_tabs[$slug] = $tab['title'];
                    }

                    $current_tab = empty( $_GET['tab'] ) ? '' : urldecode( $_GET['tab'] );
                    foreach ( $menu_tabs as $name=>$label ) {
                        $active = ( $current_tab == $name ) ? 'nav-tab-active' : '';
                        $tabs .= '<a href="' . admin_url( 'admin.php?page=um_options' . ( empty( $name ) ? '' : '&tab=' . $name ) ) . '" class="nav-tab ' . $active . '">' .
                            $label .
                            '</a>';
                    }

                    break;
                default:
                    $tabs = apply_filters( 'um_generate_tabs_menu_' . $page, $tabs );
                    break;
            }

            return $tabs . '</h2>';
        }



        function generate_subtabs_menu( $tab = '' ) {
            if ( empty( $this->settings_structure[$tab]['sections'] ) )
                return '';

            $menu_subtabs = array();
            foreach ( $this->settings_structure[$tab]['sections'] as $slug => $subtab ) {
                $menu_subtabs[$slug] = $subtab['title'];
            }

            $subtabs = '<div><ul class="subsubsub">';

            $current_tab = empty( $_GET['tab'] ) ? '' : urldecode( $_GET['tab'] );
            $current_subtab = empty( $_GET['section'] ) ? '' : urldecode( $_GET['section'] );
            foreach ( $menu_subtabs as $name => $label ) {
                $active = ( $current_subtab == $name ) ? 'current' : '';
                $subtabs .= '<a href="' . admin_url( 'admin.php?page=um_options' . ( empty( $current_tab ) ? '' : '&tab=' . $current_tab ) . ( empty( $name ) ? '' : '&section=' . $name ) ) . '" class="' . $active . '">'
                    . $label .
                    '</a> | ';
            }

            return substr( $subtabs, 0, -3 ) . '</ul></div>';
        }


        /**
         * Handler for settings forms
         * when "Save Settings" button click
         *
         */
        function save_settings_handler() {
            if ( isset( $_POST['um-settings-action'] ) && 'save' == $_POST['um-settings-action'] && ! empty( $_POST['um_options'] ) ) {
                do_action( "um_settings_before_save" );

                foreach ( $_POST['um_options'] as $key=>$value ) {
                    um_update_option( $key, $value );
                }

                do_action( "um_settings_save" );
            }
        }


        function on_settings_save() {
            if ( ! empty( $_POST['um_options'] ) ) {
                if ( ! empty( $_POST['pages_settings'] ) ) {
                    $post_ids = new \WP_Query( array(
                        'post_type' => 'page',
                        'meta_query' => array(
                            array(
                                'key'       => '_um_core',
                                'compare'   => 'EXISTS'
                            )
                        ),
                        'posts_per_page' => -1,
                        'fields'        => 'ids'
                    ) );

                    $post_ids = $post_ids->get_posts();

                    if ( ! empty( $post_ids ) ) {
                        foreach ( $post_ids as $post_id ) {
                            delete_post_meta( $post_id, '_um_core' );
                        }
                    }

                    foreach ( $_POST['um_options'] as $option_slug => $post_id ) {
                        $slug = str_replace( 'core_', '', $option_slug );
                        update_post_meta( $post_id, '_um_core', $slug );
                    }
                }
            }
        }


        function before_licenses_save() {
            if ( empty( $_POST['um_options'] ) || empty( $_POST['licenses_settings'] ) )
                return;

            foreach ( $_POST['um_options'] as $key => $value ) {
                $this->previous_licenses[$key] = um_get_option( $key );
            }
        }


        function licenses_save() {
            if ( empty( $_POST['um_options'] ) || empty( $_POST['licenses_settings'] ) )
                return;

            foreach ( $_POST['um_options'] as $key => $value ) {
                $edd_action = '';
                $license_key = '';
                if ( empty( $this->previous_licenses[$key] ) && ! empty( $value ) ) {
                    $edd_action = 'activate_license';
                    $license_key = $value;
                } elseif ( ! empty( $this->previous_licenses[$key] ) && empty( $value ) ) {
                    $edd_action = 'deactivate_license';
                    $license_key = $this->previous_licenses[$key];
                } elseif ( ! empty( $this->previous_licenses[$key] ) && ! empty( $value ) ) {
                    $edd_action = 'check_license';
                    $license_key = $value;
                }

                if ( empty( $edd_action ) )
                    continue;

                $item_name = false;
                $version = false;
                $author = false;
                foreach ( $this->settings_structure['licenses']['fields'] as $field_data ) {
                    if ( $field_data['id'] == $key ) {
                        $item_name = ! empty( $field_data['item_name'] ) ? $field_data['item_name'] : false;
                        $version = ! empty( $field_data['version'] ) ? $field_data['version'] : false;
                        $author = ! empty( $field_data['author'] ) ? $field_data['author'] : false;
                    }
                }

                $api_params = array(
                    'edd_action' => $edd_action,
                    'license'    => $license_key,
                    'item_name'  => $item_name,
                    'version'    => $version,
                    'author'     => $author,
                    'url'        => home_url(),
                );

                $request = wp_remote_post(
                    'https://ultimatemember.com/',
                    array(
                        'timeout'   => 15,
                        'sslverify' => false,
                        'body'      => $api_params
                    )
                );

                if ( ! is_wp_error( $request ) )
                    $request = json_decode( wp_remote_retrieve_body( $request ) );

                $request = ( $request ) ? maybe_unserialize( $request ) : false;

                if ( $edd_action == 'activate_license' || $edd_action == 'check_license' )
                    update_option( "{$key}_edd_answer", $request );
                else
                    delete_option( "{$key}_edd_answer" );

            }
        }


        function check_wrong_licenses() {
            $invalid_license = false;

            $section_fields = $this->settings_structure['licenses']['fields'];
            foreach ( $section_fields as $field_data ) {
                if ( isset( $field_data['is_option'] ) && $field_data['is_option'] === false )
                    continue;

                $license = get_option( "{$field_data['id']}_edd_answer" );

                if ( ( is_object( $license ) && 'valid' == $license->license ) || 'valid' == $license )
                    continue;

                $invalid_license = true;
                break;
            }

            if ( $invalid_license ) { ?>

                <div class="error">
                    <p>
                        <?php printf( __( 'You have invalid or expired license keys for %s. Please go to the <a href="%s">Licenses page</a> to correct this issue.', 'ultimatemember' ), ultimatemember_plugin_name, add_query_arg( array('page'=>'um_options', 'tab' => 'licenses'), admin_url( 'admin.php' ) ) ) ?>
                    </p>
                </div>

            <?php }
        }


        function settings_before_email_tab() {
            $email_key = empty( $_GET['email'] ) ? '' : urldecode( $_GET['email'] );
            $emails = UM()->config()->email_notifications;

            if ( empty( $email_key ) || empty( $emails[$email_key] ) )
                include_once um_path . 'includes/admin/core/list-tables/emails-list-table.php';
        }


        function settings_email_tab( $section ) {
            $email_key = empty( $_GET['email'] ) ? '' : urldecode( $_GET['email'] );
            $emails = UM()->config()->email_notifications;

            if ( empty( $email_key ) || empty( $emails[$email_key] ) )
                return $section;

            $section_fields = array(
                array(
                    'id'            => $email_key . '_on',
                    'type'          => 'checkbox',
                    'label'         => $emails[$email_key]['title'],
                    'description'   => $emails[$email_key]['description'],
                ),
                array(
                    'id'       => $email_key . '_sub',
                    'type'     => 'text',
                    'label'    => __( 'Subject Line','ultimatemember' ),
                    'conditional' => array( $email_key . '_on', '=', 1 ),
                    'description' => __('This is the subject line of the e-mail','ultimatemember'),
                ),
                array(
                    'id'       => $email_key,
                    'type'     => 'wp_editor',
                    'label'    => __( 'Message Body','ultimatemember' ),
                    'conditional' => array( $email_key . '_on', '=', 1 ),
                    'description' 	   => __('This is the content of the e-mail','ultimatemember'),
                ),
            );

            return $this->render_settings_section( $section_fields );
        }


        function settings_appearance_profile_tab() {
            wp_enqueue_media();
        }


        function settings_licenses_tab( $html, $section_fields ) {
            ob_start(); ?>

            <div class="wrap-licenses">
                <table class="form-table um-settings-section">
                    <tbody>
                    <?php foreach ( $section_fields as $field_data ) {
                        $option_value = um_get_option( $field_data['id'] );
                        $value = ! empty( $option_value ) ? $option_value : ( ! empty( $field_data['default'] ) ? $field_data['default'] : '' );

                        if ( isset( $field_data['is_option'] ) && $field_data['is_option'] === false ) {

                            echo $this->render_setting_field( $field_data );

                        } else {

                            $license = get_option( "{$field_data['id']}_edd_answer" );

                            if ( is_object( $license ) ) {
                                // activate_license 'invalid' on anything other than valid, so if there was an error capture it
                                if ( false === $license->success ) {

                                    if ( ! empty( $license->error ) ) {
                                        switch ( $license->error ) {

                                            case 'expired' :

                                                $class = 'expired';
                                                $messages[] = sprintf(
                                                    __( 'Your license key expired on %s. Please <a href="%s" target="_blank">renew your license key</a>.', 'ultimatemember' ),
                                                    date_i18n( get_option( 'date_format' ), strtotime( $license->expires, current_time( 'timestamp' ) ) ),
                                                    'https://ultimatemember.com/checkout/?edd_license_key=' . $value . '&utm_campaign=admin&utm_source=licenses&utm_medium=expired'
                                                );

                                                $license_status = 'license-' . $class . '-notice';

                                                break;

                                            case 'revoked' :

                                                $class = 'error';
                                                $messages[] = sprintf(
                                                    __( 'Your license key has been disabled. Please <a href="%s" target="_blank">contact support</a> for more information.', 'ultimatemember' ),
                                                    'https://ultimatemember.com/support?utm_campaign=admin&utm_source=licenses&utm_medium=revoked'
                                                );

                                                $license_status = 'license-' . $class . '-notice';

                                                break;

                                            case 'missing' :

                                                $class = 'error';
                                                $messages[] = sprintf(
                                                    __( 'Invalid license. Please <a href="%s" target="_blank">visit your account page</a> and verify it.', 'ultimatemember' ),
                                                    'https://ultimatemember.com/account?utm_campaign=admin&utm_source=licenses&utm_medium=missing'
                                                );

                                                $license_status = 'license-' . $class . '-notice';

                                                break;

                                            case 'invalid' :
                                            case 'site_inactive' :

                                                $class = 'error';
                                                $messages[] = sprintf(
                                                    __( 'Your %s is not active for this URL. Please <a href="%s" target="_blank">visit your account page</a> to manage your license key URLs.', 'ultimatemember' ),
                                                    $field_data['item_name'],
                                                    'https://ultimatemember.com/account?utm_campaign=admin&utm_source=licenses&utm_medium=invalid'
                                                );

                                                $license_status = 'license-' . $class . '-notice';

                                                break;

                                            case 'item_name_mismatch' :

                                                $class = 'error';
                                                $messages[] = sprintf( __( 'This appears to be an invalid license key for %s.', 'ultimatemember' ), $field_data['item_name'] );

                                                $license_status = 'license-' . $class . '-notice';

                                                break;

                                            case 'no_activations_left':

                                                $class = 'error';
                                                $messages[] = sprintf( __( 'Your license key has reached its activation limit. <a href="%s">View possible upgrades</a> now.', 'ultimatemember' ), 'https://ultimatemember.com/account' );

                                                $license_status = 'license-' . $class . '-notice';

                                                break;

                                            case 'license_not_activable':

                                                $class = 'error';
                                                $messages[] = __( 'The key you entered belongs to a bundle, please use the product specific license key.', 'ultimatemember' );

                                                $license_status = 'license-' . $class . '-notice';
                                                break;

                                            default :

                                                $class = 'error';
                                                $error = ! empty(  $license->error ) ?  $license->error : __( 'unknown_error', 'ultimatemember' );
                                                $messages[] = sprintf( __( 'There was an error with this license key: %s. Please <a href="%s">contact our support team</a>.', 'ultimatemember' ), $error, 'https://ultimatemember.com/support' );

                                                $license_status = 'license-' . $class . '-notice';
                                                break;
                                        }
                                    } else {
                                        $class = 'error';
                                        $error = ! empty(  $license->error ) ?  $license->error : __( 'unknown_error', 'ultimatemember' );
                                        $messages[] = sprintf( __( 'There was an error with this license key: %s. Please <a href="%s">contact our support team</a>.', 'ultimatemember' ), $error, 'https://ultimatemember.com/support' );

                                        $license_status = 'license-' . $class . '-notice';
                                    }

                                } else {

                                    switch( $license->license ) {

                                        case 'expired' :

                                            $class = 'expired';
                                            $messages[] = sprintf(
                                                __( 'Your license key expired on %s. Please <a href="%s" target="_blank">renew your license key</a>.', 'ultimatemember' ),
                                                date_i18n( get_option( 'date_format' ), strtotime( $license->expires, current_time( 'timestamp' ) ) ),
                                                'https://ultimatemember.com/checkout/?edd_license_key=' . $value . '&utm_campaign=admin&utm_source=licenses&utm_medium=expired'
                                            );

                                            $license_status = 'license-' . $class . '-notice';

                                            break;

                                        case 'revoked' :

                                            $class = 'error';
                                            $messages[] = sprintf(
                                                __( 'Your license key has been disabled. Please <a href="%s" target="_blank">contact support</a> for more information.', 'ultimatemember' ),
                                                'https://ultimatemember.com/support?utm_campaign=admin&utm_source=licenses&utm_medium=revoked'
                                            );

                                            $license_status = 'license-' . $class . '-notice';

                                            break;

                                        case 'missing' :

                                            $class = 'error';
                                            $messages[] = sprintf(
                                                __( 'Invalid license. Please <a href="%s" target="_blank">visit your account page</a> and verify it.', 'ultimatemember' ),
                                                'https://ultimatemember.com/account?utm_campaign=admin&utm_source=licenses&utm_medium=missing'
                                            );

                                            $license_status = 'license-' . $class . '-notice';

                                            break;

                                        case 'invalid' :
                                        case 'site_inactive' :

                                            $class = 'error';
                                            $messages[] = sprintf(
                                                __( 'Your %s is not active for this URL. Please <a href="%s" target="_blank">visit your account page</a> to manage your license key URLs.', 'ultimatemember' ),
                                                $field_data['item_name'],
                                                'https://ultimatemember.com/account?utm_campaign=admin&utm_source=licenses&utm_medium=invalid'
                                            );

                                            $license_status = 'license-' . $class . '-notice';

                                            break;

                                        case 'item_name_mismatch' :

                                            $class = 'error';
                                            $messages[] = sprintf( __( 'This appears to be an invalid license key for %s.', 'ultimatemember' ), $field_data['item_name'] );

                                            $license_status = 'license-' . $class . '-notice';

                                            break;

                                        case 'no_activations_left':

                                            $class = 'error';
                                            $messages[] = sprintf( __( 'Your license key has reached its activation limit. <a href="%s">View possible upgrades</a> now.', 'ultimatemember' ), 'https://ultimatemember.com/account' );

                                            $license_status = 'license-' . $class . '-notice';

                                            break;

                                        case 'license_not_activable':

                                            $class = 'error';
                                            $messages[] = __( 'The key you entered belongs to a bundle, please use the product specific license key.', 'ultimatemember' );

                                            $license_status = 'license-' . $class . '-notice';
                                            break;

                                        case 'valid' :
                                        default:

                                            $class = 'valid';

                                            $now        = current_time( 'timestamp' );
                                            $expiration = strtotime( $license->expires, current_time( 'timestamp' ) );

                                            if( 'lifetime' === $license->expires ) {

                                                $messages[] = __( 'License key never expires.', 'ultimatemember' );

                                                $license_status = 'license-lifetime-notice';

                                            } elseif( $expiration > $now && $expiration - $now < ( DAY_IN_SECONDS * 30 ) ) {

                                                $messages[] = sprintf(
                                                    __( 'Your license key expires soon! It expires on %s. <a href="%s" target="_blank">Renew your license key</a>.', 'ultimatemember' ),
                                                    date_i18n( get_option( 'date_format' ), strtotime( $license->expires, current_time( 'timestamp' ) ) ),
                                                    'https://ultimatemember.com/checkout/?edd_license_key=' . $value . '&utm_campaign=admin&utm_source=licenses&utm_medium=renew'
                                                );

                                                $license_status = 'license-expires-soon-notice';

                                            } else {

                                                $messages[] = sprintf(
                                                    __( 'Your license key expires on %s.', 'ultimatemember' ),
                                                    date_i18n( get_option( 'date_format' ), strtotime( $license->expires, current_time( 'timestamp' ) ) )
                                                );

                                                $license_status = 'license-expiration-date-notice';

                                            }

                                            break;

                                    }

                                }

                            } else {
                                $class = 'empty';

                                $messages[] = sprintf(
                                    __( 'To receive updates, please enter your valid %s license key.', 'ultimatemember' ),
                                    $field_data['item_name']
                                );

                                $license_status = null;
                            } ?>

                            <tr class="um-settings-line">
                                <th><label for="um_options_<?php echo $field_data['id'] ?>"><?php echo $field_data['label'] ?></label></th>
                                <td>
                                    <form method="post" action="" name="um-settings-form" class="um-settings-form">
                                        <input type="hidden" value="save" name="um-settings-action" />
                                        <input type="hidden" name="licenses_settings" value="1" />
                                        <input type="text" id="um_options_<?php echo $field_data['id'] ?>" name="um_options[<?php echo $field_data['id'] ?>]" value="<?php echo $value ?>" class="um-option-field um-long-field" data-field_id="<?php echo $field_data['id'] ?>" />
                                        <?php if ( ! empty( $field_data['description'] ) ) { ?>
                                            <div class="description"><?php echo $field_data['description'] ?></div>
                                        <?php } ?>

                                        <?php if ( ( is_object( $license ) && 'valid' == $license->license ) || 'valid' == $license ) { ?>
                                            <input type="button" class="button um_license_deactivate" id="<?php echo $field_data['id'] ?>_deactivate" value="<?php _e( 'Clear License',  'ultimatemember' ) ?>"/>
                                        <?php } elseif ( empty( $value ) ) { ?>
                                            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e( 'Activate', 'ultimatemember' ) ?>" />
                                        <?php } else { ?>
                                            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e( 'Re-Activate', 'ultimatemember' ) ?>" />
                                        <?php }

                                        if ( ! empty( $messages ) ) {
                                            foreach ( $messages as $message ) { ?>
                                                <div class="edd-license-data edd-license-<?php echo $class . ' ' . $license_status ?>">
                                                    <p><?php echo $message ?></p>
                                                </div>
                                            <?php }
                                        } ?>
                                    </form>
                                </td>
                            </tr>
                        <?php }
                    } ?>
                    </tbody>
                </table>
            </div>
            <?php $section = ob_get_clean();

            return $section;
        }

        /**
         * Render settings section
         *
         * @param $section_fields
         * @return string
         */
        function render_settings_section( $section_fields ) {
            ob_start(); ?>

            <table class="form-table um-settings-section">
                <tbody>
                <?php foreach ( $section_fields as $field_data ) {
                    echo $this->render_setting_field( $field_data );
                } ?>
                </tbody>
            </table>

            <?php $section = ob_get_clean();

            return $section;
        }


        /**
         * Render HTML for settings field
         *
         * @param $data
         * @return string
         */
        function render_setting_field( $data ) {
            if ( empty( $data['type'] ) )
                return '';

            $conditional = ! empty( $data['conditional'] ) ? 'data-conditional="' . esc_attr( json_encode( $data['conditional'] ) ) . '"' : '';

            $html = '';
            if ( $data['type'] != 'hidden' )
                $html .= '<tr class="um-settings-line" ' . $conditional . '><th><label for="um_options_' . $data['id'] . '">' . $data['label'] . '</label></th><td>';


            $option_value = UM()->um_get_option( $data['id'] );
            $default = ! empty( $data['default'] ) ? $data['default'] : UM()->um_get_default( $data['id'] );

            switch ( $data['type'] ) {
                case 'hidden':
                    $value = ! empty( $option_value ) ? $option_value : $default;

                    if ( empty( $data['is_option'] ) )
                        $html .= '<input type="hidden" id="' . $data['id'] . '" name="' . $data['id'] . '" value="' . $value . '" />';
                    else
                        $html .= '<input type="hidden" id="um_options_' . $data['id'] . '" name="um_options[' . $data['id'] . ']" value="' . $value . '" class="um-option-field" data-field_id="' . $data['id'] . '" />';

                    break;
                case 'text':
                    $value = ! empty( $option_value ) ? $option_value : $default;
                    $field_length = ! empty( $data['size'] ) ? $data['size'] : 'um-long-field';

                    $html .= '<input type="text" id="um_options_' . $data['id'] . '" name="um_options[' . $data['id'] . ']" value="' . $value . '" class="um-option-field ' . $field_length . '" data-field_id="' . $data['id'] . '" />';
                    break;
                case 'multi-text':
                    $values = ! empty( $option_value ) ? $option_value : $default;

                    $html .= '<ul class="um-multi-text-list" data-field_id="' . $data['id'] . '">';

                    if ( ! empty( $values ) ) {
                        foreach ( $values as $k=>$value ) {
                            $html .= '<li class="um-multi-text-option-line"><input type="text" id="um_options_' . $data['id'] . '-' . $k . '" name="um_options[' . $data['id'] . '][]" value="' . $value . '" class="um-option-field" data-field_id="' . $data['id'] . '" />
                                    <a href="javascript:void(0);" class="um-option-delete">' . __( 'Remove', 'ultimatemember' ) . '</a></li>';
                        }
                    }

                    $html .= '</ul><a href="javascript:void(0);" class="button button-primary um-multi-text-add-option" data-name="um_options[' . $data['id'] . '][]">' . $data['add_text'] . '</a>';
                    break;
                case 'textarea':
                    $value = ! empty( $option_value ) ? $option_value : $default;
                    $field_length = ! empty( $data['size'] ) ? $data['size'] : 'um-long-field';

                    $html .= '<textarea id="um_options_' . $data['id'] . '" name="um_options[' . $data['id'] . ']" rows="6" class="um-option-field ' . $field_length . '" data-field_id="' . $data['id'] . '">' . $value . '</textarea>';
                    break;
                case 'wp_editor':
                    $value = ! empty( $option_value ) ? $option_value : $default;

                    ob_start();
                    wp_editor( $value,
                        'um_options_' . $data['id'],
                        array(
                            'textarea_name' => 'um_options[' . $data['id'] . ']',
                            'textarea_rows' => 20,
                            'editor_height' => 425,
                            'wpautop'       => false,
                            'media_buttons' => false,
                            'editor_class'  => 'um-option-field'
                        )
                    );

                    $html .= ob_get_clean();
                    break;
                case 'checkbox':
                    $value = ( '' !== $option_value ) ? $option_value : $default;

                    $html .= '<input type="hidden" id="um_options_' . $data['id'] . '_hidden" name="um_options[' . $data['id'] . ']" value="0" /><input type="checkbox" ' . checked( $value, true, false ) . ' id="um_options_' . $data['id'] . '" name="um_options[' . $data['id'] . ']" value="1" class="um-option-field" data-field_id="' . $data['id'] . '" />';
                    break;
                case 'multi-checkbox':
                    $value = ( '' !== $option_value ) ? $option_value : $default;
                    $columns = ! empty( $data['columns'] ) ? $data['columns'] : 1;

                    $per_column = ceil( count( $data['options'] ) / $columns );

                    $html .= '<div class="multi-checkbox-line">';

                    $current_option = 1;
                    $iter = 1;
                    foreach ( $data['options'] as $key=>$option ) {
                        if ( $current_option == 1 )
                            $html .= '<div class="multi-checkbox-column" style="width:' . floor( 100/$columns ) . '%;">';

                        $html .= '<input type="hidden" id="um_options_' . $data['id'] . '_' . $key . '_hidden" name="um_options[' . $data['id'] . '][' . $key . ']" value="0" />
                        <label><input type="checkbox" ' . checked( $value[$key], true, false ) . ' id="um_options_' . $data['id'] . '" name="um_options[' . $data['id'] . '][' . $key . ']" value="1" class="um-option-field" data-field_id="' . $data['id'] . '" />' . $option . '</label>';

                        if ( $current_option == $per_column || $iter == count( $data['options'] ) ) {
                            $current_option = 1;
                            $html .= '</div>';
                        } else {
                            $current_option++;
                        }

                        $iter++;
                    }

                    $html .= '</div>';

                    break;
                case 'selectbox':
                    $value = ! empty( $option_value ) ? $option_value : $default;

                    $html .= '<select ' . ( ! empty( $data['multi'] ) ? 'multiple' : '' ) . ' id="um_options_' . $data['id'] . '" name="um_options[' . $data['id'] . ']' . ( ! empty( $data['multi'] ) ? '[]' : '' ) . '" class="um-option-field" data-field_id="' . $data['id'] . '">';
                    foreach ( $data['options'] as $key=>$option ) {
                        if ( ! empty( $data['multi'] ) ) {
                            $html .= '<option value="' . $key . '" ' . selected( in_array( $key, $value ), true, false ) . '>' . $option . '</option>';
                        } else {
                            $html .= '<option value="' . $key . '" ' . selected( $key == $value, true, false ) . '>' . $option . '</option>';
                        }
                    }
                    $html .= '</select>';

                    break;
                case 'media':
                    $upload_frame_title = ! empty( $data['upload_frame_title'] ) ? $data['upload_frame_title'] : __( 'Select media', 'ultimatemember' );
                    $value = ! empty( $option_value ) ? $option_value : $default;

                    $image_id = ! empty( $value['id'] ) ? $value['id'] : '';
                    $image_width = ! empty( $value['width'] ) ? $value['width'] : '';
                    $image_height = ! empty( $value['height'] ) ? $value['height'] : '';
                    $image_thumbnail = ! empty( $value['thumbnail'] ) ? $value['thumbnail'] : '';
                    $image_url = ! empty( $value['url'] ) ? $value['url'] : '';

                    $data_default = ! empty( $default ) ? 'data-default="' .  esc_attr( $default['url'] ) .'"' : '';

                    $html .= '<div class="um-media-upload">' .
                        '<input type="hidden" class="um-media-upload-data-id" name="um_options[' . $data['id'] . '][id]" id="um_options_' . $data['id'] . '_id" value="' . $image_id . '">' .
                        '<input type="hidden" class="um-media-upload-data-width" name="um_options[' . $data['id'] . '][width]" id="um_options_' . $data['id'] . '_width" value="' . $image_width . '">' .
                        '<input type="hidden" class="um-media-upload-data-height" name="um_options[' . $data['id'] . '][height]" id="um_options_' . $data['id'] . '_height" value="' . $image_height . '">' .
                        '<input type="hidden" class="um-media-upload-data-thumbnail" name="um_options[' . $data['id'] . '][thumbnail]" id="um_options_' . $data['id'] . '_thumbnail" value="' . $image_thumbnail . '">' .
                        '<input type="hidden" class="um-option-field um-media-upload-data-url" name="um_options[' . $data['id'] . '][url]" id="um_options_' . $data['id'] . '_url" value="' . $image_url . '" data-field_id="' . $data['id'] . '" ' .  $data_default . '>';

                    if ( ! isset( $data['preview'] ) || $data['preview'] !== false ) {
                        $html .= '<img src="' . ( ! empty( $value['url'] ) ? $value['url'] : '' ) . '" alt="" class="icon_preview"><div style="clear:both;"></div>';
                    }

                    if ( ! empty( $data['url'] ) ) {
                        $html .= '<input type="text" class="um-media-upload-url" readonly value="' . $image_url . '" /><div style="clear:both;"></div>';
                    }

                    $html .= '<input type="button" class="um-set-image button button-primary" value="' . __( 'Select', 'ultimatemember' ) . '" data-upload_frame="' . $upload_frame_title . '" />
                    <input type="button" class="um-clear-image button" value="' . __( 'Clear', 'ultimatemember' ) . '" /></div>';
                    break;
            }

            if ( ! empty( $data['description'] ) )
                $html .= '<div class="description">' . $data['description'] . '</div>';

            $html .= '</td></tr>';

            return $html;
        }

    }
}