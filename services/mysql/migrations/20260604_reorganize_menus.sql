-- 菜单重组：拆分"系统管理"为更合理的分组
-- 系统管理（8个→4个）保留核心 RBAC：管理员、角色、菜单、字典
-- 新增"运维管理"目录：定时任务、文件管理
-- 新增"内容管理"目录：通知公告

-- 1. 新增目录
INSERT INTO `menu` (`id`, `parent_id`, `name`, `path`, `icon`, `sort`, `type`) VALUES
(60, 0, '运维管理', '', 'el-icon-monitor', 30, 1),
(61, 0, '内容管理', '', 'el-icon-notebook-2', 20, 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 2. 移动子菜单到新目录
-- 定时任务(id=52) 从系统管理(id=1) → 运维管理(id=60)
UPDATE `menu` SET `parent_id` = 60, `sort` = 1 WHERE `id` = 52;
-- 文件管理(id=35) 从系统管理(id=1) → 运维管理(id=60)
UPDATE `menu` SET `parent_id` = 60, `sort` = 2 WHERE `id` = 35;
-- 系统配置(id=39) 保留在系统管理(id=1) 底部
UPDATE `menu` SET `parent_id` = 1, `sort` = 50 WHERE `id` = 39;
-- 通知公告(id=44) 从系统管理(id=1) → 内容管理(id=61)
UPDATE `menu` SET `parent_id` = 61, `sort` = 1 WHERE `id` = 44;

-- 3. 目录排序（稀疏方便扩展）：用户管理(10)、内容管理(20)、运维管理(30)、日志管理(40)、系统管理(50)
UPDATE `menu` SET `sort` = 10 WHERE `id` = 22;
UPDATE `menu` SET `sort` = 40 WHERE `id` = 23;
UPDATE `menu` SET `sort` = 50 WHERE `id` = 1;

-- 4. 修复执行日志路径：/system/cron-task-log → /log/cron-task-log
UPDATE `menu` SET `path` = '/log/cron-task-log' WHERE `id` = 57;

-- 5. 所有拥有被移动子菜单的角色，自动补上新目录权限
-- 否则 buildTree 找不到父节点，子菜单不会出现在侧边栏
INSERT IGNORE INTO `role_menu` (`role_id`, `menu_id`)
SELECT DISTINCT rm.role_id, 60 FROM role_menu rm WHERE rm.menu_id IN (35,52);
INSERT IGNORE INTO `role_menu` (`role_id`, `menu_id`)
SELECT DISTINCT rm.role_id, 61 FROM role_menu rm WHERE rm.menu_id IN (44);
