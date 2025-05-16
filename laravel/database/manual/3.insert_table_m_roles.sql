alter table m_roles
add column role_name VARCHAR(255) DEFAULT null

UPDATE m_roles
SET role_name = CASE
    WHEN id = 1 THEN 'Admin'
    WHEN id = 2 THEN 'Staff'
    WHEN id = 3 THEN 'Leader'
    ELSE role_name
END;
