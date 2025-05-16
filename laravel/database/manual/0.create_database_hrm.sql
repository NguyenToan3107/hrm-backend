/*
 Navicat Premium Data Transfer

 Source Server         : HRM-Prod
 Source Server Type    : MySQL
 Source Server Version : 101108
 Source Host           : 192.168.1.233:3306
 Source Schema         : hrm

 Target Server Type    : MySQL
 Target Server Version : 101108
 File Encoding         : 65001

 Date: 14/11/2024 14:20:42
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for m_day_offs
-- ----------------------------
DROP TABLE IF EXISTS `m_day_offs`;
CREATE TABLE `m_day_offs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT 'Tiêu đề',
  `day_off` date DEFAULT NULL COMMENT 'Ngày đăng ký nghỉ',
  `status` tinyint(4) DEFAULT NULL COMMENT '0: Nghỉ phép\r\n1: Làm việc',
  `started_at` time DEFAULT NULL COMMENT 'Thời gian bắt đầu nghỉ',
  `ended_at` time DEFAULT NULL COMMENT 'Thời gian kết thúc nghỉ',
  `description` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT 'Mô tả về ngày hôm đó',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `salary` tinyint(4) DEFAULT NULL COMMENT '0: Nghỉ không lương\r\n1: Nghỉ có lương',
  `country` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `is_delete` tinyint(4) DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `id` (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for m_department
-- ----------------------------
DROP TABLE IF EXISTS `m_department`;
CREATE TABLE `m_department` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `leader_id` bigint(20) DEFAULT NULL,
  `is_delete` tinyint(4) DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `id` (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for m_permissions
-- ----------------------------
DROP TABLE IF EXISTS `m_permissions`;
CREATE TABLE `m_permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for m_positions
-- ----------------------------
DROP TABLE IF EXISTS `m_positions`;
CREATE TABLE `m_positions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `is_delete` tinyint(4) DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `id` (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=477059 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for m_roles
-- ----------------------------
DROP TABLE IF EXISTS `m_roles`;
CREATE TABLE `m_roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for m_users
-- ----------------------------
DROP TABLE IF EXISTS `m_users`;
CREATE TABLE `m_users` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `idkey` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `leader_id` bigint(20) DEFAULT NULL COMMENT 'Lưu trữ id của leader hướng dẫn, sếp, chỉ đao, ... của người dùng',
  `username` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT 'Tên đăng nhập vào hệ thống',
  `fullname` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT 'Tên đầy đủ của người dùng',
  `slug` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `is_slug_override` tinyint(1) DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `address` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT 'Địa chỉ người dùng',
  `phone` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `role` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `birth_day` date DEFAULT NULL COMMENT 'Ngày sinh ',
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT 'Mô tả về người dùng',
  `content` mediumtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT 'Chi tiết nội dung về người dùng',
  `image` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `image_extension` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `time_off_hours` int(11) DEFAULT NULL COMMENT 'Số giờ được phép nghỉ / tháng',
  `status_working` tinyint(4) DEFAULT NULL COMMENT '1: Thực tập\r\n2: Thử việc\r\n3: Chính thức\r\n4: Nghỉ việc\r\n5: Tạm thời off',
  `started_at` datetime DEFAULT NULL COMMENT 'Ngày bắt đầu quá trình làm việc',
  `ended_at` datetime DEFAULT NULL COMMENT 'Ngày kết thúc quá trình làm việc',
  `published_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `status` tinyint(4) DEFAULT NULL COMMENT '1: Hoạt động\r\n0: Không hoạt động\r\n2: Tạm khóa',
  `country` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `position_id` bigint(20) DEFAULT NULL,
  `last_year_time_off` int(11) DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `id` (`id`) USING BTREE,
  KEY `slug` (`slug`) USING BTREE,
  KEY `leader_id` (`leader_id`) USING BTREE,
  KEY `created_at` (`created_at`) USING BTREE,
  KEY `status` (`status`) USING BTREE,
  KEY `fk_position` (`position_id`) USING BTREE,
  CONSTRAINT `fk_position` FOREIGN KEY (`position_id`) REFERENCES `m_positions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for r_attendance
-- ----------------------------
DROP TABLE IF EXISTS `r_attendance`;
CREATE TABLE `r_attendance` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `date` date DEFAULT NULL,
  `hours_worked` int(11) DEFAULT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `id` (`id`) USING BTREE,
  KEY `fk_attendance_user_id_foreign` (`user_id`) USING BTREE,
  CONSTRAINT `fk_attendance_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `m_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for r_model_has_permissions
-- ----------------------------
DROP TABLE IF EXISTS `r_model_has_permissions`;
CREATE TABLE `r_model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for r_model_has_roles
-- ----------------------------
DROP TABLE IF EXISTS `r_model_has_roles`;
CREATE TABLE `r_model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`role_id`,`model_id`,`model_type`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for r_role_has_permissions
-- ----------------------------
DROP TABLE IF EXISTS `r_role_has_permissions`;
CREATE TABLE `r_role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`permission_id`,`role_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for r_user_department
-- ----------------------------
DROP TABLE IF EXISTS `r_user_department`;
CREATE TABLE `r_user_department` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL,
  `department_id` bigint(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `user_has_department_id_foreign` (`user_id`) USING BTREE,
  KEY `department_id_foreign` (`department_id`) USING BTREE,
  CONSTRAINT `department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `m_department` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_has_department_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `m_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for t_leaves
-- ----------------------------
DROP TABLE IF EXISTS `t_leaves`;
CREATE TABLE `t_leaves` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) NOT NULL COMMENT 'Id người dùng',
  `title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT 'Lý do xin nghỉ',
  `status` tinyint(4) DEFAULT NULL COMMENT '0: Chờ xác nhận\r\n1: Xác nhận\r\n2: Hủy',
  `salary` tinyint(4) DEFAULT NULL COMMENT '0: Nghỉ không lương\r\n1: Nghỉ có lương\r\n2: Nghỉ ko lương nửa ngày',
  `started_at` time DEFAULT NULL COMMENT 'Thời gian bắt đầu xin nghỉ',
  `ended_at` time DEFAULT NULL COMMENT 'Thời gian kết thức xin nghỉ',
  `description` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL COMMENT 'Mô tả lý do xin nghỉ',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `day_leave` date DEFAULT NULL COMMENT 'Ngày nghỉ',
  `country` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `is_delete` tinyint(4) DEFAULT 0,
  `shift` tinyint(4) DEFAULT NULL COMMENT '0: cả ngày, 1: sáng, 2: chiều',
  `other_info` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `cancel_request` tinyint(4) DEFAULT 0 COMMENT '0: không có, \r\n1: đang yêu cầu, \r\n2: đồng ý\r\n3: không đồng ý',
  `approver_id` bigint(20) DEFAULT NULL,
  `approval_date` date DEFAULT NULL,
  `time_source` tinyint(4) DEFAULT NULL COMMENT '0: time năm ngoái\r\n1: time năm nay\r\n2: time cả 2 năm ',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `id` (`id`) USING BTREE,
  KEY `fk_leaves_user_id_foreign` (`user_id`) USING BTREE,
  CONSTRAINT `fk_leaves_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `m_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;

SET FOREIGN_KEY_CHECKS = 1;
