<?php

namespace SilverCommerce\ContactAdmin\Extensions;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;
use SilverCommerce\ContactAdmin\Model\Contact;
use SilverStripe\Core\Config\Config;

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
     * Update an associated member with the data from this contact
     * 
     * @return void
     */
    public function syncToContact()
    {
        $owner = $this->getOwner();
        $contact = $owner->Contact();
        $sync = Config::inst()->get(Contact::class, 'sync_fields');
        $write = false;

        if (!$contact->exists()) {
            return;
        }

        foreach ($owner->getChangedFields() as $field => $change) {
            // If this field is a field to sync, and it is different
            // then update contact
            if (in_array($field, $sync) && $contact->$field != $owner->$field) {
                $contact->$field = $owner->$field;
                $write = true;
            }
        }

        if ($write) {
            $contact->write();
        }
    }

    /**
     * If no contact exists for this account, then create one
     *
     * @return void
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();

        if (!$this->getOwner()->Contact()->exists()) {
            $sync = Config::inst()->get(Contact::class, 'sync_fields');
            $contact = Contact::create();

            foreach ($sync as $field) {
                $contact->$field = $this->getOwner()->$field;
            }

            $contact->MemberID = $this->getOwner()->ID;
            $contact->write();
        } else {
            $this->getOwner()->syncToContact();
        }


    }
}
