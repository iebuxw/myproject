-- 超级管理员账号: admin / 123456
INSERT INTO `admin` (`id`, `username`, `password`, `status`) VALUES (1, 'admin', '$2y$10$sdIYjgSTtx1Py3AjYpzOQOqp4S87kIqWBaR7GZKlAk8KUX5lWj6l2', 1)
ON DUPLICATE KEY UPDATE `username`=VALUES(`username`);

-- 测试 APP 用户: 13800000000 / 123456
INSERT INTO `user` (`id`, `phone`, `password`, `nickname`, `gender`) VALUES (1, '13800000000', '$2y$10$sdIYjgSTtx1Py3AjYpzOQOqp4S87kIqWBaR7GZKlAk8KUX5lWj6l2', '测试用户', 1)
ON DUPLICATE KEY UPDATE `phone`=VALUES(`phone`);

-- 角色
INSERT INTO `role` (`id`, `name`, `description`) VALUES (1, '超级管理员', '拥有所有权限')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 分配 admin 角色
INSERT INTO `admin_role` (`admin_id`, `role_id`) VALUES (1, 1)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- 菜单数据
INSERT INTO `menu` (`id`, `parent_id`, `name`, `path`, `icon`, `sort`, `type`) VALUES
(22, 0, '用户管理', '', 'el-icon-user', 0, 1),
(1, 0, '系统管理', '', 'el-icon-setting', 1, 1),
(2, 1, '管理员管理', '/system/admin', '', 1, 2),
(3, 1, '角色管理', '/system/role', '', 2, 2),
(4, 1, '菜单管理', '/system/menu', '', 3, 2),
(5, 2, '查询管理员', '', '', 1, 3),
(6, 2, '新增管理员', '', '', 2, 3),
(7, 2, '编辑管理员', '', '', 3, 3),
(8, 2, '删除管理员', '', '', 4, 3),
(9, 3, '查询角色', '', '', 1, 3),
(10, 3, '新增角色', '', '', 2, 3),
(11, 3, '编辑角色', '', '', 3, 3),
(12, 3, '删除角色', '', '', 4, 3),
(13, 4, '查询菜单', '', '', 1, 3),
(14, 4, '新增菜单', '', '', 2, 3),
(15, 4, '编辑菜单', '', '', 3, 3),
(16, 4, '删除菜单', '', '', 4, 3),
(17, 22, '用户列表', '/user/list', '', 0, 2),
(18, 17, '查询用户', '', '', 1, 3),
(19, 17, '新增用户', '', '', 2, 3),
(20, 17, '编辑用户', '', '', 3, 3),
(21, 17, '删除用户', '', '', 4, 3),
(23, 0, '日志管理', '', 'el-icon-document', 2, 1),
(24, 23, '登录日志', '/log/login', '', 10, 2),
(25, 24, '查询日志', '', '', 1, 3),
(26, 24, '删除日志', '', '', 2, 3)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 超级管理员拥有所有菜单权限
INSERT INTO `role_menu` (`role_id`, `menu_id`) VALUES
(1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),
(1,9),(1,10),(1,11),(1,12),(1,13),(1,14),(1,15),(1,16),
(1,17),(1,18),(1,19),(1,20),(1,21),
(1,22),(1,23),(1,24),(1,25),(1,26)
ON DUPLICATE KEY UPDATE `id`=`id`;
