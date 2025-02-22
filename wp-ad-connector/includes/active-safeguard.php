<?php
// 该文件用于处理登录页面的安全验证功能

// 添加验证码验证按钮到登录页面
function add_safeguard_button_to_login() {
    // 获取验证码验证状态
    $verified = isset($_SESSION['safeguard_verified']) && $_SESSION['safeguard_verified'] === true;

    // 如果未验证，只显示安全验证按钮
    if (!$verified) {
        echo '<form id="safeguard-verification-form" method="post">';
        echo '<input type="text" name="captcha_code" placeholder="输入验证码" required>';
        echo '<input type="submit" name="safeguard_verify" value="安全验证">';
        echo '</form>';
    } else {
        // 如果已验证，显示原本的登录表单
        echo '<form id="loginform" action="' . esc_url(site_url('wp-login.php', 'login_post')) . '" method="post">';
        echo '<input type="text" name="log" id="user_login" placeholder="用户名" required>';
        echo '<input type="password" name="pwd" id="user_pass" placeholder="密码" required>';
        echo '<input type="submit" name="wp-submit" id="wp-submit" value="登录">';
        echo '</form>';
    }
}
add_action('login_form', 'add_safeguard_button_to_login');

// 处理验证码验证请求
function handle_safeguard_verification() {
    if (isset($_POST['safeguard_verify'])) {
        // 调用验证码验证函数，这里假设验证码验证函数名为 verify_captcha
        $captcha_code = $_POST['captcha_code'];
        if (verify_captcha($captcha_code)) {
            // 验证通过，设置会话状态
            session_start();
            $_SESSION['safeguard_verified'] = true;
            // 刷新页面
            wp_redirect(wp_login_url());
            exit;
        } else {
            // 验证失败，显示错误消息
            add_action('login_message', function() {
                echo '<p style="color: red;">验证码验证失败，请重试。</p>';
            });
        }
    }
}
add_action('init', 'handle_safeguard_verification');

// 清除会话状态，在登录成功或退出时调用
function clear_safeguard_session() {
    session_start();
    unset($_SESSION['safeguard_verified']);
    session_destroy();
}
add_action('wp_login', 'clear_safeguard_session');
add_action('wp_logout', 'clear_safeguard_session');