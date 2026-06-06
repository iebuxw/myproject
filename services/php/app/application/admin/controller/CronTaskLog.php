<?php
namespace app\admin\controller;

use think\Controller;
use think\Db;

class CronTaskLog extends Controller
{
    // GET /admin/cron_task_log/list
    public function index()
    {
        $page      = input('get.page', 1);
        $limit     = input('get.limit', 10);
        $taskId    = input('get.task_id', 0);
        $status    = input('get.status', -1);
        $startDate = input('get.start_date', '');
        $endDate   = input('get.end_date', '');

        $query = Db::table('cron_task_log');

        if ($taskId > 0) {
            $query->where('task_id', $taskId);
        }
        if ($status != -1) {
            $query->where('status', (int)$status);
        }
        if (!empty($startDate)) {
            $query->where('started_at', '>=', $startDate . ' 00:00:00');
        }
        if (!empty($endDate)) {
            $query->where('started_at', '<=', $endDate . ' 23:59:59');
        }

        $total = $query->count();
        $list  = $query->order('id', 'desc')->page((int)$page, (int)$limit)->select();

        $taskIds = array_unique(array_filter(array_column($list, 'task_id')));
        $taskMap = [];
        if (!empty($taskIds)) {
            $tasks = Db::table('cron_task')->where('id', 'in', $taskIds)->column('name', 'id');
            $taskMap = $tasks;
        }
        foreach ($list as &$item) {
            $item['task_name'] = isset($taskMap[$item['task_id']]) ? $taskMap[$item['task_id']] : '(已删除)';
        }
        unset($item);

        return json(['code' => 0, 'msg' => 'success', 'data' => [
            'list'  => $list,
            'total' => $total,
        ]]);
    }

    // DELETE /admin/cron_task_log/delete
    public function delete()
    {
        $id = input('delete.id', 0);
        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        Db::table('cron_task_log')->where('id', $id)->delete();

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }
}
