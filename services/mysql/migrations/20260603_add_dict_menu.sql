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
