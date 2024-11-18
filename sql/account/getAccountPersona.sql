SELECT acct."id" as "account_id", acct."guid" as "account_guid", acct."type" as "account_type",
       COALESCE(acct."display_name", acct."first_name") as "display_name",
       acct."last_name", acct."first_name", acct."email", acct."locale_code", acct."timezone",
       CASE WHEN acct."type" IN ('account.global', 'account.admin') THEN true ELSE false END as "is_admin",
       (SELECT CASE WHEN COUNT(DISTINCT z."key") > 0 THEN true ELSE false END
         FROM "AccountMeta" z WHERE z."is_deleted" = false and z."account_id" = acct."id") as "has_meta",
       CASE WHEN acct."id" = [ACCOUNT_ID] THEN true ELSE false END as "is_you",
       acct."created_at", acct."updated_at" as "updated_at", ROUND(EXTRACT(EPOCH FROM acct."updated_at")) as "version",

       pa."id" as "persona_id", pa."guid" as "persona_guid", pa."is_default", pa."is_active",
       pa."nickname", pa."display_name" as "persona_name", pa."last_name" as "persona_last_name", pa."first_name" as "persona_first_name",
       pa."email" as "persona_email", COALESCE(pa."avatar_url", mm."value") as "avatar_url", pa."bio" as "persona_bio", pm."value" as "my_site",
       pa."created_at" as "persona_created_at", pa."updated_at" as "persona_updated_at"
  FROM "Account" acct INNER JOIN "Persona" pa ON acct."id" = pa."account_id"
                 LEFT OUTER JOIN "AccountMeta" mm ON acct."id" = mm."account_id" AND mm."key" = 'profile.avatar' AND mm."is_deleted" = false
                 LEFT OUTER JOIN "PersonaMeta" pm ON pa."id" = pm."persona_id" AND pm."key" = 'profile.url' AND pm."is_deleted" = false
 WHERE acct."is_deleted" = false and pa."is_deleted" = false and pa."guid" = '[PERSONA_GUID]'
 LIMIT 1;