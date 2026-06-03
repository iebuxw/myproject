<?php
namespace app\admin\controller;

use think\Controller;
use think\Db;

class SystemConfig extends Controller
{
    // GET /admin/system_config/read
    public function read()
    {
        $rows = Db::table('system_config')->column('value', 'key');

        $logoUrl = '';
        if (!empty($rows['logo'])) {
            $att = Db::table('attachment')->where('id', (int)$rows['logo'])->find();
            if ($att) {
                $logoUrl = $att['file_path'];
            }
        }

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'site_name' => $rows['site_name'] ?? '',
                'logo'      => $rows['logo'] ?? '',
                'logo_url'  => $logoUrl,
            ],
        ]);
    }

    // PUT /admin/system_config/update
    public function update()
    {
        $siteName = input('put.site_name', '');
        $logo     = input('put.logo', '');

        if (empty($siteName)) {
            return json(['code' => 1002, 'msg' => '站点名称不能为空', 'data' => null]);
        }
        if (mb_strlen($siteName) > 50) {
            return json(['code' => 1002, 'msg' => '站点名称不能超过50个字符', 'data' => null]);
        }

        Db::table('system_config')->where('key', 'site_name')->update(['value' => $siteName]);
        Db::table('system_config')->where('key', 'logo')->update(['value' => $logo]);

        return json(['code' => 0, 'msg' => '保存成功', 'data' => null]);
    }
}
