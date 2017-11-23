<?php

namespace SilverCommerce\ContactAdmin\Admin;

use Silverstripe\Admin\ModelAdmin;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Dev\CsvBulkLoader;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldPrintButton;
use Colymba\BulkManager\BulkManager;
use SilverCommerce\ContactAdmin\Model\Contact;
use SilverCommerce\ContactAdmin\Model\ContactTag;
use SilverCommerce\ContactAdmin\Model\ContactList;
use SilverCommerce\ContactAdmin\BulkActions\AssignToList;

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
        ContactList::class => CSVBulkLoader::class
    ];

    public $showImportForm = [
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
        $manager->removeBulkAction("unLink");

        if ($this->modelClass == Contact::class) {
            $manager->addBulkAction(
                "assign",
                _t("Contacts.AssignToList", "Assign to list"),
                AssignToList::class,
                array(
                    'isAjax' => false,
                    'icon' => 'pencil',
                    'isDestructive' => false
                )
            );
        } else {
            $config
                ->removeComponentsByType(GridFieldExportButton::class)
                ->removeComponentsByType(GridFieldPrintButton::class);
        }

        $config->addComponents($manager);

        $this->extend("updateEditForm", $form);

        return $form;
    }
}