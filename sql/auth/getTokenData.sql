SELECT "account_id", "account_guid", "email", "type", "display_name", "last_name", "first_name",
       "version", "locale_code", "timezone", "avatar_url", "pref_fontfamily", "pref_fontsize", "pref_colour", "pref_canemail",
       "is_admin", "token_id", "token_guid", "login_at"
  FROM token_chk([TOKEN_ID], '[TOKEN_GUID]', [LIFESPAN])
 LIMIT 1;