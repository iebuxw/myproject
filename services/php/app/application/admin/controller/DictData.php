<?php
namespace app\admin\controller;

use think\Controller;
use think\Db;

class DictData extends Controller
{
    // GET /admin/dict_data/list
    public function index()
    {
        $typeId = input('get.type_id', 0);
        $page   = input('get.page', 1);
        $limit  = input('get.limit', 20);

        if ($typeId <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误：缺少 type_id', 'data' => null]);
        }

        $query = Db::table('dict_data')->where('type_id', $typeId);
        $total = $query->count();
        $list  = $query->order('sort', 'asc')->order('id', 'asc')->page($page, $limit)->select();

        return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => $list, 'total' => $total]]);
    }

    // POST /admin/dict_data/add
    public function save()
    {
        $typeId = input('post.type_id', 0);
        $label  = input('post.label', '');
        $value  = input('post.value', '');
        $sort   = input('post.sort', 0);
        $status = input('post.status', 1);
        $remark = input('post.remark', '');

        if ($typeId <= 0 || empty($label) || $value === '') {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        $exists = Db::table('dict_data')->where('type_id', $typeId)->where('value', $value)->find();
        if ($exists) {
            return json(['code' => 1005, 'msg' => '字典值已存在', 'data' => null]);
        }

        Db::table('dict_data')->insert([
            'type_id' => $typeId,
            'label'   => $label,
            'value'   => $value,
            'sort'    => $sort,
            'status'  => $status,
            'remark'  => $remark,
        ]);

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // PUT /admin/dict_data/edit
    public function update()
    {
        $id     = input('put.id', 0);
        $label  = input('put.label', '');
        $value  = input('put.value', '');
        $sort   = input('put.sort', 0);
        $status = input('put.status', 1);
        $remark = input('put.remark', '');

        if ($id <= 0 || empty($label) || $value === '') {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        $item = Db::table('dict_data')->where('id', $id)->find();
        if (!$item) {
            return json(['code' => 1004, 'msg' => '字典项不存在', 'data' => null]);
        }

        $exists = Db::table('dict_data')->where('type_id', $item['type_id'])->where('value', $value)->where('id', '<>', $id)->find();
        if ($exists) {
            return json(['code' => 1005, 'msg' => '字典值已存在', 'data' => null]);
        }

        Db::table('dict_data')->where('id', $id)->update([
            'label'  => $label,
            'value'  => $value,
            'sort'   => $sort,
            'status' => $status,
            'remark' => $remark,
        ]);

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // DELETE /admin/dict_data/delete
    public function delete()
    {
        $id = input('delete.id', 0);
        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        Db::table('dict_data')->where('id', $id)->delete();

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // GET /admin/dict_data/items?codes=gender,status
    public function items()
    {
        $codes = input('get.codes', '');
        if (empty($codes)) {
            return json(['code' => 1002, 'msg' => '参数错误：缺少 codes', 'data' => null]);
        }

        $codeArr = explode(',', $codes);
        $types = Db::table('dict_type')->whereIn('code', $codeArr)->where('status', 1)->select();
        $typeMap = [];
        foreach ($types as $t) {
            $typeMap[$t['code']] = $t['id'];
        }

        $result = [];
        foreach ($codeArr as $code) {
            $result[$code] = [];
        }

        if (!empty($typeMap)) {
            $items = Db::table('dict_data')
                ->whereIn('type_id', array_values($typeMap))
                ->where('status', 1)
                ->order('sort', 'asc')
                ->select();

            foreach ($items as $item) {
                $code = array_search($item['type_id'], $typeMap);
                if ($code !== false) {
                    $result[$code][] = ['label' => $item['label'], 'value' => $item['value']];
                }
            }
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => $result]);
    }
}
