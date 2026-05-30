<?php
namespace app\admin\controller;

use think\Db;

class OperationLog
{
    // GET /admin/operation_log/list
    public function index()
    {
        $page    = input('get.page', 1);
        $limit   = input('get.limit', 10);
        $module  = input('get.module', '');
        $username = input('get.username', '');
        $startAt = input('get.start_at', '');
        $endAt   = input('get.end_at', '');

        $query = Db::table('operation_log');
        if (!empty($module)) {
            $query->where('module', $module);
        }
        if (!empty($username)) {
            $query->where('username', 'like', "%$username%");
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

    // DELETE /admin/operation_log/delete
    public function delete()
    {
        $id = input('delete.id', 0);
        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        Db::table('operation_log')->where('id', $id)->delete();

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }
}
