USE sbcdb;

ALTER TABLE admins ADD COLUMN role ENUM('admin', 'super_admin') NOT NULL DEFAULT 'admin' AFTER password;

INSERT INTO admins (email, password, role)
VALUES (
    'thesisbuilders12345@gmail.com',
    '$2y$10$g/KEWdAXWewlG1K9QNqiju.zTBOgVxYtmgfpqXslFk4.XDcc4obBi',
    'super_admin'
)
ON DUPLICATE KEY UPDATE
    password = VALUES(password),
    role = VALUES(role);