-- PostgreSQL Database Dump
-- Production-ready schema
-- Updated: 2026-07-01

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

-- =============================================
-- CREATE SEQUENCES FIRST (required before tables)
-- =============================================
CREATE SEQUENCE IF NOT EXISTS attendances_id_seq START 1;
CREATE SEQUENCE IF NOT EXISTS branches_id_seq START 1;
CREATE SEQUENCE IF NOT EXISTS divisions_id_seq START 1;
CREATE SEQUENCE IF NOT EXISTS meeting_feedbacks_id_seq START 1;
CREATE SEQUENCE IF NOT EXISTS meetings_id_seq START 1;
CREATE SEQUENCE IF NOT EXISTS meeting_templates_id_seq START 1;
CREATE SEQUENCE IF NOT EXISTS rooms_id_seq START 1;
CREATE SEQUENCE IF NOT EXISTS users_id_seq START 1;

-- =============================================
-- TABLE: branches
-- =============================================
DROP TABLE IF EXISTS "branches" CASCADE;
CREATE TABLE "branches" (
    "id" integer NOT NULL DEFAULT nextval('branches_id_seq'::regclass),
    "name" character varying NOT NULL,
    "created_at" timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "branches_pkey" PRIMARY KEY ("id")
);

INSERT INTO "branches" ("id", "name", "created_at") VALUES (1, 'Jakarta', '2026-06-07 07:28:46.140073');
INSERT INTO "branches" ("id", "name", "created_at") VALUES (2, 'Surabaya', '2026-06-07 08:10:38.998088');

-- =============================================
-- TABLE: divisions
-- =============================================
DROP TABLE IF EXISTS "divisions" CASCADE;
CREATE TABLE "divisions" (
    "id" integer NOT NULL DEFAULT nextval('divisions_id_seq'::regclass),
    "name" text NOT NULL,
    "created_at" timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    "branch_id" integer DEFAULT 1,
    CONSTRAINT "divisions_pkey" PRIMARY KEY ("id")
);

INSERT INTO "divisions" ("id", "name", "created_at", "branch_id") VALUES (1, 'FINANCE', '2026-05-15 03:59:18.945185', 1);
INSERT INTO "divisions" ("id", "name", "created_at", "branch_id") VALUES (2, 'IT', '2026-05-15 03:59:18.950594', 1);
INSERT INTO "divisions" ("id", "name", "created_at", "branch_id") VALUES (3, 'HR', '2026-05-15 03:59:18.952239', 1);
INSERT INTO "divisions" ("id", "name", "created_at", "branch_id") VALUES (4, 'HRGA', '2026-05-15 03:59:18.954500', 1);
INSERT INTO "divisions" ("id", "name", "created_at", "branch_id") VALUES (5, 'EA', '2026-05-15 03:59:18.956231', 1);
INSERT INTO "divisions" ("id", "name", "created_at", "branch_id") VALUES (6, 'CA', '2026-05-15 03:59:18.958154', 1);
INSERT INTO "divisions" ("id", "name", "created_at", "branch_id") VALUES (29, 'General Affairs', '2026-06-18 02:41:29.499965', 2);

-- =============================================
-- TABLE: rooms
-- =============================================
DROP TABLE IF EXISTS "rooms" CASCADE;
CREATE TABLE "rooms" (
    "id" integer NOT NULL DEFAULT nextval('rooms_id_seq'::regclass),
    "name" text NOT NULL,
    "created_at" timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    "branch_id" integer DEFAULT 1,
    CONSTRAINT "rooms_pkey" PRIMARY KEY ("id")
);

-- Jakarta rooms
INSERT INTO "rooms" ("id", "name", "created_at", "branch_id") VALUES (1, 'Ruang Meeting Besar', '2026-05-11 17:46:55.504547', 1);
INSERT INTO "rooms" ("id", "name", "created_at", "branch_id") VALUES (2, 'Ruang Meeting Kecil', '2026-05-11 17:46:55.508154', 1);
INSERT INTO "rooms" ("id", "name", "created_at", "branch_id") VALUES (3, 'Mezanine', '2026-05-11 17:46:55.510813', 1);
INSERT INTO "rooms" ("id", "name", "created_at", "branch_id") VALUES (4, 'Bridge', '2026-05-12 07:07:21.362873', 1);
INSERT INTO "rooms" ("id", "name", "created_at", "branch_id") VALUES (5, 'Online', NOW(), 1);
-- Surabaya rooms
INSERT INTO "rooms" ("id", "name", "created_at", "branch_id") VALUES (6, 'Online', NOW(), 2);

-- =============================================
-- TABLE: employee_groups
-- =============================================
DROP TABLE IF EXISTS "employee_groups" CASCADE;
CREATE TABLE "employee_groups" (
    "id" serial PRIMARY KEY,
    "name" character varying(100) NOT NULL UNIQUE,
    "description" text,
    "created_at" timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO "employee_groups" ("name", "description") VALUES 
('Manager', 'Kelompok Managerial & Kepala Divisi/Cabang'),
('Kepala Bagian (Kabag)', 'Kelompok Kepala Bagian / Section Head'),
('Staff', 'Kelompok Staff Karyawan')
ON CONFLICT ("name") DO NOTHING;

-- =============================================
-- TABLE: users
-- =============================================
DROP TABLE IF EXISTS "users" CASCADE;
CREATE TABLE "users" (
    "id" integer NOT NULL DEFAULT nextval('users_id_seq'::regclass),
    "nik" text,
    "name" text NOT NULL,
    "username" text NOT NULL,
    "password" text NOT NULL,
    "role" text NOT NULL DEFAULT 'user'::text,
    "jabatan" text,
    "group_name" text DEFAULT 'Staff',
    "division" text,
    "can_schedule" boolean DEFAULT false,
    "can_export" boolean DEFAULT false,
    "can_dashboard" boolean DEFAULT false,
    "photo" text,
    "branch_id" integer DEFAULT 1,
    "is_owner" boolean DEFAULT false,
    CONSTRAINT "users_pkey" PRIMARY KEY ("id")
);

-- PENTING: Ganti password setelah pertama login!
-- Semua password sudah di-hash dengan bcrypt.
-- Hash di bawah ini adalah hasil dari password_hash('password_asli', PASSWORD_BCRYPT)
-- admin       -> 'admin' (superadmin)
-- finance     -> 'password123' (admin request meeting)
-- it          -> 'password123' (user)
-- hr          -> 'password123' (user)
-- asri        -> 'asri'
INSERT INTO "users" ("id", "nik", "name", "username", "password", "role", "jabatan", "group_name", "division", "can_schedule", "can_export", "can_dashboard", "photo", "branch_id", "is_owner")
VALUES (1, '100001', 'Super Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', 'HEAD OF IT', 'Manager', 'IT', true, true, true, NULL, 1, false);

INSERT INTO "users" ("id", "nik", "name", "username", "password", "role", "jabatan", "group_name", "division", "can_schedule", "can_export", "can_dashboard", "photo", "branch_id", "is_owner")
VALUES (2, '120004', 'Umi Damayanti', 'finance', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'FA MANAGER', 'Manager', 'Finance', true, false, true, NULL, 1, false);

INSERT INTO "users" ("id", "nik", "name", "username", "password", "role", "jabatan", "group_name", "division", "can_schedule", "can_export", "can_dashboard", "photo", "branch_id", "is_owner")
VALUES (3, '120041', 'Dwi Prasetya', 'it', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'IT MANAGER', 'Manager', 'IT', false, false, false, NULL, 1, false);

INSERT INTO "users" ("id", "nik", "name", "username", "password", "role", "jabatan", "group_name", "division", "can_schedule", "can_export", "can_dashboard", "photo", "branch_id", "is_owner")
VALUES (4, '120163', 'Yahya Sabil', 'hr', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'HR & GA MANAGER', 'Manager', 'HR', false, false, false, NULL, 1, false);

INSERT INTO "users" ("id", "nik", "name", "username", "password", "role", "jabatan", "group_name", "division", "can_schedule", "can_export", "can_dashboard", "photo", "branch_id", "is_owner")
VALUES (85, '140066', 'Asri Stia Rini Maliek', 'asri', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'KEPALA BAGIAN FA & ADM', 'Kepala Bagian (Kabag)', 'General Affairs', true, true, true, NULL, 2, false);

-- =============================================
-- TABLE: meetings (kolom lengkap termasuk yg di-auto-migrate database.php)
-- =============================================
DROP TABLE IF EXISTS "meetings" CASCADE;
CREATE TABLE "meetings" (
    "id" integer NOT NULL DEFAULT nextval('meetings_id_seq'::regclass),
    "title" text NOT NULL,
    "room" text NOT NULL,
    "scheduled_time" timestamp without time zone NOT NULL,
    "end_time" timestamp without time zone NOT NULL,
    "late_tolerance" integer NOT NULL DEFAULT 15,
    "token" text NOT NULL,
    "created_by" integer NOT NULL,
    "pic_id" integer,
    "status" text NOT NULL DEFAULT 'pending'::text,
    "branch_id" integer DEFAULT 1,
    "has_snack" boolean DEFAULT false,
    "has_coffee" boolean DEFAULT false,
    "coffee_temp" text,
    "coffee_type" text,
    "is_hybrid_zoom" boolean DEFAULT false,
    "pdf_link" text,
    CONSTRAINT "meetings_pkey" PRIMARY KEY ("id")
);

-- =============================================
-- TABLE: attendances
-- =============================================
DROP TABLE IF EXISTS "attendances" CASCADE;
CREATE TABLE "attendances" (
    "id" integer NOT NULL DEFAULT nextval('attendances_id_seq'::regclass),
    "meeting_id" integer NOT NULL,
    "user_id" integer NOT NULL,
    "check_in_time" timestamp without time zone NOT NULL,
    "status" text NOT NULL,
    "late_reason" text,
    CONSTRAINT "attendances_pkey" PRIMARY KEY ("id")
);

-- =============================================
-- TABLE: meeting_feedbacks
-- =============================================
DROP TABLE IF EXISTS "meeting_feedbacks" CASCADE;
CREATE TABLE "meeting_feedbacks" (
    "id" integer NOT NULL DEFAULT nextval('meeting_feedbacks_id_seq'::regclass),
    "meeting_id" integer NOT NULL,
    "user_id" integer NOT NULL,
    "q1_rating" integer DEFAULT 0,
    "q2_rating" integer DEFAULT 0,
    "q3_rating" integer DEFAULT 0,
    "q4_rating" integer DEFAULT 0,
    "feedback_text" text NOT NULL,
    "created_at" timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "meeting_feedbacks_pkey" PRIMARY KEY ("id")
);

-- =============================================
-- TABLE: meeting_participants
-- =============================================
DROP TABLE IF EXISTS "meeting_participants" CASCADE;
CREATE TABLE "meeting_participants" (
    "meeting_id" integer NOT NULL,
    "user_id" integer NOT NULL,
    CONSTRAINT "meeting_participants_pkey" PRIMARY KEY ("meeting_id", "user_id")
);

-- =============================================
-- TABLE: meeting_templates
-- =============================================
DROP TABLE IF EXISTS "meeting_templates" CASCADE;
CREATE TABLE "meeting_templates" (
    "id" integer NOT NULL DEFAULT nextval('meeting_templates_id_seq'::regclass),
    "name" character varying NOT NULL,
    "title" character varying NOT NULL,
    "pic_id" integer,
    "participants" jsonb DEFAULT '[]'::jsonb,
    "branch_id" integer,
    "created_at" timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "meeting_templates_pkey" PRIMARY KEY ("id")
);

INSERT INTO "meeting_templates" ("id", "name", "title", "pic_id", "participants", "branch_id", "created_at")
VALUES (1, 'Review KPI', 'Review KPI Bulanan', 4, '["2", "3"]', 1, '2026-06-18 02:37:20.163371');

-- =============================================
-- TABLE: login_attempts (Rate Limiting)
-- =============================================
CREATE SEQUENCE IF NOT EXISTS login_attempts_id_seq START 1;

DROP TABLE IF EXISTS "login_attempts" CASCADE;
CREATE TABLE "login_attempts" (
    "id" integer NOT NULL DEFAULT nextval('login_attempts_id_seq'::regclass),
    "ip_address" text NOT NULL,
    "attempted_at" timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT "login_attempts_pkey" PRIMARY KEY ("id")
);

CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts (ip_address, attempted_at);

-- =============================================
-- RESET SEQUENCES to correct max values
-- =============================================
SELECT setval('"users_id_seq"', COALESCE((SELECT MAX("id") FROM "users"), 1), true);
SELECT setval('"meetings_id_seq"', COALESCE((SELECT MAX("id") FROM "meetings"), 1), true);
SELECT setval('"attendances_id_seq"', COALESCE((SELECT MAX("id") FROM "attendances"), 1), true);
SELECT setval('"rooms_id_seq"', COALESCE((SELECT MAX("id") FROM "rooms"), 1), true);
SELECT setval('"meeting_feedbacks_id_seq"', COALESCE((SELECT MAX("id") FROM "meeting_feedbacks"), 1), true);
SELECT setval('"divisions_id_seq"', COALESCE((SELECT MAX("id") FROM "divisions"), 1), true);
SELECT setval('"branches_id_seq"', COALESCE((SELECT MAX("id") FROM "branches"), 1), true);
SELECT setval('"meeting_templates_id_seq"', COALESCE((SELECT MAX("id") FROM "meeting_templates"), 1), true);
