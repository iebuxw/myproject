INSERT INTO `menu` (`id`, `parent_id`, `name`, `path`, `icon`, `sort`, `type`) VALUES
(35, 1, '文件管理', '/system/attachment', '', 5, 2),
(36, 35, '查询文件', '', '', 1, 3),
(37, 35, '上传文件', '', '', 2, 3),
(38, 35, '删除文件', '', '', 3, 3)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `role_menu` (`role_id`, `menu_id`) VALUES
(1, 35), (1, 36), (1, 37), (1, 38)
ON DUPLICATE KEY UPDATE `id`=`id`;
