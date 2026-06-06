-- notice 表加 is_popup 字段
ALTER TABLE `notice` ADD COLUMN `is_popup` TINYINT NOT NULL DEFAULT 0 COMMENT '1=弹窗 0=不弹窗' AFTER `status`;

-- admin 表加 last_notice_seen_id 字段
ALTER TABLE `admin` ADD COLUMN `last_notice_seen_id` INT UNSIGNED DEFAULT 0 COMMENT '最后查看公告ID' AFTER `avatar`;
