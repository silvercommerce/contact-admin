<?php

namespace SilverCommerce\ContactAdmin\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\TagField\TagField;
use SilverStripe\Security\Permission;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Security\PermissionProvider;
use SilverCommerce\ContactAdmin\Model\ContactTag;
use SilverStripe\ORM\FieldType\DBHTMLText as HTMLText;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;

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

        $title = $t.$f.$m.$s.$e;

        $this->extend("updateTitle", $title);
        
		return $title;
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
        
        $name = $t.' '.$f.' '.$m.' '.$s;
        
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
