DROP FUNCTION note_set;
CREATE OR REPLACE FUNCTION note_set( "in_account_id" integer, "in_note_guid" char(36), "in_parent_guid" char(36),
                                     "in_type" character varying, "in_title" character varying, "in_content" text,
                                     "in_publish_at" character varying, "in_expires_at" character varying,
                                     "in_sort_order" integer,
                                     "in_taglist" character varying,
                                 OUT "out_note_id" integer, OUT "out_note_guid" char(36), OUT "out_note_version" integer,
                                 OUT "out_created_at" timestamp, OUT "out_updated_at" timestamp )
    LANGUAGE plpgsql AS $$

    DECLARE xCanWrite   boolean;
    DECLARE xTimeZone   varchar;

    BEGIN
        /* If we have a Note to edit, check to see if we can write to the Note */
        IF LENGTH("in_note_guid") = 36 THEN
            SELECT CASE WHEN acct."id" = "in_account_id" THEN true ELSE false END INTO xCanWrite
              FROM "Account" acct INNER JOIN "Note" nn ON acct."id" = nn."account_id"
             WHERE acct."is_deleted" = false and nn."is_deleted" = false and nn."guid" = "in_note_guid";
        END IF;

        /* Perform some basic validation */
        IF xCanWrite = false THEN
            RAISE EXCEPTION '403';
        END IF;

        /* Record the Note */
        IF LENGTH("in_note_guid") = 36 THEN
                UPDATE "Note"
                   SET "type" = LOWER("in_type"),
                       "title" = CASE WHEN LENGTH(TRIM("in_title")) > 0 THEN TRIM(LEFT("in_title", 1024)) ELSE NULL END,
                       "content" = TRIM("in_content"),

                       "sort_order" = CASE WHEN "in_sort_order" BETWEEN 1 AND 9999 THEN "in_sort_order" ELSE 5000 END,
                       "thread_id" = (SELECT COALESCE(z."thread_id", z."id") FROM "Note" z WHERE z."is_deleted" = false AND z."account_id" = "in_account_guid" AND z."guid" = "in_parent_guid" ORDER BY z."id" LIMIT 1),
                       "parent_id" = (SELECT z."id" FROM "Note" z WHERE z."is_deleted" = false AND z."account_id" = "in_account_guid" AND z."guid" = "in_parent_guid" ORDER BY z."id" LIMIT 1),

                       "updated_by" = xAccountId
                 WHERE "is_deleted" = false and "guid" = "in_note_guid"
             RETURNING "id", "guid", ROUND(EXTRACT(EPOCH FROM "updated_at")), "created_at", "updated_at"
                  INTO "out_note_id", "out_note_guid", "out_note_version", "out_created_at", "out_updated_at";

        ELSE
            /* INSERT a new record */
            INSERT INTO "Note" ("account_id", "type", "title", "content", "sort_order", "thread_id", "parent_id", "updated_by")
            SELECT acct."id" as "account_id", LOWER("in_type") as "type",
                   CASE WHEN LENGTH(TRIM("in_title")) > 0 THEN TRIM(LEFT("in_title", 1024)) ELSE NULL END as "title",
                   TRIM("in_content") as "content",
                   CASE WHEN "in_sort_order" BETWEEN 1 AND 9999 THEN "in_sort_order" ELSE 5000 END as "sort_order",
                   COALESCE(pp."thread_id", pp."id") as "thread_id", pp."id" as "parent_id",

                   acct."id" as "updated_by"
              FROM "Account" acct LEFT OUTER JOIN "Note" pp ON pp."is_deleted" = false AND pp."account_id" = acct."id" AND pp."guid" = "in_parent_guid"
             WHERE acct."is_deleted" = false and acct."id" = "in_account_id"
             LIMIT 1
         RETURNING "id", "guid", ROUND(EXTRACT(EPOCH FROM "updated_at")), "created_at", "updated_at"
              INTO "out_note_id", "out_note_guid", "out_note_version", "out_created_at", "out_updated_at";
        END IF;

        /* Set the Tags (if applicable) */
        IF LENGTH("in_taglist") > 0 THEN
            /* Ensure the Tags in the List exist */
            INSERT INTO "Tag" ("account_id", "key", "name")
            SELECT src."account_id", src."key", src."name"
              FROM (SELECT acct."id" as "account_id", LOWER(tt) as "key", tt as "name", tg."guid"
                      FROM "Account" acct LEFT OUTER JOIN string_to_table("in_taglist", '|') tt ON tt IS NOT NULL
                                          LEFT OUTER JOIN "Tag" tg ON acct."id" = tg."account_id" AND tg."key" = LOWER(tt)
                     WHERE acct."is_deleted" = false and acct."id" = "in_account_id") src
             WHERE src."guid" IS NULL and LENGTH(src."key") >= 1;

            /* Set the tags to the Note (do not remove any that might already exist) */
            INSERT INTO "NoteTag" ("note_id", "tag_id")
            SELECT nn."id" as "note_id", src."tag_id"
              FROM "Note" nn INNER JOIN (SELECT acct."id" as "account_id", LOWER(tt) as "key", tt as "name", tg."id" as "tag_id"
                                           FROM "Account" acct LEFT OUTER JOIN string_to_table("in_taglist", '|') tt ON tt IS NOT NULL
                                                               LEFT OUTER JOIN "Tag" tg ON acct."id" = tg."account_id" AND tg."key" = LOWER(tt)
                                          WHERE acct."is_deleted" = false and acct."id" = "in_account_id") src ON nn."account_id" = src."account_id"
             WHERE LENGTH(src."key") >= 1 and nn."id" = "out_note_id"
                ON CONFLICT ("note_id", "tag_id") DO UPDATE
                   SET "is_deleted" = false;
        END IF;
    END
    $$;