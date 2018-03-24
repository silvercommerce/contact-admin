<?php

namespace SilverCommerce\ContactAdmin\Forms;

use SilverStripe\Control\Controller;
use TractorCow\AutoComplete\AutoCompleteField;

class ModelAdminAutoCompleteField extends AutoCompleteField
{
    /**
     * Return a link to this field (using form name to bypass model admin
     * posting via get).
     *
     * @param string $action
     * @return string
     */
    public function Link($action = null)
    {
        return Controller::join_links(
            $this->form->getController()->Link(),
            $this->form->getName(),
            'field',
            $this->name,
            $action
        );
    }
}