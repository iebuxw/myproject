<?php
namespace app\admin\controller;

use think\Controller;
use think\Db;

class Attachment extends Controller
{
    private static $mimeMap = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/gif'       => 'gif',
        'image/webp'      => 'webp',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/zip' => 'zip',
        'application/x-rar-compressed' => 'rar',
    ];

    private static $maxSize = 10485760; // 10MB

    // GET /admin/attachment/list
    public function index()
    {
        $page    = input('get.page', 1);
        $limit   = input('get.limit', 10);
        $keyword = input('get.keyword', '');

        $query = Db::table('attachment');
        if (!empty($keyword)) {
            $query->where('original_name', 'like', "%{$keyword}%");
        }
        $total = $query->count();
        $list  = $query->order('id', 'desc')->page((int)$page, (int)$limit)->select();

        $adminIds = array_unique(array_filter(array_column($list, 'uploader_id')));
        $adminMap = [];
        if (!empty($adminIds)) {
            $rows = Db::table('admin')->whereIn('id', $adminIds)->column('username', 'id');
            $adminMap = $rows;
        }
        foreach ($list as &$row) {
            $row['uploader_name'] = $adminMap[$row['uploader_id']] ?? '';
        }
        unset($row);

        return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => $list, 'total' => $total]]);
    }

    // POST /admin/attachment/upload
    public function upload()
    {
        $file = $this->request->file('file');
        if (!$file) {
            return json(['code' => 1002, 'msg' => '请选择文件', 'data' => null]);
        }

        if ($file->getInfo('size') > self::$maxSize) {
            return json(['code' => 1002, 'msg' => '文件不能超过10MB', 'data' => null]);
        }

        $tmpPath = $file->getRealPath();
        $finfo   = finfo_open(FILEINFO_MIME_TYPE);
        $mime    = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        if (!isset(self::$mimeMap[$mime])) {
            $allowed = implode(', ', array_unique(array_values(self::$mimeMap)));
            return json(['code' => 1002, 'msg' => '不支持的文件类型，允许: ' . $allowed, 'data' => null]);
        }

        $ext = self::$mimeMap[$mime];
        $adminId = session('admin_id') ?: 0;
        $savedName = $adminId . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        $saveDir = '/var/www/html/uploads/attachments';
        if (!is_dir($saveDir)) {
            mkdir($saveDir, 0755, true);
        }
        $savePath = $saveDir . '/' . $savedName;

        if (!move_uploaded_file($tmpPath, $savePath)) {
            return json(['code' => 500, 'msg' => '文件保存失败', 'data' => null]);
        }

        $filePath = '/uploads/attachments/' . $savedName;
        $originalName = $file->getInfo('name');
        $fileSize = $file->getInfo('size');

        Db::table('attachment')->insert([
            'original_name' => $originalName,
            'saved_name'    => $savedName,
            'file_path'     => $filePath,
            'file_size'     => (int)$fileSize,
            'mime_type'     => $mime,
            'ext'           => $ext,
            'uploader_id'   => (int)$adminId,
        ]);

        return json(['code' => 0, 'msg' => '上传成功', 'data' => [
            'id'            => Db::getLastInsID(),
            'original_name' => $originalName,
            'file_path'     => $filePath,
            'file_size'     => (int)$fileSize,
            'mime_type'     => $mime,
            'ext'           => $ext,
        ]]);
    }

    // DELETE /admin/attachment/delete
    public function delete()
    {
        $id = input('delete.id', 0);
        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        $row = Db::table('attachment')->where('id', $id)->find();
        if (!$row) {
            return json(['code' => 1004, 'msg' => '文件不存在', 'data' => null]);
        }

        $physicalPath = '/var/www/html' . $row['file_path'];
        if (file_exists($physicalPath)) {
            unlink($physicalPath);
        }

        Db::table('attachment')->where('id', $id)->delete();
        return json(['code' => 0, 'msg' => '删除成功', 'data' => null]);
    }
}
