-- myproject 初始化 SQL（幂等）
-- 数据库: myproject
-- 字符集: utf8mb4

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `myproject` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `myproject`;

-- ============================================================
-- admin 表：后台管理员
-- ============================================================
CREATE TABLE IF NOT EXISTS `admin` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL COMMENT 'bcrypt hash',
    `avatar` VARCHAR(255) DEFAULT '' COMMENT '头像',
    `status` TINYINT DEFAULT 1 COMMENT '1启用 0禁用',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='后台管理员';

-- ============================================================
-- user 表：APP 用户（Go API 使用）
-- ============================================================
CREATE TABLE IF NOT EXISTS `user` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `phone` VARCHAR(20) NOT NULL COMMENT '手机号',
    `password` VARCHAR(255) NOT NULL COMMENT 'bcrypt hash',
    `nickname` VARCHAR(50) DEFAULT '' COMMENT '昵称',
    `avatar` VARCHAR(255) DEFAULT '' COMMENT '头像',
    `email` VARCHAR(100) DEFAULT '' COMMENT '邮箱',
    `gender` TINYINT DEFAULT 0 COMMENT '0未知 1男 2女',
    `status` TINYINT DEFAULT 1 COMMENT '1启用 0禁用',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='APP用户';

-- ============================================================
-- role 表：角色
-- ============================================================
CREATE TABLE IF NOT EXISTS `role` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) DEFAULT '',
    `status` TINYINT DEFAULT 1 COMMENT '1启用 0禁用',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色';

-- ============================================================
-- menu 表：菜单/权限
-- ============================================================
CREATE TABLE IF NOT EXISTS `menu` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `parent_id` INT UNSIGNED DEFAULT 0 COMMENT '父级ID',
    `name` VARCHAR(50) NOT NULL COMMENT '菜单名',
    `path` VARCHAR(200) DEFAULT '' COMMENT '路由路径',
    `icon` VARCHAR(50) DEFAULT '' COMMENT '图标',
    `sort` INT DEFAULT 0 COMMENT '排序',
    `type` TINYINT NOT NULL COMMENT '1目录 2菜单 3按钮',
    `status` TINYINT DEFAULT 1 COMMENT '1启用 0禁用',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='菜单权限';

-- ============================================================
-- admin_role 表：管理员-角色关联
-- ============================================================
CREATE TABLE IF NOT EXISTS `admin_role` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT UNSIGNED NOT NULL,
    `role_id` INT UNSIGNED NOT NULL,
    UNIQUE KEY `uk_admin_role` (`admin_id`, `role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员-角色关联';

-- ============================================================
-- role_menu 表：角色-菜单关联
-- ============================================================
CREATE TABLE IF NOT EXISTS `role_menu` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT UNSIGNED NOT NULL,
    `menu_id` INT UNSIGNED NOT NULL,
    UNIQUE KEY `uk_role_menu` (`role_id`, `menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色-菜单关联';

-- ============================================================
-- 初始数据（幂等插入）
-- ============================================================

-- 超级管理员账号: admin / 123456
INSERT INTO `admin` (`id`, `username`, `password`, `status`) VALUES (1, 'admin', '$2y$10$sdIYjgSTtx1Py3AjYpzOQOqp4S87kIqWBaR7GZKlAk8KUX5lWj6l2', 1)
ON DUPLICATE KEY UPDATE `username`=VALUES(`username`);

-- 测试 APP 用户: 13800000000 / 123456
INSERT INTO `user` (`id`, `phone`, `password`, `nickname`, `gender`) VALUES (1, '13800000000', '$2y$10$sdIYjgSTtx1Py3AjYpzOQOqp4S87kIqWBaR7GZKlAk8KUX5lWj6l2', '测试用户', 1)
ON DUPLICATE KEY UPDATE `phone`=VALUES(`phone`);

-- 角色
INSERT INTO `role` (`id`, `name`, `description`) VALUES (1, '超级管理员', '拥有所有权限')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 分配 admin 角色
INSERT INTO `admin_role` (`admin_id`, `role_id`) VALUES (1, 1)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- 菜单数据
INSERT INTO `menu` (`id`, `parent_id`, `name`, `path`, `icon`, `sort`, `type`) VALUES
(1, 0, '系统管理', '', 'el-icon-setting', 0, 1),
(2, 1, '管理员管理', '/system/admin', '', 1, 2),
(3, 1, '角色管理', '/system/role', '', 2, 2),
(4, 1, '菜单管理', '/system/menu', '', 3, 2),
(5, 2, '查询管理员', '', '', 1, 3),
(6, 2, '新增管理员', '', '', 2, 3),
(7, 2, '编辑管理员', '', '', 3, 3),
(8, 2, '删除管理员', '', '', 4, 3),
(9, 3, '查询角色', '', '', 1, 3),
(10, 3, '新增角色', '', '', 2, 3),
(11, 3, '编辑角色', '', '', 3, 3),
(12, 3, '删除角色', '', '', 4, 3),
(13, 4, '查询菜单', '', '', 1, 3),
(14, 4, '新增菜单', '', '', 2, 3),
(15, 4, '编辑菜单', '', '', 3, 3),
(16, 4, '删除菜单', '', '', 4, 3)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 超级管理员拥有所有菜单权限
INSERT INTO `role_menu` (`role_id`, `menu_id`) VALUES
(1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),
(1,9),(1,10),(1,11),(1,12),(1,13),(1,14),(1,15),(1,16)
ON DUPLICATE KEY UPDATE `id`=`id`;
