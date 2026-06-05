<?php
namespace app\admin\middleware;

use think\facade\Session;
use think\facade\Cache;
use think\Db;

class Auth
{
    // 不需要按钮权限校验的路由前缀
    private static $whitelist = [
        'auth/',
        'profile',
        'server/',
        'system_config/read',
        'notice/published',
    ];

    public function handle($request, \Closure $next)
    {
        // 维护模式检查：超管不受影响
        $adminId = Session::get('admin_id');
        if ($adminId != 1) {
            try {
                if (Cache::get('system:maintenance')) {
                    return json(['code' => 1001, 'msg' => '系统维护中', 'data' => null]);
                }
            } catch (\Exception $e) {}
        }

        if (!$adminId) {
            return json(['code' => 1001, 'msg' => '未登录', 'data' => null]);
        }

        $admin = Db::table('admin')->where('id', $adminId)->where('status', 1)->find();
        if (!$admin) {
            Session::delete('admin_id');
            return json(['code' => 1001, 'msg' => '账号已被禁用', 'data' => null]);
        }

        $request->adminId = $adminId;
        $request->admin = $admin;

        // 获取权限标识（包含 type=3 按钮的 path）
        $menuIds = Db::table('admin_role')
            ->alias('ar')
            ->join('role_menu rm', 'ar.role_id = rm.role_id')
            ->where('ar.admin_id', $adminId)
            ->column('rm.menu_id');

        $authPaths = [];
        if (!empty($menuIds)) {
            $authPaths = Db::table('menu')
                ->whereIn('id', $menuIds)
                ->where('status', 1)
                ->where('path', '<>', '')
                ->column('path');
        }

        $request->authPaths = $authPaths;

        // 超级管理员跳过按钮权限校验
        if ($adminId == 1) {
            return $next($request);
        }

        // 白名单路由跳过校验
        $path = $request->path();
        $suffix = preg_replace('#^admin/#', '', $path);
        foreach (self::$whitelist as $prefix) {
            if (strpos($suffix, $prefix) === 0) {
                return $next($request);
            }
        }

        // 从 URL 解析权限标识: /admin/admin/add → admin:add
        $perm = $this->parsePermission($path);
        if ($perm && !in_array($perm, $authPaths)) {
            return json(['code' => 1007, 'msg' => '无权限', 'data' => null]);
        }

        return $next($request);
    }

    /**
     * 从请求路径解析权限标识
     * /admin/admin/list → admin:list
     * /admin/role/edit  → role:edit
     * /admin/dict_data/items → dict_data:items
     */
    private function parsePermission(string $path): ?string
    {
        // 去掉 /admin/ 前缀
        $suffix = preg_replace('#^admin/#', '', $path);
        if (!$suffix || $suffix === $path) {
            return null;
        }

        $parts = explode('/', $suffix);
        if (count($parts) < 2) {
            return null;
        }

        return $parts[0] . ':' . $parts[1];
    }
}
