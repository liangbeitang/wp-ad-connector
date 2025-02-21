<?php
// 处理 AD 配置验证的 AJAX 请求
add_action('wp_ajax_ad_verify_config', function() {
    $ad_admin_domain = sanitize_text_field($_POST['ad_admin_domain']);
    $ad_admin_password = sanitize_text_field($_POST['ad_admin_password']);
    $ad_search_org_dn = sanitize_text_field($_POST['ad_search_org_dn']);

    // 实例化 AD 操作类
    $ad_operator = new WPAD_AD_Operator();

    // 调用验证方法
    $result = $ad_operator->verify_ad_config($ad_admin_domain, $ad_admin_password, $ad_search_org_dn);

    if ($result) {
        wp_send_json_success();
    } else {
        wp_send_json_error(['message' => 'AD 配置验证失败，请检查输入信息。']);
    }
});

// 处理未登录用户的 AJAX 请求
add_action('wp_ajax_nopriv_ad_verify_config', function() {
    wp_send_json_error('你没有权限进行此操作。');
});