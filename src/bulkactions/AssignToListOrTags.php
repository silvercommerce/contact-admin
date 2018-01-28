<?php

namespace SilverCommerce\ContactAdmin\BulkActions;

use SilverStripe\Core\Convert;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\PjaxResponseNegotiator;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FormAction;
use SilverStripe\TagField\TagField;
use SilverCommerce\ContactAdmin\Model\ContactList;
use SilverCommerce\ContactAdmin\Model\ContactTag;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Admin\LeftAndMain;
use Colymba\BulkManager\BulkAction\Handler as BulkActionHandler;

/**
 * Bulk action handler that adds selected records to a list
 * 
 * @author ilateral
 * @package Contacts
 */
class AssignToListOrTags extends BulkActionHandler
{
   
    /**
     * RequestHandler allowed actions
     * @var array
     */
    private static $allowed_actions = [
        'list',
        'tags',
        'ListForm',
        'TagForm'
    ];

    private static $url_handlers = [
        'assign/list' => "list",
        'assign/tags' => "tags",
        'assign/ListForm' => "ListForm",
        'assign/TagForm' => "TagForm"
    ];
    
    /**
     * Return URL to this RequestHandler
     * @param string $action Action to append to URL
     * @return string URL
     */
    public function Link($action = null)
    {
        return Controller::join_links(
            parent::Link(),
            "assign",
            $action
        );
    }
    
    
    /**
     * Creates and return the editing interface
     * 
     * @return string Form's HTML
     */
    public function list()
    {
        $leftandmain = Injector::inst()->create(LeftAndMain::class);

        $form = $this->ListForm();
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
     * Creates and return the editing interface
     * 
     * @return string Form's HTML
     */
    public function tags()
    {
        $leftandmain = Injector::inst()->create(LeftAndMain::class);

        $form = $this->TagForm();
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
    public function ListForm()
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
            'ListForm',
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
            $this->Link('ListForm?records[]='.implode('&', $recordList))
        );

        return $form;
    }

        /**
     * Return a form with a dropdown to select the list you want to use
     * 
     * @return Form
     */
    public function TagForm()
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
            'TagForm',
            $fields = FieldList::create(
                HiddenField::create("RecordIDs", "", $record_ids),
                TagField::create(
                    'Tags',
                    null,
                    ContactTag::get()
                )->setDescription(_t(
                    "Contacts.TagDescription",
                    "List of tags related to this contact, seperated by a comma."
                ))->setShouldLazyLoad(true)
            ),
            $actions = FieldList::create(
                FormAction::create('doAddTags', _t("Contacts.Add", 'Add'))
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
            $this->Link('TagForm?records[]='.implode('&', $recordList))
        );

        return $form;
    }

    
    /**
     * Saves the changes made in the bulk edit into the dataObject
     * 
     * @return Redirect 
     */
    public function doAddToList($data, $form)
    {
        $className  = $this->gridField->list->dataClass;
        $singleton  = singleton($className);
        
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
            $controller = $this->controller;
            
            $form->sessionMessage(
                $e->getResult()->message(),
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
        
        $controller = $this->getToplevelController();
        $form = $controller->EditForm();
        
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

        /**
     * Saves the changes made in the bulk edit into the dataObject
     * 
     * @return Redirect 
     */
    public function doAddTags($data, $form)
    {
        $className  = $this->gridField->list->dataClass;
        $singleton  = singleton($className);

        $return = array();

        if (isset($data['RecordIDs'])) {
            $ids = explode(",", $data['RecordIDs']);
        } else {
            $ids = [];
        }

        $tags_list = (isset($data['Tags'])) ? $data['Tags'] : [];
        
        try {
            foreach ($tags_list as $tag_name) {
                if (!empty($tag_name)) {
                    $tag = ContactTag::get()->find("Title", $tag_name);
                    if (!$tag) {
                        $tag = ContactTag::create([
                            "Title" => $tag_name
                        ]);
                        $tag->write();
                    }

                    foreach ($ids as $record_id) {
                        $record = DataObject::get_by_id($className, $record_id);
                        
                        if ($record->hasMethod("Tags")) {
                            $record->Tags()->add($tag);
                            $return[] = $record->ID;
                        }
                        
                    }
                }
            }
        } catch (\Exception $e) {
            $controller = $this->controller;
            
            $form->sessionMessage(
                $e->getResult()->message(),
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
        
        $controller = $this->getToplevelController();
        $form = $controller->EditForm();
        
        if (count($tags_list)) {
            $message = "Added " . count($return) . " contacts to tags '" . implode(",",$tags_list) . "'";
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
