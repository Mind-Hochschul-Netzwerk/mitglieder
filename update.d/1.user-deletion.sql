
CREATE TABLE deleted_usernames (
    id int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` varchar(255) CHARACTER SET utf8 NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci PACK_KEYS=0;

INSERT INTO deleted_usernames (username) SELECT username FROM mitglieder WHERE deleted = true;

DELETE FROM mitglieder WHERE deleted = true;

ALTER TABLE mitglieder 
DROP COLUMN deleted,
DROP COLUMN delete_time,
DROP COLUMN delete_user_id;
