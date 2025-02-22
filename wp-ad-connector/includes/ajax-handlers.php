<?php
// 处理 AD 配置验证的 AJAX 请求
add_action('wp_ajax_ad_verify_config', function() {
    // 检查是否为 POST 请求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        wp_send_json_error(['message' => '无效的请求方法']);
        return;
    }

    // 检查必要的 POST 参数是否存在
    $required_fields = ['ad_ip_address', 'ad_admin_username', 'ad_admin_domain', 'ad_admin_password', 'ad_search_org_dn'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            wp_send_json_error(['message' => "缺少必要参数: {$field}"]);
            return;
        }
    }

    // 获取并清理所有参数
    $ad_server = sanitize_text_field($_POST['ad_ip_address']);
    $parts = explode(':', $ad_server);
    $host = $parts[0];
    $port = isset($parts[1]) ? intval($parts[1]) : 389; // 确保端口为整数

    $username = sanitize_text_field($_POST['ad_admin_username']);
    $domain = sanitize_text_field($_POST['ad_admin_domain']);
    $password = sanitize_text_field($_POST['ad_admin_password']);
    $base_dn = sanitize_text_field($_POST['ad_search_org_dn']);

    // 添加调试信息输出
    error_log("接收到的参数：ad_server = {$ad_server}, username = {$username}, domain = {$domain}, password = {$password}, base_dn = {$base_dn}");

    // 组合完整DN
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
            wp_send_json_error(['message' => "AD验证失败: {$error}"]);
        }
    } catch (Exception $e) {
        // 捕获可能的异常
        wp_send_json_error(['message' => "验证过程中出现错误: {$e->getMessage()}"]);
    }
});

// 处理未登录用户的 AJAX 请求，允许未登录用户使用 AD 验证功能
add_action('wp_ajax_nopriv_ad_verify_config', function() {
    // 检查是否为 POST 请求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        wp_send_json_error(['message' => '无效的请求方法']);
        return;
    }

    // 检查必要的 POST 参数是否存在
    $required_fields = ['ad_ip_address', 'ad_admin_username', 'ad_admin_domain', 'ad_admin_password', 'ad_search_org_dn'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            wp_send_json_error(['message' => "缺少必要参数: {$field}"]);
            return;
        }
    }

    // 获取并清理所有参数
    $ad_server = sanitize_text_field($_POST['ad_ip_address']);
    $parts = explode(':', $ad_server);
    $host = $parts[0];
    $port = isset($parts[1]) ? intval($parts[1]) : 389; // 确保端口为整数

    $username = sanitize_text_field($_POST['ad_admin_username']);
    $domain = sanitize_text_field($_POST['ad_admin_domain']);
    $password = sanitize_text_field($_POST['ad_admin_password']);
    $base_dn = sanitize_text_field($_POST['ad_search_org_dn']);

    // 添加调试信息输出
    error_log("接收到的参数：ad_server = {$ad_server}, username = {$username}, domain = {$domain}, password = {$password}, base_dn = {$base_dn}");

    // 组合完整DN
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
            wp_send_json_error(['message' => "AD验证失败: {$error}"]);
        }
    } catch (Exception $e) {
        // 捕获可能的异常
        wp_send_json_error(['message' => "验证过程中出现错误: {$e->getMessage()}"]);
    }
});