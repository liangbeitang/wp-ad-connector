<?php
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

    <?php
    // 使用 include_once 引入验证码模块代码
    include_once dirname(__FILE__) . '/../includes/captcha.php';
    ?>

    <script>
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
        });
    </script>