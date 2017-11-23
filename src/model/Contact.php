<?php

namespace SilverCommerce\ContactAdmin\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText as HTMLText;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\TagField\TagField;
use SilverCommerce\ContactAdmin\Model\ContactTag;

/**
 * Details on a particular contact
 * 
 * @author ilateral
 * @package Contacts
 */
class Contact extends DataObject implements PermissionProvider
{
    private static $table_name = 'Contact';

    private static $db = [
        "Salutation" => "Varchar(20)",
        "FirstName" => "Varchar(255)",
        "MiddleName" => "Varchar(255)",
        "Surname" => "Varchar(255)",
        "Company" => "Varchar(255)",
        "Phone" => "Varchar(15)",
        "Mobile" => "Varchar(15)",
        "Email" => "Varchar(255)",
        "Source" => "Text"
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
        "Salutation",
        "FirstName",
        "MiddleName",
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
    
    public function getTitle()
    {
        $t = '';
        if (!empty($this->Salutation)) {
            $t = "$this->Salutation ";
        }
        $f = '';
        if (!empty($this->FirstName)) {
            $f = "$this->FirstName ";
        }
        $m = '';
        if (!empty($this->MiddleName)) {
            $m = "$this->MiddleName ";
        }
        $s = '';
        if (!empty($this->Surname)) {
            $s = "$this->Surname ";
        }
        $e = '';
        if (!empty($this->Email)) {
            $e = "($this->Email)";
        }
        
		return $t.$f.$m.$s.$e;
	}

    public function getFullName() 
    {
		$t = '';
		if (!empty($this->Salutation)) $t = "$this->Salutation ";
        	$f = '';
		if (!empty($this->FirstName)) $f = "$this->FirstName ";
		$m = '';
		if (!empty($this->MiddleName)) $m = "$this->MiddleName ";
		$s = '';
		if (!empty($this->Surname)) $s = "$this->Surname ";
        
		return $t.' '.$f.' '.$m.' '.$s;
	}
    
    public function getFlaggedNice()
    {
        $obj = HTMLText::create();
        $obj->setValue(($this->Flagged)? '<span class="red">&#10033;</span>' : '');
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
        return $this
            ->Locations()
            ->sort("Default", "DESC")
            ->first();
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

        if ($location->exists()) {
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
		return ($this->Surname) ? trim($this->FirstName . ' ' . $this->Surname) : $this->FirstName;
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
        return implode(", ", $tags);
    }
    
    /**
     * Generate as string of list titles seperated by a comma
     *
     * @return string
     */
    public function getListsList()
    {
        $return = "";
        $tags = $this->Lists()->column("Title");
        return implode(", ", $tags);
    }
    
    public function getFlagged()
    {
        $flagged = false;
        
        foreach ($this->Notes() as $note) {
            if ($note->Flag) {
                $flagged = true;
            }
        }
        return $flagged;
    }
    
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        
        $fields->removeByName("Tags");
        $fields->removeByName("Notes");
        
        $tag_field = TagField::create(
            'Tags',
            null,
            ContactTag::get(),
            $this->Tags()
        )->setRightTitle(_t(
            "Contacts.TagDescription",
            "List of tags related to this contact, seperated by a comma."
        ))->setShouldLazyLoad(true);
        
        if ($this->ID) {
            $gridField = GridField::create(
                'Notes',
                'Notes',
                $this->Notes()
            );
            
            $config = GridFieldConfig_RelationEditor::create();

            $gridField->setConfig($config);

            $fields->addFieldToTab(
                "Root.Notes",
                $gridField
            );
        }
        
        $fields->addFieldToTab(
            "Root.Main",
            $tag_field
        );
        
        return $fields;
    }
    
    public function getCMSValidator()
    {
        return new RequiredFields(array(
            "FirstName",
            "Surname"
        ));
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
    
    public function canView($member = false)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);

        if ($extended !== null) {
            return $extended;
        }

        if (!$member) {
            $member = Member::currentUser();
        }

        if ($member && Permission::checkMember($member->ID, array("ADMIN", "CONTACTS_TAGS_MANAGE"))) {
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
            $member = Member::currentUser();
        }

        if ($member && Permission::checkMember($member->ID, array("ADMIN", "CONTACTS_TAGS_MANAGE"))) {
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
            $member = Member::currentUser();
        }

        if ($member && Permission::checkMember($member->ID, array("ADMIN", "CONTACTS_TAGS_MANAGE"))) {
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
            $member = Member::currentUser();
        }

        if ($member && Permission::checkMember($member->ID, array("ADMIN", "CONTACTS_TAGS_MANAGE"))) {
            return true;
        }

        return false;
    }
}
