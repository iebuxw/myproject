-- 数据库备份记录表
CREATE TABLE IF NOT EXISTS `db_backup` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `filename` VARCHAR(255) NOT NULL COMMENT '文件名',
    `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '文件大小(字节)',
    `trigger_type` TINYINT NOT NULL DEFAULT 1 COMMENT '1=手动 2=定时',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1=成功 0=失败',
    `is_snapshot` TINYINT NOT NULL DEFAULT 0 COMMENT '0=常规备份 1=恢复前自动快照',
    `remark` VARCHAR(500) DEFAULT '' COMMENT '备注',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_is_snapshot` (`is_snapshot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='数据库备份记录';

-- 菜单：数据库备份（挂在"运维管理"目录 id=60 下，sort=3 排在文件管理之后）
INSERT INTO `menu` (`id`, `parent_id`, `name`, `path`, `icon`, `sort`, `type`) VALUES
(70, 60, '数据库备份', '/system/db-backup', '', 3, 2),
(71, 70, '查询', 'db_backup:list', '', 1, 3),
(72, 70, '新增', 'db_backup:add', '', 2, 3),
(73, 70, '恢复', 'db_backup:restore', '', 3, 3),
(74, 70, '删除', 'db_backup:delete', '', 4, 3)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 超管拥有新菜单权限
INSERT INTO `role_menu` (`role_id`, `menu_id`) VALUES
(1,70),(1,71),(1,72),(1,73),(1,74)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- 预装定时任务：每天凌晨2点自动备份数据库
INSERT INTO `cron_task` (`name`, `command`, `cron_expr`, `status`, `remark`) VALUES
('数据库备份', 'backup_db', '0 2 * * *', 1, '每天凌晨2点自动备份数据库'),
('清理过期备份', 'clean_backup', '0 4 * * *', 1, '每天凌晨4点清理过期备份')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 备份保留天数配置
INSERT INTO `system_config` (`key`, `value`) VALUES
('db_backup_keep_days', '30')
ON DUPLICATE KEY UPDATE `value`=VALUES(`value`);
