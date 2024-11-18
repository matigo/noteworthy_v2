SELECT COUNT("id") as "records" FROM "Account" WHERE "is_deleted" = false and "login" = E'[NICKNAME]'
 UNION ALL
SELECT COUNT("id") as "records" FROM "Persona" WHERE "is_deleted" = false and "nickname" = E'[NICKNAME]'
 ORDER BY "records" DESC LIMIT 1;