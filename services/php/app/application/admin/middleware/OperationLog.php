<?php
namespace app\admin\middleware;

use think\Db;
use think\facade\Session;

class OperationLog
{
    // 路由段 → 中文名映射
    private static $moduleMap = [
        'admin'         => '管理员管理',
        'role'          => '角色管理',
        'menu'          => '菜单管理',
        'user'          => '用户管理',
        'notice'        => '通知公告',
        'log_config'    => '日志设置',
        'login_log'     => '登录日志',
        'operation_log' => '操作日志',
        'profile'       => '个人中心',
    ];

    // HTTP 方法 → 动作映射
    private static $actionMap = [
        'POST'   => '新增',
        'PUT'    => '编辑',
        'DELETE' => '删除',
    ];

    // 需要脱敏的字段名
    private static $sensitiveFields = ['password', 'old_password', 'new_password'];

    public function handle($request, \Closure $next)
    {
        $response = $next($request);

        $method = $request->method();
        if (!in_array($method, ['POST', 'PUT', 'DELETE'])) {
            return $response;
        }

        try {
            $adminId  = $request->adminId ?? 0;
            $admin    = $request->admin ?? null;
            $username = $admin ? $admin['username'] : '';

            // 从路径 /admin/{module}/{action} 解析 module
            $path   = $request->path();
            $parts  = explode('/', $path);
            $module = isset($parts[2]) ? $parts[2] : '';
            $moduleCn = isset(self::$moduleMap[$module]) ? self::$moduleMap[$module] : $module;

            $action = isset(self::$actionMap[$method]) ? self::$actionMap[$method] : $method;

            // 请求参数脱敏
            $params = $request->param();
            unset($params['adminId'], $params['admin'], $params['authPaths']);
            foreach (self::$sensitiveFields as $field) {
                if (isset($params[$field])) {
                    $params[$field] = '******';
                }
            }

            Db::name('operation_log')->insert([
                'admin_id'   => $adminId,
                'username'   => $username,
                'module'     => $moduleCn,
                'action'     => $action,
                'method'     => $method,
                'url'        => $request->url(),
                'params'     => json_encode($params, JSON_UNESCAPED_UNICODE),
                'ip'         => $request->ip(),
                'user_agent' => $request->header('user-agent', ''),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            \think\facade\Log::error('OperationLog error: ' . $e->getMessage());
        }

        return $response;
    }
}
