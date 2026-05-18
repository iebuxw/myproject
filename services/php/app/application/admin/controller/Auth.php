<?php
namespace app\admin\controller;

use think\facade\Session;
use think\facade\Db;

class Auth
{
    // POST /admin/auth/login
    public function login()
    {
        $username = input('post.username', '');
        $password = input('post.password', '');

        if (empty($username) || empty($password)) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        $admin = Db::table('admin')->where('username', $username)->where('status', 1)->find();
        if (!$admin || !password_verify($password, $admin['password'])) {
            return json(['code' => 1003, 'msg' => '用户名或密码错误', 'data' => null]);
        }

        Session::set('admin_id', $admin['id']);

        return json(['code' => 0, 'msg' => 'success', 'data' => [
            'token' => session_id(),
        ]]);
    }

    // POST /admin/auth/logout
    public function logout()
    {
        Session::delete('admin_id');
        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // GET /admin/auth/info
    public function info()
    {
        $adminId = Session::get('admin_id');
        if (!$adminId) {
            return json(['code' => 1001, 'msg' => '未登录', 'data' => null]);
        }

        $admin = Db::table('admin')->field('id,username,avatar,status,created_at')->where('id', $adminId)->find();
        if (!$admin) {
            return json(['code' => 1004, 'msg' => '用户不存在', 'data' => null]);
        }

        // 获取角色
        $roles = Db::table('admin_role')
            ->alias('ar')
            ->join('role r', 'ar.role_id = r.id')
            ->where('ar.admin_id', $adminId)
            ->where('r.status', 1)
            ->column('r.name');

        // 获取菜单权限
        $menuIds = Db::table('admin_role')
            ->alias('ar')
            ->join('role_menu rm', 'ar.role_id = rm.role_id')
            ->where('ar.admin_id', $adminId)
            ->column('rm.menu_id');

        $menus = [];
        if (!empty($menuIds)) {
            $menus = Db::table('menu')
                ->whereIn('id', $menuIds)
                ->where('status', 1)
                ->where('type', '<>', 3)
                ->order('sort', 'asc')
                ->select()
                ->toArray();
        }

        // 生成树形菜单
        $menuTree = $this->buildTree($menus);

        return json(['code' => 0, 'msg' => 'success', 'data' => [
            'admin'  => $admin,
            'roles'  => $roles,
            'menus'  => $menuTree,
        ]]);
    }

    private function buildTree(array $menus, int $parentId = 0): array
    {
        $tree = [];
        foreach ($menus as $menu) {
            if ($menu['parent_id'] == $parentId) {
                $children = $this->buildTree($menus, $menu['id']);
                if (!empty($children)) {
                    $menu['children'] = $children;
                }
                $tree[] = $menu;
            }
        }
        return $tree;
    }
}
