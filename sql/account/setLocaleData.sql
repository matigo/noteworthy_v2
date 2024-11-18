UPDATE Account 
   SET locale_code = '[LOCALE]'
 WHERE is_deleted = false and id = [ACCOUNT_ID]
 RETURNING id as account_id, locale_code, updated_at;