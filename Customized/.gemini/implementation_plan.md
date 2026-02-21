# InvoicePro — Fiverr-Ready Implementation Plan

## Current State
- **Frontend**: Premium SPA with dark mode, Chart.js, glassmorphism login ✅
- **Backend**: PHP/MySQL API with controllers, services ✅
- **Database**: MySQL schema with sample data (working) ✅
- **Auth**: Dev-mode token system (working) ✅
- **Invoice Preview Modal**: Working with Print/PDF buttons ✅
- **CSV Export**: Working ✅

## Bugs to Fix

### BUG-1: Invoice Creation — CLIENT_ACCESS_DENIED ✅ DIAGNOSED
- **Root Cause**: NOT a code bug. The test was sending `client_id: 1` but the 
  only client (Sabuja Mishra) has `id: 2`. The original ABC Corporation sample 
  data client (id=1) was replaced.
- **Fix**: Ensure the frontend dropdown always sends a valid client ID. Also 
  seed more sample data for testing. No controller code change needed.

### BUG-2: Invoice Preview shows ₹0 totals  
- **Root Cause**: The existing invoice (INV-2026-1698) has `total_amount=0.00` 
  because it was saved without proper items.
- **Fix**: Will be resolved when we create proper invoices with items.

---

## Task 1: Clean URL Routing (Folder-Based)

**Goal**: Use `/invoice-management/login` and `/invoice-management/` instead of 
`login.html` and `index.html`.

### Approach: Apache .htaccess URL Rewriting

Create an `.htaccess` in the project root that rewrites clean URLs:

```
/invoice-management/              → /invoice-management/frontend/index.html
/invoice-management/login         → /invoice-management/frontend/login.html
/invoice-management/api/*         → /invoice-management/backend/api/index.php
```

**Files to create/modify:**
1. `c:\xampp\htdocs\invoice-management\.htaccess` — Apache rewrite rules
2. `frontend/js/config.js` — Update API_BASE_URL to use `/invoice-management/api`
3. `frontend/js/auth.js` — Update login redirect from `index.html` to `/invoice-management/`
4. `frontend/login.html` — Update form action redirect
5. `frontend/index.html` — Update any hardcoded HTML file references

---

## Task 2: Seed Database with Demo Data

**Goal**: Pre-populate with realistic sample data for demos and Fiverr client reviews.

**Data to seed:**
- 3 sample clients (with diverse company types)
- 5 sample invoices (various statuses: draft, sent, paid, partial, overdue)
- 3 sample payments
- Settings with business name/address

**File**: `schema.sql` — append sample data, plus create `seed.sql` for optional re-seeding.

---

## Task 3: Fiverr-Ready Polish

### 3a. Frontend Enhancements
- **Client card delete button** — add visible delete icon on client cards
- **Invoice table action width** — widen the actions column for 3 buttons (view/edit/delete)
- **Loading states** — add skeleton loading or spinner while data fetches
- **Empty state improvements** — better illustrations/messages
- **Footer with branding** — "Powered by InvoicePro" in sidebar footer

### 3b. Backend Hardening
- **Client list**: Filter out `deleted_at IS NOT NULL` clients (already present but verify)
- **Invoice edit**: The form should prefill correctly with invoice data + items
- **Settings save**: Verify all settings tabs save properly

### 3c. Professional README ✅ (Already created)

---

## Task 4: Test Full App End-to-End

1. Login → Dashboard with real data
2. Create Client → appears in grid
3. Create Invoice → appears in list with correct amounts
4. Preview Invoice → professional paper layout with correct totals
5. Record Payment → updates invoice status
6. Export CSV → downloads valid file
7. Delete Client → soft-deleted, disappears from grid
8. Delete Invoice → soft-deleted, disappears from list
9. Settings → Save business info, shows in invoice preview
10. Dark Mode → Toggle and verify all views

---

## Execution Order

| # | Task | Priority | Est. Time |
|---|------|----------|-----------|
| 1 | Clean URL routing (.htaccess) | HIGH | 10 min |
| 2 | Fix auth redirects for clean URLs | HIGH | 5 min |
| 3 | Seed demo data | MEDIUM | 10 min |
| 4 | Frontend polish (loading states, delete buttons) | MEDIUM | 15 min |
| 5 | Full end-to-end test | HIGH | 10 min |
| 6 | Cleanup test files | LOW | 2 min |

**Total estimated: ~50 minutes**
