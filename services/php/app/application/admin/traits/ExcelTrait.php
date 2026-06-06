<?php
namespace app\admin\traits;

use think\Db;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

trait ExcelTrait
{
    /**
     * 通用 Excel 导出
     *
     * @param array $config [
     *   'query'    => Db查询构建器（可带筛选条件）,
     *   'headers'  => ['ID', '手机号', ...],           表头
     *   'columns'  => ['id', 'phone', ...],            数据库字段，与 headers 一一对应
     *   'maps'     => ['gender' => ['未知','男','女']], 值映射（可选）
     *   'filename' => '用户列表',                       下载文件名前缀
     * ]
     */
    protected function exportToXlsx(array $config)
    {
        $query    = $config['query'];
        $headers  = $config['headers'];
        $columns  = $config['columns'];
        $maps     = $config['maps'] ?? [];
        $filename = ($config['filename'] ?? '导出') . '_' . date('YmdHis') . '.xlsx';

        $list = $query->select();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 写表头
        foreach ($headers as $col => $title) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $title);
        }

        // 写数据
        foreach ($list as $rowIdx => $row) {
            foreach ($columns as $col => $field) {
                $value = $row[$field] ?? '';
                // 值映射转换
                if (isset($maps[$field]) && is_array($maps[$field])) {
                    $value = $maps[$field][$value] ?? $value;
                }
                $sheet->setCellValueByColumnAndRow($col + 1, $rowIdx + 2, $value);
            }
        }

        // 输出到缓冲区，通过框架响应返回，不落盘
        ob_start();
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0',
        ]);
    }

    /**
     * 通用 Excel 导入
     *
     * @param array $config [
     *   'file'      => request()->file('file'),
     *   'fields'    => ['phone' => '手机号', ...],       字段→表头映射
     *   'required'  => ['phone'],                        必填字段（可选）
     *   'unique'    => ['phone'],                        唯一性校验字段（可选，自动查表去重）
     *   'table'     => 'user',                           目标表名
     *   'validate'  => function($row, $line) {},         自定义行校验回调，返回错误字符串或null（可选）
     *   'transform' => function($row) { return [...]; }, 行数据转换回调（可选）
     *   'defaults'  => ['field' => 'value'],             插入时默认值（可选）
     * ]
     */
    protected function importFromXlsx(array $config)
    {
        $file = $config['file'];
        if (!$file) {
            return json(['code' => 1002, 'msg' => '请上传文件', 'data' => null]);
        }

        $ext = strtolower(pathinfo($file->getInfo('name'), PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'xls'])) {
            return json(['code' => 1002, 'msg' => '只支持 xlsx/xls 格式', 'data' => null]);
        }

        $fields    = $config['fields'];
        $required  = $config['required'] ?? [];
        $unique    = $config['unique'] ?? [];
        $table     = $config['table'];
        $validate  = $config['validate'] ?? null;
        $transform = $config['transform'] ?? null;
        $defaults  = $config['defaults'] ?? [];

        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();
        if (count($rows) <= 1) {
            return json(['code' => 1002, 'msg' => '文件中没有数据', 'data' => null]);
        }

        // 表头→列索引映射
        $headerRow = $rows[0];
        $colMap = [];
        foreach ($headerRow as $idx => $title) {
            $t = trim($title);
            if (false !== $field = array_search($t, $fields)) {
                $colMap[$field] = $idx;
            }
        }

        // 检查必填列是否存在
        foreach ($required as $field) {
            if (!isset($colMap[$field])) {
                return json(['code' => 1002, 'msg' => '缺少"' . $fields[$field] . '"列', 'data' => null]);
            }
        }

        // 收集唯一性字段的已有值
        $existValues = [];
        foreach ($unique as $field) {
            $existValues[$field] = [];
            $vals = Db::table($table)->column($field);
            if ($vals) {
                $existValues[$field] = array_flip($vals);
            }
        }

        $success = 0;
        $skip = 0;
        $errors = [];
        $insertRows = [];

        for ($i = 1, $len = count($rows); $i < $len; $i++) {
            $row = $rows[$i];
            $line = $i + 1;

            $data = [];
            $skipRow = false;

            // 按字段映射提取值
            foreach ($fields as $field => $title) {
                if (isset($colMap[$field])) {
                    $data[$field] = trim($row[$colMap[$field]] ?? '');
                }
            }

            // 必填校验
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $skipRow = true;
                    break;
                }
            }
            if ($skipRow) continue;

            // 唯一性校验（批量内+数据库）
            foreach ($unique as $field) {
                if (isset($existValues[$field][$data[$field]])) {
                    $skip = $skipRow ? $skip : $skip + 1;
                    $skipRow = true;
                    break;
                }
            }
            if ($skipRow) continue;

            // 自定义校验
            if ($validate) {
                $err = $validate($data, $line);
                if ($err) {
                    $errors[] = $err;
                    continue;
                }
            }

            // 转换
            if ($transform) {
                $data = $transform($data);
            }

            // 默认值
            $data = array_merge($defaults, $data);

            // 标记唯一值已使用
            foreach ($unique as $field) {
                $existValues[$field][$data[$field]] = true;
            }

            $insertRows[] = $data;
            $success++;
        }

        // 分块批量插入，避免单次 insert 过大
        foreach (array_chunk($insertRows, 500) as $chunk) {
            Db::table($table)->insertAll($chunk);
        }

        $msg = "导入完成，成功 {$success} 条";
        if ($skip > 0) $msg .= "，跳过 {$skip} 条（重复）";
        if ($errors) $msg .= "，" . implode('；', $errors);

        return json(['code' => 0, 'msg' => $msg, 'data' => null]);
    }
}
