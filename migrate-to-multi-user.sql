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

-- fos_user / Replace main_beneficiary_id by beneficiary_id
ALTER TABLE fos_user DROP FOREIGN KEY FK_957A64796986CF73;
ALTER TABLE fos_user ADD beneficiary_id INT DEFAULT NULL;
ALTER TABLE fos_user ADD CONSTRAINT FK_957A6479ECCAAFA0 FOREIGN KEY (beneficiary_id) REFERENCES beneficiary (id) ON DELETE SET NULL;
ALTER TABLE fos_user ADD CONSTRAINT FK_957A64796986CF73 FOREIGN KEY (last_registration_id) REFERENCES registration (id);
CREATE UNIQUE INDEX UNIQ_957A6479ECCAAFA0 ON fos_user (beneficiary_id);
UPDATE fos_user SET beneficiary_id = main_beneficiary_id;

-- Migrate time_logs
ALTER TABLE time_log ADD membership_id INT NOT NULL;
UPDATE time_log SET membership_id = user_id;
ALTER TABLE time_log ADD CONSTRAINT FK_55BE03AF1FB354CD FOREIGN KEY (membership_id) REFERENCES membership (id) ON DELETE CASCADE;
CREATE INDEX IDX_55BE03AF1FB354CD ON time_log (membership_id);

ALTER TABLE time_log DROP FOREIGN KEY FK_55BE03AFA76ED395;
DROP INDEX IDX_55BE03AFA76ED395 ON time_log;
ALTER TABLE time_log DROP user_id;

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

-- beneficiary/user relationship / OneToMany to ManyToOne
UPDATE beneficiary b JOIN fos_user u ON u.id = b.user_id SET b.user_id = NULL WHERE b.id != u.main_beneficiary_id;
ALTER TABLE beneficiary DROP INDEX IDX_7ABF446AA76ED395, ADD UNIQUE INDEX UNIQ_7ABF446AA76ED395 (user_id);

-- Migrate address
ALTER TABLE beneficiary ADD address_id INT DEFAULT NULL;
UPDATE beneficiary b JOIN fos_user u ON u.id = b.user_id SET b.address_id = u.address_id;
ALTER TABLE beneficiary ADD CONSTRAINT FK_7ABF446AF5B7AF75 FOREIGN KEY (address_id) REFERENCES address (id);
CREATE UNIQUE INDEX UNIQ_7ABF446AF5B7AF75 ON beneficiary (address_id);

-- Migrate proxies
ALTER TABLE proxy DROP FOREIGN KEY FK_7372C9BE5DE37FD9;
ALTER TABLE proxy ADD CONSTRAINT FK_7372C9BE5DE37FD9 FOREIGN KEY (giver) REFERENCES membership (id) ON DELETE CASCADE;

-- Drop old columns
ALTER TABLE fos_user DROP FOREIGN KEY FK_957A647962C6E4EA;
ALTER TABLE fos_user DROP FOREIGN KEY FK_957A64796986CF73;
ALTER TABLE fos_user DROP FOREIGN KEY FK_957A6479F5B7AF75;
DROP INDEX UNIQ_957A647962C6E4EA ON fos_user;
DROP INDEX UNIQ_957A6479F5B7AF75 ON fos_user;
DROP INDEX UNIQ_957A64796986CF73 ON fos_user;
ALTER TABLE fos_user DROP main_beneficiary_id, DROP address_id, DROP last_registration_id, DROP withdrawn, DROP frozen, DROP first_shift_date, DROP frozen_change;
ALTER TABLE registration DROP FOREIGN KEY FK_62A8A7A7A76ED395;
DROP INDEX IDX_62A8A7A7A76ED395 ON registration;
ALTER TABLE registration DROP user_id;
ALTER TABLE note DROP FOREIGN KEY FK_CFBDFA14A76ED395;
DROP INDEX IDX_CFBDFA14A76ED395 ON note;
ALTER TABLE note DROP user_id;


-- TODO remove (code dev)
ALTER TABLE code ADD value VARCHAR(255) DEFAULT NULL;
ALTER TABLE code DROP title;
ALTER TABLE code CHANGE closed closed TINYINT(1) DEFAULT '0';
UPDATE code set closed = 0 where closed is null;
ALTER TABLE code CHANGE closed closed TINYINT(1) DEFAULT '0' NOT NULL;
