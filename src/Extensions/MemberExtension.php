<?php

namespace SilverCommerce\ContactAdmin\Extensions;

use SilverCommerce\ContactAdmin\Helpers\ContactHelper;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverCommerce\ContactAdmin\Model\Contact;

/**
 * Add additional settings to a memeber object
 *
 * @package    orders-admin
 * @subpackage extensions
 */
class MemberExtension extends DataExtension
{
    private static $db = [
        "Company" => "Varchar(255)",
        "Phone" => "Varchar(15)",
        "Mobile" => "Varchar(15)"
    ];

    private static $belongs_to = [
        'Contact' => Contact::class . '.Member'
    ];

    private static $casting = [
        "ContactTitle" => "Varchar"
    ];

    /**
     * Get the locations from a contact
     */
    public function Locations()
    {
        return $this->getOwner()->Contact()->Locations();
    }

    /**
     * Return the "default" location for the associated contact.
     *
     * @return ContactLocation
     */
    public function DefaultLocation()
    {
        return $this->getOwner()->Contact()->DefaultLocation();
    }

    public function updateCMSFields(FieldList $fields)
    {
        if ($this->owner->ID) {
            $fields->addFieldToTab(
                "Root.Main",
                ReadonlyField::create("ContactTitle")
            );
        }
    }

    /**
     * The name of the contact assotiated with this account
     *
     * @return void
     */
    public function getContactTitle()
    {
        $contact = $this->owner->Contact();

        return $contact->Title;
    }

    /**
     * If no contact exists for this account, then create one
     * and push changed data to the contact
     *
     * @return void
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();

        if (ContactHelper::config()->get('auto_sync') && $this->getOwner()->isChanged()) {
            $helper = ContactHelper::create();
            $helper->setMember($this->getOwner());
            $contact = $helper->findOrMakeContact();

            ContactHelper::pushChangedFields($this->getOwner(), $contact);
        }
    }
}
