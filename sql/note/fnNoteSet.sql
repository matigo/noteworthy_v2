DROP FUNCTION note_set;
CREATE OR REPLACE FUNCTION note_set( "in_site_guid" char(36), "in_persona_guid" char(36), "in_note_guid" char(36), "in_reply_guid" char(36),
                                     "in_type" character varying, "in_title" character varying, "in_content" text,
                                     "in_publish_at" character varying, "in_expires_at" character varying,
                                     "in_published" char(1), "in_private" char(1), "in_hidden" char(1),
                                     "in_shahash" character varying, "in_password" character varying,
                                     "in_slug" character varying, "in_url" character varying,
                                 OUT "out_note_id" integer, OUT "out_note_guid" char(36), OUT "out_note_version" integer,
                                 OUT "out_created_at" timestamp, OUT "out_updated_at" timestamp )
    LANGUAGE plpgsql AS $$

    DECLARE xCanPublish boolean;
    DECLARE xAccountId  integer;
    DECLARE xPersonaId  integer;
    DECLARE xSiteId     integer;
    DECLARE xTimeZone   varchar;

    BEGIN
        /* Confirm the author has permission to publish to the requested site */
        SELECT si."id" as "site_id", pa."account_id", pa."id" as "persona_id", acct."timezone",
               CASE WHEN COALESCE(lup."account_id", si."account_id") = pa."account_id" THEN true
                    ELSE (sa."can_admin" OR sa."can_publish") END as "can_publish"
          INTO xSiteId, xAccountId, xPersonaId, xTimeZone, xCanPublish
          FROM "Site" si INNER JOIN "SiteAuthor" sa ON si."id" = sa."site_id"
                         INNER JOIN "Persona" pa ON sa."persona_id" = pa."id"
                         INNER JOIN "Account" acct ON pa."account_id" = acct."id"
                    LEFT OUTER JOIN (SELECT z."account_id" FROM "Persona" z INNER JOIN "Note" nn ON z."id" = nn."persona_id"
                                      WHERE z."is_deleted" = false and nn."is_deleted" = false and nn."guid" = "in_note_guid") lup ON lup."account_id" IS NOT NULL
         WHERE si."is_deleted" = false and sa."is_deleted" = false and pa."is_deleted" = false
           and si."guid" = "in_site_guid" and pa."guid" = "in_persona_guid";

        /* If we have a Note to edit, check to see if we own the Note */
        IF LENGTH("in_note_guid") = 36 THEN
            SELECT CASE WHEN pa."account_id" = xAccountId THEN true ELSE false END INTO xCanPublish
              FROM "Persona" pa INNER JOIN "Note" nn ON pa."id" = nn."persona_id"
             WHERE pa."is_deleted" = false and nn."is_deleted" = false and nn."guid" = "in_note_guid";
        END IF;

        /* Perform some basic validation */
        IF xCanPublish = false THEN
            RAISE EXCEPTION '403';
        END IF;

        /* Record the Note */
        IF LENGTH("in_note_guid") = 36 THEN
                UPDATE "Note"
                   SET "persona_id" = xPersonaId,
                       "type" = LOWER(CASE WHEN "in_type" IN ('note.article', 'note.bookmark', 'note.location', 'note.social', 'note.quotation') THEN "in_type" ELSE 'note.article' END),
                       "title" = CASE WHEN LENGTH(TRIM("in_title")) > 0 THEN TRIM(LEFT("in_title", 1024)) ELSE NULL END,
                       "content" = TRIM("in_content"),

                       "is_published" = CASE WHEN UPPER("in_published") IN ('Y','1','T') THEN true ELSE false END,
                       "is_private" = CASE WHEN UPPER("in_private") IN ('Y','1','T') THEN true ELSE false END,
                       "is_hidden" = CASE WHEN UPPER("in_hidden") IN ('Y','1','T') THEN true ELSE false END,

                       "publish_at" = CASE WHEN LENGTH("in_publish_at") >= 10 THEN TO_TIMESTAMP("in_publish_at", 'YYYY-MM-DD hh24:mi:ss')::timestamp AT TIME ZONE xTimeZone AT TIME ZONE 'UTC'
                                           WHEN 'Y' IN ('Y','1','true') THEN CURRENT_TIMESTAMP
                                           ELSE NULL END,
                       "expires_at" = CASE WHEN LENGTH("in_expires_at") >= 10 AND TO_TIMESTAMP("in_expires_at", 'YYYY-MM-DD hh24:mi:ss')::timestamp AT TIME ZONE xTimeZone AT TIME ZONE 'UTC' > CURRENT_TIMESTAMP
                                           THEN TO_TIMESTAMP("in_expires_at", 'YYYY-MM-DD hh24:mi:ss')::timestamp AT TIME ZONE xTimeZone AT TIME ZONE 'UTC'
                                      ELSE NULL END,

                       "slug" = CASE WHEN LENGTH("in_slug") > 0 THEN LOWER(TRIM(LEFT("in_slug", 256))) ELSE NULL END,
                       "url" = CASE WHEN LENGTH("in_url") > 0 THEN LOWER(TRIM(LEFT("in_url", 256))) ELSE NULL END,

                       "password" = CASE WHEN LENGTH("in_password") >= 8 THEN encode(sha512(CAST(CONCAT("in_shahash", "in_password") AS bytea)), 'hex') ELSE NULL END,

                       "updated_by" = xAccountId
                 WHERE "is_deleted" = false and "guid" = "in_note_guid"
             RETURNING "id", "guid", ROUND(EXTRACT(EPOCH FROM "updated_at")), "created_at", "updated_at"
                  INTO "out_note_id", "out_note_guid", "out_note_version", "out_created_at", "out_updated_at";

        ELSE
            /* INSERT a new record */
            INSERT INTO "Note" ("site_id", "persona_id", "thread_id", "parent_id", "type",
                                "title", "content", "is_published", "is_private", "is_hidden",
                                "publish_at", "expires_at", "slug", "url",
                                "password", "created_by", "updated_by")
            SELECT xSiteId as "site_id", xPersonaId as "persona_id",
                   COALESCE(rpy."thread_id", rpy."id") as "thread_id",
                   rpy."id" as "parent_id",
                   LOWER(CASE WHEN "in_type" IN ('note.article', 'note.bookmark', 'note.location', 'note.social', 'note.quotation') THEN "in_type" ELSE 'note.article' END) as "type",
                   CASE WHEN LENGTH(TRIM("in_title")) > 0 THEN TRIM(LEFT("in_title", 1024)) ELSE NULL END as "title",
                   TRIM("in_content") as "content",

                   CASE WHEN UPPER("in_published") IN ('Y','1','T') THEN true ELSE false END as "is_published",
                   CASE WHEN UPPER("in_private") IN ('Y','1','T') THEN true ELSE false END as "is_private",
                   CASE WHEN UPPER("in_hidden") IN ('Y','1','T') THEN true ELSE false END as "is_hidden",

                   CASE WHEN LENGTH("in_publish_at") >= 10 THEN TO_TIMESTAMP("in_publish_at", 'YYYY-MM-DD hh24:mi:ss')::timestamp AT TIME ZONE xTimeZone AT TIME ZONE 'UTC'
                        WHEN 'Y' IN ('Y','1','true') THEN CURRENT_TIMESTAMP
                        ELSE NULL END as "publish_at",
                   CASE WHEN LENGTH("in_expires_at") >= 10 AND TO_TIMESTAMP("in_expires_at", 'YYYY-MM-DD hh24:mi:ss')::timestamp AT TIME ZONE xTimeZone AT TIME ZONE 'UTC' > CURRENT_TIMESTAMP
                             THEN TO_TIMESTAMP("in_expires_at", 'YYYY-MM-DD hh24:mi:ss')::timestamp AT TIME ZONE xTimeZone AT TIME ZONE 'UTC'
                        ELSE NULL END as "expires_at",

                   CASE WHEN LENGTH("in_slug") > 0 THEN LOWER(TRIM(LEFT("in_slug", 256))) ELSE NULL END as "slug",
                   CASE WHEN LENGTH("in_url") > 0 THEN LOWER(TRIM(LEFT("in_url", 256))) ELSE NULL END as "url",

                   CASE WHEN LENGTH("in_password") >= 8 THEN encode(sha512(CAST(CONCAT("in_shahash", "in_password") AS bytea)), 'hex') ELSE NULL END as "password",
                   pa."account_id" as "created_by", pa."account_id" as "updated_by"
              FROM "Persona" pa LEFT OUTER JOIN "Note" rpy ON rpy."is_deleted" = false and rpy."guid" = "in_reply_guid"
             WHERE pa."is_deleted" = false and pa."guid" = "in_persona_guid"
             LIMIT 1
         RETURNING "id", "guid", ROUND(EXTRACT(EPOCH FROM "updated_at")), "created_at", "updated_at"
              INTO "out_note_id", "out_note_guid", "out_note_version", "out_created_at", "out_updated_at";
        END IF;

    END
    $$;