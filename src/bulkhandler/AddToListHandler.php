<?php

namespace SilverCommerce\ContactAdmin\BulkActions;

use SilverStripe\Forms\Form;
use SilverStripe\Core\Convert;
use SilverStripe\View\ArrayData;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\TagField\TagField;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\DropdownField;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Control\PjaxResponseNegotiator;
use SilverCommerce\ContactAdmin\Model\ContactTag;
use SilverCommerce\ContactAdmin\Model\ContactList;

/**
 * Bulk action handler that adds selected records to a list
 * 
 * @author ilateral
 * @package Contacts
 */
class AddToListHandler extends AddRelatedHandler
{

    private static $url_segment = 'addtolist';

    /**
     * Front-end label for this handler's action
     * 
     * @var string
     */
    protected $label = 'Add to List';

    /**
     * RequestHandler allowed actions
     * @var array
     */
    private static $allowed_actions = [
        'index',
        'Form'
    ];
    
    /**
     * Creates and return the editing interface
     * 
     * @return string Form's HTML
     */
    public function index()
    {
        $leftandmain = Injector::inst()->create(LeftAndMain::class);

        $form = $this->Form();
        $form->setTemplate($leftandmain->getTemplatesWithSuffix('_EditForm'));
        $form->addExtraClass('cms-edit-form center cms-content');
        $form->setAttribute('data-pjax-fragment', 'CurrentForm Content');
        
        if ($this->request->isAjax()) {
            $response = new HTTPResponse(
                Convert::raw2json(array('Content' => $form->forAjaxTemplate()->getValue()))
            );
            $response->addHeader('X-Pjax', 'Content');
            $response->addHeader('Content-Type', 'text/json');
            $response->addHeader('X-Title', 'SilverStripe - Bulk ' . $this->gridField->list->dataClass . ' Editing');

            return $response;
        } else {
            $controller = $this->getToplevelController();
            return $controller->customise(array('Content' => $form));
        }
    }

    /**
     * Return a form with a dropdown to select the list you want to use
     * 
     * @return Form
     */
    public function Form()
    {
        $crumbs = $this->Breadcrumbs();
        
        if ($crumbs && $crumbs->count()>=2) {
            $one_level_up = $crumbs->offsetGet($crumbs->count()-2);
        }
        
        $record_ids = "";
        $query_string = "";
        $recordList = $this->getRecordIDList();
        
        foreach ($this->getRecordIDList() as $id) {
            $record_ids .= $id . ',';
            $query_string .= "records[]={$id}&";
        }
        
        // Cut off the last 2 parts of the string
        $record_ids = substr($record_ids, 0, -1);
        $query_string = substr($query_string, 0, -1);
        
        $form = new Form(
            $this,
            'Form',
            $fields = FieldList::create(
                HiddenField::create("RecordIDs", "", $record_ids),
                DropdownField::create(
                    "ContactListID",
                    _t("Contacts.ChooseList", "Choose a list"),
                    ContactList::get()->map()
                )->setEmptyString(_t("Contacts.SelectList", "Select a List"))
            ),
            $actions = FieldList::create(
                FormAction::create('doAddToList', _t("Contacts.Add", 'Add'))
                    ->setAttribute('id', 'bulkEditingSaveBtn')
                    ->addExtraClass('btn btn-success')
                    ->setAttribute('data-icon', 'accept')
                    ->setUseButtonTag(true),
                    
                FormAction::create('Cancel', _t('GRIDFIELD_BULKMANAGER_EDIT_HANDLER.CANCEL_BTN_LABEL', 'Cancel'))
                    ->setAttribute('id', 'bulkEditingUpdateCancelBtn')
                    ->addExtraClass('btn btn-danger cms-panel-link')
                    ->setAttribute('data-icon', 'decline')
                    ->setAttribute('href', $one_level_up->Link)
                    ->setUseButtonTag(true)
                    ->setAttribute('src', '')
            )
        );
        
        if ($crumbs && $crumbs->count() >= 2) {
            $form->Backlink = $one_level_up->Link;
        }
        
        // override form action URL back to bulkEditForm
        // and add record ids GET var
        $form->setFormAction(
            $this->Link('Form?records[]='.implode('&', $recordList))
        );

        return $form;
    }

    /**
     * Saves the changes made in the bulk edit into the dataObject
     * 
     * @return HTTPResponse 
     */
    public function doAddToList($data, /** @scrutinizer ignore-unused */ $form)
    {
        $className  = $this->gridField->list->dataClass;
        $controller = $this->getToplevelController();
        $form = $controller->EditForm();
        $return = array();

        if (isset($data['RecordIDs'])) {
            $ids = explode(",", $data['RecordIDs']);
        } else {
            $ids = array();
        }

        $list_id = (isset($data['ContactListID'])) ? $data['ContactListID'] : 0;
        $list = ContactList::get()->byID($list_id);
        
        try {
            foreach ($ids as $record_id) {
                if ($list_id) {
                    $record = DataObject::get_by_id($className, $record_id);
                    
                    if ($record->hasMethod("Lists")) {
                        $list->Contacts()->add($record);
                        $list->write();
                    }
                    
                    $return[] = $record->ID;
                }
            }
        } catch (\Exception $e) {
            $form->sessionMessage(
                $e->getMessage(),
                ValidationResult::TYPE_ERROR
            );
                
            $responseNegotiator = new PjaxResponseNegotiator(array(
                'CurrentForm' => function () use (&$form) {
                    return $form->forTemplate();
                },
                'default' => function () use (&$controller) {
                    return $controller->redirectBack();
                }
            ));
            
            if ($controller->getRequest()->isAjax()) {
                $controller->getRequest()->addHeader('X-Pjax', 'CurrentForm');
            }
            
            return $responseNegotiator->respond($controller->getRequest());
        }
        
        if (!empty($list)) {
            $message = "Added " . count($return) . " contacts to mailing list '{$list->Title}'";
        } else {
            $message = _t("Contacts.NoListSelected", "No list selected");
        }

        $form->sessionMessage(
            $message,
            ValidationResult::TYPE_GOOD
        );
        
        // Changes to the record properties might've excluded the record from
        // a filtered list, so return back to the main view if it can't be found
        $link = $controller->Link();
        $controller->getRequest()->addHeader('X-Pjax', 'Content');
        return $controller->redirect($link);
    }
}
