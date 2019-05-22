<?php

namespace SilverCommerce\ContactAdmin\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText as HTMLText;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RelationEditor;
use SilverStripe\TagField\TagField;
use SilverCommerce\ContactAdmin\Model\ContactTag;

/**
 * Notes on a particular contact
 *
 * @property string Content
 * @property bool   Flag
 *
 * @method Contact Contact
 * 
 * @author ilateral
 * @package Contacts
 */
class ContactNote extends DataObject
{
    private static $table_name = 'ContactNote';

    private static $db = [
        "Content" => "Text",
        "Flag" => "Boolean"
    ];
    
    private static $has_one = [
        "Contact" => Contact::class
    ];
    
    private static $casting = [
        'FlaggedNice' => 'Boolean'
    ];
    
    private static $summary_fields = [
        "FlaggedNice" => "Flagged",
        "Content.Summary" => "Content",
        "Created" => "Created"
    ];
    
    /**
     * Has this note been flagged? If so, return a HTML Object that
     * can be loaded into a gridfield.
     *
     * @return DBHTMLText
     */
    public function getFlaggedNice()
    {
        $obj = HTMLText::create();
        $obj->setValue(($this->Flag)? '<span class="red">&#10033;</span>' : '');
        
        $this->extend("updateFlaggedNice", $obj);
        
        return $obj;
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
}
