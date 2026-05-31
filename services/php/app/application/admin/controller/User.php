<?php
namespace app\admin\controller;

use think\Controller;
use think\Db;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class User extends Controller
{
    // GET /admin/user/list
    public function index()
    {
        $phone   = input('get.phone', '');
        $nickname = input('get.nickname', '');

        $query = Db::table('user')->field('id,phone,nickname,email,gender,avatar,status,created_at,updated_at');
        if (!empty($phone)) {
            $query->where('phone', 'like', "%$phone%");
        }
        if (!empty($nickname)) {
            $query->where('nickname', 'like', "%$nickname%");
        }
        $list = $query->order('id', 'desc')->select();
        return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => $list]]);
    }

    // POST /admin/user/add
    public function save()
    {
        $phone    = input('post.phone', '');
        $password = input('post.password', '');
        $nickname = input('post.nickname', '');
        $email    = input('post.email', '');
        $gender   = input('post.gender', 0);

        if (empty($phone) || empty($password)) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        $exists = Db::table('user')->where('phone', $phone)->find();
        if ($exists) {
            return json(['code' => 1005, 'msg' => '手机号已存在', 'data' => null]);
        }

        Db::table('user')->insert([
            'phone'    => $phone,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'nickname' => $nickname,
            'email'    => $email,
            'gender'   => (int)$gender,
        ]);

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // PUT /admin/user/edit
    public function update()
    {
        $id       = input('put.id', 0);
        $password = input('put.password', '');
        $nickname = input('put.nickname', '');
        $email    = input('put.email', '');
        $gender   = input('put.gender', -1);
        $status   = input('put.status', -1);

        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        $data = [];
        if ($nickname !== '')   $data['nickname'] = $nickname;
        if ($email !== '')      $data['email'] = $email;
        if ($gender != -1)      $data['gender'] = (int)$gender;
        if ($status != -1)      $data['status'] = (int)$status;
        if (!empty($password))  $data['password'] = password_hash($password, PASSWORD_BCRYPT);

        if (!empty($data)) {
            Db::table('user')->where('id', $id)->update($data);
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // DELETE /admin/user/delete
    public function delete()
    {
        $id = input('delete.id', 0);
        if ($id <= 0) {
            return json(['code' => 1002, 'msg' => '参数错误', 'data' => null]);
        }

        Db::table('user')->where('id', $id)->delete();

        return json(['code' => 0, 'msg' => 'success', 'data' => null]);
    }

    // GET /admin/user/export
    public function export()
    {
        $phone    = input('get.phone', '');
        $nickname = input('get.nickname', '');

        $query = Db::table('user')->field('id,phone,nickname,email,gender,status,created_at');
        if (!empty($phone)) {
            $query->where('phone', 'like', "%$phone%");
        }
        if (!empty($nickname)) {
            $query->where('nickname', 'like', "%$nickname%");
        }
        $list = $query->order('id', 'desc')->select();

        $genderMap = ['未知', '男', '女'];
        $headers = ['ID' => 'integer', '手机号' => 'string', '昵称' => 'string', '邮箱' => 'string', '性别' => 'string', '状态' => 'string', '创建时间' => 'string'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('用户列表');

        $col = 1;
        foreach ($headers as $title => $type) {
            $sheet->setCellValueByColumnAndRow($col++, 1, $title);
        }

        $rowIdx = 2;
        foreach ($list as $row) {
            $sheet->setCellValueByColumnAndRow(1, $rowIdx, $row['id']);
            $sheet->setCellValueByColumnAndRow(2, $rowIdx, $row['phone']);
            $sheet->setCellValueByColumnAndRow(3, $rowIdx, $row['nickname'] ?? '');
            $sheet->setCellValueByColumnAndRow(4, $rowIdx, $row['email'] ?? '');
            $sheet->setCellValueByColumnAndRow(5, $rowIdx, $genderMap[$row['gender']] ?? '未知');
            $sheet->setCellValueByColumnAndRow(6, $rowIdx, $row['status'] === 1 ? '启用' : '禁用');
            $sheet->setCellValueByColumnAndRow(7, $rowIdx, $row['created_at']);
            $rowIdx++;
        }

        $filename = '用户列表_' . date('YmdHis') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . urlencode($filename) . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    // POST /admin/user/import
    public function import()
    {
        $file = request()->file('file');
        if (!$file) {
            return json(['code' => 1002, 'msg' => '请上传文件', 'data' => null]);
        }

        $ext = strtolower(pathinfo($file->getInfo('name'), PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'])) {
            return json(['code' => 1002, 'msg' => '只支持 xlsx/xls 格式', 'data' => null]);
        }

        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();
        if (count($rows) <= 1) {
            return json(['code' => 1002, 'msg' => '文件中没有数据', 'data' => null]);
        }

        // 根据表头名称映射列索引
        $headerRow = $rows[0];
        $colMap = [];
        foreach ($headerRow as $idx => $title) {
            $t = trim($title);
            if ($t === '手机号') $colMap['phone'] = $idx;
            elseif ($t === '昵称') $colMap['nickname'] = $idx;
            elseif ($t === '邮箱') $colMap['email'] = $idx;
            elseif ($t === '性别') $colMap['gender'] = $idx;
            elseif ($t === '状态') $colMap['status'] = $idx;
        }

        if (!isset($colMap['phone'])) {
            return json(['code' => 1002, 'msg' => '缺少"手机号"列', 'data' => null]);
        }

        $genderMap = ['未知' => 0, '男' => 1, '女' => 2];
        $statusMap = ['启用' => 1, '禁用' => 0];
        $success = 0;
        $skip = 0;
        $errors = [];

        for ($i = 1, $len = count($rows); $i < $len; $i++) {
            $row = $rows[$i];
            $line = $i + 1;

            $phone = trim($row[$colMap['phone']] ?? '');
            if (empty($phone)) {
                continue;
            }

            if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
                $errors[] = "第{$line}行：手机号格式不正确";
                continue;
            }

            $email = '';
            if (isset($colMap['email'])) {
                $email = trim($row[$colMap['email']] ?? '');
                if ($email !== '' && !preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $email)) {
                    $errors[] = "第{$line}行：邮箱格式不正确";
                    continue;
                }
            }

            $exists = Db::table('user')->where('phone', $phone)->find();
            if ($exists) {
                $skip++;
                continue;
            }

            $nickname = isset($colMap['nickname']) ? trim($row[$colMap['nickname']] ?? '') : '';
            $gender   = isset($colMap['gender']) ? ($genderMap[trim($row[$colMap['gender']] ?? '')] ?? 0) : 0;
            $status   = isset($colMap['status']) ? ($statusMap[trim($row[$colMap['status']] ?? '')] ?? 1) : 1;

            Db::table('user')->insert([
                'phone'    => $phone,
                'password' => password_hash('123456', PASSWORD_BCRYPT),
                'nickname' => $nickname,
                'email'    => $email,
                'gender'   => $gender,
                'status'   => $status,
            ]);
            $success++;
        }

        $msg = "导入完成，成功 {$success} 条";
        if ($skip > 0) $msg .= "，跳过 {$skip} 条（手机号已存在）";
        if ($errors) $msg .= "，" . implode('；', $errors);

        return json(['code' => 0, 'msg' => $msg, 'data' => null]);
    }
}
