<?php

namespace SilverCommerce\ContactAdmin\Admin;

use SilverStripe\Dev\CsvBulkLoader;
use Colymba\BulkManager\BulkManager;
use SilverStripe\Forms\CheckboxField;
use SilverCommerce\ContactAdmin\Model\Contact;
use SilverCommerce\ContactAdmin\Model\ContactTag;
use SilverCommerce\ContactAdmin\Model\ContactList;
use SilverCommerce\ContactAdmin\BulkActions\AddTagsHandler;
use SilverCommerce\ContactAdmin\BulkActions\AddToListHandler;
use ilateral\SilverStripe\ModelAdminPlus\ModelAdminPlus;

/**
 * Management interface for contacts
 * 
 * @author ilateral
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
        Contact::class => CSVBulkLoader::class,
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

    public function getSearchContext()
    {
        $context = parent::getSearchContext();

        if ($this->modelClass == Contact::class) {
            $context
                ->getFields()
                ->push(
                    CheckboxField::create(
                        'q[Flagged]',
                        _t("Contacts.ShowFlaggedOnly", 'Show flagged only')
                    )
                );
        }

        return $context;
    }

    public function getList()
    {
        $list = parent::getList();

        // use this to access search parameters
        $params = $this->getSearchData();

        if ($this->modelClass == Contact::class && isset($params['Flagged']) && $params['Flagged']) {
            // Find flagged contacts and ensure that we return a DataList
            // (not an arraylist)
            $ids = $list->filterByCallback(
                function ($item, $list) {
                    return $item->getFlagged();
                }
            )->column("ID");

            $list = Contact::get()->filter("ID", $ids);
        }

        return $list;
    }

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);
        $class = $this->sanitiseClassName($this->modelClass);
        $gridField = $form->Fields()->fieldByName($class);
        $config = $gridField->getConfig();

        // Add bulk editing to gridfield
        $manager = $config->getComponentByType(BulkManager::class);

        if ($this->modelClass == Contact::class) {
            $manager->addBulkAction(AddTagsHandler::class);
            $manager->addBulkAction(AddToListHandler::class);
        }

        $this->extend("updateEditForm", $form);

        return $form;
    }
}