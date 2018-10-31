<?php

namespace SilverCommerce\ContactAdmin\Admin;

use SilverStripe\Forms\TextField;
use SilverStripe\Dev\CsvBulkLoader;
use SilverStripe\TagField\TagField;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use Colymba\BulkManager\BulkManager;
use SilverStripe\Forms\CheckboxField;
use TractorCow\AutoComplete\AutoCompleteField;
use SilverCommerce\ContactAdmin\Model\Contact;
use Colymba\BulkManager\BulkAction\UnlinkHandler;
use SilverCommerce\ContactAdmin\Model\ContactTag;
use SilverCommerce\ContactAdmin\Model\ContactList;
use SilverStripe\Forms\GridField\GridFieldPrintButton;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverCommerce\ContactAdmin\BulkActions\AddTagsHandler;
use SilverCommerce\ContactAdmin\BulkActions\AddToListHandler;
use SilverCommerce\ContactAdmin\Forms\ModelAdminAutoCompleteField;
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
                ->push(new CheckboxField('q[Flagged]', _t("Contacts.ShowFlaggedOnly", 'Show flagged only')));
        }

        return $context;
    }

    public function getList()
    {
        $list = parent::getList();

        // use this to access search parameters
        $params = $this->request->requestVar('q');

        if ($this->modelClass == Contact::class && isset($params['Flagged']) && $params['Flagged']) {
            $list = $list->filter(
                "Notes.Flag",
                true
            );
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