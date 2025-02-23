<?php
// 检查是否是用户资料编辑页面
add_action( 'show_user_profile', 'wpad_add_password_change_link' );
add_action( 'edit_user_profile', 'wpad_add_password_change_link' );

/**
 * 在用户资料页面添加修改 AD 密码的链接
 *
 * @param WP_User $user 当前用户对象
 */
function wpad_add_password_change_link( $user ) {
    if ( current_user_can( 'edit_user', $user->ID ) ) {
        echo '<h2>' . esc_html__( '修改 AD 密码', 'wp-ad-connector' ) . '</h2>';
        echo '<p><a href="' . esc_url( get_permalink( get_page_by_path( 'ad-password-change' ) ) ) . '">' . esc_html__( '点击此处修改你的 AD 密码', 'wp-ad-connector' ) . '</a></p>';
    }
}

// 处理用户资料更新时的自定义字段保存
add_action( 'personal_options_update', 'wpad_save_user_profile_fields' );
add_action( 'edit_user_profile_update', 'wpad_save_user_profile_fields' );

/**
 * 保存用户资料中的自定义字段（如果有的话）
 *
 * @param int $user_id 当前用户的 ID
 */
function wpad_save_user_profile_fields( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return false;
    }

    // 这里可以添加保存自定义字段的逻辑
    // 例如，如果有额外的用户资料字段
    // update_user_meta( $user_id, 'custom_field_name', $_POST['custom_field_name'] );
}