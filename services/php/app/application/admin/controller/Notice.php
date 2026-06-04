<?php
namespace app\admin\controller;

use think\Controller;
use think\Db;

class Notice extends Controller
{
    // GET /admin/notice/list
    public function index()
    {
        $page   = input('get.page', 1);
        $limit  = input('get.limit', 10);
        $title  = input('get.title', '');
        $status = input('get.status', -1);

        $query = Db::table('notice')->alias('n')
            ->field('n.id,n.title,n.content,n.admin_id,n.status,n.created_at,n.updated_at,a.username as admin_name')
            ->join('admin a', 'n.admin_id = a.id', 'LEFT');

        if (!empty($title)) {
            $query->where('n.title', 'like', "%$title%");
        }
        if ($status != -1) {
            $query->where('n.status', (int)$status);
        }

        $total = $query->count();
        $list  = $query->order('n.id', 'desc')->page((int)$page, (int)$limit)->select();

        return json(['code' => 0, 'msg' => 'success', 'data' => [
            'list'  => $list,
            'total' => $total,
        ]]);
    }

    // GET /admin/notice/published — 已发布公告列表（无需按钮权限，所有管理员可看）
    public function published()
    {
        $list = Db::table('notice')->alias('n')
            ->field('n.id,n.title,n.content,n.created_at,a.username as admin_name')
            ->join('admin a', 'n.admin_id = a.id', 'LEFT')
            ->where('n.status', 1)
            ->order('n.id', 'desc')
            ->limit(20)
            ->select();

        return json(['code' => 0, 'msg' => 'success', 'data' => $list]);
    }

    // POST /admin/notice/add
    public function save()
    {
        $title   = input('post.title', '');
        $content = input('post.content', '');
        $status  = input('post.status', 1);
        $adminId = request()->adminId;

        if (empty($title) || empty($content)) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        Db::table('notice')->insert([
            'title'    => $title,
            'content'  => $content,
            'admin_id' => $adminId,
            'status'   => (int)$status,
        ]);

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // PUT /admin/notice/edit
    public function update()
    {
        $id      = input('put.id', 0);
        $title   = input('put.title', '');
        $content = input('put.content', '');
        $status  = input('put.status');

        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        $data = [];
        if (!empty($title)) {
            $data['title'] = $title;
        }
        if (!empty($content)) {
            $data['content'] = $content;
        }
        if ($status !== null && $status !== '') {
            $data['status'] = (int)$status;
        }

        if (!empty($data)) {
            Db::table('notice')->where('id', $id)->update($data);
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // DELETE /admin/notice/delete
    public function delete()
    {
        $id = input('delete.id', 0);
        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        Db::table('notice')->where('id', $id)->delete();

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }
}
