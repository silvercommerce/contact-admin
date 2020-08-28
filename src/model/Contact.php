<?php

namespace SilverCommerce\ContactAdmin\Model;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\TagField\TagField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Security\PermissionProvider;
use SilverCommerce\ContactAdmin\Model\ContactTag;
use SilverCommerce\ContactAdmin\Model\ContactLocation;
use SilverStripe\ORM\FieldType\DBHTMLText as HTMLText;
use SilverCommerce\CatalogueAdmin\Search\ContactSearchContext;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverCommerce\VersionHistoryField\Forms\VersionHistoryField;
use NathanCox\HasOneAutocompleteField\Forms\HasOneAutocompleteField;
use SilverStripe\Core\Config\Config;

/**
 * Details on a particular contact
 *
 * @property string FirstName
 * @property string Surname
 * @property string Company
 * @property string Phone
 * @property string Mobile
 * @property string Email
 * @property string Source
 * @property bool   Flagged
 *
 * @method \SilverStripe\ORM\HasManyList  Locations
 * @method \SilverStripe\ORM\HasManyList  Notes
 * @method \SilverStripe\ORM\ManyManyList Tags
 * @method \SilverStripe\ORM\ManyManyList Lists
 *
 * @author  ilateral
 * @package Contacts
 */
class Contact extends DataObject implements PermissionProvider
{
    const LOCATION_BY_POS = 'LocationByPos';

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

    private static $field_labels = [
        "FlaggedNice" =>"Flagged",
        "DefaultAddress" => "Default Address",
        "TagsList" => "Tags",
        "ListsList" => "Lists",
        "Locations.Address1" => 'Address 1',
        "Locations.Address2" => 'Address 2',
        "Locations.City" => 'City',
        "Locations.Country" => 'Country',
        "Locations.PostCode" => 'Post Code',
        "Tags.Title" => 'Tag',
        "Lists.Title" => 'List'
    ];
    
    private static $summary_fields = [
        "FlaggedNice",
        "FirstName",
        "Surname",
        "Email",
        "DefaultAddress",
        "TagsList",
        "ListsList"
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

    private static $export_fields = [
        "FirstName",
        "Surname",
        "Company",
        "Phone",
        "Mobile",
        "Email",
        "Source",
        "TagsList",
        "ListsList"
    ];

    private static $default_sort = [
        "FirstName" => "ASC",
        "Surname" => "ASC"
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
     * Get a contact with the most locations assigned
     *
     * @return self|null
     */
    public static function getByMostLocations()
    {
        $id = null;
        $query = new SQLSelect();
        $query
            ->setFrom('Contact')
            ->setSelect('Contact.ID, count(ContactLocation.ID) as LocationsCount')
            ->addLeftJoin('ContactLocation', 'Contact.ID = ContactLocation.ContactID')
            ->addGroupBy('Contact.ID')
            ->addOrderBy('LocationsCount', 'DESC')
            ->setLimit(1);

        foreach($query->execute() as $row) {
            $id = $row['ID'];
        }

        if (!empty($id)) {
            return Contact::get()->byID($id);
        }

        return;
    }

    /**
     * Find from our locations one marked as default (of if not the
     * first in the list).
     *
     * If not location available, return a blank one
     *
     * @return ContactLocation
     */
    public function DefaultLocation()
    {
        $location = $this
            ->Locations()
            ->sort("Default", "DESC")
            ->first();
        
        if (empty($location)) {
            $location = ContactLocation::create();
            $location->ID = -1;
        }
        
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
        return $this->getFullName();
    }

    /**
     * Generate as string of tag titles seperated by a comma
     *
     * @return string
     */
    public function getTagsList()
    {
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

    /**
     * Load custom search context to allow for filtering by flagged notes
     * 
     * @return ContactSearchContext
     */
    public function getDefaultSearchContext()
    {
        return ContactSearchContext::create(
            static::class,
            $this->scaffoldSearchFields(),
            $this->defaultSearchFilters()
        );
    }

    /**
     * Load custom search context for model admin plus
     * 
     * @return ContactSearchContext
     */
    public function getModelAdminSearchContext()
    {
        return ContactSearchContext::create(
            static::class,
            $this->scaffoldSearchFields(),
            $this->defaultSearchFilters()
        );
    }

    public function getCMSFields()
    {
        $self = $this;
        $this->beforeUpdateCMSFields(
            function ($fields) use ($self) {
                $fields->removeByName("Tags");
                $fields->removeByName("Notes");
            
                $tag_field = TagField::create(
                    'Tags',
                    null,
                    ContactTag::get(),
                    $self->Tags()
                )->setRightTitle(
                    _t(
                        "Contacts.TagDescription",
                        "List of tags related to this contact, seperated by a comma."
                    )
                )->setShouldLazyLoad(true);
            
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
            }
        );

        return parent::getCMSFields();
    }
    
    public function getCMSValidator()
    {
        $validator = new RequiredFields(
            array(
            "FirstName",
            "Surname"
            )
        );

        $this->extend('updateCMSValidator', $validator);

        return $validator;
    }

    public function LocationByPos($pos = 0)
    {
    }

    /**
     * Get the default export fields for this object
     *
     * @return array
     */
    public function getExportFields()
    {
        $raw_fields = $this->config()->get('export_fields');
        $loc_fields = ContactLocation::singleton()->getExportFields();

        // Merge associative / numeric keys
        $fields = [];
        foreach ($raw_fields as $key => $value) {
            if (is_int($key)) {
                $key = $value;
            }
            $fields[$key] = $value;
        }

        // Work out address fields for export
        $most_locations = self::getByMostLocations();
        $location_count = 0;

        if (!empty($most_locations)) {
            $location_count = $most_locations->Locations()->count();
        }

        for ($i = 0; $i < $location_count; $i++) {
            foreach ($loc_fields as $key => $value) {
                if (is_int($key)) {
                    $key = $value;
                }
                $fields[self::LOCATION_BY_POS . $i . '.' . $key] = 'Address' . $i . '_' . $value;
            }
        }

        $this->extend("updateExportFields", $fields);

        // Final fail-over, just list ID field
        if (!$fields) {
            $fields['ID'] = 'ID';
        }

        return $fields;
    }

    /**
     * Check field 
     *
     * @param string $fieldName string
     *
     * @return mixed Will return null on a missing value
     */
    public function relField($fieldName)
    {
        /** @todo This is a bit in-effitient, woud be nice to do this with slightly less queries */
        if (strpos($fieldName, self::LOCATION_BY_POS) !== false) {
            $pos = (int) substr($fieldName, strlen(self::LOCATION_BY_POS), 1);
            $loc_field = substr($fieldName, strpos($fieldName, '.') + 1);
            $location = $this->Locations()->limit(1, $pos)->first();
            return empty($location) ? "" : $location->$loc_field;
        } else {
            return parent::relField($fieldName);
        }
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
            $member = Security::getCurrentUser();
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
