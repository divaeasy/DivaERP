-- Securite des comptes (27/10/2026) -- a executer dans phpMyAdmin AVANT de deployer le code
-- qui refuse la connexion aux comptes non verifies (src/Security/UserChecker.php).
-- Sans ce script, tous les comptes (is_verified = 0) seraient bloques.

START TRANSACTION;

-- 1. Marquer les vrais comptes comme verifies
UPDATE `user` SET is_verified = 1
WHERE email IN (
    'oukajji.hassan@gmail.com',
    'test@gmail.com',
    'test1@gmail.com',
    'test2@test.com',
    'divaeasy2@outlook.com'
);

-- 2. Supprimer les comptes crees par des robots via /register
DELETE ud FROM user_dossier ud
JOIN `user` u ON u.id = ud.user_id
WHERE u.email LIKE '%@immenseignite.info' OR u.email LIKE '%@formtests.info';

DELETE FROM `user`
WHERE email LIKE '%@immenseignite.info' OR email LIKE '%@formtests.info';

COMMIT;

-- Verification : seuls les vrais comptes doivent rester, tous avec is_verified = 1
SELECT id, email, is_verified FROM `user` ORDER BY id;
