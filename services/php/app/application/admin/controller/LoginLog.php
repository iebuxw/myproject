<?php
namespace app\admin\controller;

use think\Controller;
use think\Db;

class LoginLog extends Controller
{
    // GET /admin/login_log/list
    public function index()
    {
        $page     = input('get.page', 1);
        $limit    = input('get.limit', 10);
        $username = input('get.username', '');
        $status   = input('get.status', -1);
        $startAt  = input('get.start_at', '');
        $endAt    = input('get.end_at', '');

        $query = Db::table('login_log');
        if (!empty($username)) {
            $query->where('username', 'like', "%$username%");
        }
        if ($status != -1) {
            $query->where('status', (int)$status);
        }
        if (!empty($startAt)) {
            $query->where('created_at', '>=', $startAt);
        }
        if (!empty($endAt)) {
            $query->where('created_at', '<=', $endAt . ' 23:59:59');
        }

        $total = $query->count();
        $list  = $query->order('id', 'desc')->page((int)$page, (int)$limit)->select();

        return json(['code' => 0, 'msg' => 'success', 'data' => [
            'list'  => $list,
            'total' => $total,
        ]]);
    }

    // DELETE /admin/login_log/delete
    public function delete()
    {
        $id = input('delete.id', 0);
        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        Db::table('login_log')->where('id', $id)->delete();

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }
}
