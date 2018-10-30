<?php

namespace SilverCommerce\ContactAdmin\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\TagField\TagField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Security\PermissionProvider;
use SilverCommerce\ContactAdmin\Model\ContactTag;
use SilverStripe\ORM\FieldType\DBHTMLText as HTMLText;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use NathanCox\HasOneAutocompleteField\Forms\HasOneAutocompleteField;
use SilverCommerce\VersionHistoryField\Forms\VersionHistoryField;

/**
 * Details on a particular contact
 * 
 * @author ilateral
 * @package Contacts
 */
class Contact extends DataObject implements PermissionProvider
{
    private static $table_name = 'Contact';

    /**
     * String used to seperate tags, lists, etc
     * when rendering a summary.
     *
     * @var string
     */
    private static $list_seperator = ", ";

    private static $db = [
        "FirstName" => "Varchar(255)",
        "Surname" => "Varchar(255)",
        "Company" => "Varchar(255)",
        "Phone" => "Varchar(15)",
        "Mobile" => "Varchar(15)",
        "Email" => "Varchar(255)",
        "Source" => "Text"
    ];

    private static $has_one = [
        "Member" => Member::class
    ];

    private static $has_many = [
        "Locations" => ContactLocation::class,
        "Notes" => ContactNote::class
    ];
    
    private static $many_many = [
        'Tags' => ContactTag::class
    ];

    private static $belongs_many_many = [
        'Lists' => ContactList::class
    ];
    
    private static $casting = [
        'TagsList' => 'Varchar',
        'ListsList' => 'Varchar',
        'FlaggedNice' => 'Boolean',
        'FullName' => 'Varchar',
        'Name' => 'Varchar',
        "DefaultAddress" => "Text"
    ];
    
    private static $summary_fields = [
        "FlaggedNice" =>"Flagged",
        "FirstName" => "FirstName",
        "Surname" => "Surname",
        "Email" => "Email",
        "DefaultAddress" => "Default Address",
        "TagsList" => "Tags",
        "ListsList" => "Lists"
    ];

    private static $default_sort = [
        "FirstName" => "ASC",
        "Surname" => "ASC"
    ];
    
    private static $searchable_fields = [
        "FirstName",
        "Surname",
        "Email",
        "Locations.Address1",
        "Locations.Address2",
        "Locations.City",
        "Locations.Country",
        "Locations.PostCode",
        "Tags.Title",
        "Lists.Title"
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

    /**
     * Fields that can be synced with associated member
     * 
     * @var array
     */
    private static $sync_fields = [
        "FirstName",
        "Surname",
        "Company",
        "Phone",
        "Mobile",
        "Email"
    ];
    
    public function getTitle()
    {
        $parts = [];

        if (!empty($this->FirstName)) {
            $parts[] = $this->FirstName;
        }

        if (!empty($this->Surname)) {
            $parts[] = $this->Surname;
        }

        if (!empty($this->Email)) {
            $parts[] = "($this->Email)";
        }

        $title = implode(" ", $parts);

        $this->extend("updateTitle", $title);
        
		return $title;
	}

    public function getFullName() 
    {
        $parts = [];

        if (!empty($this->FirstName)) {
            $parts[] = $this->FirstName;
        }

		if (!empty($this->Surname)) {
            $parts[] = $this->Surname;
        }

        $name = implode(" ", $parts);
        
        $this->extend("updateFullName", $name);

        return $name;
	}
    
    public function getFlaggedNice()
    {
        $obj = HTMLText::create();
        $obj->setValue(($this->Flagged)? '<span class="red">&#10033;</span>' : '');

        $this->extend("updateFlaggedNice", $obj);
       
        return $obj;
    }

    /**
     * Find from our locations one marked as default (of if not the
     * first in the list).
     *
     * @return ContactLocation
     */
    public function DefaultLocation()
    {
        $location = $this
            ->Locations()
            ->sort("Default", "DESC")
            ->first();
        
        $this->extend("updateDefaultLocation", $location);

        return $location;
    }

    /**
     * Find from our locations one marked as default (of if not the
     * first in the list).
     *
     * @return string
     */
    public function getDefaultAddress()
    {
        $location = $this->DefaultLocation();

        if ($location) {
            return $location->Address;
        } else {
            return "";
        }
    }

	/**
	 * Get the complete name of the member
	 *
	 * @return string Returns the first- and surname of the member.
	 */
	public function getName() 
    {
        $name = ($this->Surname) ? trim($this->FirstName . ' ' . $this->Surname) : $this->FirstName;
        
        $this->extend("updateName", $name);

        return $name;
	}
    
    /**
     * Generate as string of tag titles seperated by a comma
     *
     * @return string
     */
    public function getTagsList()
    {
        $return = "";
        $tags = $this->Tags()->column("Title");
        
        $this->extend("updateTagsList", $tags);
        
        return implode(
            $this->config()->list_seperator,
            $tags
        );
    }
    
    /**
     * Generate as string of list titles seperated by a comma
     *
     * @return string
     */
    public function getListsList()
    {
        $return = "";
        $list = $this->Lists()->column("Title");

        $this->extend("updateListsList", $tags);

        return implode(
            $this->config()->list_seperator,
            $list
        );
    }
    
    public function getFlagged()
    {
        $flagged = false;
        
        foreach ($this->Notes() as $note) {
            if ($note->Flag) {
                $flagged = true;
            }
        }

        $this->extend("updateFlagged", $flagged);

        return $flagged;
    }
    
    /**
     * Update an associated member with the data from this contact
     * 
     * @return void
     */
    public function syncToMember()
    {
        $member = $this->Member();
        $sync = $this->config()->sync_fields;
        $write = false;

        if (!$member->exists()) {
            return;
        }

        foreach ($this->getChangedFields() as $field => $change) {
            // If this field is a field to sync, and it is different
            // then update member
            if (in_array($field, $sync) && $member->$field != $this->$field) {
                $member->$field = $this->$field;
                $write = true;
            }
        }

        if ($write) {
            $member->write();
        }
    }

    public function getCMSFields()
    {
        $self = $this;
        $this->beforeUpdateCMSFields(function ($fields) use ($self) {
            $fields->removeByName("Tags");
            $fields->removeByName("Notes");
            
            $tag_field = TagField::create(
                'Tags',
                null,
                ContactTag::get(),
                $self->Tags()
            )->setRightTitle(_t(
                "Contacts.TagDescription",
                "List of tags related to this contact, seperated by a comma."
            ))->setShouldLazyLoad(true);
            
            if ($self->exists()) {
                $gridField = GridField::create(
                    'Notes',
                    'Notes',
                    $self->Notes()
                );
                
                $config = GridFieldConfig_RelationEditor::create();

                $gridField->setConfig($config);

                $fields->addFieldToTab(
                    "Root.Notes",
                    $gridField
                );

                $fields->addFieldToTab(
                    "Root.History",
                    VersionHistoryField::create(
                        "History",
                        _t("SilverCommerce\VersionHistoryField.History", "History"),
                        $self
                    )->addExtraClass("stacked")
                );
            }
            
            $fields->addFieldsToTab(
                "Root.Main",
                [
                    $member_field = HasOneAutocompleteField::create(
                        'MemberID',
                        _t(
                            'SilverCommerce\ContactAdmin.LinkContactToAccount',
                            'Link this contact to a user account?'
                        ),
                        Member::class,
                        'Title'
                    ),
                    $tag_field
                ]
            );
        });

        return parent::getCMSFields();
    }
    
    public function getCMSValidator()
    {
        $validator = new RequiredFields(array(
            "FirstName",
            "Surname"
        ));

        $this->extend('updateCMSValidator', $validator);

        return $validator;
    }
    
    public function providePermissions()
    {
        return array(
            "CONTACTS_MANAGE" => array(
                'name' => _t(
                    'Contacts.PERMISSION_MANAGE_CONTACTS_DESCRIPTION',
                    'Manage contacts'
                ),
                'help' => _t(
                    'Contacts.PERMISSION_MANAGE_CONTACTS_HELP',
                    'Allow creation and editing of contacts'
                ),
                'category' => _t('Contacts.Contacts', 'Contacts')
            ),
            "CONTACTS_DELETE" => array(
                'name' => _t(
                    'Contacts.PERMISSION_DELETE_CONTACTS_DESCRIPTION',
                    'Delete contacts'
                ),
                'help' => _t(
                    'Contacts.PERMISSION_DELETE_CONTACTS_HELP',
                    'Allow deleting of contacts'
                ),
                'category' => _t('Contacts.Contacts', 'Contacts')
            )
        );
    }
    
    public function canView($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);

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

        if (!$member) {
            $member = Security::getCurrentUser();
        }

        if ($member && Permission::checkMember($member->ID, "CONTACTS_MANAGE")) {
            return true;
        }

        return false;
    }

    public function canDelete($member = null, $context = [])
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);

        if ($extended !== null) {
            return $extended;
        }

        if (!$member) {
            $member = Security::getCurrentUser();
        }

        if ($member && Permission::checkMember($member->ID, "CONTACTS_DELETE")) {
            return true;
        }

        return false;
    }

    /**
     * Sync to associated member (if needed)
     * 
     * @return void
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();

        $this->syncToMember();
    }

    /**
     * Cleanup DB on removal
     *
     */
    public function onBeforeDelete()
    {
        parent::onBeforeDelete();
        
        // Delete all locations attached to this order
        foreach ($this->Locations() as $item) {
            $item->delete();
        }

        // Delete all notes attached to this order
        foreach ($this->Notes() as $item) {
            $item->delete();
        }
    }
}
