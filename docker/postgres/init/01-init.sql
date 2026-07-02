-- ============================================================================
-- PostgreSQL Initialization Script
-- AI-AA LMS — Runs on first database creation only
-- ============================================================================

-- Enable useful extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";

-- Log initialization
DO $$
BEGIN
    RAISE NOTICE '=============================================';
    RAISE NOTICE 'AI-AA LMS — PostgreSQL initialized';
    RAISE NOTICE 'Database: %', current_database();
    RAISE NOTICE 'User: %', current_user;
    RAISE NOTICE 'Time: %', NOW();
    RAISE NOTICE 'Extensions: uuid-ossp, pg_trgm';
    RAISE NOTICE '=============================================';
END $$;
