-- 1. 填充 type=3 按钮的 path 权限标识，统一 name
UPDATE `menu` SET `path` = 'admin:list', `name` = '查询' WHERE `id` = 5;
UPDATE `menu` SET `path` = 'admin:add', `name` = '新增' WHERE `id` = 6;
UPDATE `menu` SET `path` = 'admin:edit', `name` = '编辑' WHERE `id` = 7;
UPDATE `menu` SET `path` = 'admin:delete', `name` = '删除' WHERE `id` = 8;

UPDATE `menu` SET `path` = 'role:list', `name` = '查询' WHERE `id` = 9;
UPDATE `menu` SET `path` = 'role:add', `name` = '新增' WHERE `id` = 10;
UPDATE `menu` SET `path` = 'role:edit', `name` = '编辑' WHERE `id` = 11;
UPDATE `menu` SET `path` = 'role:delete', `name` = '删除' WHERE `id` = 12;

UPDATE `menu` SET `path` = 'menu:list', `name` = '查询' WHERE `id` = 13;
UPDATE `menu` SET `path` = 'menu:add', `name` = '新增' WHERE `id` = 14;
UPDATE `menu` SET `path` = 'menu:edit', `name` = '编辑' WHERE `id` = 15;
UPDATE `menu` SET `path` = 'menu:delete', `name` = '删除' WHERE `id` = 16;

UPDATE `menu` SET `path` = 'user:list', `name` = '查询' WHERE `id` = 18;
UPDATE `menu` SET `path` = 'user:add', `name` = '新增' WHERE `id` = 19;
UPDATE `menu` SET `path` = 'user:edit', `name` = '编辑' WHERE `id` = 20;
UPDATE `menu` SET `path` = 'user:delete', `name` = '删除' WHERE `id` = 21;

UPDATE `menu` SET `path` = 'login_log:list', `name` = '查询' WHERE `id` = 25;
UPDATE `menu` SET `path` = 'login_log:delete', `name` = '删除' WHERE `id` = 26;

UPDATE `menu` SET `path` = 'operation_log:list', `name` = '查询' WHERE `id` = 28;
UPDATE `menu` SET `path` = 'operation_log:delete', `name` = '删除' WHERE `id` = 29;

UPDATE `menu` SET `path` = 'dict:list', `name` = '查询' WHERE `id` = 31;
UPDATE `menu` SET `path` = 'dict:add', `name` = '新增' WHERE `id` = 32;
UPDATE `menu` SET `path` = 'dict:edit', `name` = '编辑' WHERE `id` = 33;
UPDATE `menu` SET `path` = 'dict:delete', `name` = '删除' WHERE `id` = 34;

UPDATE `menu` SET `path` = 'attachment:list', `name` = '查询' WHERE `id` = 36;
UPDATE `menu` SET `path` = 'attachment:upload', `name` = '上传' WHERE `id` = 37;
UPDATE `menu` SET `path` = 'attachment:delete', `name` = '删除' WHERE `id` = 38;

UPDATE `menu` SET `path` = 'system_config:list' WHERE `id` = 40;
UPDATE `menu` SET `path` = 'system_config:edit' WHERE `id` = 41;

-- 2. 新增用户导出/导入按钮
INSERT INTO `menu` (`id`, `parent_id`, `name`, `path`, `icon`, `sort`, `type`) VALUES
(42, 17, '导出', 'user:export', '', 5, 3),
(43, 17, '导入', 'user:import', '', 6, 3)
ON DUPLICATE KEY UPDATE `path`=VALUES(`path`), `name`=VALUES(`name`);

-- 3. 超级管理员拥有新按钮权限
INSERT INTO `role_menu` (`role_id`, `menu_id`) VALUES
(1, 42), (1, 43)
ON DUPLICATE KEY UPDATE `id`=`id`;
