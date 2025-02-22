<?php

// 确保在 WordPress 环境中
if (!defined('ABSPATH')) {
    exit;
}

$captcha_appid = get_option('wpad_captcha_appid');
$ajax_url = admin_url('admin-ajax.php'); // 定义 AJAX 请求的 URL
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AD域登录</title>
    <link rel="stylesheet" href="<?php echo esc_url(includes_url('css/login.min.css')); ?>">
    <!-- 验证码程序依赖(必须)。请勿修改以下程序依赖，如使用本地缓存，或通过其他手段规避加载，会导致验证码无法正常更新，对抗能力无法保证，甚至引起误拦截。 -->
    <script src="https://turing.captcha.qcloud.com/TCaptcha.js"></script>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f1f1f1;
        }
        form {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 320px;
            position: relative;
        }
        .form-content {
            padding: 10%; /* 上下左右 10% 的留白 */
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }
        input[type="submit"],
        input[type="button"] {
            background-color: #0073aa;
            color: #fff;
            padding: 10px 15px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }
        input[type="submit"]:hover,
        input[type="button"]:hover {
            background-color: #005177;
        }
        #message {
            margin-top: 15px;
            color: red;
        }
        .enterprise-name {
            text-align: center;
            margin-bottom: 20px;
        }
        #ad-login-button {
            margin-left: 10px;
        }
        #security-verify-button {
            margin-right: 10px;
        }
        /* 禁用登录按钮的样式 */
        #ad-login-button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <form id="ad-login-form">
        <div class="form-content">
            <div class="enterprise-name">
                <h2><?php echo esc_html(get_option('wpad_enterprise_name', '')); ?></h2>
            </div>
            <label for="employee_id">人员编号（工号）:</label>
            <input type="text" id="employee_id" name="employee_id" required>
            <label for="password">密码:</label>
            <input type="password" id="password" name="password" required>
            <div id="captcha-container">
            </div>
            <!-- 新增安全验证按钮 -->
            <input type="button" id="security-verify-button" value="安全验证">
            <input type="submit" id="ad-login-button" value="登录" disabled>
            <div id="message"></div>
        </div>
    </form>

    <script>
        // 定义全局变量存储验证码结果
        let captchaResult = {
            ret: null,
            randstr: '',
            ticket: ''
        };

        // 定义验证码js加载错误处理函数
        function loadErrorCallback() {
            var appid = '<?php echo esc_js($captcha_appid); ?>';
            // 生成容灾票据或自行做其它处理
            var ticket = 'trerror_1001_' + appid + '_' + Math.floor(new Date().getTime() / 1000);
            callback({
                ret: 0,
                randstr: '@' + Math.random().toString(36).substr(2),
                ticket: ticket,
                errorCode: 1001,
                errorMessage: 'jsload_error'
            });
        }

        document.getElementById('security-verify-button').addEventListener('click', function() {
            // 检查用户名和密码是否填写
            var employeeId = document.getElementById('employee_id').value;
            var password = document.getElementById('password').value;
            if (!employeeId || !password) {
                document.getElementById('message').innerHTML = '请先填写人员编号（工号）和密码。';
                return;
            }

            try {
                // 生成一个验证码对象
                var captcha = new TencentCaptcha('<?php echo esc_js($captcha_appid); ?>', function(res) {
                    // 验证码回调函数
                    console.log('callback:', res);
                    if (res.ret === 0) {
                        // 验证码验证成功，存储结果
                        captchaResult.ret = res.ret;
                        captchaResult.randstr = res.randstr;
                        captchaResult.ticket = res.ticket;
                        document.getElementById('ad-login-button').disabled = false;
                        document.getElementById('message').innerHTML = '';
                    } else if (res.ret === 2) {
                        document.getElementById('message').innerHTML = '用户主动关闭验证码，请重新验证。';
                    } else {
                        // 处理错误情况
                        var errorMessage = '验证码验证失败，错误代码：' + res.errorCode + '，错误信息：' + res.errorMessage;
                        document.getElementById('message').innerHTML = errorMessage;
                    }
                }, {
                    userLanguage: 'zh-cn',
                    showFn: (ret) => {
                        const { duration, sid } = ret;
                    },
                });
                // 调用方法，显示验证码
                captcha.show();
            } catch (error) {
                // 加载异常，调用验证码js加载错误处理函数
                loadErrorCallback();
            }
        });

        document.getElementById('ad-login-form').addEventListener('submit', function(event) {
            event.preventDefault();

            if (document.getElementById('ad-login-button').disabled) {
                document.getElementById('message').innerHTML = '请先完成安全验证。';
                return;
            }

            var formData = new FormData(event.target);
            formData.append('action', 'nopriv_ad_login');
            formData.append('captcha_randstr', captchaResult.randstr);
            formData.append('captcha_ticket', captchaResult.ticket);

            fetch('<?php echo $ajax_url; ?>', {
                method: 'POST',
                body: formData
            })
           .then(response => {
                if (!response.ok) {
                    // 获取详细的错误信息
                    return response.text().then(text => {
                        throw new Error(`Network response was not ok: ${response.status} ${response.statusText} - ${text}`);
                    });
                }
                return response.json();
            })
           .then(data => {
                console.log('后端返回的数据:', data);
                if (data.success) {
                    window.location.href = data.redirect_url;
                } else {
                    document.getElementById('message').innerHTML = data.data;
                }
            })
           .catch(error => {
                console.error('登录请求出错:', error);
                // 显示详细的错误信息给用户
                document.getElementById('message').innerHTML = `登录请求失败，详细错误信息：${error.message}，请稍后重试。`;
            });
        });
    </script>
</body>
</html>