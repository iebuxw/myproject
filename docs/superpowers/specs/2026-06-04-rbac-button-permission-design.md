# RBAC 按钮级权限设计

## 背景

当前 RBAC 体系存在以下问题：
- `menu` 表中 type=3（按钮）的 `path` 字段全部为空，无法标识权限
- `Auth@info()` 接口显式排除 type=3，前端无法获取按钮权限数据
- `Auth` 中间件计算了 `$request->authPaths` 但没有任何控制器消费
- 前端没有任何权限检查机制，所有按钮对所有登录用户可见

## 设计

### 一、按钮权限标识

为每个 type=3 按钮的 `path` 填入 `模块:操作` 格式的标识：

| 父菜单 | 按钮名 | path |
|--------|--------|------|
| 管理员管理 | 查询管理员 | `admin:list` |
| | 新增管理员 | `admin:add` |
| | 编辑管理员 | `admin:edit` |
| | 删除管理员 | `admin:delete` |
| 角色管理 | 查询角色 | `role:list` |
| | 新增角色 | `role:add` |
| | 编辑角色 | `role:edit` |
| | 删除角色 | `role:delete` |
| 菜单管理 | 查询菜单 | `menu:list` |
| | 新增菜单 | `menu:add` |
| | 编辑菜单 | `menu:edit` |
| | 删除菜单 | `menu:delete` |
| 用户列表 | 查询用户 | `user:list` |
| | 新增用户 | `user:add` |
| | 编辑用户 | `user:edit` |
| | 删除用户 | `user:delete` |
| 登录日志 | 查询日志 | `login_log:list` |
| | 删除日志 | `login_log:delete` |
| 操作日志 | 查询日志 | `operation_log:list` |
| | 删除日志 | `operation_log:delete` |
| 字典管理 | 查询字典 | `dict:list` |
| | 新增字典 | `dict:add` |
| | 编辑字典 | `dict:edit` |
| | 删除字典 | `dict:delete` |
| 文件管理 | 查询文件 | `attachment:list` |
| | 上传文件 | `attachment:upload` |
| | 删除文件 | `attachment:delete` |
| 系统配置 | 查看 | `system_config:list` |
| | 编辑 | `system_config:edit` |

URL 到权限标识的解析规则：取 `/admin/` 之后的第一段为模块，第二段为操作，拼接为 `模块:操作`。例如 `/admin/admin/add` → `admin:add`。

通过迁移 SQL 更新现有数据。

### 二、后端中间件改造

**Auth 中间件**：

1. `$request->authPaths` 改为收集所有非空 path（包含 type=3 按钮的权限标识）
2. 超级管理员（id=1）跳过校验，直接放行
3. 从当前请求 URL 解析权限标识，与 `authPaths` 比对
4. 白名单路由直接放行：`auth/*`、`profile/*`、`server/info`、`system_config/read`
5. 无权限时返回 `{"code": 1007, "msg": "无权限"}`

**Auth 控制器 info()**：

- 去掉 `where('type', '<>', 3)` 过滤，将 type=3 按钮也返回
- `buildTree()` 天然支持嵌套，按钮作为菜单的 children 出现

**新增错误码**：`1007` = 无权限

### 三、前端改造

**Vuex store**：

- 从 `info()` 返回的菜单树中提取所有 type=3 按钮的 path，存入 `state.permissions` 数组
- 新增 getter `hasPerm(perm)` 判断是否拥有某权限

**v-auth 指令**：

- 注册全局自定义指令，`v-auth="'admin:add'"`
- `inserted` 钩子中调用 `store.getters.hasPerm(binding.value)`，不通过则 `el.parentNode.removeChild(el)`

**各页面按钮**：

- 新增、编辑、删除等操作按钮加 `v-auth` 指令
- 查询类权限不控制按钮（页面本身即查询结果展示）
- 导出/导入归入 `list` 权限

**菜单管理页面**：

- 编辑对话框中 type=3 按钮的 path 字段改为可输入，允许管理员设置权限标识
