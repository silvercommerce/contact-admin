<?php

namespace SilverCommerce\ContactAdmin\Tests;

use DateTime;
use ReflectionClass;
use SilverStripe\Security\Member;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;
use SilverCommerce\ContactAdmin\Model\Contact;
use SilverCommerce\ContactAdmin\Helpers\ContactHelper;

class ContactHelperTest extends SapphireTest
{
    protected static $fixture_file = 'ContactHelperTest.yml';

    protected function setUp()
    {
        // Disable auto sync so that data is created as per fixtures
        $curr = ContactHelper::config()->get('auto_sync');
        ContactHelper::config()->set('auto_sync', false);

        parent::setUp();

        ContactHelper::config()->set('auto_sync', $curr);
    }

    public function testFindOrMakeMember()
    {
        $contact_one = $this->objFromFixture(Contact::class, 'contact_one');
        $member_one = $this->objFromFixture(Member::class, 'member_one');

        $contact_two = $this->objFromFixture(Contact::class, 'contact_two');
        $member_two = $this->objFromFixture(Member::class, 'member_two');

        $contact_three = $this->objFromFixture(Contact::class, 'contact_three');

        $helper = ContactHelper::create();
        $helper->setContact($contact_one);
        $member = $helper->findOrMakeMember();
        $this->assertEquals($member_one->ID, $member->ID);

        $helper->setContact($contact_two);
        $member = $helper->findOrMakeMember();
        $this->assertEquals($member_two->ID, $member->ID);

        $helper->setContact($contact_three);
        $member = $helper->findOrMakeMember();
        $this->assertEquals($member->ID, $contact_three->MemberID);
    }

    public function testFindOrMakeContact()
    {
        $member_one = $this->objFromFixture(Member::class, 'member_one');
        $contact_one = $this->objFromFixture(Contact::class, 'contact_one');

        $member_two = $this->objFromFixture(Member::class, 'member_two');
        $contact_two = $this->objFromFixture(Contact::class, 'contact_two');

        $helper = ContactHelper::create();
        $helper->setMember($member_one);

        $contact = $helper->findOrMakeContact();
        $this->assertEquals($contact_one->ID, $contact->ID);

        $helper->setMember($member_two);
        $contact = $helper->findOrMakeContact();
        $this->assertEquals($contact_two->ID, $contact->ID);

        $member_four = $this->objFromFixture(Member::class, 'member_four');
        $id = $member_four->ID;

        $helper = ContactHelper::create();
        $helper->setMember($member_four);
        $contact = $helper->findOrMakeContact();

        // Re-get member from DB
        $member_four = Member::get()->byID($id);
        $this->assertEquals($contact->ID, $member_four->Contact()->ID);
        $this->assertEquals($contact->Email, $member_four->Email);
        $this->assertEquals($contact->FirstName, $member_four->FirstName);
        $this->assertEquals($contact->Surname, $member_four->Surname);
    }

    public function testSyncContactAndMember()
    {
        // Test syncing from contact to member
        $contact_one = $this->objFromFixture(Contact::class, 'contact_one');
        $member_one = $this->objFromFixture(Member::class, 'member_one');

        // Change name and last edited. Not ideal, but last edited is often the same as created
        // due to scrip execution time 
        $contact_one->FirstName = "Member Changed";

        $helper = ContactHelper::create()
            ->setContact($contact_one)
            ->setMember($member_one)
            ->syncContactAndMember(false);
        
        $member = $helper->getMember();
        
        $this->assertEquals("Member Changed", $member->FirstName);

        // Test syncing from member to contact
        $contact_two = $this->objFromFixture(Contact::class, 'contact_two');
        $member_two = $this->objFromFixture(Member::class, 'member_two');
        $member_two->FirstName = "Member Changed";

        $helper = ContactHelper::create()
            ->setContact($contact_two)
            ->setMember($member_two)
            ->syncContactAndMember(false);
        
        $contact = $helper->getContact();

        $this->assertEquals("Member Changed", $contact->FirstName);
    }

    public function testPushChangedFields()
    {
        $contact_one = $this->objFromFixture(Contact::class, 'contact_one');
        $member_one = $this->objFromFixture(Member::class, 'member_one');
        $helper = ContactHelper::create();

        $contact_one->FirstName = "Member Changed";
        $helper->pushChangedFields($contact_one, $member_one);

        $this->assertEquals("Member Changed", $member_one->FirstName);

        $contact_two = $this->objFromFixture(Contact::class, 'contact_two');
        $member_two = $this->objFromFixture(Member::class, 'member_two');
        $helper = ContactHelper::create();

        $contact_two->Surname = "Two Changed";
        $member_two->FirstName = "Member Changed";
        $helper->pushChangedFields($member_two, $contact_two);

        $this->assertEquals("Member Changed", $contact_two->FirstName);
        $this->assertEquals("Two Changed", $contact_two->Surname);
    }

    public function testPushFields()
    {
        $contact_one = $this->objFromFixture(Contact::class, 'contact_one');
        $member_one = $this->objFromFixture(Member::class, 'member_one');
        $helper = ContactHelper::create();

        $contact_one->FirstName = "Member Changed";
        $helper->pushFields($contact_one, $member_one);

        $this->assertEquals("Member Changed", $member_one->FirstName);

        $contact_two = $this->objFromFixture(Contact::class, 'contact_two');
        $member_two = $this->objFromFixture(Member::class, 'member_two');
        $helper = ContactHelper::create();

        $contact_two->Surname = "Two Changed";
        $member_two->FirstName = "Member Changed";
        $helper->pushFields($member_two, $contact_two);

        $this->assertEquals("Member Changed", $contact_two->FirstName);
        $this->assertEquals("Two", $contact_two->Surname);
    }
}
