CREATE TABLE tt_content (
    tx_example_relation_wall int(11) unsigned DEFAULT '0' NOT NULL,
    tx_example_relation_brick int(11) unsigned DEFAULT '0' NOT NULL
);

CREATE TABLE tx_example_wall (
    title varchar(255) DEFAULT '' NOT NULL,
    tt_content int(11) unsigned DEFAULT '0' NOT NULL,
    relation_brick int(11) unsigned DEFAULT '0' NOT NULL
);

CREATE TABLE tx_example_brick (
    title varchar(255) DEFAULT '' NOT NULL,
    tt_content int(11) unsigned DEFAULT '0' NOT NULL,
    tx_example_wall int(11) unsigned DEFAULT '0' NOT NULL
);
