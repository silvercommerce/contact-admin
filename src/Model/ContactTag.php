<?php

namespace SilverCommerce\ContactAdmin\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use Colymba\BulkManager\BulkManager;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use Colymba\BulkManager\BulkAction\EditHandler;
use Colymba\BulkManager\BulkAction\DeleteHandler;

/**
 * A tag for keyword descriptions of a contact.
 *
 * @property string Title
 *
 * @method \SilverStripe\ORM\ManyManyList Contacts
 *
 * @package    silverstripe
 * @subpackage contacts
 */
class ContactTag extends DataObject implements PermissionProvider
{
    private static $table_name = 'ContactTag';
    
    private static $singular_name = 'Tag';

    private static $plural_name = 'Tags';
        
    private static $db = [
        'Title' => 'Varchar(255)',
    ];

    private static $belongs_many_many = [
        'Contacts' => Contact::class,
    ];
    
    private static $summary_fields = [
        'Title',
        'Contacts.Count'
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // Move contacts field to main tab
        $contacts_field = $fields->dataFieldByName("Contacts");
        $fields->removeByName("Contacts");

        if ($contacts_field) {
            $manager = new BulkManager();
            $manager->removeBulkAction(DeleteHandler::class);
            $manager->removeBulkAction(EditHandler::class);
            
            $config = $contacts_field->getConfig();
            $config->addComponent($manager);

            $fields->addFieldToTab(
                'Root.Main',
                $contacts_field
            );
        }

        $this->extend('updateCMSFields', $fields);

        return $fields;
    }
    
    public function providePermissions()
    {
        return [
            "CONTACTS_TAGS_MANAGE" => [
                'name' => _t(
                    'Contacts.PERMISSION_MANAGE_CONTACTS_TAGS_DESCRIPTION',
                    'Manage contact tags'
                ),
                'help' => _t(
                    'Contacts.PERMISSION_MANAGE_CONTACTS_TAGS_HELP',
                    'Allow creation and editing of contact lists'
                ),
                'category' => _t('Contacts.Contacts', 'Contacts')
            ],
            "CONTACTS_TAGS_DELETE" => [
                'name' => _t(
                    'Contacts.PERMISSION_DELETE_CONTACTS_TAGS_DESCRIPTION',
                    'Delete contact lists'
                ),
                'help' => _t(
                    'Contacts.PERMISSION_DELETE_CONTACTS_TAGS_HELP',
                    'Allow deleting of contact lists'
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
        
        if (!$member) {
            $member = Member::currentUser();
        }
            
        if ($member && Permission::checkMember($member->ID, "CONTACTS_TAGS_MANAGE")) {
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
            
        if ($member && Permission::checkMember($member->ID, "CONTACTS_TAGS_MANAGE")) {
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
            
        if ($member && Permission::checkMember($member->ID, "CONTACTS_TAGS_MANAGE")) {
            return true;
        }

        return false;
    }

    public function canDelete($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);

        if ($extended !== null) {
            return $extended;
        }
        
        if (!$member) {
            $member = Member::currentUser();
        }
            
        if ($member && Permission::checkMember($member->ID, "CONTACTS_TAGS_DELETE")) {
            return true;
        }

        return false;
    }
}
