CREATE TABLE IF NOT EXISTS `system_config` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(50) NOT NULL UNIQUE COMMENT '配置键',
    `value` TEXT DEFAULT NULL COMMENT '配置值',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置';

INSERT INTO `system_config` (`key`, `value`) VALUES
('site_name', '后台管理系统'),
('logo', '')
ON DUPLICATE KEY UPDATE `value`=VALUES(`value`);

INSERT INTO `menu` (`id`, `parent_id`, `name`, `path`, `icon`, `sort`, `type`) VALUES
(39, 1, '系统配置', '/system/config', 'setting', 60, 2),
(40, 39, '查看', '', '', 1, 3),
(41, 39, '编辑', '', '', 2, 3)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `role_menu` (`role_id`, `menu_id`) VALUES
(1, 39), (1, 40), (1, 41)
ON DUPLICATE KEY UPDATE `id`=`id`;
