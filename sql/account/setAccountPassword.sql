UPDATE "Account"
   SET "password" = encode(sha512(CAST(CONCAT('[SHA_SALT]', '[PASSWORD]') AS bytea)), 'hex')
 WHERE "is_deleted" = false and "id" = [ACCOUNT_ID];