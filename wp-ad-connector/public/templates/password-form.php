<?php
// 确保在 WordPress 环境中
if (!defined('ABSPATH')) {
    exit;
}

// 获取 AJAX 请求的 URL
$ajax_url = admin_url('admin-ajax.php');
// 生成验证码（这里只是简单示例，实际中可使用更安全的方式）
$captcha = rand(100000, 999999);
// 将验证码存储到会话中以便后续验证
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['captcha'] = $captcha;
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修改 AD 密码</title>
    <!-- 引入 jQuery，WordPress 自带 jQuery，使用 wp_enqueue_script 加载更规范，这里简单引入 -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        form {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 300px;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        input[type="submit"] {
            background-color: #0073aa;
            color: #fff;
            padding: 10px 15px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #005177;
        }
        #message {
            margin-top: 15px;
            color: red;
        }
    </style>
</head>
<body>
    <form id="ad-password-change-form">
        <h2>修改 AD 密码</h2>
        <label for="current_password">当前密码:</label>
        <input type="password" id="current_password" name="current_password" required>
        <label for="new_password">新密码:</label>
        <input type="password" id="new_password" name="new_password" required>
        <label for="captcha">验证码 (<?php echo $captcha; ?>):</label>
        <input type="text" id="captcha" name="captcha" required>
        <input type="submit" value="修改密码">
        <div id="message"></div>
    </form>

    <script>
        jQuery(document).ready(function ($) {
            $('#ad-password-change-form').on('submit', function (e) {
                e.preventDefault();
                // 收集表单数据
                var formData = {
                    action: 'ad_change_password',
                    current_password: $('#current_password').val(),
                    new_password: $('#new_password').val(),
                    captcha: $('#captcha').val()
                };

                // 发送 AJAX 请求
                $.ajax({
                    url: '<?php echo $ajax_url; ?>',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            $('#message').text(response.data).css('color', 'green');
                        } else {
                            $('#message').text(response.data).css('color', 'red');
                        }
                    },
                    error: function () {
                        $('#message').text('请求出错，请稍后重试。').css('color', 'red');
                    }
                });
            });
        });
    </script>
</body>
</html>