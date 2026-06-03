-- 字典类型（id 写死以保证 dict_data 的 type_id 引用稳定）
INSERT INTO `dict_type` (`id`, `code`, `name`, `status`, `remark`) VALUES
(1, 'gender',      '性别',      1, '0未知 1男 2女'),
(2, 'status',      '通用状态',  1, '0禁用 1启用'),
(3, 'menu_type',   '菜单类型',  1, '1目录 2菜单 3按钮'),
(4, 'login_status', '登录状态', 1, '0失败 1成功'),
(5, 'module',      '操作模块',  1, '控制器/模块中文名'),
(6, 'action',      '操作动作',  1, 'POST新增 PUT编辑 DELETE删除')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 字典项
INSERT INTO `dict_data` (`type_id`, `label`, `value`, `sort`, `status`) VALUES
-- gender（与 user.gender 对齐）
(1, '未知', '0', 0, 1),
(1, '男',   '1', 1, 1),
(1, '女',   '2', 2, 1),
-- status（通用，admin/role/menu/user/dict 共用）
(2, '启用', '1', 0, 1),
(2, '禁用', '0', 1, 1),
-- menu_type（与 menu.type 对齐）
(3, '目录', '1', 0, 1),
(3, '菜单', '2', 1, 1),
(3, '按钮', '3', 2, 1),
-- login_status（与 login_log.status 对齐）
(4, '成功', '1', 0, 1),
(4, '失败', '0', 1, 1),
-- module（OperationLog 中的模块映射）
(5, '管理员管理', 'admin',         0, 1),
(5, '角色管理',   'role',          1, 1),
(5, '菜单管理',   'menu',          2, 1),
(5, '用户管理',   'user',          3, 1),
(5, '登录日志',   'login_log',     4, 1),
(5, '操作日志',   'operation_log', 5, 1),
(5, '字典管理',   'dict',          6, 1),
(5, '个人中心',   'profile',       7, 1),
-- action（OperationLog 中的动作映射）
(6, '新增', 'POST',   0, 1),
(6, '编辑', 'PUT',    1, 1),
(6, '删除', 'DELETE', 2, 1)
ON DUPLICATE KEY UPDATE `label`=VALUES(`label`), `sort`=VALUES(`sort`);
