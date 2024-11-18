UPDATE Account 
   SET display_name = E'[DISPLAY_AS]'
 WHERE is_deleted = false and id = [ACCOUNT_ID]
 RETURNING id, guid, updated_at;