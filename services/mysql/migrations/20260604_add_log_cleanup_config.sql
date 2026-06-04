INSERT INTO `system_config` (`key`, `value`) VALUES
('log_retention_days', '360'),
('clean_operation_log', '1'),
('clean_login_log', '1')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- 日志设置菜单（挂在"日志管理"目录 id=23 下）
INSERT INTO `menu` (`id`, `parent_id`, `name`, `path`, `icon`, `sort`, `type`) VALUES
(49, 23, '日志设置', '/log/settings', '', 50, 2),
(50, 49, '查询', 'log_config:read', '', 1, 3),
(51, 49, '编辑', 'log_config:update', '', 2, 3)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 超管拥有新菜单权限
INSERT INTO `role_menu` (`role_id`, `menu_id`) VALUES
(1,49),(1,50),(1,51)
ON DUPLICATE KEY UPDATE `id`=`id`;
