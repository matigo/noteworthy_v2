SELECT nn."id" as "note_id", nn."guid", nn."type", nn."title", nn."content", nn."hash",
       CASE WHEN nn."account_id" = [ACCOUNT_ID] THEN true ELSE false END as "can_access",

       th."id" as "thread_id", th."guid" as "thread_guid", th."hash" as "thread_hash", ROUND(EXTRACT(EPOCH FROM th."updated_at")) as "thread_version",
       pp."id" as "parent_id", pp."guid" as "parent_guid", pp."hash" as "parent_hash", ROUND(EXTRACT(EPOCH FROM pp."updated_at")) as "parent_version",
       acct."id" as "account_id", ROUND(EXTRACT(EPOCH FROM acct."updated_at")) as "account_version",
       (SELECT CASE WHEN COUNT(z."key") > 0 THEN true ELSE false END FROM "NoteMeta" z WHERE z."is_deleted" = false AND z."note_id" = nn."id" LIMIT 1) as "has_meta",
       (SELECT CASE WHEN COUNT(z."tag_id") > 0 THEN true ELSE false END FROM "NoteTag" z WHERE z."is_deleted" = false AND z."note_id" = nn."id" LIMIT 1) as "has_tags",
       (SELECT CASE WHEN COUNT(z."id") > 0 THEN true ELSE false END FROM "NoteHistory" z WHERE z."is_deleted" = false AND z."note_id" = nn."id" LIMIT 1) as "has_history",
       nn."created_at", nn."updated_at", nn."updated_by", ROUND(EXTRACT(EPOCH FROM ua."updated_at")) as "updated_by_version"
  FROM "Account" acct INNER JOIN "Note" nn ON acct."id" = nn."account_id"
                 LEFT OUTER JOIN "Note" pp ON nn."parent_id" = pp."id" AND pp."is_deleted" = false
                 LEFT OUTER JOIN "Note" th ON nn."thread_id" = th."id" AND th."is_deleted" = false
                 LEFT OUTER JOIN "Account" ua ON nn."updated_by" = ua."id" AND ua."is_deleted" = false
 WHERE acct."is_deleted" = false and nn."is_deleted" = false and nn."id" = [NOTE_ID]
 LIMIT 1;