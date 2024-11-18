SELECT su."site_id", si."https", su."url" as "site_url", si."guid" as "site_guid", si."name" as "site_name", si."description", si."keywords",
       si."locale_code", si."theme", ROUND(EXTRACT(EPOCH FROM si."updated_at")) as "version", si."is_default", si."updated_at", 10 as "sort_order",
       CAST(CASE WHEN su."url" = LOWER('[SITE_URL]') THEN false ELSE true END AS boolean) as "do_redirect"
  FROM "Site" si INNER JOIN "SiteUrl" su ON si."id" = su."site_id"
                 INNER JOIN "SiteUrl" bb ON su."site_id" = bb."site_id"
 WHERE bb."is_deleted" = false and su."is_deleted" = false and si."is_deleted" = false and su."is_active" = true
   and bb."url" = LOWER('[SITE_URL]')
 UNION ALL
SELECT su."site_id", si."https", su."url" as "site_url", si."guid" as "site_guid", si."name" as "site_name", si."description", si."keywords",
       si."locale_code", si."theme", ROUND(EXTRACT(EPOCH FROM si."updated_at")) as "version", si."is_default", si."updated_at", 20 as "sort_order",
       CAST(CASE WHEN su."url" = LOWER('[SITE_URL]') THEN false ELSE true END AS boolean) as "do_redirect"
  FROM "Site" si INNER JOIN "SiteUrl" su ON si."id" = su."site_id"
 WHERE su."is_deleted" = false and si."is_deleted" = false and su."is_active" = true and si."is_default" = true
 ORDER BY "sort_order" LIMIT 1;