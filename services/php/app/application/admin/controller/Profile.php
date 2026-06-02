<?php
namespace app\admin\controller;

use think\Controller;
use think\Db;

class Profile extends Controller
{
    // 获取当前管理员信息
    public function read()
    {
        $admin = $this->request->admin;
        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'id'         => $admin['id'],
                'username'   => $admin['username'],
                'avatar'     => $admin['avatar'],
                'status'     => $admin['status'],
                'created_at' => $admin['created_at'],
            ],
        ]);
    }

    // 上传头像
    public function avatar()
    {
        $file = $this->request->file('file');
        if (!$file) {
            return json(['code' => 1002, 'msg' => '请上传头像文件', 'data' => null]);
        }

        if ($file->getInfo('size') > 2 * 1024 * 1024) {
            return json(['code' => 1002, 'msg' => '头像文件不能超过2MB', 'data' => null]);
        }

        // 验证实际文件内容，防止伪装扩展名
        $tmpPath   = $file->getRealPath();
        $imageInfo = @getimagesize($tmpPath);
        if (!$imageInfo) {
            return json(['code' => 1002, 'msg' => '文件不是有效图片', 'data' => null]);
        }

        $mimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
        $mime      = $imageInfo['mime'];
        if (!isset($mimeToExt[$mime])) {
            return json(['code' => 1002, 'msg' => '只支持 jpg/png/gif 格式', 'data' => null]);
        }
        $ext = $mimeToExt[$mime];

        $adminId  = $this->request->adminId;
        $saveDir  = '/var/www/html/uploads/avatars';
        $filename = $adminId . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;

        if (!is_dir($saveDir)) {
            if (!mkdir($saveDir, 0755, true)) {
                return json(['code' => 500, 'msg' => '上传目录创建失败', 'data' => null]);
            }
        }

        $savePath = $saveDir . '/' . $filename;
        if (!move_uploaded_file($tmpPath, $savePath)) {
            return json(['code' => 500, 'msg' => '头像上传失败', 'data' => null]);
        }

        $avatarUrl = '/uploads/avatars/' . $filename;

        // 删除旧头像文件
        $oldAvatar = $this->request->admin['avatar'];
        if (!empty($oldAvatar) && strpos($oldAvatar, '/uploads/avatars/') === 0) {
            $oldPath = '/var/www/html' . $oldAvatar;
            if (file_exists($oldPath) && !unlink($oldPath)) {
                \think\facade\Log::warning('Failed to delete old avatar: ' . $oldPath);
            }
        }

        Db::table('admin')->where('id', $adminId)->update(['avatar' => $avatarUrl]);

        return json(['code' => 0, 'msg' => 'success', 'data' => ['avatar' => $avatarUrl]]);
    }

    // 修改密码
    public function password()
    {
        $oldPassword = input('put.old_password', '');
        $newPassword = input('put.new_password', '');

        if (empty($oldPassword) || empty($newPassword)) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        if (strlen($newPassword) < 6) {
            return json(['code' => 1002, 'msg' => '新密码长度不能少于6位', 'data' => null]);
        }

        $admin = $this->request->admin;
        if (!password_verify($oldPassword, $admin['password'])) {
            return json(['code' => 1003, 'msg' => '原密码错误', 'data' => null]);
        }

        Db::table('admin')->where('id', $admin['id'])->update([
            'password' => password_hash($newPassword, PASSWORD_BCRYPT),
        ]);

        return json(['code' => 0, 'msg' => '密码修改成功', 'data' => null]);
    }

    // 更新基本信息（预留，后续可添加 nickname/email 等字段）
    public function update()
    {
        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }
}
