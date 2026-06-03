# 系统配置页面设计

## 概述

新增"系统配置"页面，支持设置站点名称和 Logo。页面位于"系统管理"菜单下，使用专用 `system_config` 表存储配置数据，通过专用控制器和 API 读写。

## 数据层

### 存储

新建 `system_config` 表（key-value 结构），与字典管理互不干扰，扩展时只需 INSERT 新行 + 前端加表单字段。

```sql
CREATE TABLE IF NOT EXISTS system_config (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key`      VARCHAR(50)  NOT NULL UNIQUE,
  value      TEXT         DEFAULT NULL,
  created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

初始数据：
- `key='site_name'`，`value='后台管理系统'`
- `key='logo'`，`value=''`（存储 attachment ID）

**为何不复用 dict_type/dict_data**：
1. 字典管理页面会显示 system_config 类型，管理员可在两处编辑同一份数据，易混乱
2. dict_data.value 是 VARCHAR(100)，后续存长文本（公告、统计代码等）受限
3. 字典的语义是"枚举值"，配置的语义是"设置项"，概念不同应分表

### 后端控制器

新增 `application/admin/controller/SystemConfig.php`，继承 `think\Controller`，两个方法：

- `read()`：查 `system_config` 全表，按 `key` 作 key 返回。Logo 字段若非空，关联 `attachment` 表查出完整 URL 一并返回。
- `update()`：接收 `site_name`（string）和 `logo`（attachment ID 或空字符串），按 `key` 匹配更新对应行的 `value`。参数校验：`site_name` 必填、最大 50 字符。

### API

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/admin/system_config/read` | 获取配置 |
| PUT | `/admin/system_config/update` | 更新配置 |

响应格式遵循项目约定：`{code: 0, msg: 'success', data: {site_name: '...', logo: '...'}}`

## 前端页面

### 页面

路径：`frontend/src/views/system/config.vue`

布局：`el-card` + `el-form`（label-width="120px"）

- **站点名称**：`el-input`，必填，maxlength=50，show-word-limit
- **Logo**：`el-upload` 图片上传组件
  - 调用 `POST /admin/attachment/upload`（子目录 `site`）
  - accept 限制：jpg/png/svg
  - 上传成功后显示缩略图预览（使用 attachment URL）
  - 支持删除（清空 logo 值）和重新上传
  - 使用 `el-upload` 的 list-type="picture-card" 模式，limit=1
- **保存按钮**：`el-button type="primary"`，点击调 `PUT /admin/system_config/update`

页面加载时调 `GET /admin/system_config/read` 填充表单。

### 路由

在 `router/index.js` 的 system 路由组下新增：

```js
{ path: 'config', name: 'SystemConfig', component: () => import('@/views/system/config.vue') }
```

## 菜单与权限

新增 SQL 迁移文件 `20260603_add_system_config.sql`，在 `menu` 表中插入：

| ID | parent_id | name | path | type | sort | icon |
|----|-----------|------|------|------|------|------|
| 39 | 1 | 系统配置 | /system/config | 2 | 60 | setting |
| 40 | 39 | 查看 | | 3 | 1 | |
| 41 | 39 | 编辑 | | 3 | 2 | |

权限绑定：超管角色（role_id=1）自动绑定新菜单权限。

## 文件变更清单

| 文件 | 操作 | 说明 |
|------|------|------|
| `services/mysql/migrations/20260603_add_system_config.sql` | 新增 | 建表 + 初始数据 + 菜单权限 |
| `services/php/app/application/admin/controller/SystemConfig.php` | 新增 | read + update 方法 |
| `services/php/app/route/route.php` | 修改 | 新增 2 条路由 |
| `frontend/src/views/system/config.vue` | 新增 | 配置页面 |
| `frontend/src/router/index.js` | 修改 | 新增路由 |
