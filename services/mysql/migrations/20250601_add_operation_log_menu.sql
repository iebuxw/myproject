-- 操作日志菜单（挂在"日志管理"目录下）
INSERT INTO `menu` (`id`, `parent_id`, `name`, `path`, `icon`, `sort`, `type`) VALUES
(27, 23, '操作日志', '/log/operation', '', 2, 2),
(28, 27, '查询日志', '', '', 1, 3),
(29, 27, '删除日志', '', '', 2, 3)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 超级管理员拥有操作日志菜单权限
INSERT INTO `role_menu` (`role_id`, `menu_id`) VALUES
(1, 27), (1, 28), (1, 29)
ON DUPLICATE KEY UPDATE `id`=`id`;
