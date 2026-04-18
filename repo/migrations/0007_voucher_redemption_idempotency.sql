-- Redemption idempotency: store the outcome on the claim so retries with the
-- same key replay the original result instead of double-redeeming. The
-- unique index is nullable-friendly (MySQL allows multiple NULLs under a
-- UNIQUE KEY) so unredeemed claims are unaffected.

ALTER TABLE voucher_claims
    ADD COLUMN redemption_idempotency_key VARCHAR(128) NULL AFTER redeemed_at,
    ADD COLUMN redeemed_order_amount_cents INT NULL AFTER redemption_idempotency_key,
    ADD COLUMN redeemed_discount_cents INT NULL AFTER redeemed_order_amount_cents,
    ADD UNIQUE KEY ux_claims_redemption_key (redemption_idempotency_key);
