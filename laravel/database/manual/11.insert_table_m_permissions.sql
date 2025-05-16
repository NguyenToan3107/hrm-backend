alter table m_permissions
add column `level` int(11) DEFAULT 0;

alter table m_permissions
add column `permission_cd` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL;

UPDATE m_permissions
SET
    permission_cd = CASE id
        WHEN 1 THEN 'DA011'
        WHEN 2 THEN 'DA021'
        WHEN 3 THEN 'MY011'
        WHEN 4 THEN 'LE011'
        WHEN 5 THEN 'LE021'
        WHEN 6 THEN 'LE022'
        WHEN 7 THEN 'ST011'
        WHEN 8 THEN 'RO011'
        WHEN 9 THEN 'EX011'
        WHEN 10 THEN 'LE023'
        ELSE permission_cd
    END,
    `level` = CASE id
        WHEN 1 THEN 1
        WHEN 2 THEN 2
        WHEN 3 THEN 1
        WHEN 4 THEN 1
        WHEN 5 THEN 2
        WHEN 6 THEN 2
        WHEN 7 THEN 1
        WHEN 8 THEN 1
        WHEN 9 THEN 1
        WHEN 10 THEN 2
        ELSE `level`
    END,
    description = CASE id
        WHEN 1 THEN "User can view the company's work schedule"
        WHEN 2 THEN "Users can register or edit the company's work/leave schedule"
        WHEN 3 THEN 'Users can view and edit their personal information, professional information and change password'
        WHEN 4 THEN 'Users can view the list of leave requests and see the detail of leave request.'
        WHEN 5 THEN 'Users can create and edit leave requests.'
        WHEN 6 THEN 'Users can execute leave requests.'
        WHEN 7 THEN 'Users can view the staff list, add, and edit staff.'
        WHEN 8 THEN 'Users can view the role list, add, and edit roles.'
        WHEN 9 THEN 'Users can generate monthly attendance reports.'
        WHEN 10 THEN 'Users can add an approved supplementary leave request.'
        ELSE description
    END,
    permission_name = CASE id
        WHEN 1 THEN "View Dashboard"
        WHEN 2 THEN "Edit Dashboard"
        WHEN 3 THEN 'My Page'
        WHEN 4 THEN 'Leave List'
        WHEN 5 THEN 'Create Leave'
        WHEN 6 THEN 'Execute Leave'
        WHEN 7 THEN 'Staff Master'
        WHEN 8 THEN 'Role Master'
        WHEN 9 THEN 'Attendance '
        WHEN 10 THEN 'Add Supplementary Leave'
        ELSE permission_name
    END
WHERE id IN (1, 2, 3, 4, 5, 6, 7, 8, 9, 10);
