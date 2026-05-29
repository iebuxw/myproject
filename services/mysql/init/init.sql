-- myproject 初始化 SQL
-- 仅建库 + 迁移追踪表，表结构和数据由 migrations/ 目录管理

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `myproject` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `myproject`;

CREATE TABLE IF NOT EXISTS `migrations` (
    `filename` VARCHAR(255) PRIMARY KEY,
    `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='迁移记录';
