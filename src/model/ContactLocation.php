<?php

namespace SilverCommerce\ContactAdmin\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Security\PermissionProvider;
use SilverCommerce\VersionHistoryField\Forms\VersionHistoryField;
use SilverStripe\Security\Security;

/**
 * Details on a particular contact
 *
 * @property string Title
 * @property string Address
 * @property string Address1
 * @property string Address2
 * @property string City
 * @property string Country
 * @property string County
 * @property string PostCode
 * @property bool   Default
 *
 * @method Contact Contact
 *
 * @author  ilateral
 * @package Contacts
 */
class ContactLocation extends DataObject
{
    private static $table_name = 'ContactLocation';

    private static $db = [
        "Address1" => "Varchar(255)",
        "Address2" => "Varchar(255)",
        "City" => "Varchar(255)",
        "Country" => "Varchar(255)",
        "County" => "Varchar(255)",
        "PostCode" => "Varchar(10)",
        "Default" => "Boolean"
    ];
    
    private static $has_one = [
        "Contact" => Contact::class
    ];
    
    private static $casting = [
        "Title" => "Varchar",
        "Address" => "Text"
    ];

    private static $frontend_fields = [
        "Address1",
        "Address2",
        "City",
        "Country",
        "County",
        "PostCode",
        "Default"
    ];

    private static $summary_fields = [
        "Address1",
        "Address2",
        "City",
        "County",
        "Country",
        "PostCode",
        "Default"
    ];

    private static $export_fields = [
        "Address1",
        "Address2",
        "City",
        "County",
        "Country",
        "PostCode",
        "Default"
    ];

    /**
     * Add extension classes
     *
     * @var    array
     * @config
     */
    private static $extensions = [
        Versioned::class . '.versioned',
    ];

    /**
     * Declare version history
     *
     * @var    array
     * @config
     */
    private static $versioning = [
        "History"
    ];

    /**
     * Generate a title for this location
     *
     * @return string
     */
    public function getTitle()
    {
        $title = $this->Address1 . " (" . $this->PostCode . ")";

        $this->extend("updateTitle", $title);

        return $title;
    }

    /**
     * Get a list of address fields as an array
     *
     * @return array
     */
    protected function getAddressArray()
    {
        $array = [$this->Address1];
        
        if (!empty($this->Address2)) {
            $array[] = $this->Address2;
        }
        
        $array[] = $this->City;

        if (!empty($this->County)) {
            $array[] = $this->County;
        }

        $array[] = $this->Country;
        $array[] = $this->PostCode;

        $this->extend("updateAddressArray", $array);

        return $array;
    }

    /**
     * Get the address from this location as a string
     *
     * @return string
     */
    public function getAddress()
    {
        $return = $this->getAddressArray();

        $this->extend("updateAddress", $return);
    
        return implode(",\n", $return);
    }

    public function getCMSFields()
    {
        $self = $this;
        $this->beforeUpdateCMSFields(
            function ($fields) use ($self) {
                if ($self->exists()) {
                    $fields->addFieldToTab(
                        "Root.History",
                        VersionHistoryField::create(
                            "History",
                            _t("SilverCommerce\VersionHistoryField.History", "History"),
                            $this
                        )->addExtraClass("stacked")
                    );
                }
            }
        );

        return parent::getCMSFields();
    }

    public function getCMSValidator()
    {
        $validator = new RequiredFields(
            [
            "Address1",
            "City",
            "Country",
            "PostCode"
            ]
        );

        $this->extend('updateCMSValidator', $validator);

        return $validator;
    }

    /**
     * Get the default export fields for this object
     *
     * @return array
     */
    public function getExportFields()
    {
        $raw_fields = $this->config()->get('export_fields');

        // Merge associative / numeric keys
        $fields = [];
        foreach ($raw_fields as $key => $value) {
            if (is_int($key)) {
                $key = $value;
            }
            $fields[$key] = $value;
        }

        $this->extend("updateExportFields", $fields);

        // Final fail-over, just list ID field
        if (!$fields) {
            $fields['ID'] = 'ID';
        }

        return $fields;
    }

    public function canView($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);

        if ($extended !== null) {
            return $extended;
        }

        return $this->Contact()->canView($member);
    }

    public function canCreate($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member, $context);

        if ($extended !== null) {
            return $extended;
        }

        if (!$member) {
            $member = Security::getCurrentUser();
        }

        if ($member && Permission::checkMember($member->ID, "CONTACTS_MANAGE")) {
            return true;
        }

        return false;
    }

    public function canEdit($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);

        if ($extended !== null) {
            return $extended;
        }

        return $this->Contact()->canEdit($member);
    }

    public function canDelete($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);

        if ($extended !== null) {
            return $extended;
        }

        return $this->Contact()->canDelete($member);
    }

    /**
     * If we have assigned this as a default location, loop through
     * other locations and disable default.
     *
     * @return void
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();

        if ($this->Default) {
            foreach ($this->Contact()->Locations() as $location) {
                if ($location->ID != $this->ID && $location->Default) {
                    $location->Default = false;
                    $location->write();
                }
            }
        }
    }
}
