<?php
namespace app\admin\controller;

use think\facade\Db;

class Admin
{
    protected $middleware = ['app\admin\middleware\Auth'];

    // GET /admin/admin/list
    public function index()
    {
        $list = Db::table('admin')->field('id,username,avatar,status,created_at,updated_at')->order('id', 'desc')->select();
        foreach ($list as &$admin) {
            $admin['role_ids'] = Db::table('admin_role')->where('admin_id', $admin['id'])->column('role_id');
        }
        return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => $list]]);
    }

    // POST /admin/admin/add
    public function save()
    {
        $username = input('post.username', '');
        $password = input('post.password', '');
        $roleIds  = input('post.role_ids', []);

        if (empty($username) || empty($password)) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        $exists = Db::table('admin')->where('username', $username)->find();
        if ($exists) {
            return json(['code' => 1005, 'msg' => '用户名已存在', 'data' => null]);
        }

        $adminId = Db::table('admin')->insertGetId([
            'username' => $username,
            'password' => password_hash($password, PASSWORD_BCRYPT),
        ]);

        if (!empty($roleIds)) {
            foreach ((array)$roleIds as $roleId) {
                Db::table('admin_role')->insert(['admin_id' => $adminId, 'role_id' => $roleId]);
            }
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // PUT /admin/admin/edit
    public function update()
    {
        $id       = input('put.id', 0);
        $password = input('put.password', '');
        $roleIds  = input('put.role_ids', []);

        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        $data = [];
        if (!empty($password)) {
            $data['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        if (!empty($data)) {
            Db::table('admin')->where('id', $id)->update($data);
        }

        // 更新角色
        if (!empty($roleIds)) {
            Db::table('admin_role')->where('admin_id', $id)->delete();
            foreach ((array)$roleIds as $roleId) {
                Db::table('admin_role')->insert(['admin_id' => $id, 'role_id' => $roleId]);
            }
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // DELETE /admin/admin/delete
    public function delete()
    {
        $id = input('delete.id', 0);
        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        if ($id == 1) {
            return json(['code' => 1006, 'msg' => '不能删除超级管理员', 'data' => null]);
        }

        Db::table('admin')->where('id', $id)->delete();
        Db::table('admin_role')->where('admin_id', $id)->delete();

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }
}
