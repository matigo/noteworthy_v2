SELECT acct."id" as "account_id", acct."display_name", acct."last_name", acct."first_name", acct."email",
       acct."locale_code", acct."timezone", acct."type", acct."guid",
       (SELECT CASE WHEN COUNT(z."key") > 0 THEN true ELSE false END FROM "AccountMeta" z WHERE z."is_deleted" = false AND z."account_id" = acct."id" LIMIT 1) as "has_meta",
       ROUND(EXTRACT(EPOCH FROM acct."created_at")) as "created_unix", ROUND(EXTRACT(EPOCH FROM acct."updated_at")) as "updated_unix"
  FROM "Account" acct
 WHERE acct."is_deleted" = false and acct."id" = [ACCOUNT_ID]
 ORDER BY acct."id" LIMIT 1;