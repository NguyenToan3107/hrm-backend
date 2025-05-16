alter table m_permissions
add column feature varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL;

alter table m_permissions
add column description varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL;

INSERT INTO m_permissions (name, guard_name, created_at, updated_at, feature) VALUES
    ('dashboard_view', 'api', NOW(), NOW(), 'dashboard'),
    ('dashboard_edit', 'api', NOW(), NOW(), 'dashboard'),
    ('mypage', 'api', NOW(), NOW(), 'mypage'),
    ('leave_list', 'api', NOW(), NOW(), 'leave'),
    ('leave_create', 'api', NOW(), NOW(), 'leave'),
    ('leave_execute', 'api', NOW(), NOW(), 'leave'),
    ('staff_master', 'api', NOW(), NOW(), 'master'),
    ('role_master', 'api', NOW(), NOW(), 'master'),
    ('exportPDF', 'api', NOW(), NOW(), 'report'),
    ('add_supplementary', 'api', NOW(), NOW(), 'leave');

alter table m_permissions
add column permission_name varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL;

alter table m_permissions
add column feature_desc varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL;
