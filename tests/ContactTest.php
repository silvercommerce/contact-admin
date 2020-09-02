<?php

namespace SilverCommerce\ContactAdmin\Tests;

use SilverCommerce\ContactAdmin\Model\Contact;
use SilverStripe\Dev\SapphireTest;

class ContactTest extends SapphireTest
{
    protected static $fixture_file = 'ContactTest.yml';

    public function testGetTitle()
    {
        $one = $this->objFromFixture(Contact::class, 'contact_one');
        $two = $this->objFromFixture(Contact::class, 'contact_two');
        $three = $this->objFromFixture(Contact::class, 'contact_three');
        $four = $this->objFromFixture(Contact::class, 'contact_four');
        $five = $this->objFromFixture(Contact::class, 'contact_five');

        $this->assertEquals('Member One', $one->getTitle());
        $this->assertEquals('Member Two (member.two@notavaliddomain.com)', $two->getTitle());
        $this->assertEquals('Contact Three', $three->getTitle());
        $this->assertEquals('Contact Four', $four->getTitle());
        $this->assertEquals('Contact (contact@notavaliddomain.com)', $five->getTitle());
    }

    public function testGetFullName()
    {
        $one = $this->objFromFixture(Contact::class, 'contact_one');
        $two = $this->objFromFixture(Contact::class, 'contact_two');
        $three = $this->objFromFixture(Contact::class, 'contact_three');
        $four = $this->objFromFixture(Contact::class, 'contact_four');
        $five = $this->objFromFixture(Contact::class, 'contact_five');

        $this->assertEquals('Member One', $one->getFullName());
        $this->assertEquals('Member Two', $two->getFullName());
        $this->assertEquals('Contact Three', $three->getFullName());
        $this->assertEquals('Contact Four', $four->getFullName());
        $this->assertEquals('Contact', $five->getFullName());
    }

    public function testGetByMostLocations()
    {
        $contact = Contact::getByMostLocations();

        $this->assertEquals('Member', $contact->FirstName);
        $this->assertEquals('Two', $contact->Surname);
        $this->assertEquals(3, $contact->Locations()->count());
    }

    public function testGetDefaultLocation()
    {
        $one = $this->objFromFixture(Contact::class, 'contact_one');
        $two = $this->objFromFixture(Contact::class, 'contact_two');
        $three = $this->objFromFixture(Contact::class, 'contact_three');
        $four = $this->objFromFixture(Contact::class, 'contact_four');
        $five = $this->objFromFixture(Contact::class, 'contact_five');

        $this->assertEquals('2 Savoy Pl', $one->DefaultLocation()->Address1);
        $this->assertEquals('21 Searle Ct Ave', $two->DefaultLocation()->Address1);
        $this->assertEquals('21 Searle Ct Ave', $three->DefaultLocation()->Address1);
        $this->assertNull($four->DefaultLocation()->Address1);
        $this->assertNull($five->DefaultLocation()->Address1);
    }

    public function testGetTagsList()
    {
        $one = $this->objFromFixture(Contact::class, 'contact_one');
        $two = $this->objFromFixture(Contact::class, 'contact_two');
        $three = $this->objFromFixture(Contact::class, 'contact_three');
        $four = $this->objFromFixture(Contact::class, 'contact_four');
        $five = $this->objFromFixture(Contact::class, 'contact_five');

        $this->assertEquals('One', $one->getTagsList());
        $this->assertEquals('One, Two', $two->getTagsList());
        $this->assertEquals('Three, Two', $three->getTagsList());
        $this->assertEquals('Three', $four->getTagsList());
        $this->assertEquals('', $five->getTagsList());
    }
}
