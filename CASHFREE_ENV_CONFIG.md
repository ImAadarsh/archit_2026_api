# Cashfree Environment Configuration

Add these environment variables to your `.env` file in the API directory:

```env
# Cashfree Configuration
CASHFREE_CLIENT_ID=TEST108162956a8ed7c31b33fa15f63f59261801
CASHFREE_CLIENT_SECRET=cfsk_ma_test_c2c13226a4c8c3a197c0c64dcd71270f_e929b4e6
CASHFREE_BASE_URL=https://test.cashfree.com/pg
CASHFREE_API_VERSION=2023-08-01
CASHFREE_MODE=test
```

## For Production:

When you're ready for production, update these values:

```env
# Production Cashfree Configuration
CASHFREE_CLIENT_ID=your_production_client_id
CASHFREE_CLIENT_SECRET=your_production_client_secret
CASHFREE_BASE_URL=https://api.cashfree.com/pg
CASHFREE_API_VERSION=2023-08-01
CASHFREE_MODE=production
```

## Database Schema Update

Make sure your `subscription_plans` table has these fields:

```sql
ALTER TABLE subscription_plans 
ADD COLUMN cashfree_plan_id VARCHAR(255) NULL,
ADD COLUMN payment_gateway VARCHAR(50) DEFAULT 'cashfree',
DROP COLUMN razorpay_plan_id;
```

## Migration Complete

The PlanController has been updated to:
1. Use CashfreeService instead of RazorpayService
2. Create plans in Cashfree
3. Store plans in database with `cashfree_plan_id` and `payment_gateway` fields
4. Handle Cashfree API responses properly
