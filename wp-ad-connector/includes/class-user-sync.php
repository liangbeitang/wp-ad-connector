<?php
/**
 * 该类负责处理从 Active Directory 到 WordPress 的用户同步
 */
class WPAD_User_Sync {
    private $ad_operator;

    /**
     * 构造函数，初始化 AD 操作类实例，并设置定时同步任务
     */
    public function __construct() {
        $this->ad_operator = new WPAD_AD_Operator();

        // 安排每小时执行一次增量同步任务
        if (!wp_next_scheduled('wpad_hourly_user_sync')) {
            wp_schedule_event(time(), 'hourly', 'wpad_hourly_user_sync');
        }
        add_action('wpad_hourly_user_sync', [$this, 'sync_users']);
    }

    /**
     * 执行用户同步操作
     */
    public function sync_users() {
        do_action('wpad_pre_user_sync');

        // 从 AD 中获取用户数据
        $ad_users = $this->fetch_ad_users();
        if ($ad_users) {
            foreach ($ad_users as $user) {
                if (!$this->user_exists($user['employeeId'])) {
                    // 如果用户不存在于 WordPress 中，则创建新用户
                    $this->create_wp_user($user);
                } else {
                    // 如果用户已存在，则更新用户元数据
                    $this->update_user_meta($user);
                }
            }
        }

        // 清理非活动用户
        $this->cleanup_inactive_users();

        do_action('wpad_post_user_update');
    }

    /**
     * 从 AD 中获取用户数据
     *
     * @return array|bool 如果获取成功，返回用户数据数组；否则返回 false
     */
    private function fetch_ad_users() {
        $base_dn = get_option('wpad_base_dn', 'DC=example,DC=com');
        $filter = '(objectClass=user)';
        $attributes = ['employeeId', 'mail', 'memberOf', 'objectGUID'];

        $search_result = $this->ad_operator->search_users($base_dn, $filter, $attributes);
        if ($search_result) {
            $users = [];
            for ($i = 0; $i < $search_result['count']; $i++) {
                $user = [];
                if (isset($search_result[$i]['employeeid'][0])) {
                    $user['employeeId'] = $search_result[$i]['employeeid'][0];
                }
                if (isset($search_result[$i]['mail'][0])) {
                    $user['mail'] = $search_result[$i]['mail'][0];
                }
                if (isset($search_result[$i]['memberof'])) {
                    $user['memberOf'] = [];
                    for ($j = 0; $j < $search_result[$i]['memberof']['count']; $j++) {
                        $user['memberOf'][] = $search_result[$i]['memberof'][$j];
                    }
                }
                if (isset($search_result[$i]['objectguid'][0])) {
                    $user['objectGUID'] = $search_result[$i]['objectguid'][0];
                }
                $users[] = $user;
            }
            return $users;
        }
        return false;
    }

    /**
     * 检查用户是否已存在于 WordPress 中
     *
     * @param string $employee_id 用户的 EmployeeID
     * @return bool 如果用户存在，返回 true；否则返回 false
     */
    private function user_exists($employee_id) {
        $user = get_user_by('login', $employee_id);
        return ($user !== false);
    }


    /**
     * 更新 WordPress 用户的元数据
     *
     * @param array $user 从 AD 中获取的用户数据
     */
    private function update_user_meta($user) {
        $wp_user = get_user_by('login', $user['employeeId']);
        if ($wp_user) {
            $user_id = $wp_user->ID;
            if (isset($user['mail'])) {
                update_user_meta($user_id, 'user_email', $user['mail']);
            }
            if (isset($user['objectGUID'])) {
                update_user_meta($user_id, 'ad_guid', $user['objectGUID']);
            }
            $new_role = $this->map_ad_group($user['memberOf']);
            if ($new_role != $wp_user->roles[0]) {
                $wp_user->set_role($new_role);
            }
        }
    }

    /**
     * 根据 AD 组映射 WordPress 角色
     *
     * @param array $groups 用户所属的 AD 组列表
     * @return string WordPress 角色名称
     */
    private function map_ad_group($groups) {
        // 示例：将 AD 组映射为 WP 角色
        $mapping = [
            'CN=IT_Admins'   => 'administrator',
            'CN=Editors'     => 'editor',
            'CN=Departments' => 'subscriber'
        ];
        // 允许开发者通过过滤器修改角色映射
        $mapping = apply_filters('wpad_role_mapping', $mapping);

        return $this->find_matching_role($groups, $mapping);
    }

    /**
     * 查找匹配的 WordPress 角色
     *
     * @param array $groups 用户所属的 AD 组列表
     * @param array $mapping AD 组到 WordPress 角色的映射数组
     * @return string WordPress 角色名称
     */
    private function find_matching_role($groups, $mapping) {
        foreach ($groups as $group) {
            if (isset($mapping[$group])) {
                return $mapping[$group];
            }
        }
        // 如果没有匹配的组，返回默认角色
        return get_option('wpad_default_role', 'subscriber');
    }

    /**
     * 清理非活动用户
     */
    private function cleanup_inactive_users() {
        // 这里可以实现清理非活动用户的逻辑
        // 例如，根据 AD 中的用户状态，删除 WordPress 中对应的非活动用户
    }
    //如果用户不存在，需要创建新用户。
private function create_wp_user($ad_data) {
    $user_id = wp_insert_user([
        'user_login' => $ad_data['employeeId'],
        'user_pass'  => $ad_data['password'], // 假设AD数据中有password字段
        'user_email' => $ad_data['mail'],
        'nickname'   => $ad_data['cn'],
        'display_name' => $ad_data['cn'],
        'role'       => 'contributor'
    ]);

    if (!is_wp_error($user_id)) {
        // 更新用户元数据，存储 AD 的 GUID
        update_user_meta($user_id, 'ad_guid', $ad_data['objectGUID']);
        do_action('wpad_user_created', $user_id);
    }
}


    
    
    
}