-- 定时任务表
CREATE TABLE IF NOT EXISTS `cron_task` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL COMMENT '任务名称',
    `command` VARCHAR(200) NOT NULL COMMENT 'ThinkPHP 命令名',
    `cron_expr` VARCHAR(50) NOT NULL COMMENT 'cron 表达式',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1=启用 0=停用',
    `last_run_at` DATETIME NULL COMMENT '上次执行时间',
    `last_status` TINYINT NULL COMMENT '0=失败 1=成功',
    `remark` VARCHAR(255) DEFAULT '' COMMENT '备注',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_command` (`command`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='定时任务';

-- 定时任务执行日志表
CREATE TABLE IF NOT EXISTS `cron_task_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `task_id` INT UNSIGNED NOT NULL COMMENT '关联 cron_task.id',
    `command` VARCHAR(200) NOT NULL COMMENT '命令名',
    `status` TINYINT NOT NULL COMMENT '0=失败 1=成功',
    `output` TEXT COMMENT '命令输出',
    `duration` INT DEFAULT 0 COMMENT '执行耗时(秒)',
    `started_at` DATETIME NOT NULL COMMENT '开始时间',
    INDEX `idx_task_id` (`task_id`),
    INDEX `idx_started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='定时任务日志';

-- 初始数据：将现有 clean_logs 任务纳入管理
INSERT INTO `cron_task` (`name`, `command`, `cron_expr`, `status`, `remark`) VALUES
('清理过期日志', 'clean_logs', '0 3 * * *', 1, '每天3点清理操作日志和登录日志');

-- 菜单：定时任务（挂在"系统管理"目录 id=1 下，sort=6 排在通知公告之后）
INSERT INTO `menu` (`id`, `parent_id`, `name`, `path`, `icon`, `sort`, `type`) VALUES
(52, 1, '定时任务', '/system/cron-task', '', 6, 2),
(53, 52, '查询', 'cron_task:list', '', 1, 3),
(54, 52, '新增', 'cron_task:add', '', 2, 3),
(55, 52, '编辑', 'cron_task:edit', '', 3, 3),
(56, 52, '删除', 'cron_task:delete', '', 4, 3)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 菜单：执行日志（挂在"日志管理"目录 id=23 下，sort=3 排在操作日志之后）
INSERT INTO `menu` (`id`, `parent_id`, `name`, `path`, `icon`, `sort`, `type`) VALUES
(57, 23, '执行日志', '/system/cron-task-log', '', 30, 2),
(58, 57, '查询', 'cron_task_log:list', '', 1, 3),
(59, 57, '删除', 'cron_task_log:delete', '', 2, 3)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 超管拥有新菜单权限
INSERT INTO `role_menu` (`role_id`, `menu_id`) VALUES
(1,52),(1,53),(1,54),(1,55),(1,56),
(1,57),(1,58),(1,59)
ON DUPLICATE KEY UPDATE `id`=`id`;
