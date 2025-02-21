<?php
// 处理 AD 配置验证的 AJAX 请求
add_action('wp_ajax_ad_verify_config', function() {
    // 检查是否为 POST 请求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        error_log('无效的请求方法');
        wp_send_json_error(['message' => '无效的请求方法']);
        return;
    }

    // 检查必要的 POST 参数是否存在
    $required_fields = ['ad_ip_address', 'ad_admin_username', 'ad_admin_domain', 'ad_admin_password', 'ad_search_org_dn'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            error_log("缺少必要参数: {$field}");
            wp_send_json_error(['message' => "缺少必要参数: {$field}"]);
            return;
        }
    }

    // 获取并清理所有参数
    $ad_server = sanitize_text_field($_POST['ad_ip_address']);
    $parts = explode(':', $ad_server);
    $host = $parts[0];
    $port = isset($parts[1]) ? intval($parts[1]) : 389; // 确保端口为整数

    // 验证端口范围
    if ($port < 1 || $port > 65535) {
        error_log('无效的端口号');
        wp_send_json_error(['message' => '无效的端口号']);
        return;
    }

    $username = sanitize_text_field($_POST['ad_admin_username']);
    $domain = sanitize_text_field($_POST['ad_admin_domain']);
    $password = sanitize_text_field($_POST['ad_admin_password']);
    $base_dn = sanitize_text_field($_POST['ad_search_org_dn']);

    // 组合完整 DN
    $full_dn = "{$domain}\\{$username}";

    // 实例化 AD 操作类
    $ad_operator = new WPAD_AD_Operator();

    // 调用验证方法
    try {
        $result = $ad_operator->verify_ad_config($host, $port, $full_dn, $password, $base_dn);
        if ($result) {
            wp_send_json_success();
        } else {
            $error = ldap_error($ad_operator->ldap_connection);
            error_log("AD 验证失败: {$error}");
            wp_send_json_error(['message' => "AD 验证失败: {$error}"]);
        }
    } catch (Exception $e) {
        // 捕获可能的异常
        error_log("验证过程中出现错误: {$e->getMessage()}");
        wp_send_json_error(['message' => "验证过程中出现错误: {$e->getMessage()}"]);
    }
});

// 处理未登录用户的 AJAX 请求
add_action('wp_ajax_nopriv_ad_verify_config', function() {
    error_log('未登录用户尝试进行 AD 配置验证');
    wp_send_json_error('你没有权限进行此操作。');
});