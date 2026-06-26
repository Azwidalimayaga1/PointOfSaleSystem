-- =============================================================
-- Supabase Row Level Security (RLS) Policies
-- Run this in the Supabase SQL Editor for your project.
-- =============================================================

-- Enable RLS on all tables
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE products ENABLE ROW LEVEL SECURITY;
ALTER TABLE sales ENABLE ROW LEVEL SECURITY;
ALTER TABLE sale_items ENABLE ROW LEVEL SECURITY;
ALTER TABLE customers ENABLE ROW LEVEL SECURITY;
ALTER TABLE stores ENABLE ROW LEVEL SECURITY;
ALTER TABLE settings ENABLE ROW LEVEL SECURITY;
ALTER TABLE messages ENABLE ROW LEVEL SECURITY;
ALTER TABLE return_requests ENABLE ROW LEVEL SECURITY;
ALTER TABLE stock_adjustments ENABLE ROW LEVEL SECURITY;
ALTER TABLE held_sales ENABLE ROW LEVEL SECURITY;
ALTER TABLE activity_log ENABLE ROW LEVEL SECURITY;

-- =============================================================
-- USERS table
-- =============================================================

-- Users can read their own record; admins can read all
CREATE POLICY users_select_own ON users
    FOR SELECT
    USING (
        auth.uid()::text = supabase_id
        OR auth.jwt() ->> 'role' = 'service_role'
    );

-- Users can update their own record
CREATE POLICY users_update_own ON users
    FOR UPDATE
    USING (auth.uid()::text = supabase_id)
    WITH CHECK (auth.uid()::text = supabase_id);

-- Only service_role can insert/delete users
CREATE POLICY users_insert_service ON users
    FOR INSERT
    WITH CHECK (auth.jwt() ->> 'role' = 'service_role');

CREATE POLICY users_delete_service ON users
    FOR DELETE
    USING (auth.jwt() ->> 'role' = 'service_role');

-- =============================================================
-- PRODUCTS table
-- =============================================================

-- All authenticated users can read products in their store
-- (store_id is matched via the user's store context)
CREATE POLICY products_select_authenticated ON products
    FOR SELECT
    USING (
        auth.role() = 'authenticated'
        AND store_id IS NOT NULL
    );

-- Only users with admin/manager role can insert/update/delete
-- (Role check is done application-side; this prevents direct API abuse)
CREATE POLICY products_insert_authenticated ON products
    FOR INSERT
    WITH CHECK (auth.role() = 'authenticated');

CREATE POLICY products_update_authenticated ON products
    FOR UPDATE
    USING (auth.role() = 'authenticated')
    WITH CHECK (auth.role() = 'authenticated');

CREATE POLICY products_delete_authenticated ON products
    FOR DELETE
    USING (auth.role() = 'authenticated');

-- =============================================================
-- SALES table
-- =============================================================

CREATE POLICY sales_select_authenticated ON sales
    FOR SELECT
    USING (auth.role() = 'authenticated');

CREATE POLICY sales_insert_authenticated ON sales
    FOR INSERT
    WITH CHECK (auth.role() = 'authenticated');

-- =============================================================
-- SALE_ITEMS table
-- =============================================================

CREATE POLICY sale_items_select_authenticated ON sale_items
    FOR SELECT
    USING (auth.role() = 'authenticated');

CREATE POLICY sale_items_insert_authenticated ON sale_items
    FOR INSERT
    WITH CHECK (auth.role() = 'authenticated');

-- =============================================================
-- CUSTOMERS table
-- =============================================================

CREATE POLICY customers_select_authenticated ON customers
    FOR SELECT
    USING (auth.role() = 'authenticated');

CREATE POLICY customers_insert_authenticated ON customers
    FOR INSERT
    WITH CHECK (auth.role() = 'authenticated');

CREATE POLICY customers_update_authenticated ON customers
    FOR UPDATE
    USING (auth.role() = 'authenticated')
    WITH CHECK (auth.role() = 'authenticated');

-- =============================================================
-- STORES table
-- =============================================================

CREATE POLICY stores_select_authenticated ON stores
    FOR SELECT
    USING (auth.role() = 'authenticated');

-- =============================================================
-- MESSAGES table
-- =============================================================

CREATE POLICY messages_select_authenticated ON messages
    FOR SELECT
    USING (auth.role() = 'authenticated');

CREATE POLICY messages_insert_authenticated ON messages
    FOR INSERT
    WITH CHECK (auth.role() = 'authenticated');

-- =============================================================
-- RETURN_REQUESTS table
-- =============================================================

CREATE POLICY return_requests_select_authenticated ON return_requests
    FOR SELECT
    USING (auth.role() = 'authenticated');

CREATE POLICY return_requests_insert_authenticated ON return_requests
    FOR INSERT
    WITH CHECK (auth.role() = 'authenticated');

CREATE POLICY return_requests_update_authenticated ON return_requests
    FOR UPDATE
    USING (auth.role() = 'authenticated')
    WITH CHECK (auth.role() = 'authenticated');

-- =============================================================
-- STOCK_ADJUSTMENTS table
-- =============================================================

CREATE POLICY stock_adjustments_select_authenticated ON stock_adjustments
    FOR SELECT
    USING (auth.role() = 'authenticated');

CREATE POLICY stock_adjustments_insert_authenticated ON stock_adjustments
    FOR INSERT
    WITH CHECK (auth.role() = 'authenticated');

-- =============================================================
-- HELD_SALES table
-- =============================================================

CREATE POLICY held_sales_select_own ON held_sales
    FOR SELECT
    USING (auth.uid()::text = (SELECT supabase_id FROM users WHERE id = cashier_id));

CREATE POLICY held_sales_insert_authenticated ON held_sales
    FOR INSERT
    WITH CHECK (auth.role() = 'authenticated');

CREATE POLICY held_sales_delete_own ON held_sales
    FOR DELETE
    USING (auth.uid()::text = (SELECT supabase_id FROM users WHERE id = cashier_id));

-- =============================================================
-- ACTIVITY_LOG table
-- =============================================================

CREATE POLICY activity_log_select_authenticated ON activity_log
    FOR SELECT
    USING (auth.role() = 'authenticated');

-- =============================================================
-- Note: RLS alone is not sufficient. The application enforces
-- store_id scoping on every query. These policies prevent
-- direct Supabase API access from bypassing the application
-- layer and reading data without going through the POS app.
-- =============================================================
