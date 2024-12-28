SELECT "out_note_id" as "note_id", "out_note_guid" as "note_guid", "out_note_version" as "version",
       ROUND(EXTRACT(EPOCH FROM "out_created_at")) as "created_unix", ROUND(EXTRACT(EPOCH FROM "out_updated_at")) as "updated_unix"
  FROM note_set([ACCOUNT_ID], '[NOTE_GUID]', '[PARENT_GUID]', E'[TYPE]', E'[TITLE]', E'[CONTENT]', '[PUBLISH_AT]', '[EXPIRES_AT]', [SORT_ORDER], '[TAG_LIST]');