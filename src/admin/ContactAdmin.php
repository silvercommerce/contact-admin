<?php

namespace SilverCommerce\ContactAdmin\Admin;

use SilverStripe\Forms\TextField;
use Silverstripe\Admin\ModelAdmin;
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

/**
 * Management interface for contacts
 * 
 * @author ilateral
 * @package Contacts
 */
class ContactAdmin extends ModelAdmin
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
        $manager = new BulkManager();
        $manager->removeBulkAction(UnlinkHandler::class);

        if ($this->modelClass == Contact::class) {
            $manager->addBulkAction(AddTagsHandler::class);
            $manager->addBulkAction(AddToListHandler::class);
        } else {
            $config
                ->removeComponentsByType(GridFieldExportButton::class)
                ->removeComponentsByType(GridFieldPrintButton::class);
        }

        $config->addComponents($manager);

        $this->extend("updateEditForm", $form);

        return $form;
    }

    public function SearchForm()
    {
        $form = parent::SearchForm();
        $fields = $form->Fields();

        if ($this->modelClass == Contact::class) {
            $config = Contact::config();
            $has_one = $config->has_one;
            $has_many = $config->has_many;
            $many_many = $config->many_many;
            $belongs_many_many = $config->belongs_many_many;
            $associations = array_merge(
                $has_one,
                $has_many,
                $many_many,
                $belongs_many_many
            );

            foreach ($fields as $field) {
                if ($field instanceof TextField) {
                    $name = $field->getName();
                    $title = $field->Title();
                    $db_field = str_replace(["q[", "]"], "", $name);
                    $class = $this->modelClass;

                    // If this is a relation, switch class name
                    if (strpos($name, "__")) {
                        $parts = explode("__", $db_field);
                        $class = $associations[$parts[0]];
                        $db_field = $parts[1];
                    }

                    $fields->replaceField(
                        $name,
                        ModelAdminAutoCompleteField::create(
                            /** @scrutinizer ignore-type */ $name,
                            $title,
                            $field->Value(),
                            $class,
                            $db_field
                        )->setForm($form)
                        ->setDisplayField($db_field)
                        ->setLabelField($db_field)
                        ->setStoredField($db_field)
                    );
                }
            }

        }

        return $form;
    }
}