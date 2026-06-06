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
            ->field('n.id,n.title,n.content,n.admin_id,n.status,n.is_popup,n.created_at,n.updated_at,a.username as admin_name')
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
            ->field('n.id,n.title,n.content,n.is_popup,n.created_at,a.username as admin_name')
            ->join('admin a', 'n.admin_id = a.id', 'LEFT')
            ->where('n.status', 1)
            ->order('n.id', 'desc')
            ->limit(10)
            ->select();

        return json(['code' => 0, 'msg' => 'success', 'data' => $list]);
    }

    // GET /admin/notice/popup — 登录后弹窗公告
    public function popup()
    {
        $admin = request()->admin;

        $query = Db::table('notice')->alias('n')
            ->field('n.id,n.title,n.content,n.created_at,a.username as admin_name')
            ->join('admin a', 'n.admin_id = a.id', 'LEFT')
            ->where('n.status', 1)
            ->where('n.is_popup', 1);

        if (!empty($admin['last_notice_seen_id'])) {
            $query->where('n.id', '>', $admin['last_notice_seen_id']);
        }

        $list = $query->order('n.id', 'desc')->limit(10)->select();

        return json(['code' => 0, 'msg' => 'success', 'data' => $list]);
    }

    // POST /admin/notice/seen — 标记已查看弹窗公告
    public function seen()
    {
        $maxId = input('post.max_id', 0);
        if ($maxId > 0) {
            Db::table('admin')->where('id', request()->adminId)->update([
                'last_notice_seen_id' => (int)$maxId,
            ]);
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // POST /admin/notice/add
    public function save()
    {
        $title    = input('post.title', '');
        $content  = input('post.content', '');
        $status   = input('post.status', 1);
        $isPopup  = input('post.is_popup', 0);
        $adminId  = request()->adminId;

        if (empty($title) || empty($content)) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        Db::table('notice')->insert([
            'title'    => $title,
            'content'  => $content,
            'admin_id' => $adminId,
            'status'   => (int)$status,
            'is_popup' => (int)$isPopup,
        ]);

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // PUT /admin/notice/edit
    public function update()
    {
        $id       = input('put.id', 0);
        $title    = input('put.title', '');
        $content  = input('put.content', '');
        $status   = input('put.status');
        $isPopup  = input('put.is_popup');

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
        if ($isPopup !== null && $isPopup !== '') {
            $data['is_popup'] = (int)$isPopup;
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
