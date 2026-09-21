USE sbcdb;

ALTER TABLE admins ADD COLUMN role ENUM('admin', 'super_admin') NOT NULL DEFAULT 'admin' AFTER password;

-- Deliberately does not create or reset an administrator account.
-- Provision the initial account through a deployment-only process using a
-- unique password hash supplied through a protected secret channel.
