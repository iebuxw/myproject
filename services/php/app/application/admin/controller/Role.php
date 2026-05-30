<?php
namespace app\admin\controller;

use think\Db;

class Role
{
    protected $middleware = ['app\admin\middleware\Auth', 'app\admin\middleware\OperationLog'];

    // GET /admin/role/list
    public function index()
    {
        $list = Db::table('role')->order('id', 'asc')->select();

        // 查询每个角色拥有的菜单ID
        foreach ($list as &$role) {
            $role['menu_ids'] = Db::table('role_menu')->where('role_id', $role['id'])->column('menu_id');
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => $list]]);
    }

    // POST /admin/role/add
    public function save()
    {
        $name   = input('post.name', '');
        $desc   = input('post.description', '');
        $menuIds = input('post.menu_ids', []);

        if (empty($name)) {
            return json(['code' => 1002, 'msg' => '角色名不能为空', 'data' => null]);
        }

        $exists = Db::table('role')->where('name', $name)->find();
        if ($exists) {
            return json(['code' => 1005, 'msg' => '角色名已存在', 'data' => null]);
        }

        $roleId = Db::table('role')->insertGetId(['name' => $name, 'description' => $desc]);

        if (!empty($menuIds)) {
            foreach ((array)$menuIds as $menuId) {
                Db::table('role_menu')->insert(['role_id' => $roleId, 'menu_id' => $menuId]);
            }
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // PUT /admin/role/edit
    public function update()
    {
        $id      = input('put.id', 0);
        $name    = input('put.name', '');
        $desc    = input('put.description', '');
        $menuIds = input('put.menu_ids', []);

        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        if ($id == 1 && !empty($name)) {
            return json(['code' => 1006, 'msg' => '不能修改超级管理员角色名', 'data' => null]);
        }

        if (!empty($name)) {
            $exists = Db::table('role')->where('name', $name)->where('id', '<>', $id)->find();
            if ($exists) {
                return json(['code' => 1005, 'msg' => '角色名已存在', 'data' => null]);
            }
        }

        Db::table('role')->where('id', $id)->update(['name' => $name, 'description' => $desc]);

        // 更新菜单权限
        Db::table('role_menu')->where('role_id', $id)->delete();
        if (!empty($menuIds)) {
            foreach ((array)$menuIds as $menuId) {
                Db::table('role_menu')->insert(['role_id' => $id, 'menu_id' => $menuId]);
            }
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // DELETE /admin/role/delete
    public function delete()
    {
        $id = input('delete.id', 0);
        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        if ($id == 1) {
            return json(['code' => 1006, 'msg' => '不能删除超级管理员角色', 'data' => null]);
        }

        Db::table('role')->where('id', $id)->delete();
        Db::table('role_menu')->where('role_id', $id)->delete();
        Db::table('admin_role')->where('role_id', $id)->delete();

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }
}
