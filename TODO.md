# TODO: Implement Barcode Scanning for Purchase Receiving

## 1. Database Migrations
- [ ] Create migration to add `received_quantity` (float, default 0) to `purchase_details` table.
- [ ] Create migration for new `purchase_barcode_scans` table: id, purchase_detail_id, barcode (string), type (enum: 'indoor', 'outdoor', null), timestamps.

## 2. Model Updates
- [ ] Update `PurchaseDetail` model: add `received_quantity` to fillable and casts (double), add relationship to `PurchaseBarcodeScan`.

## 3. Backend API
- [x] Add new model `PurchaseBarcodeScan` with fillable: purchase_detail_id, barcode, type.
- [x] In `PurchaseController`, add `scanBarcode` method: validate input (purchase_id, barcode, type for cat 123), find product, check category, save scan, update received_quantity logic.
- [x] Add route for POST /purchases/scan-barcode.

## 4. Frontend Updates
- [x] In `index_purchase.vue`, add "Scan Barcode" button in actions dropdown for each purchase.
- [x] Add modal with barcode input, and for category 123, radio/select for type (indoor/outdoor).
- [x] On submit, call API, show success/error, refresh purchase data.

## 5. Testing and Followup
- [x] Run migrations.
- [x] Test scanning: for cat 123, scan indoor/outdoor up to quantity, received_quantity = min(indoor, outdoor).
- [x] For other cats, each scan increments received_quantity up to quantity.
- [x] Update purchase statut to 'received' if all items fully received.
- [x] Verify scans saved, prevent over-scanning.
