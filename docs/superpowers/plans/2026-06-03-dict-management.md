# 字典管理功能 实现计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 为 PHP 后台管理系统新增字典管理模块（dict_type + dict_data 双表），管理员可维护字典类型及字典项，前端提供工具函数按需加载字典数据。

**Architecture:** 在现有 PHP ThinkPHP 5.1 + Vue2/Element UI 架构下，遵循现有 CRUD 模式（Db 门面、统一 JSON 响应、Vuex 不参与）。字典类型页面为主入口，字典项通过右侧抽屉（el-drawer）管理，前端封装 `utils/dict.js` 供各页面调用 `loadDicts()` / `dictMap()`。

**Tech Stack:** PHP 7.4 + ThinkPHP 5.1 + MySQL 5.7 + Vue2 + Element UI + axios

---

## 文件结构

| 操作 | 文件 | 职责 |
|------|------|------|
| 新增 | `services/mysql/migrations/20260603_add_dict.sql` | 建 dict_type、dict_data 表 |
| 新增 | `services/mysql/migrations/20260603_add_dict_menu.sql` | 插入菜单 + 按钮权限 + 超级管理员授权 |
| 新增 | `services/php/app/application/admin/controller/DictType.php` | 字典类型 CRUD（list/add/edit/delete）|
| 新增 | `services/php/app/application/admin/controller/DictData.php` | 字典项 CRUD（list/add/edit/delete）+ 批量查询 items |
| 修改 | `services/php/app/route/route.php` | 追加 dict_type、dict_data 两组路由 |
| 新增 | `frontend/src/views/system/dict.vue` | 字典类型列表 + 抽屉式字典项管理 |
| 新增 | `frontend/src/utils/dict.js` | loadDicts() / dictMap() 工具函数 |
| 修改 | `frontend/src/router/index.js` | 追加 /system/dict 路由 |

---

### Task 1: 数据库迁移 — 建表

**Files:**
- Create: `services/mysql/migrations/20260603_add_dict.sql`

- [ ] **Step 1: 编写建表迁移 SQL**

```sql
CREATE TABLE IF NOT EXISTS `dict_type` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL COMMENT '类型编码',
    `name` VARCHAR(100) NOT NULL COMMENT '类型名称',
    `status` TINYINT DEFAULT 1 COMMENT '1启用 0停用',
    `remark` VARCHAR(255) DEFAULT '' COMMENT '备注',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='字典类型';

CREATE TABLE IF NOT EXISTS `dict_data` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `type_id` INT UNSIGNED NOT NULL COMMENT '字典类型ID',
    `label` VARCHAR(100) NOT NULL COMMENT '显示文本',
    `value` VARCHAR(100) NOT NULL COMMENT '存储值',
    `sort` INT DEFAULT 0 COMMENT '排序号',
    `status` TINYINT DEFAULT 1 COMMENT '1启用 0停用',
    `remark` VARCHAR(255) DEFAULT '' COMMENT '备注',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_type_value` (`type_id`, `value`),
    INDEX `idx_type_id` (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='字典项';
```

- [ ] **Step 2: 验证 SQL 语法**

Run: `docker exec mysql bash -c "tr -d '\r' < /scripts/migrations/20260603_add_dict.sql | mysql -uroot -proot myproject"`

Expected: 无错误输出，表创建成功

- [ ] **Step 3: 提交**

```bash
git add services/mysql/migrations/20260603_add_dict.sql
git commit -m "feat: 字典管理 — 建表迁移（dict_type + dict_data）

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>"
```

---

### Task 2: 数据库迁移 — 菜单与权限

**Files:**
- Create: `services/mysql/migrations/20260603_add_dict_menu.sql`

- [ ] **Step 1: 编写菜单迁移 SQL**

菜单 ID 规划（系统管理 id=1，其现有子菜单 id=2/3/4，使用 id=30-34）：

```sql
-- 字典管理菜单（挂在"系统管理"目录下）
INSERT INTO `menu` (`id`, `parent_id`, `name`, `path`, `icon`, `sort`, `type`) VALUES
(30, 1, '字典管理', '/system/dict', '', 4, 2),
(31, 30, '查询字典', '', '', 1, 3),
(32, 30, '新增字典', '', '', 2, 3),
(33, 30, '编辑字典', '', '', 3, 3),
(34, 30, '删除字典', '', '', 4, 3)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 超级管理员拥有字典管理菜单权限
INSERT INTO `role_menu` (`role_id`, `menu_id`) VALUES
(1, 30), (1, 31), (1, 32), (1, 33), (1, 34)
ON DUPLICATE KEY UPDATE `id`=`id`;
```

- [ ] **Step 2: 验证**

Run: `docker exec mysql bash -c "tr -d '\r' < /scripts/migrations/20260603_add_dict_menu.sql | mysql -uroot -proot myproject"`

Expected: 无错误输出

- [ ] **Step 3: 提交**

```bash
git add services/mysql/migrations/20260603_add_dict_menu.sql
git commit -m "feat: 字典管理 — 菜单权限迁移

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>"
```

---

### Task 3: PHP 后端 — DictType 控制器

**Files:**
- Create: `services/php/app/application/admin/controller/DictType.php`

- [ ] **Step 1: 创建 DictType.php**

```php
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
```

- [ ] **Step 2: PHP 语法检查**

Run: `php -l services/php/app/application/admin/controller/DictType.php`

Expected: `No syntax errors detected`

- [ ] **Step 3: 提交**

```bash
git add services/php/app/application/admin/controller/DictType.php
git commit -m "feat: 字典管理 — DictType 控制器

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>"
```

---

### Task 4: PHP 后端 — DictData 控制器

**Files:**
- Create: `services/php/app/application/admin/controller/DictData.php`

- [ ] **Step 1: 创建 DictData.php**

```php
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
```

- [ ] **Step 2: PHP 语法检查**

Run: `php -l services/php/app/application/admin/controller/DictData.php`

Expected: `No syntax errors detected`

- [ ] **Step 3: 提交**

```bash
git add services/php/app/application/admin/controller/DictData.php
git commit -m "feat: 字典管理 — DictData 控制器

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>"
```

---

### Task 5: PHP 路由注册

**Files:**
- Modify: `services/php/app/route/route.php`

- [ ] **Step 1: 在 route.php 的 group 闭包内追加路由**

在 `// Menu` 路由组之后插入。找到第 37 行附近的 `});`（Menu delete 路由），在其后添加：

```php

    // DictType
    Route::get('dict_type/list', 'admin/DictType/index');
    Route::post('dict_type/add', 'admin/DictType/save');
    Route::put('dict_type/edit', 'admin/DictType/update');
    Route::delete('dict_type/delete', 'admin/DictType/delete');

    // DictData
    Route::get('dict_data/list', 'admin/DictData/index');
    Route::get('dict_data/items', 'admin/DictData/items');
    Route::post('dict_data/add', 'admin/DictData/save');
    Route::put('dict_data/edit', 'admin/DictData/update');
    Route::delete('dict_data/delete', 'admin/DictData/delete');
```

注意：`dict_data/items` 路由必须放在 `dict_data/list` 之后、`dict_data/add` 之前，避免 `items` 被误匹配。

- [ ] **Step 2: PHP 语法检查**

Run: `php -l services/php/app/route/route.php`

Expected: `No syntax errors detected`

- [ ] **Step 3: 提交**

```bash
git add services/php/app/route/route.php
git commit -m "feat: 字典管理 — 路由注册

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>"
```

---

### Task 6: 前端 — utils/dict.js 工具函数

**Files:**
- Create: `frontend/src/utils/dict.js`

- [ ] **Step 1: 创建 dict.js**

```js
import request from '@/api'

// 字典项缓存（页面生命周期内有效）
const cache = {}

/**
 * 批量加载字典项
 * @param  {...string} codes 字典类型编码，如 'gender', 'status'
 * @return {Promise<Object>}  { gender: [{label, value}], status: [...] }
 */
export async function loadDicts(...codes) {
  const uncached = codes.filter(c => !cache[c])
  if (uncached.length === 0) {
    const result = {}
    codes.forEach(c => { result[c] = cache[c] })
    return result
  }

  const res = await request.get('/dict_data/items', { params: { codes: uncached.join(',') } })
  if (res.code === 0 && res.data) {
    Object.keys(res.data).forEach(code => {
      cache[code] = res.data[code]
    })
  }

  const result = {}
  codes.forEach(c => { result[c] = cache[c] || [] })
  return result
}

/**
 * 将字典项列表转换为 {value: label} 映射
 * @param  {Array}  items [{label, value}]
 * @return {Object}       {value: label}
 */
export function dictMap(items) {
  const map = {}
  if (items) {
    items.forEach(item => { map[item.value] = item.label })
  }
  return map
}
```

- [ ] **Step 2: 提交**

```bash
git add frontend/src/utils/dict.js
git commit -m "feat: 字典管理 — 前端字典加载工具函数

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>"
```

---

### Task 7: 前端 — system/dict.vue 字典管理页面

**Files:**
- Create: `frontend/src/views/system/dict.vue`

- [ ] **Step 1: 创建 dict.vue**

```vue
<template>
  <div>
    <!-- 字典类型列表 -->
    <el-card>
      <div slot="header">
        <span>字典管理</span>
        <el-button type="primary" size="small" style="float:right" @click="handleAddType">新增</el-button>
      </div>
      <el-row style="margin-bottom:15px">
        <el-input v-model="keyword" placeholder="输入编码或名称搜索" clearable style="width:220px" @keyup.enter.native="fetchTypeList" />
        <el-button type="primary" style="margin-left:10px" @click="fetchTypeList">搜索</el-button>
      </el-row>
      <el-table :data="typeList" border stripe>
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column prop="code" label="类型编码" width="150" />
        <el-table-column prop="name" label="类型名称" width="150" />
        <el-table-column prop="status" label="状态" width="80">
          <template slot-scope="{row}">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'">{{ row.status === 1 ? '启用' : '停用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="remark" label="备注" min-width="120" />
        <el-table-column prop="created_at" label="创建时间" width="180" />
        <el-table-column label="操作" width="200">
          <template slot-scope="{row}">
            <el-button type="text" @click="handleEditType(row)">编辑</el-button>
            <el-button type="text" @click="openDrawer(row)">字典项</el-button>
            <el-button type="text" style="color:#f56c6c" @click="handleDeleteType(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
      <el-pagination
        v-if="typeTotal > 0"
        style="margin-top:15px;text-align:right"
        :current-page="typePage"
        :page-size="typeLimit"
        :total="typeTotal"
        layout="total, prev, pager, next"
        @current-change="onTypePageChange"
      />
    </el-card>

    <!-- 字典类型弹窗 -->
    <el-dialog :title="typeDialogTitle" :visible.sync="typeDialogVisible" width="500px">
      <el-form ref="typeForm" :model="typeForm" label-width="80px">
        <el-form-item label="类型编码" required>
          <el-input v-model="typeForm.code" :disabled="!!typeForm.id" />
        </el-form-item>
        <el-form-item label="类型名称" required>
          <el-input v-model="typeForm.name" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="typeForm.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="typeForm.remark" type="textarea" />
        </el-form-item>
      </el-form>
      <span slot="footer">
        <el-button @click="typeDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitType">确定</el-button>
      </span>
    </el-dialog>

    <!-- 字典项抽屉 -->
    <el-drawer :title="'字典项 — ' + currentTypeName" :visible.sync="drawerVisible" size="500px">
      <div style="padding:0 20px">
        <el-button type="primary" size="small" style="margin-bottom:15px" @click="handleAddData">新增字典项</el-button>
        <el-table :data="dataList" border stripe>
          <el-table-column prop="label" label="标签" width="120" />
          <el-table-column prop="value" label="值" width="100" />
          <el-table-column prop="sort" label="排序" width="60" />
          <el-table-column prop="status" label="状态" width="70">
            <template slot-scope="{row}">
              <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">{{ row.status === 1 ? '启用' : '停用' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="remark" label="备注" min-width="100" />
          <el-table-column label="操作" width="120">
            <template slot-scope="{row}">
              <el-button type="text" @click="handleEditData(row)">编辑</el-button>
              <el-button type="text" style="color:#f56c6c" @click="handleDeleteData(row)">删除</el-button>
            </template>
          </el-table-column>
        </el-table>
        <el-pagination
          v-if="dataTotal > 0"
          style="margin-top:15px;text-align:right"
          :current-page="dataPage"
          :page-size="dataLimit"
          :total="dataTotal"
          layout="total, prev, pager, next"
          @current-change="onDataPageChange"
        />
      </div>
    </el-drawer>

    <!-- 字典项弹窗 -->
    <el-dialog :title="dataDialogTitle" :visible.sync="dataDialogVisible" width="500px" append-to-body>
      <el-form ref="dataForm" :model="dataForm" label-width="80px">
        <el-form-item label="标签" required>
          <el-input v-model="dataForm.label" />
        </el-form-item>
        <el-form-item label="值" required>
          <el-input v-model="dataForm.value" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="dataForm.sort" :min="0" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="dataForm.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="dataForm.remark" type="textarea" />
        </el-form-item>
      </el-form>
      <span slot="footer">
        <el-button @click="dataDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSubmitData">确定</el-button>
      </span>
    </el-dialog>
  </div>
</template>

<script>
import request from '@/api'

export default {
  name: 'SystemDict',
  data() {
    return {
      // 字典类型
      keyword: '',
      typeList: [],
      typePage: 1,
      typeLimit: 20,
      typeTotal: 0,
      typeDialogVisible: false,
      typeDialogTitle: '',
      typeForm: { id: 0, code: '', name: '', status: 1, remark: '' },
      // 字典项
      currentTypeId: 0,
      currentTypeName: '',
      drawerVisible: false,
      dataList: [],
      dataPage: 1,
      dataLimit: 20,
      dataTotal: 0,
      dataDialogVisible: false,
      dataDialogTitle: '',
      dataForm: { id: 0, type_id: 0, label: '', value: '', sort: 0, status: 1, remark: '' }
    }
  },
  created() {
    this.fetchTypeList()
  },
  methods: {
    async fetchTypeList() {
      const res = await request.get('/dict_type/list', { params: { keyword: this.keyword, page: this.typePage, limit: this.typeLimit } })
      if (res.code === 0) {
        this.typeList = res.data.list
        this.typeTotal = res.data.total
      }
    },
    onTypePageChange(page) {
      this.typePage = page
      this.fetchTypeList()
    },
    handleAddType() {
      this.typeDialogTitle = '新增字典类型'
      this.typeForm = { id: 0, code: '', name: '', status: 1, remark: '' }
      this.typeDialogVisible = true
    },
    handleEditType(row) {
      this.typeDialogTitle = '编辑字典类型'
      this.typeForm = { id: row.id, code: row.code, name: row.name, status: row.status, remark: row.remark }
      this.typeDialogVisible = true
    },
    handleDeleteType(row) {
      this.$confirm('确定删除该字典类型吗？其下所有字典项也将被删除', '提示', { type: 'warning' }).then(async () => {
        const res = await request.delete('/dict_type/delete', { data: { id: row.id } })
        if (res.code === 0) {
          this.$message.success('删除成功')
          this.fetchTypeList()
        } else {
          this.$message.error(res.msg)
        }
      }).catch(() => {})
    },
    async handleSubmitType() {
      const { id, code, name, status, remark } = this.typeForm
      if (!code || !name) return this.$message.warning('请填写编码和名称')

      const api = id ? request.put : request.post
      const url = id ? '/dict_type/edit' : '/dict_type/add'
      const data = id ? { id, code, name, status, remark } : { code, name, status, remark }

      const res = await api(url, data)
      if (res.code === 0) {
        this.$message.success(id ? '编辑成功' : '新增成功')
        this.typeDialogVisible = false
        this.fetchTypeList()
      } else {
        this.$message.error(res.msg)
      }
    },
    // 字典项
    openDrawer(row) {
      this.currentTypeId = row.id
      this.currentTypeName = row.name
      this.dataPage = 1
      this.drawerVisible = true
      this.fetchDataList()
    },
    async fetchDataList() {
      const res = await request.get('/dict_data/list', { params: { type_id: this.currentTypeId, page: this.dataPage, limit: this.dataLimit } })
      if (res.code === 0) {
        this.dataList = res.data.list
        this.dataTotal = res.data.total
      }
    },
    onDataPageChange(page) {
      this.dataPage = page
      this.fetchDataList()
    },
    handleAddData() {
      this.dataDialogTitle = '新增字典项'
      this.dataForm = { id: 0, type_id: this.currentTypeId, label: '', value: '', sort: 0, status: 1, remark: '' }
      this.dataDialogVisible = true
    },
    handleEditData(row) {
      this.dataDialogTitle = '编辑字典项'
      this.dataForm = { id: row.id, type_id: row.type_id, label: row.label, value: row.value, sort: row.sort, status: row.status, remark: row.remark }
      this.dataDialogVisible = true
    },
    handleDeleteData(row) {
      this.$confirm('确定删除该字典项吗？', '提示', { type: 'warning' }).then(async () => {
        const res = await request.delete('/dict_data/delete', { data: { id: row.id } })
        if (res.code === 0) {
          this.$message.success('删除成功')
          this.fetchDataList()
        } else {
          this.$message.error(res.msg)
        }
      }).catch(() => {})
    },
    async handleSubmitData() {
      const { id, type_id, label, value, sort, status, remark } = this.dataForm
      if (!label || value === '') return this.$message.warning('请填写标签和值')

      const api = id ? request.put : request.post
      const url = id ? '/dict_data/edit' : '/dict_data/add'
      const data = id ? { id, label, value, sort, status, remark } : { type_id, label, value, sort, status, remark }

      const res = await api(url, data)
      if (res.code === 0) {
        this.$message.success(id ? '编辑成功' : '新增成功')
        this.dataDialogVisible = false
        this.fetchDataList()
      } else {
        this.$message.error(res.msg)
      }
    }
  }
}
</script>
```

- [ ] **Step 2: 检查 Vue 语法**

Run: `cd frontend && npx vue-cli-service lint --no-fix src/views/system/dict.vue 2>/dev/null; echo "lint done"`

Expected: 无新引入的严重语法错误（可能提示 warning 或无需检查）

- [ ] **Step 3: 提交**

```bash
git add frontend/src/views/system/dict.vue
git commit -m "feat: 字典管理 — 前端管理页面

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>"
```

---

### Task 8: 前端路由注册

**Files:**
- Modify: `frontend/src/router/index.js`

- [ ] **Step 1: 在 children 数组中追加路由**

在 `system/menu` 路由之后插入（约第 48 行之后）：

```js
      {
        path: 'system/dict',
        name: 'Dict',
        component: () => import('@/views/system/dict'),
        meta: { title: '字典管理' }
      }
```

- [ ] **Step 2: 提交**

```bash
git add frontend/src/router/index.js
git commit -m "feat: 字典管理 — 前端路由注册

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>"
```

---

## 验证清单

全部完成后执行以下验证：

- [ ] `docker-compose config --quiet` — compose 配置正确
- [ ] `docker exec mysql bash -c "tr -d '\r' < /scripts/migrations/20260603_add_dict.sql | mysql -uroot -proot myproject"` — 表可重复执行
- [ ] `docker exec mysql bash -c "tr -d '\r' < /scripts/migrations/20260603_add_dict_menu.sql | mysql -uroot -proot myproject"` — 菜单可重复执行
- [ ] `php -l services/php/app/application/admin/controller/DictType.php` — PHP 语法
- [ ] `php -l services/php/app/application/admin/controller/DictData.php` — PHP 语法
- [ ] `php -l services/php/app/route/route.php` — PHP 语法
- [ ] `docker-compose up -d --build` — 服务启动成功
- [ ] 浏览器访问 `/admin/dict_data/items?codes=gender,status` — 返回字典数据（即使为空数组）
- [ ] 浏览器访问后台管理 → 系统管理 → 字典管理 — 菜单出现，页面正常渲染，可进行 CRUD 操作
