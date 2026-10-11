ALTER TABLE tiab_functional_roles ADD COLUMN member_id VARCHAR(10) NULL;
-- Link only unambiguous old names. Keep unmatched legacy names for correction.
UPDATE tiab_functional_roles r JOIN (
 SELECT TRIM(full_name) AS name, MIN(member_id) AS member_id FROM tin_members
 WHERE member_id IS NOT NULL AND member_id <> '' AND TRIM(full_name) <> ''
 GROUP BY TRIM(full_name) HAVING COUNT(DISTINCT member_id) = 1
) m ON TRIM(r.person_name) = m.name
SET r.member_id = m.member_id, r.person_name = NULL WHERE r.member_id IS NULL;
