-- ============================================
-- InvoicePro — Demo Data Seed
-- Run this AFTER schema.sql to populate demo data
-- ============================================

-- Clear existing demo data (safe to re-run)
DELETE FROM payments WHERE invoice_id IN (SELECT id FROM invoices WHERE user_id = 1);
DELETE FROM invoice_items WHERE invoice_id IN (SELECT id FROM invoices WHERE user_id = 1);
DELETE FROM invoices WHERE user_id = 1;
DELETE FROM clients WHERE user_id = 1;

-- ── Demo Clients ──
INSERT INTO clients (user_id, name, company, email, phone, address, gst_number) VALUES
(1, 'Rahul Sharma', 'TechVista Solutions Pvt Ltd', 'rahul@techvista.io', '+91 98765 43210', '401 Millennium Tower, Sector 62, Noida, UP 201301', 'GSTIN07AABCT1234A1Z5'),
(1, 'Priya Menon', 'Pixel & Co Design Studio', 'priya@pixelco.design', '+91 87654 32109', '12B Indiranagar, 100 Feet Road, Bangalore, KA 560038', 'GSTIN29BBCPD5678B2Z8'),
(1, 'Arjun Kapoor', 'Kapoor Consulting Group', 'arjun@kapoorcg.com', '+91 76543 21098', '55 Connaught Place, New Delhi 110001', 'GSTIN07CCKPG9012C3Z1');

-- Get client IDs (assuming auto-increment from last state)
SET @client1 = (SELECT id FROM clients WHERE email = 'rahul@techvista.io' AND user_id = 1);
SET @client2 = (SELECT id FROM clients WHERE email = 'priya@pixelco.design' AND user_id = 1);
SET @client3 = (SELECT id FROM clients WHERE email = 'arjun@kapoorcg.com' AND user_id = 1);

-- ── Demo Invoices ──

-- Invoice 1: PAID (TechVista - Web App Development)
INSERT INTO invoices (user_id, client_id, invoice_number, issue_date, due_date, subtotal, tax_amount, total_amount, paid_amount, status, currency, notes,
    client_name_snapshot, client_company_snapshot, client_email_snapshot, client_address_snapshot, client_gst_snapshot,
    business_name_snapshot, business_address_snapshot, business_gst_snapshot)
VALUES (1, @client1, 'INV-2026-01-0001', '2026-01-10', '2026-02-10', 85000.00, 15300.00, 100300.00, 100300.00, 'sent', 'INR', 'Full stack web application development - Phase 1',
    'Rahul Sharma', 'TechVista Solutions Pvt Ltd', 'rahul@techvista.io', '401 Millennium Tower, Sector 62, Noida', 'GSTIN07AABCT1234A1Z5',
    'InvoicePro Solutions', '123 Business Park, Mumbai 400001', 'GSTIN27AABCI5678D4Z2');

SET @inv1 = LAST_INSERT_ID();

INSERT INTO invoice_items (invoice_id, description, quantity, rate, tax_percent, line_total) VALUES
(@inv1, 'Frontend Development (React)', 1, 35000.00, 18, 35000.00),
(@inv1, 'Backend API Development (Node.js)', 1, 30000.00, 18, 30000.00),
(@inv1, 'Database Design & Setup', 1, 20000.00, 18, 20000.00);

-- Invoice 2: PARTIAL (Pixel & Co - Branding Package)
INSERT INTO invoices (user_id, client_id, invoice_number, issue_date, due_date, subtotal, tax_amount, total_amount, paid_amount, status, currency, notes,
    client_name_snapshot, client_company_snapshot, client_email_snapshot, client_address_snapshot, client_gst_snapshot,
    business_name_snapshot, business_address_snapshot, business_gst_snapshot)
VALUES (1, @client2, 'INV-2026-01-0002', '2026-01-20', '2026-02-20', 45000.00, 8100.00, 53100.00, 25000.00, 'sent', 'INR', 'Complete brand identity package including logo, colors, and guidelines',
    'Priya Menon', 'Pixel & Co Design Studio', 'priya@pixelco.design', '12B Indiranagar, Bangalore', 'GSTIN29BBCPD5678B2Z8',
    'InvoicePro Solutions', '123 Business Park, Mumbai 400001', 'GSTIN27AABCI5678D4Z2');

SET @inv2 = LAST_INSERT_ID();

INSERT INTO invoice_items (invoice_id, description, quantity, rate, tax_percent, line_total) VALUES
(@inv2, 'Logo Design (3 concepts)', 1, 15000.00, 18, 15000.00),
(@inv2, 'Brand Guidelines Document', 1, 12000.00, 18, 12000.00),
(@inv2, 'Business Card & Letterhead Design', 1, 8000.00, 18, 8000.00),
(@inv2, 'Social Media Kit', 1, 10000.00, 18, 10000.00);

-- Invoice 3: OVERDUE (Kapoor Consulting - Strategy Report)
INSERT INTO invoices (user_id, client_id, invoice_number, issue_date, due_date, subtotal, tax_amount, total_amount, paid_amount, status, currency, notes,
    client_name_snapshot, client_company_snapshot, client_email_snapshot, client_address_snapshot, client_gst_snapshot,
    business_name_snapshot, business_address_snapshot, business_gst_snapshot)
VALUES (1, @client3, 'INV-2026-01-0003', '2025-12-15', '2026-01-15', 60000.00, 10800.00, 70800.00, 0.00, 'sent', 'INR', 'Digital transformation consulting - Phase 1 assessment',
    'Arjun Kapoor', 'Kapoor Consulting Group', 'arjun@kapoorcg.com', '55 Connaught Place, New Delhi', 'GSTIN07CCKPG9012C3Z1',
    'InvoicePro Solutions', '123 Business Park, Mumbai 400001', 'GSTIN27AABCI5678D4Z2');

SET @inv3 = LAST_INSERT_ID();

INSERT INTO invoice_items (invoice_id, description, quantity, rate, tax_percent, line_total) VALUES
(@inv3, 'Business Analysis & Assessment', 1, 25000.00, 18, 25000.00),
(@inv3, 'Technology Roadmap Document', 1, 20000.00, 18, 20000.00),
(@inv3, 'Executive Presentation', 1, 15000.00, 18, 15000.00);

-- Invoice 4: DRAFT (TechVista - Phase 2)
INSERT INTO invoices (user_id, client_id, invoice_number, issue_date, due_date, subtotal, tax_amount, total_amount, paid_amount, status, currency, notes,
    client_name_snapshot, client_company_snapshot, client_email_snapshot, client_address_snapshot, client_gst_snapshot,
    business_name_snapshot, business_address_snapshot, business_gst_snapshot)
VALUES (1, @client1, 'INV-2026-02-0003', '2026-02-20', '2026-03-22', 120000.00, 21600.00, 141600.00, 0.00, 'draft', 'INR', 'Mobile app development - iOS & Android',
    'Rahul Sharma', 'TechVista Solutions Pvt Ltd', 'rahul@techvista.io', '401 Millennium Tower, Sector 62, Noida', 'GSTIN07AABCT1234A1Z5',
    'InvoicePro Solutions', '123 Business Park, Mumbai 400001', 'GSTIN27AABCI5678D4Z2');

SET @inv4 = LAST_INSERT_ID();

INSERT INTO invoice_items (invoice_id, description, quantity, rate, tax_percent, line_total) VALUES
(@inv4, 'iOS App Development', 1, 50000.00, 18, 50000.00),
(@inv4, 'Android App Development', 1, 50000.00, 18, 50000.00),
(@inv4, 'API Integration & Testing', 1, 20000.00, 18, 20000.00);

-- Invoice 5: SENT (Pixel & Co - Website Design)
INSERT INTO invoices (user_id, client_id, invoice_number, issue_date, due_date, subtotal, tax_amount, total_amount, paid_amount, status, currency, notes,
    client_name_snapshot, client_company_snapshot, client_email_snapshot, client_address_snapshot, client_gst_snapshot,
    business_name_snapshot, business_address_snapshot, business_gst_snapshot)
VALUES (1, @client2, 'INV-2026-02-0004', '2026-02-15', '2026-03-15', 75000.00, 13500.00, 88500.00, 0.00, 'sent', 'INR', 'Portfolio website design and development with CMS',
    'Priya Menon', 'Pixel & Co Design Studio', 'priya@pixelco.design', '12B Indiranagar, Bangalore', 'GSTIN29BBCPD5678B2Z8',
    'InvoicePro Solutions', '123 Business Park, Mumbai 400001', 'GSTIN27AABCI5678D4Z2');

SET @inv5 = LAST_INSERT_ID();

INSERT INTO invoice_items (invoice_id, description, quantity, rate, tax_percent, line_total) VALUES
(@inv5, 'UI/UX Design (10 pages)', 1, 30000.00, 18, 30000.00),
(@inv5, 'Frontend Development', 1, 25000.00, 18, 25000.00),
(@inv5, 'CMS Integration (WordPress)', 1, 15000.00, 18, 15000.00),
(@inv5, 'SEO Setup & Optimization', 1, 5000.00, 18, 5000.00);

-- ── Demo Payments ──
INSERT INTO payments (invoice_id, user_id, amount, payment_date, method, reference) VALUES
(@inv1, 1, 50000.00, '2026-01-25', 'bank_transfer', 'NEFT/UTR-2026012500123'),
(@inv1, 1, 50300.00, '2026-02-08', 'upi', 'UPI/CR/2026020800456'),
(@inv2, 1, 25000.00, '2026-02-05', 'bank_transfer', 'NEFT/UTR-2026020500789');

-- ── Update Settings ──
INSERT INTO settings (user_id, business_name, address, gst_number, default_tax, invoice_prefix, number_format, payment_terms)
VALUES (1, 'InvoicePro Solutions', '123 Business Park, Andheri East, Mumbai, Maharashtra 400069', 'GSTIN27AABCI5678D4Z2', 18, 'INV', 'YYYY-MM-NNNN', 'Payment due within 30 days of invoice date.\nBank: HDFC Bank\nAccount: 50100123456789\nIFSC: HDFC0001234\nUPI: business@upi')
ON DUPLICATE KEY UPDATE
    business_name = VALUES(business_name),
    address = VALUES(address),
    gst_number = VALUES(gst_number),
    default_tax = VALUES(default_tax),
    invoice_prefix = VALUES(invoice_prefix),
    number_format = VALUES(number_format),
    payment_terms = VALUES(payment_terms);
