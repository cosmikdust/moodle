<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Test message API.
 *
 * @package core_message
 * @category test
 * @copyright 2016 Mark Nelson <markn@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/message/tests/messagelib_test.php');

/**
 * Test message API.
 *
 * @package core_message
 * @category test
 * @copyright 2016 Mark Nelson <markn@moodle.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_message_api_testcase extends core_message_messagelib_testcase {

    public function test_message_mark_all_read_for_user_touser() {
        $sender = $this->getDataGenerator()->create_user(array('firstname' => 'Test1', 'lastname' => 'User1'));
        $recipient = $this->getDataGenerator()->create_user(array('firstname' => 'Test2', 'lastname' => 'User2'));

        $this->send_fake_message($sender, $recipient, 'Notification', 1);
        $this->send_fake_message($sender, $recipient, 'Notification', 1);
        $this->send_fake_message($sender, $recipient, 'Notification', 1);
        $this->send_fake_message($sender, $recipient);
        $this->send_fake_message($sender, $recipient);
        $this->send_fake_message($sender, $recipient);

        \core_message\api::mark_all_read_for_user($recipient->id);
        $this->assertEquals(message_count_unread_messages($recipient), 0);
    }

    public function test_message_mark_all_read_for_user_touser_with_fromuser() {
        $sender1 = $this->getDataGenerator()->create_user(array('firstname' => 'Test1', 'lastname' => 'User1'));
        $sender2 = $this->getDataGenerator()->create_user(array('firstname' => 'Test3', 'lastname' => 'User3'));
        $recipient = $this->getDataGenerator()->create_user(array('firstname' => 'Test2', 'lastname' => 'User2'));

        $this->send_fake_message($sender1, $recipient, 'Notification', 1);
        $this->send_fake_message($sender1, $recipient, 'Notification', 1);
        $this->send_fake_message($sender1, $recipient, 'Notification', 1);
        $this->send_fake_message($sender1, $recipient);
        $this->send_fake_message($sender1, $recipient);
        $this->send_fake_message($sender1, $recipient);
        $this->send_fake_message($sender2, $recipient, 'Notification', 1);
        $this->send_fake_message($sender2, $recipient, 'Notification', 1);
        $this->send_fake_message($sender2, $recipient, 'Notification', 1);
        $this->send_fake_message($sender2, $recipient);
        $this->send_fake_message($sender2, $recipient);
        $this->send_fake_message($sender2, $recipient);

        \core_message\api::mark_all_read_for_user($recipient->id, $sender1->id);
        $this->assertEquals(message_count_unread_messages($recipient), 6);
    }

    public function test_message_mark_all_read_for_user_touser_with_type() {
        $sender = $this->getDataGenerator()->create_user(array('firstname' => 'Test1', 'lastname' => 'User1'));
        $recipient = $this->getDataGenerator()->create_user(array('firstname' => 'Test2', 'lastname' => 'User2'));

        $this->send_fake_message($sender, $recipient, 'Notification', 1);
        $this->send_fake_message($sender, $recipient, 'Notification', 1);
        $this->send_fake_message($sender, $recipient, 'Notification', 1);
        $this->send_fake_message($sender, $recipient);
        $this->send_fake_message($sender, $recipient);
        $this->send_fake_message($sender, $recipient);

        \core_message\api::mark_all_read_for_user($recipient->id, 0, MESSAGE_TYPE_NOTIFICATION);
        $this->assertEquals(message_count_unread_messages($recipient), 3);

        \core_message\api::mark_all_read_for_user($recipient->id, 0, MESSAGE_TYPE_MESSAGE);
        $this->assertEquals(message_count_unread_messages($recipient), 0);
    }

    /**
     * Test count_blocked_users.
     *
     */
    public function test_message_count_blocked_users() {
        // Set this user as the admin.
        $this->setAdminUser();

        // Create users to add to the admin's contact list.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $this->assertEquals(0, \core_message\api::count_blocked_users());

        // Add 1 blocked and 1 normal contact to admin's contact list.
        message_add_contact($user1->id);
        message_add_contact($user2->id, 1);

        $this->assertEquals(0, \core_message\api::count_blocked_users($user2));
        $this->assertEquals(1, \core_message\api::count_blocked_users());
    }

    /**
     * Tests searching users in a course.
     */
    public function test_search_users_in_course() {
        global $DB;

        $this->resetAfterTest(true);

        // Create some users.
        $user1 = new stdClass();
        $user1->firstname = 'User';
        $user1->lastname = 'One';
        $user1 = self::getDataGenerator()->create_user($user1);

        // The person doing the search.
        $this->setUser($user1);

        // Second user is going to have their last access to now, so they are online.
        $user2 = new stdClass();
        $user2->firstname = 'User';
        $user2->lastname = 'Two';
        $user2->lastaccess = time();
        $user2 = self::getDataGenerator()->create_user($user2);

        // Block the second user.
        message_block_contact($user2->id, $user1->id);

        $user3 = new stdClass();
        $user3->firstname = 'User';
        $user3->lastname = 'Three';
        $user3 = self::getDataGenerator()->create_user($user3);

        // Create a course.
        $course1 = new stdClass();
        $course1->fullname = 'Course';
        $course1->shortname = 'One';
        $course1 = $this->getDataGenerator()->create_course();

        // Enrol the searcher and one user in the course.
        $coursecontext = context_course::instance($course1->id);
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id);
        role_assign($studentrole->id, $user1->id, $coursecontext->id);

        // Perform a search.
        $results = \core_message\api::search_users_in_course($user1->id, $course1->id, 'User');

        $this->assertEquals(1, count($results));

        $user = $results[0];
        $this->assertEquals($user2->id, $user->userid);
        $this->assertEquals(fullname($user2), $user->fullname);
        $this->assertFalse($user->ismessaging);
        $this->assertNull($user->lastmessage);
        $this->assertNull($user->messageid);
        $this->assertTrue($user->isonline);
        $this->assertFalse($user->isread);
        $this->assertTrue($user->isblocked);
        $this->assertNull($user->unreadcount);
    }

    /**
     * Tests searching users.
     */
    public function test_search_users() {
        $this->resetAfterTest(true);

        // Create some users.
        $user1 = new stdClass();
        $user1->firstname = 'User';
        $user1->lastname = 'One';
        $user1 = self::getDataGenerator()->create_user($user1);

        // Set as the user performing the search.
        $this->setUser($user1);

        $user2 = new stdClass();
        $user2->firstname = 'User search';
        $user2->lastname = 'Two';
        $user2 = self::getDataGenerator()->create_user($user2);

        $user3 = new stdClass();
        $user3->firstname = 'User search';
        $user3->lastname = 'Three';
        $user3 = self::getDataGenerator()->create_user($user3);

        $user4 = new stdClass();
        $user4->firstname = 'User';
        $user4->lastname = 'Four';
        $user4 = self::getDataGenerator()->create_user($user4);

        $user5 = new stdClass();
        $user5->firstname = 'User search';
        $user5->lastname = 'Five';
        $user5 = self::getDataGenerator()->create_user($user5);

        $user6 = new stdClass();
        $user6->firstname = 'User';
        $user6->lastname = 'Six';
        $user6 = self::getDataGenerator()->create_user($user6);

        // Create some courses.
        $course1 = new stdClass();
        $course1->fullname = 'Course search';
        $course1->shortname = 'One';
        $course1 = $this->getDataGenerator()->create_course($course1);

        $course2 = new stdClass();
        $course2->fullname = 'Course';
        $course2->shortname = 'Two';
        $course2 = $this->getDataGenerator()->create_course($course2);

        $course3 = new stdClass();
        $course3->fullname = 'Course';
        $course3->shortname = 'Three search';
        $course3 = $this->getDataGenerator()->create_course($course3);

        // Add some users as contacts.
        message_add_contact($user2->id, 0, $user1->id);
        message_add_contact($user3->id, 0, $user1->id);
        message_add_contact($user4->id, 0, $user1->id);

        // Perform a search.
        list($contacts, $courses, $noncontacts) = \core_message\api::search_users($user1->id, 'search');

        // Check that we retrieved the correct contacts.
        $this->assertEquals(2, count($contacts));
        $this->assertEquals($user3->id, $contacts[0]->userid);
        $this->assertEquals($user2->id, $contacts[1]->userid);

        // Check that we retrieved the correct courses.
        $this->assertEquals(2, count($courses));
        $this->assertEquals($course3->id, $courses[0]->id);
        $this->assertEquals($course1->id, $courses[1]->id);

        // Check that we retrieved the correct non-contacts.
        $this->assertEquals(1, count($noncontacts));
        $this->assertEquals($user5->id, $noncontacts[0]->userid);
    }

    /**
     * Tests searching messages.
     */
    public function test_search_messages() {
        $this->resetAfterTest(true);

        // Create some users.
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        // The person doing the search.
        $this->setUser($user1);

        // Send some messages back and forth.
        $this->send_fake_message($user1, $user2, 'Yo!');
        $this->send_fake_message($user2, $user1, 'Sup mang?');
        $this->send_fake_message($user1, $user2, 'Writing PHPUnit tests!');
        $this->send_fake_message($user2, $user1, 'Word.');

        // Perform a search.
        $messages = \core_message\api::search_messages($user1->id, 'o');

        // Confirm the data is correct.
        $this->assertEquals(2, count($messages));

        $message1 = $messages[0];
        $message2 = $messages[1];

        $this->assertEquals($user2->id, $message1->userid);
        $this->assertEquals($user2->id, $message1->useridfrom);
        $this->assertEquals(fullname($user2), $message1->fullname);
        $this->assertTrue($message1->ismessaging);
        $this->assertEquals('Word.', $message1->lastmessage);
        $this->assertNotEmpty($message1->messageid);
        $this->assertFalse($message1->isonline);
        $this->assertFalse($message1->isread);
        $this->assertFalse($message1->isblocked);
        $this->assertNull($message1->unreadcount);

        $this->assertEquals($user2->id, $message2->userid);
        $this->assertEquals($user1->id, $message2->useridfrom);
        $this->assertEquals(fullname($user2), $message2->fullname);
        $this->assertTrue($message2->ismessaging);
        $this->assertEquals('Yo!', $message2->lastmessage);
        $this->assertNotEmpty($message2->messageid);
        $this->assertFalse($message2->isonline);
        $this->assertTrue($message2->isread);
        $this->assertFalse($message2->isblocked);
        $this->assertNull($message2->unreadcount);
    }

    /**
     * Tests retrieving conversations.
     */
    public function test_get_conversations() {
        $this->resetAfterTest(true);

        // Create some users.
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();

        // The person doing the search.
        $this->setUser($user1);

        // Send some messages back and forth, have some different conversations with different users.
        $this->send_fake_message($user1, $user2, 'Yo!');
        $this->send_fake_message($user2, $user1, 'Sup mang?');
        $this->send_fake_message($user1, $user2, 'Writing PHPUnit tests!');
        $this->send_fake_message($user2, $user1, 'Word.');

        $this->send_fake_message($user1, $user3, 'Booyah');
        $this->send_fake_message($user3, $user1, 'Whaaat?');
        $this->send_fake_message($user1, $user3, 'Nothing.');
        $this->send_fake_message($user3, $user1, 'Cool.');

        $this->send_fake_message($user1, $user4, 'Hey mate, you see the new messaging UI in Moodle?');
        $this->send_fake_message($user4, $user1, 'Yah brah, it\'s pretty rad.');
        $this->send_fake_message($user1, $user4, 'Dope.');

        // Retrieve the conversations.
        $conversations = \core_message\api::get_conversations($user1->id);

        // Confirm the data is correct.
        $this->assertEquals(3, count($conversations));

        $message1 = array_shift($conversations);
        $message2 = array_shift($conversations);
        $message3 = array_shift($conversations);

        $this->assertEquals($user4->id, $message1->userid);
        $this->assertEquals($user1->id, $message1->useridfrom);
        $this->assertTrue($message1->ismessaging);
        $this->assertEquals('Dope.', $message1->lastmessage);
        $this->assertNull($message1->messageid);
        $this->assertFalse($message1->isonline);
        $this->assertTrue($message1->isread);
        $this->assertFalse($message1->isblocked);
        $this->assertEquals(0, $message1->unreadcount);

        $this->assertEquals($user3->id, $message2->userid);
        $this->assertEquals($user3->id, $message2->useridfrom);
        $this->assertTrue($message2->ismessaging);
        $this->assertEquals('Cool.', $message2->lastmessage);
        $this->assertNull($message2->messageid);
        $this->assertFalse($message2->isonline);
        $this->assertFalse($message2->isread);
        $this->assertFalse($message2->isblocked);
        $this->assertEquals(2, $message2->unreadcount);

        $this->assertEquals($user2->id, $message3->userid);
        $this->assertEquals($user2->id, $message3->useridfrom);
        $this->assertTrue($message3->ismessaging);
        $this->assertEquals('Word.', $message3->lastmessage);
        $this->assertNull($message3->messageid);
        $this->assertFalse($message3->isonline);
        $this->assertFalse($message3->isread);
        $this->assertFalse($message3->isblocked);
        $this->assertEquals(2, $message3->unreadcount);
    }

    /**
     * Tests retrieving contacts.
     */
    public function test_get_contacts() {
        $this->resetAfterTest(true);

        // Create some users.
        $user1 = self::getDataGenerator()->create_user();

        // Set as the user.
        $this->setUser($user1);

        $user2 = new stdClass();
        $user2->firstname = 'User';
        $user2->lastname = 'A';
        $user2 = self::getDataGenerator()->create_user($user2);

        $user3 = new stdClass();
        $user3->firstname = 'User';
        $user3->lastname = 'B';
        $user3 = self::getDataGenerator()->create_user($user3);

        $user4 = new stdClass();
        $user4->firstname = 'User';
        $user4->lastname = 'C';
        $user4 = self::getDataGenerator()->create_user($user4);

        $user5 = new stdClass();
        $user5->firstname = 'User';
        $user5->lastname = 'D';
        $user5 = self::getDataGenerator()->create_user($user5);

        // Add some users as contacts.
        message_add_contact($user2->id, 0, $user1->id);
        message_add_contact($user3->id, 0, $user1->id);
        message_add_contact($user4->id, 0, $user1->id);

        // Retrieve the contacts.
        $contacts = \core_message\api::get_contacts($user1->id);

        // Confirm the data is correct.
        $this->assertEquals(3, count($contacts));

        $contact1 = $contacts[0];
        $contact2 = $contacts[1];
        $contact3 = $contacts[2];

        $this->assertEquals($user2->id, $contact1->userid);
        $this->assertEmpty($contact1->useridfrom);
        $this->assertFalse($contact1->ismessaging);
        $this->assertNull($contact1->lastmessage);
        $this->assertNull($contact1->messageid);
        $this->assertFalse($contact1->isonline);
        $this->assertFalse($contact1->isread);
        $this->assertFalse($contact1->isblocked);
        $this->assertNull($contact1->unreadcount);

        $this->assertEquals($user3->id, $contact2->userid);
        $this->assertEmpty($contact2->useridfrom);
        $this->assertFalse($contact2->ismessaging);
        $this->assertNull($contact2->lastmessage);
        $this->assertNull($contact2->messageid);
        $this->assertFalse($contact2->isonline);
        $this->assertFalse($contact2->isread);
        $this->assertFalse($contact2->isblocked);
        $this->assertNull($contact2->unreadcount);

        $this->assertEquals($user4->id, $contact3->userid);
        $this->assertEmpty($contact3->useridfrom);
        $this->assertFalse($contact3->ismessaging);
        $this->assertNull($contact3->lastmessage);
        $this->assertNull($contact3->messageid);
        $this->assertFalse($contact3->isonline);
        $this->assertFalse($contact3->isread);
        $this->assertFalse($contact3->isblocked);
        $this->assertNull($contact3->unreadcount);
    }

    /**
     * Tests retrieving messages.
     */
    public function test_get_messages() {
        $this->resetAfterTest(true);

        // Create some users.
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        // The person doing the search.
        $this->setUser($user1);

        // Send some messages back and forth.
        $this->send_fake_message($user1, $user2, 'Yo!');
        $this->send_fake_message($user2, $user1, 'Sup mang?');
        $this->send_fake_message($user1, $user2, 'Writing PHPUnit tests!');
        $this->send_fake_message($user2, $user1, 'Word.');

        // Retrieve the messages.
        $messages = \core_message\api::get_messages($user1->id, $user2->id);

        // Confirm the message data is correct.
        $this->assertEquals(4, count($messages));

        $message1 = $messages[0];
        $message2 = $messages[1];
        $message3 = $messages[2];
        $message4 = $messages[3];

        $this->assertEquals($user1->id, $message1->useridfrom);
        $this->assertEquals($user2->id, $message1->useridto);
        $this->assertTrue($message1->displayblocktime);
        $this->assertContains('Yo!', $message1->text);

        $this->assertEquals($user2->id, $message2->useridfrom);
        $this->assertEquals($user1->id, $message2->useridto);
        $this->assertFalse($message2->displayblocktime);
        $this->assertContains('Sup mang?', $message2->text);

        $this->assertEquals($user1->id, $message3->useridfrom);
        $this->assertEquals($user2->id, $message3->useridto);
        $this->assertFalse($message3->displayblocktime);
        $this->assertContains('Writing PHPUnit tests!', $message3->text);

        $this->assertEquals($user2->id, $message4->useridfrom);
        $this->assertEquals($user1->id, $message4->useridto);
        $this->assertFalse($message4->displayblocktime);
        $this->assertContains('Word.', $message4->text);
    }

    /**
     * Tests retrieving most recent message.
     */
    public function test_get_most_recent_message() {
        $this->resetAfterTest(true);

        // Create some users.
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        // The person doing the search.
        $this->setUser($user1);

        // Send some messages back and forth.
        $this->send_fake_message($user1, $user2, 'Yo!');
        $this->send_fake_message($user2, $user1, 'Sup mang?');
        $this->send_fake_message($user1, $user2, 'Writing PHPUnit tests!');
        $this->send_fake_message($user2, $user1, 'Word.');

        // Retrieve the most recent messages.
        $message = \core_message\api::get_most_recent_message($user1->id, $user2->id);

        // Check the results are correct.
        $this->assertEquals($user2->id, $message->useridfrom);
        $this->assertEquals($user1->id, $message->useridto);
        $this->assertContains('Word.', $message->text);
    }

    /**
     * Tests retrieving a user's profile.
     */
    public function test_get_profile() {
        $this->resetAfterTest(true);

        // Create some users.
        $user1 = self::getDataGenerator()->create_user();

        $user2 = new stdClass();
        $user2->country = 'AU';
        $user2->city = "Perth";
        $user2 = self::getDataGenerator()->create_user();

        // The person doing the search.
        $this->setUser($user1);

        // Get the profile.
        $profile = \core_message\api::get_profile($user1->id, $user2->id);

        $this->assertEquals($user2->id, $profile->userid);
        $this->assertEmpty($profile->email);
        $this->assertEmpty($profile->country);
        $this->assertEmpty($profile->city);
        $this->assertEquals(fullname($user2), $profile->fullname);
        $this->assertFalse($profile->isonline);
        $this->assertFalse($profile->isblocked);
        $this->assertFalse($profile->iscontact);
    }

    /**
     * Tests checking if a user can delete a conversation.
     */
    public function test_can_delete_conversation() {
        // Set as the admin.
        $this->setAdminUser();

        // Create some users.
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        // The admin can do anything.
        $this->assertTrue(\core_message\api::can_delete_conversation($user1->id));

        // Set as the user 1.
        $this->setUser($user1);

        // They can delete their own messages.
        $this->assertTrue(\core_message\api::can_delete_conversation($user1->id));

        // They can't delete someone elses.
        $this->assertFalse(\core_message\api::can_delete_conversation($user2->id));
    }

    /**
     * Tests deleting a conversation.
     */
    public function test_delete_conversation() {
        // Create some users.
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        // The person doing the search.
        $this->setUser($user1);

        // Send some messages back and forth.
        $this->send_fake_message($user1, $user2, 'Yo!');
        $this->send_fake_message($user2, $user1, 'Sup mang?');
        $this->send_fake_message($user1, $user2, 'Writing PHPUnit tests!');
        $this->send_fake_message($user2, $user1, 'Word.');

    }

    /**
     * Tests counting unread conversations.
     */
    public function test_count_unread_conversations() {

    }

    /**
     * Tests deleting a conversation.
     */
    public function test_get_all_message_preferences() {
        $this->resetAfterTest(true);

        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);

        // Set a couple of preferences to test.
        set_user_preference('message_provider_mod_assign_assign_notification_loggedin', 'popup', $user);
        set_user_preference('message_provider_mod_assign_assign_notification_loggedoff', 'email', $user);

        $prefs = core_message_external::get_user_notification_preferences();
        $prefs = external_api::clean_returnvalue(core_message_external::get_user_notification_preferences_returns(), $prefs);
        // Check processors.
        $this->assertCount(2, $prefs['preferences']['processors']);
        $this->assertEquals($user->id, $prefs['preferences']['userid']);

        // Check components.
        $this->assertCount(8, $prefs['preferences']['components']);

        // Check some preferences that we previously set.
        $found = 0;
        foreach ($prefs['preferences']['components'] as $component) {
            foreach ($component['notifications'] as $prefdata) {
                if ($prefdata['preferencekey'] != 'message_provider_mod_assign_assign_notification') {
                    continue;
                }
                foreach ($prefdata['processors'] as $processor) {
                    if ($processor['name'] == 'popup') {
                        $this->assertTrue($processor['loggedin']['checked']);
                        $found++;
                    } else if ($processor['name'] == 'email') {
                        $this->assertTrue($processor['loggedoff']['checked']);
                        $found++;
                    }
                }
            }
        }
        $this->assertEquals(2, $found);
    }

    /**
     * Tests if the user can post a message.
     */
    public function test_can_post_message() {
        // Set as the admin.
        $this->setAdminUser();

        // Create some users.
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        // The admin can do anything.
        $this->assertTrue(\core_message\api::can_delete_conversation($user1->id));

        // Set as the user 1.
        $this->setUser($user1);

        // They can delete their own messages.
        $this->assertTrue(\core_message\api::can_delete_conversation($user1->id));

        // They can't delete someone elses.
        $this->assertFalse(\core_message\api::can_delete_conversation($user2->id));
    }

    /**
     * Tests if blocking messages from non-contacts is enabled that
     * non-contacts trying to send a message return false.
     */
    public function test_is_user_non_contact_blocked() {

    }

    /**
     * Tests that we return true when a user is blocked, or false
     * if they are not blocked, or have the correct capability to
     * view any message.
     */
    public function test_is_user_blocked() {

    }
}
