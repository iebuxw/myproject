<?php
namespace app\admin\controller;

use think\Controller;
use think\Db;
use app\admin\traits\ExcelTrait;

class User extends Controller
{
    use ExcelTrait;

    // GET /admin/user/list
    public function index()
    {
        $page     = input('get.page', 1);
        $limit    = input('get.limit', 10);
        $phone    = input('get.phone', '');
        $nickname = input('get.nickname', '');

        $query = Db::table('user')->field('id,phone,nickname,email,gender,avatar,status,created_at,updated_at');
        if (!empty($phone)) {
            $query->where('phone', 'like', "%$phone%");
        }
        if (!empty($nickname)) {
            $query->where('nickname', 'like', "%$nickname%");
        }
        $total = $query->count();
        $list  = $query->order('id', 'desc')->page((int)$page, (int)$limit)->select();
        return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => $list, 'total' => $total]]);
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

    // GET /admin/user/export
    public function export()
    {
        $phone    = input('get.phone', '');
        $nickname = input('get.nickname', '');

        $query = Db::table('user')->field('id,phone,nickname,email,gender,status,created_at');
        if (!empty($phone)) {
            $query->where('phone', 'like', "%$phone%");
        }
        if (!empty($nickname)) {
            $query->where('nickname', 'like', "%$nickname%");
        }
        $query->order('id', 'desc');

        $genderMap = ['未知', '男', '女'];

        return $this->exportToXlsx([
            'query'    => $query,
            'headers'  => ['ID', '手机号', '昵称', '邮箱', '性别', '状态', '创建时间'],
            'columns'  => ['id', 'phone', 'nickname', 'email', 'gender', 'status', 'created_at'],
            'maps'     => [
                'gender' => $genderMap,
                'status' => [0 => '禁用', 1 => '启用'],
            ],
            'filename' => '用户列表',
        ]);
    }

    // POST /admin/user/import
    public function import()
    {
        $genderMap = ['未知' => 0, '男' => 1, '女' => 2];
        $statusMap = ['启用' => 1, '禁用' => 0];

        return $this->importFromXlsx([
            'file'     => request()->file('file'),
            'fields'   => ['phone' => '手机号', 'nickname' => '昵称', 'email' => '邮箱', 'gender' => '性别', 'status' => '状态'],
            'required' => ['phone'],
            'unique'   => ['phone'],
            'table'    => 'user',
            'validate' => function ($row, $line) {
                if (!preg_match('/^1[3-9]\d{9}$/', $row['phone'])) {
                    return "第{$line}行：手机号格式不正确";
                }
                if (!empty($row['email']) && !preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $row['email'])) {
                    return "第{$line}行：邮箱格式不正确";
                }
                return null;
            },
            'transform' => function ($row) use ($genderMap, $statusMap) {
                $row['gender'] = $genderMap[$row['gender']] ?? 0;
                $row['status'] = $statusMap[$row['status']] ?? 1;
                return $row;
            },
            'defaults' => ['password' => password_hash('123456', PASSWORD_BCRYPT)],
        ]);
    }
}
