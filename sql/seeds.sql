-- Demo catalog + outlets so the home tiles show numbers after install.
-- Safe to re-run only on an empty products/customers table.

INSERT INTO products (sku, name, category, unit, pack_size, mrp, trade_price, gst_pct, qty_on_hand, reorder_level, is_active) VALUES
    ('BIS-GLD-200', 'Gold Marie 200g',        'Biscuits', 'case', '20×200g',  40.00, 32.00, 18.00, 48, 12, 1),
    ('BIS-GLD-400', 'Gold Marie 400g',        'Biscuits', 'case', '12×400g',  75.00, 60.00, 18.00, 24,  8, 1),
    ('OIL-SUN-1L', 'Sunlite Refined Oil 1L', 'Edible oil','case', '12×1L',   165.00, 148.00, 5.00, 36, 10, 1),
    ('SOAP-FSH-100','Fresh Bar Soap 100g',   'Personal', 'case', '72×100g',  18.00, 13.50, 18.00, 80, 20, 1),
    ('DET-CLN-1KG', 'CleanWash Detergent 1kg','Home care','bag',  '1kg',      95.00, 78.00, 18.00, 15, 10, 1),
    ('TEA-LEA-250', 'Leaf Gold Tea 250g',    'Beverages','case', '24×250g',  85.00, 68.00,  5.00,  6, 12, 1);

INSERT INTO customers (name, type, phone, gstin, area, route, address, credit_limit, is_active) VALUES
    ('Sharma Kirana',     'retailer',    '9876500001', NULL, 'Indiranagar', 'Route A', '12, 8th Main',           25000, 1),
    ('City Super Mart',   'modern_trade','9876500002', '29ABCDE1234F1Z5', 'MG Road', 'Route B', '44, Commercial St', 80000, 1),
    ('Lakshmi Wholesale', 'wholesaler',  '9876500003', '29ABCDE5678F1Z5', 'Yelahanka', 'Route A', 'Warehouse 3, KIADB', 150000, 1),
    ('Corner Store 14',   'retailer',    '9876500004', NULL, 'Koramangala', 'Route C', '14th Cross',             15000, 1);

INSERT INTO stock_moves (product_id, qty_delta, reason, note)
SELECT id, qty_on_hand, 'opening', 'Opening stock from seeds'
FROM products;
