<?php
namespace app\admin\middleware;

use think\facade\Session;
use think\Db;

class Auth
{
    public function handle($request, \Closure $next)
    {
        $adminId = Session::get('admin_id');
        if (!$adminId) {
            return json(['code' => 1001, 'msg' => '未登录', 'data' => null]);
        }

        // 将 admin 信息存入 request
        $admin = Db::table('admin')->where('id', $adminId)->where('status', 1)->find();
        if (!$admin) {
            Session::delete('admin_id');
            return json(['code' => 1001, 'msg' => '账号已被禁用', 'data' => null]);
        }

        $request->adminId = $adminId;
        $request->admin = $admin;

        // 获取权限标识
        $menuIds = Db::table('admin_role')
            ->alias('ar')
            ->join('role_menu rm', 'ar.role_id = rm.role_id')
            ->where('ar.admin_id', $adminId)
            ->column('rm.menu_id');

        $request->authPaths = [];
        if (!empty($menuIds)) {
            $request->authPaths = Db::table('menu')
                ->whereIn('id', $menuIds)
                ->where('status', 1)
                ->where('path', '<>', '')
                ->column('path');
        }

        return $next($request);
    }
}
