<?php

namespace SilverCommerce\ContactAdmin\BulkActions;

use SilverStripe\View\ArrayData;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use Colymba\BulkManager\BulkAction\Handler as BulkActionHandler;
use SilverStripe\Forms\GridField\GridFieldDetailForm_ItemRequest;

/**
 * Abstract(ish) handler class for bulk assigning actions that pre-loads some
 * common functions and properties.
 * 
 */
class AddRelatedHandler extends BulkActionHandler
{

    private static $url_handlers = [
        '' => "index",
        'Form' => "Form"
    ];

        /**
     * Whether this handler should be called via an XHR from the front-end
     * 
     * @var boolean
     */
    protected $xhr = false;

    /**
     * Set to true is this handler will destroy any data.
     * A warning and confirmation will be shown on the front-end.
     * 
     * @var boolean
     */
    protected $destructive = false;

    /**
     * Return URL to this RequestHandler
     * @param string $action Action to append to URL
     * @return string URL
     */
    public function Link($action = null)
    {
        return Controller::join_links(
            parent::Link(),
            $this->config()->url_segment,
            $action
        );
    }

    /**
     * Traverse up nested requests until we reach the first that's not a GridFieldDetailForm or GridFieldDetailForm_ItemRequest.
     * The opposite of {@link Controller::curr()}, required because
     * Controller::$controller_stack is not directly accessible.
     *
     * @return Controller
     */
    protected function getToplevelController()
    {
        $c = Controller::curr();
        while ($c && ($c instanceof GridFieldDetailForm_ItemRequest || $c instanceof GridFieldDetailForm)) {
            $c = $c->getController();
        }

        return $c;
    }

    /**
     * Edited version of the GridFieldEditForm function
     * adds the 'Bulk Upload' at the end of the crums.
     *
     * CMS-specific functionality: Passes through navigation breadcrumbs
     * to the template, and includes the currently edited record (if any).
     * see {@link LeftAndMain->Breadcrumbs()} for details.
     *
     * @author SilverStripe original Breadcrumbs() method
     *
     * @see GridFieldDetailForm_ItemRequest
     *
     * @param bool $unlinked
     *
     * @return ArrayData
     */
    public function Breadcrumbs($unlinked = false)
    {
        if (!Controller::curr()->hasMethod('Breadcrumbs')) {
            return;
        }

        $items = Controller::curr()->Breadcrumbs($unlinked);
        $items->push(ArrayData::create([
            'Title' => $this->getI18nLabel(),
            'Link' => false,
        ]));

        return $items;
    }
}