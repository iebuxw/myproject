CREATE TABLE IF NOT EXISTS `attachment` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `original_name` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '原始文件名',
    `saved_name` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '存储文件名',
    `file_path` VARCHAR(500) NOT NULL DEFAULT '' COMMENT '公网URL',
    `file_size` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '字节数',
    `mime_type` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'MIME类型',
    `ext` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '扩展名',
    `uploader_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '上传者admin.id',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_uploader_id` (`uploader_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='附件文件';
