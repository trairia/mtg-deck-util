CREATE TABLE carddata (
multiverseid mediumint(8) UNSIGNED NOT NULL,
set_code char(4),
cardname varchar(128) NOT NULL,
typecode tinyint(3) UNSIGNED NOT NULL,
manacost varchar(45),
colors char(5),
cmc mediumint(8),
INDEX idx (multiverseid)
) CHARACTER SET utf8;

CREATE TABLE cardname (
multiverseid mediumint(8) UNSIGNED NOT NULL,
ru varchar(128),
fr varchar(128),
pt varchar(128),
zh_tw varchar(128),
zh_cn varchar(128),
de varchar(128),
ja varchar(128),
it varchar(128),
es varchar(128),
ko varchar(128),
INDEX idx (multiverseid)
) CHARACTER SET utf8;
