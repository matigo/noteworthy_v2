SELECT nn."id" as "note_id", ROUND(EXTRACT(EPOCH FROM nn."updated_at")) as "version",
       CASE WHEN nn."account_id" = [ACCOUNT_ID] THEN true ELSE false END as "can_access"
  FROM "Note" nn
 WHERE nn."is_deleted" = false and nn."guid" = '[NOTE_GUID]'
 LIMIT 1;