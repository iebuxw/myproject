-- 通知公告表
CREATE TABLE IF NOT EXISTS `notice` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(200) NOT NULL COMMENT '标题',
    `content` TEXT NOT NULL COMMENT '内容',
    `admin_id` INT UNSIGNED NOT NULL COMMENT '发布人ID',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1=发布 0=草稿',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='通知公告';

-- 菜单：放在"系统管理"目录下
INSERT INTO `menu` (`id`, `parent_id`, `name`, `path`, `icon`, `sort`, `type`) VALUES
(44, 1, '通知公告', '/system/notice', '', 5, 2),
(45, 44, '查询', 'notice:list', '', 1, 3),
(46, 44, '新增', 'notice:add', '', 2, 3),
(47, 44, '编辑', 'notice:edit', '', 3, 3),
(48, 44, '删除', 'notice:delete', '', 4, 3)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 超级管理员拥有新菜单权限
INSERT INTO `role_menu` (`role_id`, `menu_id`) VALUES
(1,44),(1,45),(1,46),(1,47),(1,48)
ON DUPLICATE KEY UPDATE `id`=`id`;
