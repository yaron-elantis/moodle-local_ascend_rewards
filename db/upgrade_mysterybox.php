<?php
/**
 * Mystery Box table upgrade script
 * Run this to add the mystery box tracking table
 */

defined('MOODLE_INTERNAL') || die();

function add_mystery_box_table() {
    global $DB;
    $dbman = $DB->get_manager();
    
    // Define table local_ascend_mysterybox
    $table = new xmldb_table('local_ascend_mysterybox');
    
    <?php
    // This file is part of Moodle - https://moodle.org/
    //
    // Moodle is free software: you can redistribute it and/or modify
    // it under the terms of the GNU General Public License as published by
    // the Free Software Foundation, either version 3 of the License, or
    // (at your option) any later version.
    //
    // Moodle is distributed in the hope that it will be useful,
    // but WITHOUT ANY WARRANTY; without even the implied warranty of
    // MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    // GNU General Public License for more details.
    //
    // You should have received a copy of the GNU General Public License
    // along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

    defined('MOODLE_INTERNAL') || die();
    // Add fields
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('reward_type', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
    $table->add_field('reward_id', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
    $table->add_field('reward_rarity', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
    $table->add_field('pity_triggered', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    
    // Add keys
    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
    
    // Add indexes
    $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);
    $table->add_index('timecreated_idx', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
    $table->add_index('reward_type_idx', XMLDB_INDEX_NOTUNIQUE, ['reward_type']);
    
    // Create table if it doesn't exist
    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
        echo "Mystery box table created successfully.\n";
    } else {
        echo "Mystery box table already exists.\n";
    }
}
