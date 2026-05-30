<?php
namespace app\admin\controller;

use think\Db;

class User
{
    protected $middleware = ['app\admin\middleware\Auth', 'app\admin\middleware\OperationLog'];

    // GET /admin/user/list
    public function index()
    {
        $phone   = input('get.phone', '');
        $nickname = input('get.nickname', '');

        $query = Db::table('user')->field('id,phone,nickname,email,gender,avatar,status,created_at,updated_at');
        if (!empty($phone)) {
            $query->where('phone', 'like', "%$phone%");
        }
        if (!empty($nickname)) {
            $query->where('nickname', 'like', "%$nickname%");
        }
        $list = $query->order('id', 'desc')->select();
        return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => $list]]);
    }

    // POST /admin/user/add
    public function save()
    {
        $phone    = input('post.phone', '');
        $password = input('post.password', '');
        $nickname = input('post.nickname', '');
        $email    = input('post.email', '');
        $gender   = input('post.gender', 0);

        if (empty($phone) || empty($password)) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        $exists = Db::table('user')->where('phone', $phone)->find();
        if ($exists) {
            return json(['code' => 1005, 'msg' => '手机号已存在', 'data' => null]);
        }

        Db::table('user')->insert([
            'phone'    => $phone,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'nickname' => $nickname,
            'email'    => $email,
            'gender'   => (int)$gender,
        ]);

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // PUT /admin/user/edit
    public function update()
    {
        $id       = input('put.id', 0);
        $password = input('put.password', '');
        $nickname = input('put.nickname', '');
        $email    = input('put.email', '');
        $gender   = input('put.gender', -1);
        $status   = input('put.status', -1);

        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        $data = [];
        if ($nickname !== '')   $data['nickname'] = $nickname;
        if ($email !== '')      $data['email'] = $email;
        if ($gender != -1)      $data['gender'] = (int)$gender;
        if ($status != -1)      $data['status'] = (int)$status;
        if (!empty($password))  $data['password'] = password_hash($password, PASSWORD_BCRYPT);

        if (!empty($data)) {
            Db::table('user')->where('id', $id)->update($data);
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // DELETE /admin/user/delete
    public function delete()
    {
        $id = input('delete.id', 0);
        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        Db::table('user')->where('id', $id)->delete();

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }
}
