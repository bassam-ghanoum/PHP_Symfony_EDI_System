-- Small, repeatable dataset for exercising the sample invoice import.
-- Run after doctrine:migrations:migrate against a development database only.

INSERT INTO vendor (internal_id, name)
SELECT 'FIXTURE-VENDOR-001', 'Fixture Vendor'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM vendor WHERE internal_id = 'FIXTURE-VENDOR-001'
);

INSERT INTO brand (vendor_id, name)
SELECT vendor.id, 'Fixture Brand'
FROM vendor
WHERE vendor.internal_id = 'FIXTURE-VENDOR-001'
  AND NOT EXISTS (
      SELECT 1 FROM brand
      WHERE brand.vendor_id = vendor.id AND brand.name = 'Fixture Brand'
  );

INSERT INTO product (brand_id, vendor_id, name, product_code)
SELECT brand.id, vendor.id, 'Fixture Product', 'FIXTURE-SKU-001'
FROM vendor
JOIN brand ON brand.vendor_id = vendor.id AND brand.name = 'Fixture Brand'
WHERE vendor.internal_id = 'FIXTURE-VENDOR-001'
  AND NOT EXISTS (
      SELECT 1 FROM product
      WHERE product.vendor_id = vendor.id
        AND product.brand_id = brand.id
        AND product.product_code = 'FIXTURE-SKU-001'
  );

INSERT INTO item (product_id, brand_id, vendor_id, name, ean)
SELECT product.id, product.brand_id, product.vendor_id, 'Fixture Product Unit', '0000000000001'
FROM product
WHERE product.product_code = 'FIXTURE-SKU-001'
  AND NOT EXISTS (
      SELECT 1 FROM item WHERE item.ean = '0000000000001'
  );

INSERT INTO buyer (name, email, status, created_at, updated_at)
SELECT 'Fixture Buyer', 'fixture-buyer@example.invalid', 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM buyer WHERE email = 'fixture-buyer@example.invalid'
);

INSERT INTO orders (brand_id, buyer_id, order_number, created_at, status)
SELECT brand.id, buyer.id, '900001', CURRENT_TIMESTAMP, 'fixture'
FROM brand
JOIN vendor ON vendor.id = brand.vendor_id
JOIN buyer ON buyer.email = 'fixture-buyer@example.invalid'
WHERE vendor.internal_id = 'FIXTURE-VENDOR-001'
  AND brand.name = 'Fixture Brand'
  AND NOT EXISTS (
      SELECT 1 FROM orders WHERE order_number = '900001'
  );

INSERT INTO dispatch (order_id, dispatcher_number, order_number, created_at)
SELECT orders.id, '800001', '900001', CURRENT_TIMESTAMP
FROM orders
WHERE orders.order_number = '900001'
  AND NOT EXISTS (
      SELECT 1 FROM dispatch
      WHERE dispatcher_number = '800001' AND order_number = '900001'
  );