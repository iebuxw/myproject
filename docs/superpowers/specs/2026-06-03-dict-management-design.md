# 字典管理功能设计

## 目标

为 PHP 后台管理系统新增字典管理模块，统一管理下拉选项（性别、状态、类型等枚举值），避免硬编码。本次仅新增 CRUD 功能，不替换现有硬编码枚举。

## 数据库

### dict_type（字典类型）

| 字段 | 类型 | 约束 | 说明 |
|------|------|------|------|
| id | INT UNSIGNED | PK AUTO_INCREMENT | 自增主键 |
| code | VARCHAR(50) | UNIQUE NOT NULL | 类型编码，如 `gender`、`status` |
| name | VARCHAR(100) | NOT NULL | 类型名称，如"性别"、"状态" |
| status | TINYINT | DEFAULT 1 | 1=启用 0=停用 |
| remark | VARCHAR(255) | DEFAULT '' | 备注 |
| created_at | DATETIME | | |
| updated_at | DATETIME | | |

### dict_data（字典项）

| 字段 | 类型 | 约束 | 说明 |
|------|------|------|------|
| id | INT UNSIGNED | PK AUTO_INCREMENT | 自增主键 |
| type_id | INT UNSIGNED | NOT NULL | 关联 dict_type.id |
| label | VARCHAR(100) | NOT NULL | 显示文本，如"男"、"启用" |
| value | VARCHAR(100) | NOT NULL | 存储值，如"1"、"0" |
| sort | INT | DEFAULT 0 | 排序号 |
| status | TINYINT | DEFAULT 1 | 1=启用 0=停用 |
| remark | VARCHAR(255) | DEFAULT '' | 备注 |
| created_at | DATETIME | | |
| updated_at | DATETIME | | |

UNIQUE 约束：`(type_id, value)` — 同一类型下值不重复。

外键：不使用数据库外键约束，删除类型时 PHP 代码级联删除字典项。

## PHP 后端接口

### 接口列表

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/admin/dict_type/list` | 字典类型列表（分页+搜索） |
| POST | `/admin/dict_type/add` | 新增字典类型 |
| PUT | `/admin/dict_type/edit` | 编辑字典类型 |
| DELETE | `/admin/dict_type/delete` | 删除字典类型（级联删除其下所有字典项） |
| GET | `/admin/dict_data/list` | 字典项列表（按 type_id 筛选，分页） |
| POST | `/admin/dict_data/add` | 新增字典项 |
| PUT | `/admin/dict_data/edit` | 编辑字典项 |
| DELETE | `/admin/dict_data/delete` | 删除字典项 |
| GET | `/admin/dict_data/items` | 按类型编码批量获取字典项（前端页面用） |

### 核心接口：GET /admin/dict_data/items

请求参数：`codes=gender,status`（逗号分隔的类型编码）

响应：
```json
{
  "code": 0,
  "msg": "success",
  "data": {
    "gender": [
      {"label": "未知", "value": "0"},
      {"label": "男", "value": "1"},
      {"label": "女", "value": "2"}
    ],
    "status": [
      {"label": "启用", "value": "1"},
      {"label": "禁用", "value": "0"}
    ]
  }
}
```

只返回 status=1 的字典项，按 sort 升序排列。

### 控制器

- `DictType.php` — 字典类型 CRUD，遵循现有控制器模式
- `DictData.php` — 字典项 CRUD + items 批量查询

## 前端页面

### system/dict.vue

主页面展示字典类型列表。操作列有"字典项"按钮，点击后弹出右侧抽屉（el-drawer）显示该类型下的字典项列表，可增删改。

布局：
- 顶部搜索栏：类型编码/名称关键字搜索 + 新增按钮
- 主体：el-table 展示字典类型（编码、名称、状态、备注、操作列）
- 操作列按钮：编辑、字典项、删除
- 右侧抽屉：字典项管理（el-table 展示 label/value/sort/status/操作列，底部新增按钮）

字典项表单（el-dialog）：标签(label)、值(value)、排序(sort)、状态(status switch)、备注(remark)

### utils/dict.js

```js
// 批量获取字典项，返回 { gender: [...], status: [...] }
export async function loadDicts(...codes) { ... }

// 将字典项数组转为 { value: label } 映射，用于表格列展示
export function dictMap(items) { ... }
```

页面使用示例：
```js
const dicts = await loadDicts('gender', 'status')
// 表格列：{{ dictMap(dicts.gender)[row.gender] }}
// 下拉选项：<el-option v-for="d in dicts.gender" :key="d.value" :label="d.label" :value="+d.value" />
```

## 菜单与权限

在"系统管理"目录下新增：

| 类型 | 名称 | 路径 |
|------|------|------|
| 菜单(type=2) | 字典管理 | /system/dict |
| 按钮(type=3) | 查询字典 | |
| 按钮(type=3) | 新增字典 | |
| 按钮(type=3) | 编辑字典 | |
| 按钮(type=3) | 删除字典 | |

超级管理员角色(id=1)默认授权所有新菜单。

## 文件清单

| 操作 | 文件 |
|------|------|
| 新增 | `services/mysql/migrations/20260603_add_dict.sql` |
| 新增 | `services/mysql/migrations/20260603_add_dict_menu.sql` |
| 新增 | `services/php/app/application/admin/controller/DictType.php` |
| 新增 | `services/php/app/application/admin/controller/DictData.php` |
| 修改 | `services/php/app/route/route.php` |
| 新增 | `frontend/src/views/system/dict.vue` |
| 新增 | `frontend/src/utils/dict.js` |
| 修改 | `frontend/src/router/index.js` |
