CREATE TABLE membership (id INT AUTO_INCREMENT NOT NULL, last_registration_id INT DEFAULT NULL, main_beneficiary_id INT DEFAULT NULL, member_number INT NOT NULL, withdrawn TINYINT(1) DEFAULT '0', frozen TINYINT(1) DEFAULT '0', frozen_change TINYINT(1) DEFAULT '0', first_shift_date DATE DEFAULT NULL, UNIQUE INDEX UNIQ_86FFD2856986CF73 (last_registration_id), UNIQUE INDEX UNIQ_86FFD28562C6E4EA (main_beneficiary_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB;
ALTER TABLE membership ADD CONSTRAINT FK_86FFD2856986CF73 FOREIGN KEY (last_registration_id) REFERENCES registration (id);
ALTER TABLE membership ADD CONSTRAINT FK_86FFD28562C6E4EA FOREIGN KEY (main_beneficiary_id) REFERENCES beneficiary (id) ON DELETE SET NULL;

INSERT INTO membership (id, last_registration_id, main_beneficiary_id, member_number, withdrawn, frozen, frozen_change, first_shift_date)
SELECT id, last_registration_id, main_beneficiary_id, member_number, withdrawn, frozen, frozen_change, first_shift_date FROM fos_user;

-- beneficiary / Add membership_id
ALTER TABLE beneficiary ADD membership_id INT DEFAULT NULL;
ALTER TABLE beneficiary ADD CONSTRAINT FK_7ABF446A1FB354CD FOREIGN KEY (membership_id) REFERENCES membership (id) ON DELETE CASCADE;
CREATE INDEX IDX_7ABF446A1FB354CD ON beneficiary (membership_id);
UPDATE beneficiary set membership_id = user_id;

-- fos_user / Delete main_beneficiary_id
ALTER TABLE fos_user DROP FOREIGN KEY FK_957A64796986CF73;
ALTER TABLE fos_user ADD CONSTRAINT FK_957A64796986CF73 FOREIGN KEY (last_registration_id) REFERENCES registration (id);

-- Migrate time_logs
ALTER TABLE time_log ADD membership_id INT NOT NULL;
UPDATE time_log SET membership_id = user_id;
ALTER TABLE time_log ADD CONSTRAINT FK_55BE03AF1FB354CD FOREIGN KEY (membership_id) REFERENCES membership (id) ON DELETE CASCADE;
CREATE INDEX IDX_55BE03AF1FB354CD ON time_log (membership_id);

ALTER TABLE time_log DROP FOREIGN KEY FK_55BE03AFA76ED395;
DROP INDEX IDX_55BE03AFA76ED395 ON time_log;
ALTER TABLE time_log DROP user_id;

-- beneficiary/user relationship / OneToMany to ManyToOne
UPDATE beneficiary b JOIN fos_user u ON u.id = b.user_id SET b.user_id = NULL WHERE b.id != u.main_beneficiary_id;
ALTER TABLE beneficiary DROP INDEX IDX_7ABF446AA76ED395, ADD UNIQUE INDEX UNIQ_7ABF446AA76ED395 (user_id);
ALTER TABLE beneficiary DROP FOREIGN KEY FK_7ABF446AA76ED395;
ALTER TABLE beneficiary ADD CONSTRAINT FK_7ABF446AA76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id);

-- Migrate registrations
ALTER TABLE registration ADD membership_id INT DEFAULT NULL;
UPDATE registration SET membership_id = user_id;
ALTER TABLE registration ADD CONSTRAINT FK_62A8A7A71FB354CD FOREIGN KEY (membership_id) REFERENCES membership (id) ON DELETE CASCADE;
CREATE INDEX IDX_62A8A7A71FB354CD ON registration (membership_id);
ALTER TABLE fos_user DROP FOREIGN KEY FK_957A64796986CF73;
ALTER TABLE fos_user ADD CONSTRAINT FK_957A64796986CF73 FOREIGN KEY (last_registration_id) REFERENCES registration (id) ON DELETE SET NULL;

-- Migrate notes
ALTER TABLE note ADD membership_id INT DEFAULT NULL;
UPDATE note SET membership_id = user_id;
ALTER TABLE note ADD CONSTRAINT FK_CFBDFA141FB354CD FOREIGN KEY (membership_id) REFERENCES membership (id);
CREATE INDEX IDX_CFBDFA141FB354CD ON note (membership_id);

-- Migrate address
ALTER TABLE beneficiary ADD address_id INT DEFAULT NULL;
UPDATE beneficiary b JOIN fos_user u ON u.id = b.user_id SET b.address_id = u.address_id;
ALTER TABLE beneficiary ADD CONSTRAINT FK_7ABF446AF5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id);
CREATE UNIQUE INDEX UNIQ_7ABF446AF5B7AF75 ON beneficiary (address_id);

-- Migrate proxies
ALTER TABLE proxy DROP FOREIGN KEY FK_7372C9BE5DE37FD9;
ALTER TABLE proxy ADD CONSTRAINT FK_7372C9BE5DE37FD9 FOREIGN KEY (giver) REFERENCES membership (id) ON DELETE CASCADE;


-- Drop old columns of user
ALTER TABLE fos_user DROP FOREIGN KEY FK_957A647962C6E4EA;
ALTER TABLE fos_user DROP FOREIGN KEY FK_957A64796986CF73;
ALTER TABLE fos_user DROP FOREIGN KEY FK_957A6479F5B7AF75;

DROP INDEX UNIQ_957A647962C6E4EA ON fos_user;
DROP INDEX UNIQ_957A6479F5B7AF75 ON fos_user;
DROP INDEX UNIQ_957A64796986CF73 ON fos_user;

ALTER TABLE fos_user DROP address_id, DROP last_registration_id, DROP withdrawn, DROP frozen, DROP first_shift_date, DROP frozen_change, DROP member_number;

-- Specific links between beneficiaries and users
-- Sonia SELMI
update beneficiary set user_id = 1395 where id = 1093;
-- Clément DREVETON
update beneficiary set user_id = 104 where id = 103;
-- Camille ALEZIER
update beneficiary set user_id = 481 where id = 480;
-- Perrine DELAISON
update beneficiary set user_id = 156 where id = 155;
-- Joséphine BROI
update beneficiary set user_id = 272 where id = 271;
-- Pierre JODAR
update beneficiary set user_id = 548 where id = 547;
-- Vannina TOMASINI
update beneficiary set user_id = 739 where id = 738;
-- Thomas MALAFOSSE
update beneficiary set user_id = 843 where id = 842;
-- Julie ZAMORA
update beneficiary set user_id = 877 where id = 876;
-- Lucas DELIGEON
update beneficiary set user_id = 1420 where id = 1633;
-- Patrick CHERRIER
update beneficiary set user_id = 51 where id = 50;


-- Move email from beneficiary to user
CREATE TABLE `newuser` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `beneficiary_id` int(11) DEFAULT NULL,
  `username` varchar(180) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(180) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
);

INSERT INTO newuser (beneficiary_id,username,email)
(SELECT b3.id,LOWER(CONCAT(SUBSTRING(b3.firstname,1,1),b3.lastname,
            CASE (b3.cnt + b3.cnt2) WHEN 1 THEN '' ELSE (b3.cnt + b3.cnt2) END)),CASE (b3.email_count) WHEN 0 THEN b3.email ELSE CONCAT('membres+b',b3.id,'@lelefan.org') END
 FROM (
    SELECT
        b.id,
        b.firstname,
        b.lastname,
        b.email,
        (
            SELECT COUNT(*)
            FROM beneficiary b2
            WHERE SUBSTRING(b2.firstname,1,1) = SUBSTRING(b.firstname,1,1)
            AND b2.lastname = b.lastname AND b2.id <= b.id
        ) as cnt,
        (
            SELECT COUNT(*)
            FROM fos_user u
            WHERE LOWER(CONCAT(SUBSTRING(b.firstname,1,1),b.lastname)) = u.username
        ) as cnt2,
        (
            SELECT COUNT(*)
            FROM fos_user u
            WHERE b.email = u.email
        ) as email_count
    FROM beneficiary b LEFT JOIN fos_user as user ON user.main_beneficiary_id = b.id WHERE user.id IS NULL AND b.email IS NOT NULL) b3);
UPDATE newuser SET username = LOWER(username);

UPDATE newuser SET username = REPLACE(username,'ž','z');
UPDATE newuser SET username = REPLACE(username,'à','a');
UPDATE newuser SET username = REPLACE(username,'á','a');
UPDATE newuser SET username = REPLACE(username,'â','a');
UPDATE newuser SET username = REPLACE(username,'ã','a');
UPDATE newuser SET username = REPLACE(username,'ä','a');
UPDATE newuser SET username = REPLACE(username,'å','a');
UPDATE newuser SET username = REPLACE(username,'æ','a');
UPDATE newuser SET username = REPLACE(username,'ç','c');
UPDATE newuser SET username = REPLACE(username,'è','e');
UPDATE newuser SET username = REPLACE(username,'é','e');
UPDATE newuser SET username = REPLACE(username,'ê','e');
UPDATE newuser SET username = REPLACE(username,'ë','e');
UPDATE newuser SET username = REPLACE(username,'ì','i');
UPDATE newuser SET username = REPLACE(username,'í','i');
UPDATE newuser SET username = REPLACE(username,'î','i');
UPDATE newuser SET username = REPLACE(username,'ï','i');
UPDATE newuser SET username = REPLACE(username,'ð','o');
UPDATE newuser SET username = REPLACE(username,'ñ','n');
UPDATE newuser SET username = REPLACE(username,'ò','o');
UPDATE newuser SET username = REPLACE(username,'ó','o');
UPDATE newuser SET username = REPLACE(username,'ô','o');
UPDATE newuser SET username = REPLACE(username,'õ','o');
UPDATE newuser SET username = REPLACE(username,'ö','o');
UPDATE newuser SET username = REPLACE(username,'ø','o');
UPDATE newuser SET username = REPLACE(username,'ù','u');
UPDATE newuser SET username = REPLACE(username,'ú','u');
UPDATE newuser SET username = REPLACE(username,'û','u');
UPDATE newuser SET username = REPLACE(username,'ý','y');
UPDATE newuser SET username = REPLACE(username,'ý','y');
UPDATE newuser SET username = REPLACE(username,'þ','b');
UPDATE newuser SET username = REPLACE(username,'ÿ','y');
UPDATE newuser SET username = REPLACE(username,'ƒ','f');
UPDATE newuser SET username = REPLACE(username,'.','');
UPDATE newuser SET username = REPLACE(username,' ','');
UPDATE newuser SET username = REPLACE(username,'-','');
UPDATE newuser SET username = REPLACE(username,'ě','e');
UPDATE newuser SET username = REPLACE(username,'ž','z');
UPDATE newuser SET username = REPLACE(username,'š','s');
UPDATE newuser SET username = REPLACE(username,'č','c');
UPDATE newuser SET username = REPLACE(username,'ř','r');
UPDATE newuser SET username = REPLACE(username,'ď','d');
UPDATE newuser SET username = REPLACE(username,'ť','t');
UPDATE newuser SET username = REPLACE(username,'ň','n');
UPDATE newuser SET username = REPLACE(username,'ů','u');
UPDATE newuser SET username = REPLACE(username,"'",'');

INSERT INTO fos_user (username, username_canonical, email, email_canonical, enabled, salt, password, last_login, confirmation_token, password_requested_at, roles)
  SELECT username, username, email, email, false, NULL, SUBSTRING(MD5(RAND()) FROM 1 FOR 12) , NULL, NULL, NULL, 'a:0:{}' FROM newuser;

UPDATE beneficiary b JOIN newuser n ON n.beneficiary_id = b.id JOIN fos_user u ON u.username = n.username SET b.user_id = u.id;

DROP TABLE newuser;

DROP INDEX UNIQ_7ABF446AE7927C74 ON beneficiary;
ALTER TABLE beneficiary DROP email;

SET FOREIGN_KEY_CHECKS = 0;
ALTER TABLE beneficiary CHANGE user_id user_id INT NOT NULL;
SET FOREIGN_KEY_CHECKS = 1;

ALTER TABLE fos_user DROP main_beneficiary_id;

-----

ALTER TABLE registration DROP FOREIGN KEY FK_62A8A7A7A76ED395;
DROP INDEX IDX_62A8A7A7A76ED395 ON registration;
ALTER TABLE registration DROP user_id;
ALTER TABLE note DROP FOREIGN KEY FK_CFBDFA14A76ED395;
DROP INDEX IDX_CFBDFA14A76ED395 ON note;
ALTER TABLE note DROP user_id;

-- Role to Formation --
ALTER TABLE beneficiaries_roles RENAME beneficiaries_formations;
ALTER TABLE role RENAME formation;
ALTER TABLE beneficiaries_formations DROP FOREIGN KEY FK_A83C2E5ED60322AC;
DROP INDEX IDX_A83C2E5ED60322AC ON beneficiaries_formations;
ALTER TABLE beneficiaries_formations DROP PRIMARY KEY;
ALTER TABLE beneficiaries_formations DROP FOREIGN KEY FK_A83C2E5EECCAAFA0;
ALTER TABLE beneficiaries_formations CHANGE role_id formation_id INT NOT NULL;
ALTER TABLE beneficiaries_formations ADD CONSTRAINT FK_4B438FE75200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE;
CREATE INDEX IDX_4B438FE75200282E ON beneficiaries_formations (formation_id);
ALTER TABLE beneficiaries_formations ADD PRIMARY KEY (beneficiary_id, formation_id);
DROP INDEX idx_a83c2e5eeccaafa0 ON beneficiaries_formations;
CREATE INDEX IDX_4B438FE7ECCAAFA0 ON beneficiaries_formations (beneficiary_id);
ALTER TABLE beneficiaries_formations ADD CONSTRAINT FK_A83C2E5EECCAAFA0 FOREIGN KEY (beneficiary_id) REFERENCES beneficiary (id) ON DELETE CASCADE;
ALTER TABLE period_position DROP FOREIGN KEY FK_2367D496D60322AC;
DROP INDEX IDX_2367D496D60322AC ON period_position;
ALTER TABLE period_position CHANGE role_id formation_id INT DEFAULT NULL;
ALTER TABLE period_position ADD CONSTRAINT FK_2367D4965200282E FOREIGN KEY (formation_id) REFERENCES formation (id);
CREATE INDEX IDX_2367D4965200282E ON period_position (formation_id);
DROP INDEX uniq_57698a6a5e237e06 ON formation;
CREATE UNIQUE INDEX UNIQ_404021BF5E237E06 ON formation (name);
ALTER TABLE shift DROP FOREIGN KEY FK_A50B3B45D60322AC;
DROP INDEX IDX_A50B3B45D60322AC ON shift;
ALTER TABLE shift CHANGE role_id formation_id INT DEFAULT NULL;
ALTER TABLE shift ADD CONSTRAINT FK_A50B3B455200282E FOREIGN KEY (formation_id) REFERENCES formation (id);
CREATE INDEX IDX_A50B3B455200282E ON shift (formation_id);
-- Migrate our old permissions fields to our new group based mechanism
ALTER TABLE formation ADD roles LONGTEXT NOT NULL COMMENT '(DC2Type:array)';
UPDATE formation SET roles = 'a:0:{}';
UPDATE formation SET roles = 'a:1:{i:0;s:16:"ROLE_USER_VIEWER";}' WHERE has_view_user_data_rights = 1;
UPDATE formation SET roles = 'a:1:{i:0;s:17:"ROLE_USER_MANAGER";}' WHERE has_edit_user_data_rights = 1;
ALTER TABLE formation CHANGE name name VARCHAR(180) NOT NULL;
ALTER TABLE formation DROP has_view_user_data_rights, DROP has_edit_user_data_rights;

-- Missing ON DELETE clauses --
ALTER TABLE task DROP FOREIGN KEY FK_527EDB25D1AA2FC1;
ALTER TABLE task ADD CONSTRAINT FK_527EDB25D1AA2FC1 FOREIGN KEY (registrar_id) REFERENCES fos_user (id) ON DELETE SET NULL;

ALTER TABLE code DROP FOREIGN KEY FK_77153098D1AA2FC1;
ALTER TABLE code ADD CONSTRAINT FK_77153098D1AA2FC1 FOREIGN KEY (registrar_id) REFERENCES fos_user (id) ON DELETE SET NULL;

ALTER TABLE access_token DROP FOREIGN KEY FK_B6A2DD68A76ED395;
ALTER TABLE access_token ADD CONSTRAINT FK_B6A2DD68A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id) ON DELETE CASCADE;

ALTER TABLE refresh_token DROP FOREIGN KEY FK_C74F2195A76ED395;
ALTER TABLE refresh_token ADD CONSTRAINT FK_C74F2195A76ED395 FOREIGN KEY (user_id) REFERENCES fos_user (id) ON DELETE CASCADE;

ALTER TABLE period_position DROP FOREIGN KEY FK_2367D4965200282E;
ALTER TABLE period_position ADD CONSTRAINT FK_2367D4965200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE;

ALTER TABLE shift DROP FOREIGN KEY FK_A50B3B455200282E;
ALTER TABLE shift ADD CONSTRAINT FK_A50B3B455200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE SET NULL;

ALTER TABLE membership DROP FOREIGN KEY FK_86FFD2856986CF73;
ALTER TABLE membership ADD CONSTRAINT FK_86FFD2856986CF73 FOREIGN KEY (last_registration_id) REFERENCES registration (id) ON DELETE CASCADE;
