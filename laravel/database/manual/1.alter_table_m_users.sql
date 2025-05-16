SHOW FULL COLUMNS FROM `m_users`;

ALTER TABLE `m_users`
MODIFY `fullname` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
