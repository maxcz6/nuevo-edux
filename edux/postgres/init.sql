-- Script de Inicialización PostgreSQL 16 para EDUX

-- Base de datos de testing para PHPUnit
CREATE DATABASE aula_virtual_testing;
GRANT ALL PRIVILEGES ON DATABASE aula_virtual_testing TO postgres;

-- Extensiones útiles para UUIDs y criptografía
\c aula_virtual;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

\c aula_virtual_testing;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
