<?php
namespace app\admin\controller;

use think\Db;

class Menu
{
    protected $middleware = ['app\admin\middleware\Auth'];

    // GET /admin/menu/list
    public function index()
    {
        $result = Db::table('menu')->order('sort', 'asc')->select();
        $menus = is_object($result) ? $result->toArray() : $result;
        $tree = $this->buildTree($menus);
        return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => $tree]]);
    }

    // POST /admin/menu/add
    public function save()
    {
        $data = [
            'parent_id' => input('post.parent_id', 0),
            'name'      => input('post.name', ''),
            'path'      => input('post.path', ''),
            'icon'      => input('post.icon', ''),
            'sort'      => input('post.sort', 0),
            'type'      => input('post.type', 2),
            'status'    => input('post.status', 1),
        ];

        if (empty($data['name'])) {
            return json(['code' => 1002, 'msg' => '菜单名不能为空', 'data' => null]);
        }

        Db::table('menu')->insert($data);
        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // PUT /admin/menu/edit
    public function update()
    {
        $id = input('put.id', 0);
        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        $data = [];
        $fields = ['parent_id', 'name', 'path', 'icon', 'sort', 'type', 'status'];
        foreach ($fields as $f) {
            $val = input('put.' . $f);
            if ($val !== null && $val !== '') {
                $data[$f] = $val;
            }
        }

        if (empty($data)) {
            return json(['code' => 1002, 'msg' => '无更新数据', 'data' => null]);
        }

        Db::table('menu')->where('id', $id)->update($data);
        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // DELETE /admin/menu/delete
    public function delete()
    {
        $id = input('delete.id', 0);
        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        // 递归删除子菜单
        $this->deleteChildren($id);

        Db::table('role_menu')->where('menu_id', $id)->delete();

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    private function deleteChildren(int $parentId)
    {
        $children = Db::table('menu')->where('parent_id', $parentId)->column('id');
        foreach ($children as $childId) {
            $this->deleteChildren($childId);
            Db::table('role_menu')->where('menu_id', $childId)->delete();
        }
        Db::table('menu')->where('parent_id', $parentId)->delete();
        Db::table('menu')->where('id', $parentId)->delete();
    }

    private function buildTree(array $menus, int $parentId = 0): array
    {
        $tree = [];
        foreach ($menus as $menu) {
            if ($menu['parent_id'] == $parentId) {
                $children = $this->buildTree($menus, $menu['id']);
                if (!empty($children)) {
                    $menu['children'] = $children;
                }
                $tree[] = $menu;
            }
        }
        return $tree;
    }
}
