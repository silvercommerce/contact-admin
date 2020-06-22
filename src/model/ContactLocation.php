<?php

namespace SilverCommerce\ContactAdmin\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Security\PermissionProvider;
use SilverCommerce\VersionHistoryField\Forms\VersionHistoryField;

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
 * @author ilateral
 * @package Contacts
 */
class ContactLocation extends DataObject implements PermissionProvider
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

    /**
     * Add extension classes
     *
     * @var array
     * @config
     */
    private static $extensions = [
        Versioned::class . '.versioned',
    ];

    /**
     * Declare version history
     *
     * @var array
     * @config
     */
    private static $versioning = [
        "History"
    ];

    public function getTitle()
    {
        $title = $this->Address1 . " (" . $this->PostCode . ")";

        $this->extend("updateTitle", $title);

        return $title;
    }

    public function getAddress() 
    {
        $return = [];
        $return[] = $this->Address1;
        
		if (!empty($this->Address2)) {
            $return[] = $this->Address2;
        }
        
        $return[] = $this->City;

		if (!empty($this->County)) {
            $return[] = $this->County;
        }

        $return[] = $this->Country;
        $return[] = $this->PostCode;

        $this->extend("updateAddress", $return);
        
		return implode(",\n", $return);
    }
    
    public function getCMSFields()
    {
        $self = $this;
        $this->beforeUpdateCMSFields(function ($fields) use ($self) {
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
        });

        return parent::getCMSFields();
    }

    public function getCMSValidator()
    {
        $validaotr = new RequiredFields([
            "Address1",
            "City",
            "Country",
            "PostCode"
        ]);

        $this->extend('updateCMSValidator', $validator);

        return $validator;
    }
    
    public function providePermissions()
    {
        return [
            "CONTACTS_MANAGE" => [
                'name' => _t(
                    'Contacts.PERMISSION_MANAGE_CONTACTS_DESCRIPTION',
                    'Manage contacts'
                ),
                'help' => _t(
                    'Contacts.PERMISSION_MANAGE_CONTACTS_HELP',
                    'Allow creation and editing of contacts'
                ),
                'category' => _t('Contacts.Contacts', 'Contacts')
            ],
            "CONTACTS_DELETE" => [
                'name' => _t(
                    'Contacts.PERMISSION_DELETE_CONTACTS_DESCRIPTION',
                    'Delete contacts'
                ),
                'help' => _t(
                    'Contacts.PERMISSION_DELETE_CONTACTS_HELP',
                    'Allow deleting of contacts'
                ),
                'category' => _t('Contacts.Contacts', 'Contacts')
            ]
        ];
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
            $member = Member::currentUser();
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
