<?php
namespace app\admin\controller;

use think\Controller;
use think\Db;

class DictType extends Controller
{
    // GET /admin/dict_type/list
    public function index()
    {
        $keyword = input('get.keyword', '');
        $page    = input('get.page', 1);
        $limit   = input('get.limit', 20);

        $query = Db::table('dict_type');
        if (!empty($keyword)) {
            $query->where('name|code', 'like', "%{$keyword}%");
        }
        $total = $query->count();
        $list  = $query->order('id', 'asc')->page($page, $limit)->select();

        return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => $list, 'total' => $total]]);
    }

    // POST /admin/dict_type/add
    public function save()
    {
        $code   = input('post.code', '');
        $name   = input('post.name', '');
        $status = input('post.status', 1);
        $remark = input('post.remark', '');

        if (empty($code) || empty($name)) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        $exists = Db::table('dict_type')->where('code', $code)->find();
        if ($exists) {
            return json(['code' => 1005, 'msg' => '类型编码已存在', 'data' => null]);
        }

        Db::table('dict_type')->insert([
            'code'   => $code,
            'name'   => $name,
            'status' => $status,
            'remark' => $remark,
        ]);

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // PUT /admin/dict_type/edit
    public function update()
    {
        $id     = input('put.id', 0);
        $code   = input('put.code', '');
        $name   = input('put.name', '');
        $status = input('put.status', 1);
        $remark = input('put.remark', '');

        if ($id <= 0 || empty($code) || empty($name)) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        $exists = Db::table('dict_type')->where('code', $code)->where('id', '<>', $id)->find();
        if ($exists) {
            return json(['code' => 1005, 'msg' => '类型编码已存在', 'data' => null]);
        }

        Db::table('dict_type')->where('id', $id)->update([
            'code'   => $code,
            'name'   => $name,
            'status' => $status,
            'remark' => $remark,
        ]);

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // DELETE /admin/dict_type/delete
    public function delete()
    {
        $id = input('delete.id', 0);
        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        Db::table('dict_data')->where('type_id', $id)->delete();
        Db::table('dict_type')->where('id', $id)->delete();

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }
}
