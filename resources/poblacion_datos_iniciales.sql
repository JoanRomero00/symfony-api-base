/* INSERTAR DATOS INICIALES */

-- Usuarios del sistema
-- Las contraseñas son hashes bcrypt. Reemplazar con hashes reales antes de ejecutar en producción.
INSERT INTO usuario (username, roles, password, nombre, apellido, email, fecha_alta) VALUES
    ('superadmin', '["ROLE_SUPER_ADMIN"]'::jsonb, '$2y$13$PLACEHOLDER_HASH_SUPERADMIN', 'Super', 'Admin', 'admin@example.com', NOW());
