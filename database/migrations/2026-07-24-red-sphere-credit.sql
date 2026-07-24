-- RED-CMS 5.0: opt-out Red Sphere website credit.
-- Existing and future language inventories default to displaying the credit.

INSERT INTO `RED_Advanced` (`Item`, `Content`, `Language`)
SELECT 'Website_Red_Sphere_Credit', 'Y', languages.`Language`
FROM (
  SELECT DISTINCT `Language`
  FROM `RED_Advanced`
  WHERE `Language` REGEXP '^[a-z]{2}$'
) AS languages
WHERE NOT EXISTS (
  SELECT 1
  FROM `RED_Advanced` AS credit
  WHERE credit.`Item`='Website_Red_Sphere_Credit'
    AND credit.`Language`=languages.`Language`
);
