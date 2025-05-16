alter table m_roles
add column description varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL;

INSERT INTO r_role_has_permissions (permission_id, role_id, created_at, updated_at)
SELECT id, 1, NOW(), NOW() FROM m_permissions;

UPDATE m_roles
SET
    description = CASE id
        WHEN 1 THEN N'Admin là role có mọi quyền trong hệ thống'
        WHEN 2 THEN N'Staff là role có quyền thấp nhất, chỉ có thể xem lịch làm việc, update thông tin cá nhân và tạo đơn xin nghỉ phép.'
        WHEN 3 THEN N'Leader là role có toàn bộ quyền của Staff và có thêm quyền duyệt đơn xin nghỉ.'
        ELSE description
    END
WHERE id IN (1, 2, 3);

INSERT INTO r_role_has_permissions (permission_id, role_id, created_at, updated_at)
SELECT id, 2, NOW(), NOW()
FROM m_permissions
WHERE id IN (1, 3, 4, 5, 9);

INSERT INTO r_role_has_permissions (permission_id, role_id, created_at, updated_at)
SELECT id, 3, NOW(), NOW()
FROM m_permissions
WHERE id IN (1, 2, 3, 4, 5, 6, 7, 8, 9);
