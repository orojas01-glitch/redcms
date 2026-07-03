-- Phase 1 auth hardening.
-- Keeps the RED_Admin table name unchanged and only expands Password storage.

ALTER TABLE `RED_Admin`
  MODIFY `Password` varchar(255) NOT NULL;

