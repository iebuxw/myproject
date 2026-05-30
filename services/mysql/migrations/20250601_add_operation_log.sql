-- 操作日志表
CREATE TABLE IF NOT EXISTS `operation_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT UNSIGNED NOT NULL COMMENT '操作人ID',
    `username` VARCHAR(50) NOT NULL COMMENT '操作人用户名',
    `module` VARCHAR(50) NOT NULL COMMENT '模块',
    `action` VARCHAR(20) NOT NULL COMMENT '动作',
    `method` VARCHAR(10) NOT NULL COMMENT 'HTTP方法',
    `url` VARCHAR(255) NOT NULL COMMENT '请求URL',
    `params` TEXT COMMENT '请求参数JSON',
    `ip` VARCHAR(45) DEFAULT '' COMMENT '客户端IP',
    `user_agent` VARCHAR(500) DEFAULT '' COMMENT '浏览器UA',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_admin_id` (`admin_id`),
    INDEX `idx_module` (`module`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作日志';
