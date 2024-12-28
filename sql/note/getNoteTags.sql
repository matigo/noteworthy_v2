SELECT tg."key", tg."name", tg."summary", tg."guid"
  FROM "Note" nn INNER JOIN "NoteTag" nt ON nn."id" = nt."note_id"
                 INNER JOIN "Tag" tg ON nt."tag_id" = tg."id"
 WHERE nn."is_deleted" = false and nt."is_deleted" = false and tg."is_deleted" = false
   and nn."id" = [NOTE_ID]
 ORDER BY tg."key", tg."name";