<?php

namespace SilverCommerce\ContactAdmin\Admin;

use SilverStripe\Dev\CsvBulkLoader;
use Colymba\BulkManager\BulkManager;
use SilverCommerce\ContactAdmin\Model\Contact;
use SilverCommerce\ContactAdmin\Model\ContactTag;
use SilverCommerce\ContactAdmin\Model\ContactList;
use SilverCommerce\ContactAdmin\BulkActions\AddTagsHandler;
use SilverCommerce\ContactAdmin\BulkActions\AddToListHandler;
use ilateral\SilverStripe\ModelAdminPlus\ModelAdminPlus;
use SilverCommerce\CatalogueAdmin\Forms\GridField\ContactDetailForm_ItemRequest;
use SilverCommerce\ContactAdmin\Import\ContactCSVBulkLoader;
use SilverStripe\Forms\GridField\GridFieldDetailForm;

/**
 * Management interface for contacts
 *
 * @author  ilateral
 * @package Contacts
 */
class ContactAdmin extends ModelAdminPlus
{
    private static $menu_priority = 0;

    private static $managed_models = [
        Contact::class,
        ContactTag::class,
        ContactList::class
    ];

    private static $url_segment = 'contacts';

    private static $menu_title = 'Contacts';

    private static $model_importers = [
        Contact::class => ContactCSVBulkLoader::class,
        ContactTag::class => CSVBulkLoader::class,
        ContactList::class => CSVBulkLoader::class
    ];

    private static $allowed_actions = [
        "SearchForm"
    ];

    public $showImportForm = [
        Contact::class,
        ContactTag::class,
        ContactList::class
    ];

    /**
     * @var string
     */
    private static $menu_icon_class = 'font-icon-torso';

    /**
     * Listen for customised export fields on the currently managed object
     *
     * @return array
     */
    public function getExportFields()
    {
        $model = singleton($this->modelClass);
        if ($model->hasMethod('getExportFields')) {
            return $model->getExportFields();
        }

        return parent::getExportFields();
    }

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);
        $class = $this->sanitiseClassName($this->modelClass);
        $gridField = $form->Fields()->fieldByName($class);
        /** @var \SilverStripe\Forms\GridField\GridFieldConfig */
        $config = $gridField->getConfig();

        // Add bulk editing to gridfield
        $manager = $config->getComponentByType(BulkManager::class);

        if ($this->modelClass == Contact::class) {
            $manager->addBulkAction(AddTagsHandler::class);
            $manager->addBulkAction(AddToListHandler::class);

            $config
                ->getComponentByType(GridFieldDetailForm::class)
                ->setItemRequestClass(ContactDetailForm_ItemRequest::class);
        }

        $this->extend("updateEditForm", $form);

        return $form;
    }

    public function getList()
    {
        // Get contacts via Search Context results
        if ($this->modelClass == Contact::class) {
            /** @var Contact */
            $context = singleton(Contact::class)->getDefaultSearchContext();
            return $context->getResults([]);
        }

        $list = parent::getList();

        $this->extend('updateContactAdminList', $list);

        return $list;
    }
}
