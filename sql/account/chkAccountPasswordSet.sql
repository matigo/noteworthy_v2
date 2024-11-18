SELECT acct."id" as "account_id", 
       CASE WHEN acct."password" = encode(sha512(CAST(CONCAT('[SHA_SALT]', '[PASSWORD]') AS bytea)), 'hex') THEN true ELSE false END as "pass_set",
       COUNT(tt."id") as "active_tokens"
  FROM "Account" acct LEFT OUTER JOIN "Tokens" tt ON acct."id" = tt."account_id" AND tt."is_deleted" = false
 WHERE acct."is_deleted" = false and acct."id" = [ACCOUNT_ID]
 GROUP BY acct."id", acct."password";