-- 将系统管理菜单移至底部，同时将顶级菜单 sort 改为稀疏值便于后续扩展
-- 之前：用户管理(0) → 系统管理(1) → 日志管理(2)
-- 之后：用户管理(10) → 日志管理(20) → 系统管理(30)
UPDATE `menu` SET `sort` = 10 WHERE `id` = 22;
UPDATE `menu` SET `sort` = 30 WHERE `id` = 1;
UPDATE `menu` SET `sort` = 20 WHERE `id` = 23;
