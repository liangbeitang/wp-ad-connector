<?php
/**
 * 该类负责处理与 Active Directory 的连接和基本操作
 */
 
require_once dirname(__FILE__) . '/ajax-handlers.php';
 
class WPAD_AD_Operator {
    private $ldap_connection;
    private $ldap_host;
    private $ldap_port;
    private $ldap_dn;
    private $ldap_password;
    private $use_ldaps;
    private $base_dn; // 新增属性，用于存储基础 DN

    /**
     * 构造函数，初始化 AD 连接所需的参数
     */
    public function __construct() {
        $this->ldap_host = get_option('wpad_ldap_host', '');
        $this->ldap_port = get_option('wpad_ldap_port', 389);
        $this->ldap_dn = get_option('wpad_service_account_dn', '');
        $this->ldap_password = get_option('wpad_service_account_password', '');
        $this->use_ldaps = (get_option('wpad_use_ldaps', '0') === '1');
        $this->base_dn = get_option('wpad_ad_search_org_dn', '');

        // 可以添加日志输出，检查获取的配置信息
        error_log("LDAP Host: " . $this->ldap_host);
        error_log("LDAP Port: " . $this->ldap_port);
        error_log("LDAP DN: " . $this->ldap_dn);
        error_log("LDAP Password: " . $this->ldap_password);
    }

    /**
     * 建立与 AD 的 LDAP 连接
     *
     * @return bool|resource 如果连接成功，返回 LDAP 连接资源；否则返回 false
     */
    public function connect() {
        $protocol = $this->use_ldaps ? 'ldaps://' : 'ldap://';
        $this->ldap_connection = ldap_connect($protocol . $this->ldap_host, $this->ldap_port);

        if (!$this->ldap_connection) {
            error_log("无法连接LDAP服务器: {$protocol}{$this->ldap_host}:{$this->ldap_port}");
            return false;
        }

        ldap_set_option($this->ldap_connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($this->ldap_connection, LDAP_OPT_REFERRALS, 0);

        $bind = ldap_bind($this->ldap_connection, $this->ldap_dn, $this->ldap_password);
        if (!$bind) {
            error_log("LDAP绑定失败: " . ldap_error($this->ldap_connection));
            error_log("使用的 DN: " . $this->ldap_dn);
            error_log("使用的密码: " . $this->ldap_password);
            return false;
        }

        return $this->ldap_connection;
    }

    /**
     * 从 AD 中搜索用户信息
     *
     * @param string $base_dn 搜索的基础 DN
     * @param string $filter 搜索过滤器
     * @param array $attributes 需要返回的属性列表
     * @return array|bool 如果搜索成功，返回用户信息数组；否则返回 false
     */
    public function search_users($base_dn, $filter, $attributes = []) {
        if (!$this->ldap_connection) {
            if (!$this->connect()) {
                return false;
            }
        }

        $search_result = ldap_search($this->ldap_connection, $base_dn, $filter, $attributes);
        if ($search_result) {
            $entries = ldap_get_entries($this->ldap_connection, $search_result);
            if ($entries['count'] > 0) {
                return $entries;
            }
        }
        return false;
    }

    /**
     * 修改 AD 中用户的密码
     *
     * @param string $user_dn 用户的 DN
     * @param string $old_password 用户的旧密码
     * @param string $new_password 用户的新密码
     * @return bool 如果密码修改成功，返回 true；否则返回 false
     */
    public function change_user_password($user_dn, $old_password, $new_password) {
        if (!$this->ldap_connection) {
            if (!$this->connect()) {
                return false;
            }
        }

        $new_password_utf16 = iconv('UTF-8', 'UTF-16LE', '"' . $new_password . '"');
        $old_password_utf16 = iconv('UTF-8', 'UTF-16LE', '"' . $old_password . '"');

        $modifications = [
            [
                'attrib' => 'unicodePwd',
                'modtype' => LDAP_MODIFY_BATCH_REMOVE,
                'values' => [$old_password_utf16]
            ],
            [
                'attrib' => 'unicodePwd',
                'modtype' => LDAP_MODIFY_BATCH_ADD,
                'values' => [$new_password_utf16]
            ]
        ];

        $result = ldap_modify_batch($this->ldap_connection, $user_dn, $modifications);
        if ($result) {
            return true;
        } else {
            error_log("修改 AD 用户密码失败: " . ldap_error($this->ldap_connection));
            return false;
        }
    }

    /**
     * 验证 AD 配置
     *
     * @param string $host AD 服务器主机名
     * @param int $port AD 服务器端口号
     * @param string $dn 服务账户的 DN
     * @param string $password 服务账户的密码
     * @param string $base_dn 基础 DN
     * @return bool 如果验证成功，返回 true；否则返回 false
     */
    public function verify_ad_config($host, $port, $dn, $password, $base_dn) {
        // 保存原始属性值
        $original_host = $this->ldap_host;
        $original_port = $this->ldap_port;
        $original_dn = $this->ldap_dn;
        $original_password = $this->ldap_password;
        $original_base_dn = $this->base_dn;

        // 临时覆盖类属性
        $this->ldap_host = $host;
        $this->ldap_port = $port;
        $this->ldap_dn = $dn;
        $this->ldap_password = $password;
        $this->base_dn = $base_dn;

        // 强制不使用LDAPS
        $this->use_ldaps = false;

        // 建立连接
        if (!$this->connect()) {
            error_log("连接失败: ".ldap_error($this->ldap_connection));
            // 恢复原始属性值
            $this->ldap_host = $original_host;
            $this->ldap_port = $original_port;
            $this->ldap_dn = $original_dn;
            $this->ldap_password = $original_password;
            $this->base_dn = $original_base_dn;
            return false;
        }

        // 执行基础DN搜索验证
        try {
            $search = @ldap_search(
                $this->ldap_connection,
                $base_dn,
                '(objectClass=*)',
                [],
                0,
                1
            );
            // 恢复原始属性值
            $this->ldap_host = $original_host;
            $this->ldap_port = $original_port;
            $this->ldap_dn = $original_dn;
            $this->ldap_password = $original_password;
            $this->base_dn = $original_base_dn;
            return $search !== false;
        } catch (Exception $e) {
            error_log("DN搜索失败: ".$e->getMessage());
            // 恢复原始属性值
            $this->ldap_host = $original_host;
            $this->ldap_port = $original_port;
            $this->ldap_dn = $original_dn;
            $this->ldap_password = $original_password;
            $this->base_dn = $original_base_dn;
            return false;
        }
    }

    /**
     * 验证 AD employeeID 和密码
     *
     * @param string $employee_id AD employeeID
     * @param string $password 密码
     * @return bool 如果验证成功，返回 true；否则返回 false
     */
    public function verify_ad_employee_id_and_password($employee_id, $password) {
        if (!$this->ldap_connection) {
            if (!$this->connect()) {
                return false;
            }
        }

        // 根据 employeeID 查找用户 DN
        $filter = "(employeeID=$employee_id)";
        $search_result = ldap_search($this->ldap_connection, $this->base_dn, $filter);
        if ($search_result) {
            $entries = ldap_get_entries($this->ldap_connection, $search_result);
            if ($entries['count'] > 0) {
                $user_dn = $entries[0]['dn'];

                // 验证密码
                $bind_result = ldap_bind($this->ldap_connection, $user_dn, $password);
                if ($bind_result) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 验证 AD 管理员登录
     *
     * @param string $ad_ip_address AD 服务器 IP 地址
     * @param string $ad_admin_username AD 管理员用户名
     * @param string $ad_admin_password AD 管理员密码
     * @param string $ad_admin_domain AD 管理员域名
     * @return bool 如果验证成功，返回 true；否则返回 false
     */
    public function verify_admin_login($ad_ip_address, $ad_admin_username, $ad_admin_password, $ad_admin_domain) {
        // 连接到 AD 服务器
        $ldap_connection = ldap_connect($ad_ip_address);
        if (!$ldap_connection) {
            error_log("无法连接到 AD 服务器: $ad_ip_address");
            return false;
        }

        // 设置 LDAP 协议版本
        ldap_set_option($ldap_connection, LDAP_OPT_PROTOCOL_VERSION, 3);

        // 尝试绑定，这里可能需要使用 ad_admin_domain 构建完整的用户名
        $full_username = $ad_admin_domain . '\\' . $ad_admin_username;
        $bind_result = ldap_bind($ldap_connection, $full_username, $ad_admin_password);
        if (!$bind_result) {
            error_log("AD 管理员用户登录失败: " . ldap_error($ldap_connection));
            ldap_close($ldap_connection);
            return false;
        }

        // 关闭连接
        ldap_close($ldap_connection);

        return true;
    }

    /**
     * 验证 AD 登录
     *
     * @param string $employee_id AD employeeID
     * @param string $password 密码
     * @return bool 如果验证成功，返回 true；否则返回 false
     */
    public function verify_ad_login($employee_id, $password) {
        if (!$this->ldap_connection) {
            if (!$this->connect()) {
                error_log('LDAP 连接失败');
                return false;
            }
        }

        // 根据 employeeID 查找用户 DN
        $filter = "(employeeID=$employee_id)";
        $search_result = ldap_search($this->ldap_connection, $this->base_dn, $filter);
        if (!$search_result) {
            error_log('用户 DN 查找失败');
            return false;
        }

        $entries = ldap_get_entries($this->ldap_connection, $search_result);
        if ($entries['count'] <= 0) {
            error_log('未找到用户 DN');
            return false;
        }

        $user_dn = $entries[0]['dn'];

        // 验证密码
        $bind_result = ldap_bind($this->ldap_connection, $user_dn, $password);
        if (!$bind_result) {
            error_log('密码验证失败');
            return false;
        }

        return true;
    }

    /**
     * 关闭 LDAP 连接
     */
    public function close() {
        if ($this->ldap_connection) {
            ldap_close($this->ldap_connection);
        }
    }
    
    /**
     * 获取 LDAP 连接
     *
     * @return resource|null LDAP 连接资源，如果没有连接则返回 null
     */
    public function getLdapConnection() {
        return $this->ldap_connection;
    }
}