<?php

namespace SilverCommerce\CatalogueAdmin\Forms\GridField;

use SilverCommerce\ContactAdmin\Helpers\ContactHelper;
use SilverCommerce\ContactAdmin\Model\Contact;
use SilverStripe\Forms\FormAction;
use SilverStripe\Security\Security;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;

/**
 * Custom detailform that allows generating user account from a contact
 *
 * @author ilateral
 */
class ContactDetailForm_ItemRequest extends GridFieldDetailForm_ItemRequest
{
    private static $allowed_actions = [
        'edit',
        'view',
        'ItemEditForm'
    ];

    public function ItemEditForm()
    {
        $form = parent::ItemEditForm();
        $record = $this->record;

        if ($record instanceof Contact
            && $form && $record->ID !== 0
            && $record->canEdit()
            && !$record->Member()->exists()
        ) {
            $actions = $form->Actions();
            $actions->insertAfter(
                FormAction::create(
                    'doCreateUser',
                    _t('ContactAdmin.CreateUser', 'Create User Account')
                )->setUseButtonTag(true)
                ->addExtraClass('btn btn-outline-info')
                ->addExtraClass('action font-icon-torso'),
                "action_doSave"
            );
        }
        
        $this->extend("updateItemEditForm", $form);
        
        return $form;
    }

    public function doCreateUser($data, $form)
    {
        $record = $this->record;

        if ($record && !$record->canEdit()) {
            return Security::permissionFailure($this);
        }

        $helper = ContactHelper::create()
            ->setContact($record);

        $member = $helper->findOrMakeMember();
        $helper->setMember($member);
        $helper->linkMemberToGroups();
        
        return $this->redirectAfterSave(false);
    }
}
