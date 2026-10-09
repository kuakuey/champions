-- Liga Municipal 2026
-- Formato: todos contra todos, ida y vuelta.
-- Puntos: victoria 3, empate 1, derrota 0.
-- Desempate: puntos, diferencia de gol, goles a favor, enfrentamiento directo.
-- La vuelta y el enfrentamiento directo se resuelven en la aplicación;
-- esta vista solo calcula las cifras, no guarda una tabla de posiciones.
--
-- XAMPP: importa este archivo completo desde phpMyAdmin.
-- Hosting compartido: si no puedes crear bases, comenta CREATE DATABASE y USE,
-- crea la base en el panel e impórtalo dentro de ella.

CREATE DATABASE IF NOT EXISTS campeonato
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE campeonato;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW IF EXISTS v_asistidores;
DROP VIEW IF EXISTS v_goleadores;
DROP VIEW IF EXISTS v_tabla;
DROP TABLE IF EXISTS cuadro_llave;
DROP TABLE IF EXISTS cuadro_puesto;
DROP TABLE IF EXISTS eventos;
DROP TABLE IF EXISTS sanciones;
DROP TABLE IF EXISTS partidos;
DROP TABLE IF EXISTS jugadores;
DROP TABLE IF EXISTS noticias;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS equipos;
DROP TABLE IF EXISTS grupos;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Grupos
-- Se crean al asignar el primer equipo.
-- ------------------------------------------------------------
CREATE TABLE grupos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(20) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_grupos_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Equipos
-- ------------------------------------------------------------
CREATE TABLE equipos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL,
  nombre_corto VARCHAR(10) NOT NULL,
  grupo_id INT UNSIGNED DEFAULT NULL,
  orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  escudo VARCHAR(255) DEFAULT NULL,
  dt VARCHAR(120) DEFAULT NULL,
  ciudad VARCHAR(100) DEFAULT NULL,
  fundacion SMALLINT UNSIGNED DEFAULT NULL,
  colores VARCHAR(80) DEFAULT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_equipos_nombre (nombre),
  UNIQUE KEY uq_equipos_corto (nombre_corto),
  KEY idx_equipos_grupo (grupo_id),
  CONSTRAINT fk_equipos_grupo
    FOREIGN KEY (grupo_id) REFERENCES grupos (id)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Jugadores
-- ------------------------------------------------------------
CREATE TABLE jugadores (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  equipo_id INT UNSIGNED NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  apellido VARCHAR(80) NOT NULL,
  dorsal SMALLINT UNSIGNED DEFAULT NULL,
  posicion ENUM('ARQ', 'DEF', 'MED', 'DEL') DEFAULT NULL,
  foto VARCHAR(255) DEFAULT NULL,
  fecha_nacimiento DATE DEFAULT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_jugadores_dorsal (equipo_id, dorsal),
  KEY idx_jugadores_equipo (equipo_id),
  CONSTRAINT fk_jugadores_equipo
    FOREIGN KEY (equipo_id) REFERENCES equipos (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Partidos
-- El marcador (goles_local / goles_visitante) es la fuente de la tabla.
-- NULL mientras el partido no se haya jugado.
-- ------------------------------------------------------------
CREATE TABLE partidos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  jornada SMALLINT UNSIGNED NOT NULL,
  vuelta TINYINT(1) NOT NULL DEFAULT 0,
  local_id INT UNSIGNED NOT NULL,
  visitante_id INT UNSIGNED NOT NULL,
  fecha DATETIME DEFAULT NULL,
  cancha VARCHAR(150) DEFAULT NULL,
  arbitro VARCHAR(120) DEFAULT NULL,
  goles_local TINYINT UNSIGNED DEFAULT NULL,
  goles_visitante TINYINT UNSIGNED DEFAULT NULL,
  estado ENUM('programado', 'jugado', 'suspendido', 'aplazado') NOT NULL DEFAULT 'programado',
  tipo ENUM('clasificatoria', 'eliminatoria') NOT NULL DEFAULT 'clasificatoria',
  orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_partidos_fecha (fecha),
  KEY idx_partidos_jornada (jornada),
  KEY idx_partidos_estado_fecha (estado, fecha),
  KEY idx_partidos_local (local_id),
  KEY idx_partidos_visitante (visitante_id),
  CONSTRAINT fk_partidos_local
    FOREIGN KEY (local_id) REFERENCES equipos (id)
    ON UPDATE CASCADE,
  CONSTRAINT fk_partidos_visitante
    FOREIGN KEY (visitante_id) REFERENCES equipos (id)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Cuadro de la final: 8 equipos.
-- Puestos 1 a 8 arman los cuartos. Llaves 1 a 4 son cuartos,
-- 5 y 6 la semifinal y 7 la final.
-- ------------------------------------------------------------
CREATE TABLE cuadro_puesto (
  puesto TINYINT UNSIGNED NOT NULL,
  equipo_id INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (puesto),
  CONSTRAINT fk_cuadro_puesto_equipo
    FOREIGN KEY (equipo_id) REFERENCES equipos (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cuadro_llave (
  llave TINYINT UNSIGNED NOT NULL,
  partido_id INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (llave),
  CONSTRAINT fk_cuadro_llave_partido
    FOREIGN KEY (partido_id) REFERENCES partidos (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Eventos del partido
-- gol / autogol: equipo_id es el equipo al que se le acredita el gol.
-- asistencia, amarilla y roja: equipo_id es el equipo del jugador.
-- ------------------------------------------------------------
CREATE TABLE eventos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  partido_id INT UNSIGNED NOT NULL,
  jugador_id INT UNSIGNED DEFAULT NULL,
  equipo_id INT UNSIGNED NOT NULL,
  tipo ENUM('gol', 'asistencia', 'amarilla', 'roja', 'autogol') NOT NULL,
  minuto SMALLINT UNSIGNED NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_eventos_partido (partido_id),
  KEY idx_eventos_jugador (jugador_id),
  KEY idx_eventos_equipo (equipo_id),
  KEY idx_eventos_tipo_jugador (tipo, jugador_id),
  CONSTRAINT fk_eventos_partido
    FOREIGN KEY (partido_id) REFERENCES partidos (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_eventos_jugador
    FOREIGN KEY (jugador_id) REFERENCES jugadores (id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_eventos_equipo
    FOREIGN KEY (equipo_id) REFERENCES equipos (id)
    ON UPDATE CASCADE,
  CONSTRAINT chk_eventos_minuto CHECK (minuto <= 130)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Sanciones
-- tipo amarillas: acumulación (umbral de 3, aplicado al cargar el resultado).
-- tipo roja: expulsión directa. tipo manual: cargada por el administrador.
-- ------------------------------------------------------------
CREATE TABLE sanciones (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  jugador_id INT UNSIGNED NOT NULL,
  partido_origen_id INT UNSIGNED DEFAULT NULL,
  tipo ENUM('amarillas', 'roja', 'manual') NOT NULL,
  partidos_suspension TINYINT UNSIGNED NOT NULL DEFAULT 1,
  partidos_cumplidos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  motivo VARCHAR(255) DEFAULT NULL,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sanciones_jugador (jugador_id),
  KEY idx_sanciones_origen (partido_origen_id),
  KEY idx_sanciones_activa (activa, jugador_id),
  CONSTRAINT fk_sanciones_jugador
    FOREIGN KEY (jugador_id) REFERENCES jugadores (id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_sanciones_partido
    FOREIGN KEY (partido_origen_id) REFERENCES partidos (id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Noticias
-- ------------------------------------------------------------
CREATE TABLE noticias (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titulo VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL,
  resumen VARCHAR(300) DEFAULT NULL,
  cuerpo TEXT NOT NULL,
  imagen VARCHAR(255) DEFAULT NULL,
  publicada TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_noticias_slug (slug),
  KEY idx_noticias_fecha (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Usuarios del panel
-- admin: gestión completa. planillero: solo carga de resultados.
-- ------------------------------------------------------------
CREATE TABLE usuarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('admin', 'planillero') NOT NULL DEFAULT 'planillero',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Vista de la tabla. No es una tabla física: se recalcula siempre.
-- Orden de consulta: pts DESC, dg DESC, gf DESC, nombre ASC.
-- El enfrentamiento directo se aplica en la página de la tabla
-- cuando esos tres criterios siguen empatados.
-- ------------------------------------------------------------
CREATE VIEW v_tabla AS
SELECT
  e.id AS equipo_id,
  e.nombre,
  e.nombre_corto,
  e.escudo,
  e.orden,
  g.id AS grupo_id,
  g.nombre AS grupo,
  COUNT(p.id) AS pj,
  COALESCE(SUM(
    CASE
      WHEN p.local_id = e.id AND p.goles_local > p.goles_visitante THEN 1
      WHEN p.visitante_id = e.id AND p.goles_visitante > p.goles_local THEN 1
      ELSE 0
    END
  ), 0) AS pg,
  COALESCE(SUM(
    CASE
      WHEN p.id IS NOT NULL AND p.goles_local = p.goles_visitante THEN 1
      ELSE 0
    END
  ), 0) AS pe,
  COALESCE(SUM(
    CASE
      WHEN p.local_id = e.id AND p.goles_local < p.goles_visitante THEN 1
      WHEN p.visitante_id = e.id AND p.goles_visitante < p.goles_local THEN 1
      ELSE 0
    END
  ), 0) AS pp,
  COALESCE(SUM(
    CASE
      WHEN p.local_id = e.id THEN p.goles_local
      WHEN p.visitante_id = e.id THEN p.goles_visitante
      ELSE 0
    END
  ), 0) AS gf,
  COALESCE(SUM(
    CASE
      WHEN p.local_id = e.id THEN p.goles_visitante
      WHEN p.visitante_id = e.id THEN p.goles_local
      ELSE 0
    END
  ), 0) AS gc,
  COALESCE(SUM(
    CASE
      WHEN p.local_id = e.id THEN CAST(p.goles_local AS SIGNED) - CAST(p.goles_visitante AS SIGNED)
      WHEN p.visitante_id = e.id THEN CAST(p.goles_visitante AS SIGNED) - CAST(p.goles_local AS SIGNED)
      ELSE 0
    END
  ), 0) AS dg,
  COALESCE(SUM(
    CASE
      WHEN p.local_id = e.id AND p.goles_local > p.goles_visitante THEN 3
      WHEN p.visitante_id = e.id AND p.goles_visitante > p.goles_local THEN 3
      WHEN p.id IS NOT NULL AND p.goles_local = p.goles_visitante THEN 1
      ELSE 0
    END
  ), 0) AS pts
FROM equipos e
LEFT JOIN grupos g ON g.id = e.grupo_id
LEFT JOIN partidos p
  ON (p.local_id = e.id OR p.visitante_id = e.id)
 AND p.estado = 'jugado'
 AND p.tipo = 'clasificatoria'
WHERE e.activo = 1
GROUP BY e.id, e.nombre, e.nombre_corto, e.escudo, e.orden, g.id, g.nombre;

CREATE VIEW v_goleadores AS
SELECT
  j.id AS jugador_id,
  j.nombre,
  j.apellido,
  j.dorsal,
  j.equipo_id,
  e.nombre AS equipo,
  e.nombre_corto,
  e.escudo,
  COUNT(ev.id) AS goles
FROM eventos ev
INNER JOIN jugadores j ON j.id = ev.jugador_id
INNER JOIN equipos e ON e.id = j.equipo_id
WHERE ev.tipo = 'gol'
GROUP BY j.id, j.nombre, j.apellido, j.dorsal, j.equipo_id, e.nombre, e.nombre_corto, e.escudo;

CREATE VIEW v_asistidores AS
SELECT
  j.id AS jugador_id,
  j.nombre,
  j.apellido,
  j.dorsal,
  j.equipo_id,
  e.nombre AS equipo,
  e.nombre_corto,
  e.escudo,
  COUNT(ev.id) AS asistencias
FROM eventos ev
INNER JOIN jugadores j ON j.id = ev.jugador_id
INNER JOIN equipos e ON e.id = j.equipo_id
WHERE ev.tipo = 'asistencia'
GROUP BY j.id, j.nombre, j.apellido, j.dorsal, j.equipo_id, e.nombre, e.nombre_corto, e.escudo;

-- ------------------------------------------------------------
-- Datos de ejemplo: 8 equipos, 6 jugadores cada uno.
-- Jornadas 1 a 3 jugadas. Jornadas 4 y 5 programadas.
-- Emparejamientos de la ida con el método del círculo (round-robin).
-- ------------------------------------------------------------

INSERT INTO equipos (id, nombre, nombre_corto, dt, ciudad, fundacion, colores) VALUES
(1, 'Atlético Verde', 'AVE', 'Carlos Mendoza', 'Villa Norte', 1988, 'Verde y blanco'),
(2, 'Real Blanco', 'RBL', 'Laura Peña', 'Puerto Blanco', 1995, 'Blanco y azul'),
(3, 'Deportivo Norte', 'DNO', 'Ricardo Salas', 'Altos del Norte', 1979, 'Azul y rojo'),
(4, 'Unión Sur', 'USU', 'Helena Duarte', 'Barrio Sur', 2001, 'Amarillo y negro'),
(5, 'Club Central', 'CCE', 'Martín Ospina', 'Centro', 1964, 'Rojo y blanco'),
(6, 'Estrella FC', 'EFC', 'Patricia León', 'Loma Alta', 2010, 'Morado y oro'),
(7, 'Racing del Valle', 'RDV', 'Fernando Calle', 'El Valle', 1992, 'Celeste y blanco'),
(8, 'Independiente Este', 'IES', 'Jorge Ramírez', 'Zona Este', 1983, 'Rojo y negro');

INSERT INTO jugadores (id, equipo_id, nombre, apellido, dorsal, posicion, fecha_nacimiento) VALUES
(1,  1, 'Andrés', 'Ruiz', 1, 'ARQ', '1996-03-12'),
(2,  1, 'Mateo', 'López', 2, 'DEF', '1998-07-21'),
(3,  1, 'Julián', 'Castro', 4, 'DEF', '1997-11-02'),
(4,  1, 'Diego', 'Morales', 8, 'MED', '1999-01-18'),
(5,  1, 'Santiago', 'Herrera', 10, 'MED', '2000-05-09'),
(6,  1, 'Camilo', 'Vargas', 9, 'DEL', '1998-09-30'),
(7,  2, 'Pablo', 'García', 1, 'ARQ', '1995-04-14'),
(8,  2, 'Nicolás', 'Ríos', 3, 'DEF', '1997-08-25'),
(9,  2, 'Héctor', 'Silva', 5, 'DEF', '1996-12-01'),
(10, 2, 'Felipe', 'Duarte', 6, 'MED', '1999-02-17'),
(11, 2, 'Oscar', 'Medina', 8, 'MED', '2001-06-11'),
(12, 2, 'Iván', 'Torres', 11, 'DEL', '1998-10-08'),
(13, 3, 'Ricardo', 'Peña', 1, 'ARQ', '1994-01-23'),
(14, 3, 'Álvaro', 'Gómez', 2, 'DEF', '1997-05-19'),
(15, 3, 'Luis', 'Cárdenas', 4, 'DEF', '1996-09-07'),
(16, 3, 'Emilio', 'Vargas', 10, 'MED', '1999-03-28'),
(17, 3, 'Javier', 'Ortiz', 14, 'MED', '2000-11-15'),
(18, 3, 'Samuel', 'Díaz', 9, 'DEL', '1998-07-04'),
(19, 4, 'Fernando', 'Lara', 1, 'ARQ', '1995-02-09'),
(20, 4, 'Hugo', 'Benítez', 3, 'DEF', '1997-06-22'),
(21, 4, 'Pedro', 'Ramos', 6, 'DEF', '1998-08-13'),
(22, 4, 'Martín', 'Cruz', 8, 'MED', '1999-12-03'),
(23, 4, 'Andrés', 'Pardo', 15, 'MED', '2001-04-27'),
(24, 4, 'Gabriel', 'Soto', 7, 'DEL', '1998-01-31'),
(25, 5, 'César', 'Molina', 1, 'ARQ', '1993-10-16'),
(26, 5, 'Rubén', 'Acosta', 2, 'DEF', '1996-03-05'),
(27, 5, 'Daniel', 'Vega', 5, 'DEF', '1997-07-29'),
(28, 5, 'Tomás', 'Aguilar', 8, 'MED', '1999-09-12'),
(29, 5, 'Sergio', 'Núñez', 10, 'MED', '2000-02-20'),
(30, 5, 'Kevin', 'Rojas', 9, 'DEL', '1998-05-06'),
(31, 6, 'Mario', 'Quintero', 1, 'ARQ', '1996-11-11'),
(32, 6, 'Juan Camilo', 'Rey', 4, 'DEF', '1998-01-08'),
(33, 6, 'Esteban', 'Gil', 13, 'DEF', '1997-04-18'),
(34, 6, 'Alejandro', 'Parra', 6, 'MED', '1999-08-02'),
(35, 6, 'David', 'Cano', 10, 'MED', '2000-12-24'),
(36, 6, 'Cristian', 'León', 11, 'DEL', '1998-06-15'),
(37, 7, 'Jorge', 'Herrera', 1, 'ARQ', '1995-09-09'),
(38, 7, 'Wilson', 'Castro', 2, 'DEF', '1997-02-14'),
(39, 7, 'Fabio', 'Méndez', 3, 'DEF', '1996-07-07'),
(40, 7, 'Harold', 'Suárez', 8, 'MED', '1999-03-03'),
(41, 7, 'Yeison', 'Prado', 14, 'MED', '2001-10-19'),
(42, 7, 'Brayan', 'Córdoba', 9, 'DEL', '1998-12-28'),
(43, 8, 'Omar', 'Delgado', 1, 'ARQ', '1994-05-25'),
(44, 8, 'Carlos', 'Ibáñez', 4, 'DEF', '1997-08-30'),
(45, 8, 'Miguel Ángel', 'Ruiz', 5, 'DEF', '1996-01-12'),
(46, 8, 'Jonathan', 'Pérez', 7, 'MED', '1999-06-06'),
(47, 8, 'Edwin', 'Salazar', 10, 'MED', '2000-09-21'),
(48, 8, 'Jhon Fredy', 'Gómez', 19, 'DEL', '1998-04-04');

INSERT INTO partidos
  (id, jornada, vuelta, local_id, visitante_id, fecha, cancha, arbitro, goles_local, goles_visitante, estado)
VALUES
(1,  1, 0, 1, 8, '2026-09-13 15:00:00', 'Estadio Municipal', 'Carlos Jiménez', 2, 1, 'jugado'),
(2,  1, 0, 2, 7, '2026-09-13 17:00:00', 'Estadio Municipal', 'Ana Lucía Mora', 1, 1, 'jugado'),
(3,  1, 0, 3, 6, '2026-09-13 15:00:00', 'Cancha La Pradera', 'Pedro Arias', 0, 0, 'jugado'),
(4,  1, 0, 4, 5, '2026-09-13 17:00:00', 'Cancha La Pradera', 'Laura Gómez', 1, 3, 'jugado'),
(5,  2, 0, 1, 7, '2026-09-20 15:00:00', 'Estadio Municipal', 'Héctor Blandón', 3, 1, 'jugado'),
(6,  2, 0, 8, 6, '2026-09-20 17:00:00', 'Estadio Municipal', 'Mónica Ruiz', 2, 1, 'jugado'),
(7,  2, 0, 2, 5, '2026-09-20 15:00:00', 'Cancha La Pradera', 'Andrés Cifuentes', 0, 2, 'jugado'),
(8,  2, 0, 3, 4, '2026-09-20 17:00:00', 'Cancha La Pradera', 'Paola Restrepo', 2, 2, 'jugado'),
(9,  3, 0, 1, 6, '2026-09-27 15:00:00', 'Estadio Municipal', 'Carlos Jiménez', 1, 0, 'jugado'),
(10, 3, 0, 7, 5, '2026-09-27 17:00:00', 'Estadio Municipal', 'Ana Lucía Mora', 2, 0, 'jugado'),
(11, 3, 0, 8, 4, '2026-09-27 15:00:00', 'Cancha La Pradera', 'Pedro Arias', 1, 1, 'jugado'),
(12, 3, 0, 2, 3, '2026-09-27 17:00:00', 'Cancha La Pradera', 'Laura Gómez', 0, 1, 'jugado'),
(13, 4, 0, 1, 5, '2026-10-11 15:00:00', 'Estadio Municipal', 'Héctor Blandón', NULL, NULL, 'programado'),
(14, 4, 0, 6, 4, '2026-10-11 17:00:00', 'Estadio Municipal', 'Mónica Ruiz', NULL, NULL, 'programado'),
(15, 4, 0, 7, 3, '2026-10-11 15:00:00', 'Cancha La Pradera', 'Andrés Cifuentes', NULL, NULL, 'programado'),
(16, 4, 0, 8, 2, '2026-10-11 17:00:00', 'Cancha La Pradera', 'Paola Restrepo', NULL, NULL, 'programado'),
(17, 5, 0, 1, 4, '2026-10-18 15:00:00', 'Estadio Municipal', 'Carlos Jiménez', NULL, NULL, 'programado'),
(18, 5, 0, 5, 3, '2026-10-18 17:00:00', 'Estadio Municipal', 'Ana Lucía Mora', NULL, NULL, 'programado'),
(19, 5, 0, 6, 2, '2026-10-18 15:00:00', 'Cancha La Pradera', 'Pedro Arias', NULL, NULL, 'programado'),
(20, 5, 0, 7, 8, '2026-10-18 17:00:00', 'Cancha La Pradera', 'Laura Gómez', NULL, NULL, 'programado');

-- Goles, asistencias y tarjetas. El marcador del partido manda en la tabla.
INSERT INTO eventos (partido_id, jugador_id, equipo_id, tipo, minuto) VALUES
-- Jornada 1: Atlético Verde 2-1 Independiente Este
(1, 4, 1, 'gol', 23),
(1, 5, 1, 'asistencia', 23),
(1, 6, 1, 'gol', 67),
(1, 4, 1, 'asistencia', 67),
(1, 48, 8, 'gol', 51),
(1, 46, 8, 'asistencia', 51),
(1, 2, 1, 'amarilla', 40),
(1, 45, 8, 'amarilla', 70),
-- Real Blanco 1-1 Racing del Valle
(2, 12, 2, 'gol', 34),
(2, 11, 2, 'asistencia', 34),
(2, 42, 7, 'gol', 81),
(2, 40, 7, 'asistencia', 81),
(2, 8, 2, 'amarilla', 55),
(2, 40, 7, 'amarilla', 22),
-- Deportivo Norte 0-0 Estrella FC
(3, 15, 3, 'amarilla', 33),
(3, 33, 6, 'amarilla', 60),
(3, 34, 6, 'amarilla', 88),
-- Unión Sur 1-3 Club Central
(4, 30, 5, 'gol', 12),
(4, 28, 5, 'asistencia', 12),
(4, 24, 4, 'gol', 18),
(4, 22, 4, 'asistencia', 18),
(4, 28, 5, 'gol', 44),
(4, 29, 5, 'asistencia', 44),
(4, 29, 5, 'gol', 80),
(4, 21, 4, 'amarilla', 66),
(4, 20, 4, 'roja', 85),
-- Jornada 2: Atlético Verde 3-1 Racing del Valle
(5, 6, 1, 'gol', 11),
(5, 5, 1, 'asistencia', 11),
(5, 4, 1, 'gol', 39),
(5, 42, 7, 'gol', 52),
(5, 6, 1, 'gol', 78),
(5, 38, 7, 'amarilla', 41),
(5, 2, 1, 'amarilla', 70),
-- Independiente Este 2-1 Estrella FC
(6, 48, 8, 'gol', 15),
(6, 47, 8, 'asistencia', 15),
(6, 36, 6, 'gol', 48),
(6, 35, 6, 'asistencia', 48),
(6, 46, 8, 'gol', 73),
(6, 33, 6, 'amarilla', 64),
(6, 44, 8, 'amarilla', 80),
-- Real Blanco 0-2 Club Central
(7, 30, 5, 'gol', 27),
(7, 29, 5, 'asistencia', 27),
(7, 28, 5, 'gol', 69),
(7, 10, 2, 'amarilla', 33),
(7, 26, 5, 'amarilla', 58),
-- Deportivo Norte 2-2 Unión Sur
(8, 18, 3, 'gol', 9),
(8, 16, 3, 'asistencia', 9),
(8, 24, 4, 'gol', 31),
(8, 17, 3, 'gol', 55),
(8, 22, 4, 'gol', 84),
(8, 14, 3, 'amarilla', 40),
(8, 21, 4, 'amarilla', 62),
-- Jornada 3: Atlético Verde 1-0 Estrella FC
(9, 6, 1, 'gol', 55),
(9, 4, 1, 'asistencia', 55),
(9, 3, 1, 'amarilla', 22),
(9, 33, 6, 'amarilla', 71),
-- Racing del Valle 2-0 Club Central
(10, 42, 7, 'gol', 10),
(10, 41, 7, 'asistencia', 10),
(10, 41, 7, 'gol', 70),
(10, 40, 7, 'asistencia', 70),
(10, 27, 5, 'amarilla', 36),
-- Independiente Este 1-1 Unión Sur
(11, 48, 8, 'gol', 20),
(11, 24, 4, 'gol', 64),
(11, 23, 4, 'asistencia', 64),
(11, 45, 8, 'amarilla', 44),
(11, 23, 4, 'amarilla', 77),
-- Real Blanco 0-1 Deportivo Norte
(12, 18, 3, 'gol', 48),
(12, 16, 3, 'asistencia', 48),
(12, 9, 2, 'amarilla', 25),
(12, 15, 3, 'amarilla', 81);

-- Roja directa a Hugo Benítez y suspensión por 3 amarillas de Esteban Gil.
INSERT INTO sanciones
  (jugador_id, partido_origen_id, tipo, partidos_suspension, partidos_cumplidos, motivo, activa)
VALUES
(20, 4, 'roja', 1, 1, 'Roja directa en la jornada 1. Cumplida en la jornada 2.', 0),
(33, 9, 'amarillas', 1, 0, 'Acumulación de 3 tarjetas amarillas', 1);

INSERT INTO noticias (titulo, slug, resumen, cuerpo, publicada, creado_en) VALUES
(
  'Arranca la Liga Municipal 2026',
  'arranca-la-liga-municipal-2026',
  'Ocho equipos disputan el título en un todos contra todos a ida y vuelta.',
  'La Liga Municipal 2026 ya está en marcha. Atlético Verde, Real Blanco, Deportivo Norte, Unión Sur, Club Central, Estrella FC, Racing del Valle e Independiente Este se miden en el Estadio Municipal y en la Cancha La Pradera. La victoria suma 3 puntos, el empate 1 y la derrota 0. Si hay empate en la tabla, manda la diferencia de gol, luego los goles a favor y, por último, el enfrentamiento directo.',
  1,
  '2026-09-10 09:00:00'
),
(
  'Atlético Verde lidera tras la jornada 3',
  'atletico-verde-lidera-tras-la-jornada-3',
  'Los verdes suman 9 puntos y todavía no conocen la derrota.',
  'Camilo Vargas volvió a marcar y Atlético Verde cerró la tercera fecha con tres victorias. Club Central es el escolta, con 6 puntos, y Deportivo Norte completa el podio provisional. La próxima fecha se juega el 11 de octubre.',
  1,
  '2026-09-27 21:30:00'
),
(
  'Esteban Gil, suspendido por acumulación',
  'esteban-gil-suspendido-por-acumulacion',
  'Tres amarillas le cuestan el partido de la jornada 4 ante Unión Sur.',
  'El defensor de Estrella FC vio la tercera tarjeta amarilla frente a Atlético Verde y no podrá jugar la jornada 4 ante Unión Sur. Hugo Benítez, de Unión Sur, ya cumplió en la jornada 2 la suspensión por la roja directa de la primera fecha. El umbral del campeonato es de 3 amarillas.',
  1,
  '2026-09-28 11:00:00'
);

-- Contraseñas de ejemplo (cámbialas al publicar el sitio):
-- admin@campeonato.local / admin123
-- planillero@campeonato.local / planilla123
INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES
(
  'Administrador',
  'admin@campeonato.local',
  '$2y$10$BjcJA477Op5swY3VjUZV8.8e4rn40LqXmGbKInORq76zo4K1Kaive',
  'admin'
),
(
  'Planillero',
  'planillero@campeonato.local',
  '$2y$10$I.6ESWfCcWoRwyW.O.mQqO.iYxOJhgCEj9jvaQomZ1jMFUiCQ6nb.',
  'planillero'
);
